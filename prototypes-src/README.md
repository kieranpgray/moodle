# Password-protecting a prototype

The prototypes in `public/prototypes/` are flat files rsynced to the VPS, so
there is no server to hold a session and no real login to hang auth off. The
way to genuinely protect one is to publish it encrypted and decrypt it in the
browser.

`encrypt-prototype.mjs` takes a prototype and wraps it in a lock screen. The
whole source document is gzipped and encrypted with AES-256-GCM under a key
derived from your password (PBKDF2-SHA256, 600,000 iterations). The deployed
file holds the lock screen and one base64 blob — the markup, styles, data and
notes are not in it in any readable form, and the password is not in it at all,
not even as a hash.

This is real protection, not a speed bump. The trade-off is a build step.

## Protecting a prototype

Put the plaintext in `source/` (this folder, gitignored) and run from the repo
root:

```bash
node prototypes-src/encrypt-prototype.mjs prototypes-src/source/PROTOTYPE-foo.html
```

That writes the encrypted page to `public/prototypes/PROTOTYPE-foo.html`. It
prompts for the password twice and does not echo it, so it stays out of shell
history. `--out` puts the output somewhere else; `--label` and `--title` set the
two bits of text on the lock screen; `--help` lists everything.

Then commit **only the encrypted output** and push to `main` as usual — the
deploy workflow rsyncs `public/prototypes/` to the VPS and the page goes live
locked.

## Changing a protected prototype

Edit the file in `source/`, re-run the command, commit the new output. Editing
the deployed file directly is not possible — it is ciphertext. The tool refuses
to run on an already-encrypted file so you cannot double-wrap one by accident.

Re-running produces a fresh salt and IV, so every build differs even with the
same password and content. That also invalidates anyone's remembered unlock.

## The three things that can undo this

**Where the plaintext lives.** `source/` is gitignored because this repo is a
public fork; a plaintext prototype committed here is readable by anyone who
finds the fork, which would make the encryption pointless. Nothing stops you
committing one anyway — so don't. Keep your own backup instead, because nothing
else does.

**Git history.** Encrypting a prototype that was previously published in the
clear does not remove the old plaintext from history. It stays reachable at its
old commit for anyone who thinks to look. Closing that off means rewriting
history or moving the prototype to a private repo.

**Password strength.** The only thing between a downloaded copy of the page and
its contents is an offline guessing run against PBKDF2. 600,000 iterations makes
each guess cost roughly a second in a browser, but a short or obvious password
is still a short or obvious password. Use something long, and share it out of
band — not in the same email as the link.

## Requirements

Node 18+ to build (uses `node:crypto` webcrypto and `node:zlib`, no packages).

To view: any browser with `crypto.subtle` and `DecompressionStream` — Chrome
80+, Firefox 113+, Safari 16.4+, Edge 80+. Pass `--no-compress` to drop the
`DecompressionStream` requirement at the cost of a roughly 5x bigger file.
