#!/usr/bin/env node
/**
 * Wrap a prototype HTML file in a password gate that actually encrypts it.
 *
 * The whole source document is gzipped, encrypted with AES-256-GCM under a key
 * derived from the password (PBKDF2-SHA256), and written into a small carrier
 * page as base64. The carrier holds nothing but the lock screen and the
 * ciphertext — the prototype's markup, styles, data and notes are not in the
 * deployed file in any readable form. On a correct password the browser
 * decrypts in memory and rewrites the document, so the prototype runs exactly
 * as it would have unencrypted.
 *
 * Usage:
 *   node prototypes-src/encrypt-prototype.mjs source/PROTOTYPE-foo.html
 *   node prototypes-src/encrypt-prototype.mjs source/PROTOTYPE-foo.html \
 *        --out public/prototypes/PROTOTYPE-foo.html \
 *        --label "Public roadmap with community voting"
 *
 * The password is prompted for (not echoed) so it never lands in shell history.
 * For scripted runs, set PROTOTYPE_PASSWORD in the environment instead.
 *
 * See README.md in this folder for the full workflow.
 */

import { webcrypto } from "node:crypto";
import { gzipSync } from "node:zlib";
import { readFile, writeFile } from "node:fs/promises";
import { basename, dirname, resolve, relative } from "node:path";
import { fileURLToPath } from "node:url";
import process from "node:process";

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO_ROOT = resolve(HERE, "..");

/* OWASP's floor for PBKDF2-SHA256 (2023). Costs roughly a second in the
   browser, which is the point: it is the only thing standing between a stolen
   copy of the page and an offline guessing run. */
const PBKDF2_ITERATIONS = 600000;
const SALT_BYTES = 16;
const IV_BYTES = 12;

function fail(message) {
  console.error(`\nerror: ${message}\n`);
  process.exit(1);
}

function parseArgs(argv) {
  const opts = { compress: true };
  const positional = [];

  for (let i = 0; i < argv.length; i++) {
    const arg = argv[i];
    if (arg === "--out" || arg === "-o") opts.out = argv[++i];
    else if (arg === "--label" || arg === "-l") opts.label = argv[++i];
    else if (arg === "--title" || arg === "-t") opts.title = argv[++i];
    else if (arg === "--no-compress") opts.compress = false;
    else if (arg === "--help" || arg === "-h") opts.help = true;
    else if (arg.startsWith("-")) fail(`unknown option: ${arg}`);
    else positional.push(arg);
  }

  opts.source = positional[0];
  return opts;
}

const USAGE = `
Encrypt a prototype HTML file behind a password gate.

  node prototypes-src/encrypt-prototype.mjs <source.html> [options]

Options:
  -o, --out <path>    Where to write the encrypted page.
                      Default: public/prototypes/<source filename>
  -l, --label <text>  One-line description shown on the lock screen.
                      Keep it vague — it is public. Default: none.
  -t, --title <text>  <title> of the lock screen. It is public too.
                      Default: "Moodle prototype — password required"
      --no-compress   Skip gzip. Bigger file, but works on browsers without
                      DecompressionStream (pre-2023).
  -h, --help          Show this.

The password is prompted for unless PROTOTYPE_PASSWORD is set.
`;

/** Read a password from the terminal without echoing it. */
function promptPassword(prompt) {
  return new Promise((resolvePassword) => {
    const { stdin, stdout } = process;
    if (!stdin.isTTY) {
      fail("no terminal to prompt on — set PROTOTYPE_PASSWORD instead");
    }

    stdout.write(prompt);
    stdin.setRawMode(true);
    stdin.resume();
    stdin.setEncoding("utf8");

    let value = "";
    const onData = (char) => {
      /* Enter, or EOF, ends the read. */
      if (char === "\r" || char === "\n" || char === "\u0004") {
        stdin.setRawMode(false);
        stdin.pause();
        stdin.removeListener("data", onData);
        stdout.write("\n");
        resolvePassword(value);
        return;
      }
      /* Ctrl-C. */
      if (char === "\u0003") {
        stdin.setRawMode(false);
        stdout.write("\n");
        process.exit(130);
      }
      /* Backspace / delete. */
      if (char === "\u0008" || char === "\u007f") {
        value = value.slice(0, -1);
        return;
      }
      value += char;
    };

    stdin.on("data", onData);
  });
}

async function readPassword() {
  const fromEnv = process.env.PROTOTYPE_PASSWORD;
  if (fromEnv) {
    if (fromEnv.length < 6) fail("PROTOTYPE_PASSWORD is under 6 characters");
    return fromEnv;
  }

  const password = await promptPassword("Password for this prototype: ");
  if (password.length < 6) fail("password must be at least 6 characters");

  const again = await promptPassword("Confirm password:            ");
  if (password !== again) fail("passwords did not match");

  return password;
}

async function encrypt(plaintext, password, compress) {
  const salt = webcrypto.getRandomValues(new Uint8Array(SALT_BYTES));
  const iv = webcrypto.getRandomValues(new Uint8Array(IV_BYTES));

  const baseKey = await webcrypto.subtle.importKey(
    "raw",
    new TextEncoder().encode(password),
    "PBKDF2",
    false,
    ["deriveKey"]
  );

  const key = await webcrypto.subtle.deriveKey(
    { name: "PBKDF2", salt, iterations: PBKDF2_ITERATIONS, hash: "SHA-256" },
    baseKey,
    { name: "AES-GCM", length: 256 },
    false,
    ["encrypt"]
  );

  const body = compress
    ? new Uint8Array(gzipSync(Buffer.from(plaintext, "utf8"), { level: 9 }))
    : new TextEncoder().encode(plaintext);

  const ciphertext = await webcrypto.subtle.encrypt({ name: "AES-GCM", iv }, key, body);

  return {
    v: 1,
    kdf: "PBKDF2-SHA256",
    iterations: PBKDF2_ITERATIONS,
    cipher: "AES-GCM",
    compression: compress ? "gzip" : "none",
    salt: Buffer.from(salt).toString("base64"),
    iv: Buffer.from(iv).toString("base64"),
    data: Buffer.from(ciphertext).toString("base64"),
  };
}

/* The payload rides in a <script type="application/json"> block, so the only
   character that could break out of it is "<". */
const jsonForHtml = (value) => JSON.stringify(value).replace(/</g, "\\u003c");

const escapeHtml = (text) =>
  String(text).replace(/[&<>"]/g, (char) =>
    ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[char]
  );

async function main() {
  const opts = parseArgs(process.argv.slice(2));

  if (opts.help || !opts.source) {
    console.log(USAGE);
    process.exit(opts.help ? 0 : 1);
  }

  const sourcePath = resolve(process.cwd(), opts.source);
  const outPath = opts.out
    ? resolve(process.cwd(), opts.out)
    : resolve(REPO_ROOT, "public/prototypes", basename(sourcePath));

  if (sourcePath === outPath) fail("source and output are the same file");

  const plaintext = await readFile(sourcePath, "utf8").catch(() =>
    fail(`cannot read ${opts.source}`)
  );

  /* The generator meta survives into the output, so this catches the mistake
     of pointing the tool at its own result and wrapping it twice. */
  if (plaintext.includes('name="generator" content="encrypt-prototype"')) {
    fail(`${opts.source} is already encrypted — point at the plaintext source instead`);
  }

  const password = await readPassword();
  const template = await readFile(resolve(HERE, "gate-template.html"), "utf8");
  const payload = await encrypt(plaintext, password, opts.compress);

  const page = template
    .replace("__PAGE_TITLE__", escapeHtml(opts.title || "Moodle prototype — password required"))
    .replace("__PAGE_LABEL__", opts.label ? `  <p class="sub">${escapeHtml(opts.label)}</p>` : "")
    .replace('"__PROTOTYPE_PAYLOAD__"', jsonForHtml(payload));

  await writeFile(outPath, page, "utf8");

  const kb = (bytes) => `${Math.round(bytes / 1024)} KB`;
  console.log(`
  source     ${relative(REPO_ROOT, sourcePath)}  (${kb(Buffer.byteLength(plaintext))} plaintext)
  output     ${relative(REPO_ROOT, outPath)}  (${kb(Buffer.byteLength(page))} encrypted)
  cipher     AES-256-GCM, PBKDF2-SHA256 x${PBKDF2_ITERATIONS.toLocaleString("en")}${opts.compress ? ", gzipped" : ""}

  The output holds no readable trace of the prototype. Keep the plaintext
  source out of the deployed folder and out of the public repo.
`);
}

main().catch((error) => fail(error.message));
