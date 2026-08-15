<?php
/**
 * Stress-test seeder for the "Biology 101 - Mock data" course (id 15).
 *
 * Populates worst-case / high-complexity data into:
 *   - forum : the existing general "debate" forum (bio101_forum_debate)
 *   - book  : the existing "Course Syllabus & Study Guide" (bio101_book_syllabus)
 *   - quiz  : a dedicated sandbox section with one quiz per behaviour/config variant
 *
 * Idempotent: each phase wipes its own prior stress data and rebuilds.
 *
 *   php biology101_stress.php --phase=all --scale=1
 *   php biology101_stress.php --phase=forum --scale=3   # heavier forum load
 */

define('CLI_SCRIPT', true);
require('/var/www/html/public/config.php');
global $CFG, $DB;
$CFG->noemailever = true; // Never attempt to send mail while generating thousands of posts.
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->libdir . '/questionlib.php');

list($options) = cli_get_params(
    ['phase' => 'all', 'scale' => 1, 'courseid' => 15, 'help' => false],
    ['h' => 'help']
);
if ($options['help']) {
    cli_writeln("Usage: php biology101_stress.php --phase=forum|book|quiz|all --scale=N --courseid=15");
    exit(0);
}
$COURSEID = (int)$options['courseid'];
$SCALE = max(1, (int)$options['scale']);
$PHASE = $options['phase'];

$course = $DB->get_record('course', ['id' => $COURSEID], '*', MUST_EXIST);
if (!in_array($course->shortname, ['MYOV-T-02', 'BIO101-MOCK'])) {
    cli_error("Refusing to run on course {$COURSEID} ('{$course->shortname}').");
}

$dg = \core\test\phpunit\phpunit_util::get_data_generator();
\core\session\manager::set_user(get_admin());

function bio_log(string $m): void {
    cli_writeln('  ' . $m);
}

/** Resolve a bio101 course module by idnumber. */
function bio_cm(int $courseid, string $idnumber) {
    global $DB;
    return $DB->get_record('course_modules', ['course' => $courseid, 'idnumber' => $idnumber]) ?: null;
}

/** Idempotent module create (delete prior instance with same idnumber). */
function bio_mod($dg, $course, string $modname, string $idnumber, array $record) {
    global $DB;
    foreach ($DB->get_records('course_modules', ['course' => $course->id, 'idnumber' => $idnumber]) as $cm) {
        course_delete_module($cm->id);
    }
    $record['course'] = $course->id;
    $record['idnumber'] = $idnumber;
    return $dg->get_plugin_generator('mod_' . $modname)->create_instance($record);
}

/** Roster: mock students + the busy fixture student + the teacher. */
function bio_people(int $courseid): array {
    global $DB;
    $people = ['students' => [], 'teacher' => null];
    foreach (['bio_ava', 'bio_liam', 'bio_sofia', 'bio_noah', 'bio_mia', 'bio_ethan', 'student_busy'] as $un) {
        if ($u = $DB->get_record('user', ['username' => $un])) {
            $people['students'][] = $u;
        }
    }
    $people['teacher'] = $DB->get_record('user', ['username' => 'educator_busy']);
    return $people;
}

/** A block of pseudo-biology HTML paragraphs for long-content stress. */
function bio_lorem(int $paras): string {
    $sent = [
        'The mitochondrion is the primary site of aerobic respiration in eukaryotic cells.',
        'Enzymes lower the activation energy required for metabolic reactions to proceed.',
        'Osmosis describes the net movement of water across a selectively permeable membrane.',
        'Natural selection acts on heritable variation within a population over generations.',
        'Photosynthesis converts light energy into the chemical energy stored in glucose.',
        'DNA replication is semi-conservative, producing two identical daughter strands.',
        'The sodium&ndash;potassium pump maintains electrochemical gradients using ATP.',
        'Meiosis introduces genetic variation through crossing over and independent assortment.',
    ];
    $out = '';
    for ($i = 0; $i < $paras; $i++) {
        $n = 3 + ($i % 5);
        $p = [];
        for ($j = 0; $j < $n; $j++) {
            $p[] = $sent[($i + $j) % count($sent)];
        }
        $out .= '<p>' . implode(' ', $p) . '</p>' . "\n";
    }
    return $out;
}

// A varied set of authors, used round-robin / pseudo-random.
$people = bio_people($COURSEID);
$students = $people['students'];
$teacher = $people['teacher'];
$authors = $students;
if ($teacher) {
    $authors[] = $teacher;
}
$pickauthor = function (int $i) use ($authors) {
    return $authors[$i % count($authors)];
};

// ===========================================================================
// PHASE: forum  (target: existing general "debate" forum)
// ===========================================================================
if (in_array($PHASE, ['forum', 'all'])) {
    cli_heading('Phase: forum stress (scale=' . $SCALE . ')');
    $forumgen = $dg->get_plugin_generator('mod_forum');
    $cm = bio_cm($COURSEID, 'bio101_forum_debate');
    if (!$cm) {
        cli_error('Target forum bio101_forum_debate not found — run biology101_seed.php first.');
    }
    $forumid = $cm->instance;

    // Wipe existing discussions/posts in this forum (fast DB cleanup for a dev fixture).
    $discids = $DB->get_fieldset_select('forum_discussions', 'id', 'forum = ?', [$forumid]);
    if ($discids) {
        list($insql, $inparams) = $DB->get_in_or_equal($discids);
        $DB->delete_records_select('forum_posts', "discussion $insql", $inparams);
        $DB->delete_records_select('forum_read', "discussionid $insql", $inparams);
        $DB->delete_records_select('forum_discussion_subs', "discussion $insql", $inparams);
        $DB->delete_records_select('forum_discussions', "id $insql", $inparams);
    }
    $DB->delete_records('forum_queue', ['userid' => 0]); // best-effort.
    bio_log('wiped ' . count($discids) . ' existing discussions');

    $now = time();
    $postcount = 0;
    // Global post-budget so the "monster" thread cannot explode unbounded.
    $monsterbudget = 400 * $SCALE;

    // Build a reply subtree recursively.
    $buildtree = function ($discid, $parentid, $depth, $maxdepth, $minb, $maxb, &$budget, &$time, $seed)
            use (&$buildtree, $forumgen, $authors, &$postcount) {
        if ($depth >= $maxdepth || $budget <= 0) {
            return;
        }
        $nreplies = $minb + (($seed + $depth) % max(1, ($maxb - $minb + 1)));
        for ($i = 0; $i < $nreplies; $i++) {
            if ($budget <= 0) {
                return;
            }
            $budget--;
            $postcount++;
            $time += 60 + (($seed + $i) % 5000);
            $author = $authors[($seed + $depth + $i) % count($authors)];
            $bodies = [
                '+1 👍',
                'Agreed — good point.',
                'Can you cite a source for that?',
                'I disagree. ' . strip_tags(bio_lorem(1)),
                bio_lorem(2 + (($seed + $i) % 3)),
                'See the reading in Topic ' . (1 + (($seed + $i) % 12)) . '.',
                '🧬🔬 Interesting, but what about the exceptions?',
            ];
            $post = $forumgen->create_post([
                'discussion' => $discid,
                'userid' => $author->id,
                'parent' => $parentid,
                'subject' => 'Re: reply at depth ' . ($depth + 1),
                'message' => $bodies[($seed + $depth + $i) % count($bodies)],
                'created' => $time,
                'modified' => $time,
                'mailed' => 1,
            ]);
            $buildtree($discid, $post->id, $depth + 1, $maxdepth, $minb, $maxb, $budget, $time, $seed + $i + 1);
        }
    };

    // Helper: create a discussion with a given title/first author/base time.
    $mkdisc = function (string $title, int $authorindex, int $daysago, bool $pinned, string $body)
            use ($forumgen, $forumid, $COURSEID, $authors, $now) {
        $t = $now - ($daysago * DAYSECS);
        $disc = $forumgen->create_discussion([
            'course' => $COURSEID, 'forum' => $forumid,
            'userid' => $authors[$authorindex % count($authors)]->id,
            'name' => $title, 'message' => $body, 'pinned' => $pinned ? 1 : 0,
            'timemodified' => $t,
        ]);
        // Pin the first post at the discussion base time.
        return [$disc, $t];
    };

    // ---- 1. Title edge cases (short, long, unicode, RTL, emoji, HTML, unbroken) ----
    $long255 = substr('Why does cellular respiration occur in the mitochondria and how does the electron transport '
        . 'chain establish the proton-motive force that ATP synthase uses to phosphorylate ADP into ATP across '
        . 'the inner mitochondrial membrane in eukaryotic cells during aerobic metabolism of glucose molecules', 0, 255);
    $unbroken = substr(str_repeat('Deoxyribonucleicacidpolymerase', 12), 0, 240);
    $titlecases = [
        '?',
        'A',
        '§',
        '🧬',
        '.',
        'Re:',
        '   ', // whitespace-only-ish
        $long255,
        $unbroken,
        'Ampersands & <angle> brackets "quotes" \'apostrophes\' and <script>alert(1)</script>',
        'Osmose et diffusion — considérations spéciales à propos des cellules végétales (accents éàü)',
        '细胞呼吸与光合作用有什么区别？',        // CJK
        'لماذا تحدث عملية التنفس الخلوي في الميتوكوندريا؟', // Arabic RTL
        'מדוע מתרחשת נשימה תאית במיטוכונדריה?',   // Hebrew RTL
        '🧫🧪🌱🦠🔬 all the lab emoji in one very colourful discussion title 🧬🧫🧪🌱🦠🔬',
        "Tabs\tand\nnewlines\rin a title",
        'MiXeD  CaSe   and    irregular     spacing',
        'SQL-ish: SELECT * FROM cells WHERE alive = 1; DROP TABLE students;--',
    ];
    foreach ($titlecases as $i => $title) {
        [$disc, $t] = $mkdisc($title, $i, 55 - $i, ($i < 2), bio_lorem(1 + ($i % 3)));
        // Give each a small mixed reply tree.
        $budget = 8;
        $time = $t + 3600;
        $buildtree($disc->id, $disc->firstpost, 0, 3, 1, 3, $budget, $time, $i + 1);
    }
    bio_log('created ' . count($titlecases) . ' title-edge-case discussions');

    // ---- 2. Normal-looking biology discussions (varied small trees) ----
    $normaltitles = [
        'Best mnemonic for the stages of mitosis?',
        'Confused about the difference between mRNA and tRNA',
        'Study group for the genetics quiz this weekend',
        'Is the peppered moth still the best evolution example?',
        'How deep do we need to know the Krebs cycle?',
        'Osmosis vs diffusion — quick sanity check',
        'Why do plants need both light and dark reactions?',
        'Enzyme inhibitors: competitive vs non-competitive',
        'Punnett square practice thread',
        'Field trip: what should we bring?',
        'Lab safety reminder before Friday',
        'Anyone have good Crash Course video links?',
        'Homeostasis real-world examples',
        'Cell membrane transport — flashcards to share',
        'Meiosis crossing over: draw it out?',
        'Central dogma: transcription then translation',
        'What counts as a producer in the species database?',
        'Photosynthesis equation — do we memorise it exactly?',
        'ATP: why is it called the energy currency?',
        'Prokaryote vs eukaryote checklist',
    ];
    foreach ($normaltitles as $i => $title) {
        [$disc, $t] = $mkdisc($title, $i + 3, 50 - $i, false, bio_lorem(1 + ($i % 2)));
        $budget = 3 + ($i % 6);
        $time = $t + 1800;
        // Mostly shallow, some medium depth.
        $buildtree($disc->id, $disc->firstpost, 0, 2 + ($i % 3), 1, 3, $budget, $time, $i + 20);
    }
    bio_log('created ' . count($normaltitles) . ' normal discussions');

    // ---- 3. DEEP chains (linear parent chain, depth ~25) ----
    for ($d = 0; $d < 3; $d++) {
        [$disc, $t] = $mkdisc('Deep chain #' . ($d + 1) . ' — reply-to-reply stress (nested ' . (20 + $d * 5) . ' levels)',
            $d, 30 - $d, false, 'Follow the chain down. Each post replies to the one directly above it.');
        $parent = $disc->firstpost;
        $time = $t;
        $maxdepth = 20 + $d * 5;
        for ($k = 0; $k < $maxdepth; $k++) {
            $time += 300 + $k * 60;
            $author = $authors[$k % count($authors)];
            $p = $forumgen->create_post([
                'discussion' => $disc->id, 'userid' => $author->id, 'parent' => $parent,
                'subject' => 'Re: depth ' . ($k + 1),
                'message' => 'Level ' . ($k + 1) . '. ' . (($k % 4 === 0) ? bio_lorem(1) : 'Continuing the thread. 🧬'),
                'created' => $time, 'modified' => $time, 'mailed' => 1,
            ]);
            $parent = $p->id;
            $postcount++;
        }
    }
    bio_log('created 3 deep chains (20/25/30 levels)');

    // ---- 4. WIDE fan-out (one first post, many direct replies) ----
    for ($w = 0; $w < 2; $w++) {
        $width = 40 + $w * 20; // 40, 60
        [$disc, $t] = $mkdisc('Wide thread #' . ($w + 1) . ' — ' . $width . ' direct replies to the opening post',
            $w + 1, 20 - $w, false, 'Everyone reply directly to THIS post (flat, not nested).');
        $time = $t;
        for ($k = 0; $k < $width; $k++) {
            $time += 120 + ($k % 7) * 30;
            $author = $authors[$k % count($authors)];
            $forumgen->create_post([
                'discussion' => $disc->id, 'userid' => $author->id, 'parent' => $disc->firstpost,
                'subject' => 'Re: (flat) #' . ($k + 1),
                'message' => (($k % 3 === 0) ? 'Reply #' . ($k + 1) . ' 👍' : bio_lorem(1)),
                'created' => $time, 'modified' => $time, 'mailed' => 1,
            ]);
            $postcount++;
        }
    }
    bio_log('created 2 wide threads (40 + 60 direct replies)');

    // ---- 5. MONSTER discussion: deep AND wide, hundreds of posts ----
    [$disc, $t] = $mkdisc('MONSTER MEGATHREAD — the worst-case discussion (deep + wide, hundreds of posts) 🌋',
        0, 60, true, '<p>This single discussion combines deep nesting and wide fan-out to produce the worst-case '
        . 'rendering scenario for a single forum discussion.</p>' . bio_lorem(3));
    $budget = $monsterbudget;
    $time = $t + 600;
    // Several top-level branches, each a deep+wide subtree.
    $topbranches = 6;
    for ($b = 0; $b < $topbranches; $b++) {
        if ($budget <= 0) {
            break;
        }
        $budget--;
        $postcount++;
        $time += 200;
        $branchhead = $forumgen->create_post([
            'discussion' => $disc->id, 'userid' => $authors[$b % count($authors)]->id,
            'parent' => $disc->firstpost, 'subject' => 'Branch ' . ($b + 1),
            'message' => 'Top-level branch ' . ($b + 1) . '. ' . bio_lorem(2),
            'created' => $time, 'modified' => $time, 'mailed' => 1,
        ]);
        $buildtree($disc->id, $branchhead->id, 0, 6, 2, 4, $budget, $time, $b * 7 + 1);
    }
    bio_log('created MONSTER megathread (~' . ($monsterbudget - $budget) . ' posts)');

    // Make sure nothing is queued for mailing.
    $allpids = $DB->get_fieldset_sql('SELECT p.id FROM {forum_posts} p JOIN {forum_discussions} d ON d.id=p.discussion WHERE d.forum = ?', [$forumid]);
    if ($allpids) {
        list($insql, $inparams) = $DB->get_in_or_equal($allpids);
        $DB->set_field_select('forum_posts', 'mailed', 1, "id $insql", $inparams);
    }

    $totaldisc = $DB->count_records('forum_discussions', ['forum' => $forumid]);
    $totalposts = count($allpids);
    cli_writeln("Forum phase complete: {$totaldisc} discussions, {$totalposts} posts.");
}

// ===========================================================================
// PHASE: book  (target: existing "Course Syllabus & Study Guide")
// ===========================================================================
if (in_array($PHASE, ['book', 'all'])) {
    cli_heading('Phase: book stress');
    $bookgen = $dg->get_plugin_generator('mod_book');
    $cm = bio_cm($COURSEID, 'bio101_book_syllabus');
    if (!$cm) {
        cli_error('Target book bio101_book_syllabus not found — run biology101_seed.php first.');
    }
    $book = $DB->get_record('book', ['id' => $cm->instance], '*', MUST_EXIST);
    $book->cmid = $cm->id;

    // Wipe existing chapters.
    $DB->delete_records('book_chapters', ['bookid' => $book->id]);

    $pn = 1;
    $add = function (string $title, string $content, int $sub) use ($bookgen, &$book, &$pn) {
        $bookgen->create_content($book, ['title' => $title, 'content' => $content, 'subchapter' => $sub, 'pagenum' => $pn++]);
    };

    // ---- Keep the real syllabus up front ----
    $add('Course Overview', '<p>Biology 101 is a one-semester introduction to the science of life.</p>', 0);
    $add('Learning Outcomes', '<ul><li>Describe cells.</li><li>Explain energy flow.</li><li>Apply genetics.</li></ul>', 0);
    $add('Assessment', '<p>Quizzes, assignments, peer review and a capstone.</p>', 0);

    // ---- Title edge cases (mix of chapters and subchapters) ----
    $long255 = substr('A deliberately very long chapter title that keeps going well past what any reasonable table '
        . 'of contents was designed to display in order to stress wrapping truncation and layout of the book '
        . 'navigation block and the chapter heading rendering across desktop and mobile breakpoints in Moodle', 0, 255);
    $unbroken = substr(str_repeat('Photophosphorylation', 14), 0, 230);
    $edgetitles = [
        ['§', 0], ['🧬', 1], ['?', 1], ['A', 1],
        [$long255, 0],
        [$unbroken, 1],
        ['Entities & <b>bold?</b> "quotes" \'apos\' <script>alert(1)</script>', 1],
        ['Accents: naïve café résumé Ångström — Δµ across the membrane', 1],
        ['光合作用与呼吸作用', 1],
        ['التنفس الخلوي', 1],
        ['נשימה תאית', 1],
        ['Emoji chapter 🧫🧪🌱🦠🔬🧬', 1],
    ];
    foreach ($edgetitles as [$title, $sub]) {
        $add($title, '<p>' . bio_lorem(1) . '</p>', $sub);
    }
    bio_log('added ' . count($edgetitles) . ' title-edge-case chapters/subchapters');

    // ---- One huge-content chapter (big table, deep nested lists, all heading levels, code, blockquote, img) ----
    $bigtable = '<table border="1" cellpadding="4"><thead><tr><th>#</th><th>Term</th><th>Definition</th>'
        . '<th>Topic</th><th>Example</th><th>Notes</th></tr></thead><tbody>';
    for ($r = 1; $r <= 80; $r++) {
        $bigtable .= '<tr><td>' . $r . '</td><td>Term ' . $r . '</td><td>' . strip_tags(bio_lorem(1))
            . '</td><td>Topic ' . (1 + $r % 12) . '</td><td>Example ' . $r . '</td><td>&mdash;</td></tr>';
    }
    $bigtable .= '</tbody></table>';
    $nestedlist = '<ul><li>Domain<ul><li>Kingdom<ul><li>Phylum<ul><li>Class<ul><li>Order<ul>'
        . '<li>Family<ul><li>Genus<ul><li>Species (7 levels deep)</li></ul></li></ul></li></ul></li></ul></li></ul></li></ul></li></ul></li></ul>';
    $headings = '';
    for ($h = 1; $h <= 6; $h++) {
        $headings .= "<h{$h}>Heading level {$h}</h{$h}><p>" . strip_tags(bio_lorem(1)) . '</p>';
    }
    $huge = $headings
        . '<blockquote>"Nothing in biology makes sense except in the light of evolution." — Dobzhansky</blockquote>'
        . '<pre><code>for cell in organism:\n    if cell.has_oxygen():\n        cell.respire_aerobically()\n</code></pre>'
        . '<p><img src="https://upload.wikimedia.org/wikipedia/commons/thumb/1/16/Animal_cell_structure_en.svg/500px-Animal_cell_structure_en.svg.png" alt="animal cell" style="max-width:100%"></p>'
        . $nestedlist
        . bio_lorem(40)
        . $bigtable;
    $add('MEGA CHAPTER — huge mixed content (80-row table, 7-level list, all headings, code, image)', $huge, 0);
    bio_log('added 1 mega-content chapter');

    // ---- One chapter with MANY subchapters (TOC length stress) ----
    $add('Chapter with many subchapters', '<p>The following ' . (25) . ' subchapters stress the table of contents.</p>', 0);
    for ($s = 1; $s <= 25; $s++) {
        $add('Subchapter ' . $s . ' of 25 — ' . ['glycolysis', 'Krebs cycle', 'electron transport', 'Calvin cycle', 'transcription'][$s % 5],
            '<p>' . bio_lorem(1 + $s % 3) . '</p>', 1);
    }
    bio_log('added 1 chapter + 25 subchapters');

    // ---- Minimal / empty-ish content chapters ----
    $add('Almost-empty chapter', '<p>.</p>', 0);
    $add('Whitespace subchapter', '&nbsp;', 1);

    // ---- Bulk chapters to make a long book ----
    for ($c = 1; $c <= 20 * 1; $c++) {
        $add('Bulk chapter ' . $c . ' — ' . strip_tags(substr(bio_lorem(1), 3, 40)), '<p>' . bio_lorem(2) . '</p>', ($c % 3 === 0) ? 1 : 0);
    }
    bio_log('added 20 bulk chapters');

    $total = $DB->count_records('book_chapters', ['bookid' => $book->id]);
    cli_writeln("Book phase complete: {$total} chapters/subchapters.");
}

// ===========================================================================
// PHASE: quiz  (dedicated sandbox section, one quiz per behaviour/config variant)
// ===========================================================================
if (in_array($PHASE, ['quiz', 'all'])) {
    cli_heading('Phase: quiz variants');
    $qgen = $dg->get_plugin_generator('core_question');
    $quizgen = $dg->get_plugin_generator('mod_quiz');
    $coursecontext = context_course::instance($COURSEID);

    // Sandbox section: reuse by name, else create at end of course.
    $sandboxname = 'Quiz stress-test sandbox';
    $section = $DB->get_record('course_sections', ['course' => $COURSEID, 'name' => $sandboxname]);
    if (!$section) {
        $section = course_create_section($course, 0); // append.
        $DB->set_field('course_sections', 'name', $sandboxname, ['id' => $section->id]);
        $DB->set_field('course_sections', 'summary',
            '<p>Each quiz below demonstrates a different Moodle quiz behaviour, navigation, layout or grading configuration.</p>',
            ['id' => $section->id]);
        $DB->set_field('course_sections', 'summaryformat', FORMAT_HTML, ['id' => $section->id]);
    }
    $sectionnum = $section->section;
    bio_log('sandbox section #' . $sectionnum);

    // Question bank: reuse category, wipe its questions for idempotency.
    $qcat = $DB->get_record('question_categories', ['name' => 'Biology 101 stress bank', 'contextid' => $coursecontext->id]);
    if (!$qcat) {
        $qcat = $qgen->create_question_category(['name' => 'Biology 101 stress bank', 'contextid' => $coursecontext->id]);
    } else {
        $qids = $DB->get_fieldset_sql(
            'SELECT q.id FROM {question} q
               JOIN {question_versions} qv ON qv.questionid = q.id
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              WHERE qbe.questioncategoryid = ?', [$qcat->id]);
        foreach ($qids as $qid) {
            question_delete_question($qid);
        }
    }

    // Build one question of every supported type (the "kitchen sink" set).
    $mk = function (string $qtype, ?string $which, string $name, ?string $text = null) use ($qgen, $qcat) {
        $over = ['category' => $qcat->id, 'name' => $name];
        if ($text !== null) {
            $over['questiontext'] = ['text' => $text, 'format' => FORMAT_HTML];
        }
        return $qgen->create_question($qtype, $which, $over);
    };
    $bank = [];
    $bank['mc1'] = $mk('multichoice', 'one_of_four', 'Sink: MC single', 'Which organelle makes most ATP?');
    $bank['mc2'] = $mk('multichoice', 'two_of_four', 'Sink: MC multi', 'Select the TWO nucleic acids.');
    $bank['tf']  = $mk('truefalse', 'true', 'Sink: True/False', 'Glycolysis occurs in the cytoplasm.');
    $bank['num'] = $mk('numerical', 'pi', 'Sink: Numerical');
    $bank['match'] = $mk('match', 'foursubq', 'Sink: Matching');
    $bank['essay'] = $mk('essay', 'plain', 'Sink: Essay (manual)');
    $bank['ddwtos'] = $mk('ddwtos', 'fox', 'Sink: Drag words into text');
    $bank['ord'] = $mk('ordering', 'moodle', 'Sink: Ordering');
    // A couple of simple auto-gradable questions for behaviour demos + attempts.
    $bank['tf2'] = $mk('truefalse', 'true', 'Demo: TF simple', 'Enzymes lower activation energy.');
    $bank['mc3'] = $mk('multichoice', 'one_of_four', 'Demo: MC simple', 'What is the powerhouse of the cell?');
    bio_log('built question bank (' . count($bank) . ' questions, 8 types)');

    // Helper to spin up a quiz variant, add questions, repaginate, recompute grades.
    $makequiz = function (string $idnumber, string $name, array $config, array $qkeys, int $perpage)
            use ($dg, $course, $sectionnum, $bank, &$quizgen) {
        $rec = array_merge([
            'section' => $sectionnum, 'name' => $name, 'grade' => 100, 'introformat' => FORMAT_HTML,
        ], $config);
        $quiz = bio_mod($dg, $course, 'quiz', $idnumber, $rec);
        foreach ($qkeys as $k) {
            quiz_add_quiz_question($bank[$k]->id, $quiz, 0, 1);
        }
        quiz_repaginate_questions($quiz->id, $perpage);
        \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        return $quiz;
    };

    $allkeys = ['mc1', 'mc2', 'tf', 'num', 'match', 'essay', 'ddwtos', 'ord'];
    $demokeys = ['tf2', 'mc3', 'tf'];

    // 1. Kitchen sink — every question type, deferred feedback, one per page, free nav.
    $q_sink = $makequiz('bio101_stress_quiz_sink', 'Variant: Kitchen sink (every question type)',
        ['intro' => 'One of every supported question type. Deferred feedback, one question per page, free navigation.',
         'preferredbehaviour' => 'deferredfeedback', 'navmethod' => 'free', 'shuffleanswers' => 1], $allkeys, 1);

    // 2. Adaptive behaviour, multiple attempts.
    $makequiz('bio101_stress_quiz_adaptive', 'Variant: Adaptive mode (penalties, retry within attempt)',
        ['intro' => 'Adaptive behaviour — students may try again within the same attempt (with penalty).',
         'preferredbehaviour' => 'adaptive', 'attempts' => 3, 'grademethod' => QUIZ_GRADEHIGHEST], $demokeys, 1);

    // 3. Immediate feedback.
    $makequiz('bio101_stress_quiz_immediate', 'Variant: Immediate feedback',
        ['intro' => 'Immediate feedback behaviour — check each question and see the result straight away.',
         'preferredbehaviour' => 'immediatefeedback'], $demokeys, 1);

    // 4. Interactive with multiple tries.
    $makequiz('bio101_stress_quiz_interactive', 'Variant: Interactive with multiple tries',
        ['intro' => 'Interactive behaviour — multiple tries per question with hints between tries.',
         'preferredbehaviour' => 'interactive'], $demokeys, 1);

    // 5. Deferred CBM (certainty-based marking).
    $makequiz('bio101_stress_quiz_cbm', 'Variant: Certainty-based marking (deferred CBM)',
        ['intro' => 'Deferred CBM — students also rate how certain they are of each answer.',
         'preferredbehaviour' => 'deferredcbm'], $demokeys, 1);

    // 6. Sequential navigation, one question per page.
    $makequiz('bio101_stress_quiz_sequential', 'Variant: Sequential navigation (no going back)',
        ['intro' => 'Sequential navigation — you cannot return to a previous question. One per page.',
         'preferredbehaviour' => 'deferredfeedback', 'navmethod' => 'sequential'], $allkeys, 1);

    // 7. All questions on one page, shuffle everything, unlimited attempts, average grading.
    $makequiz('bio101_stress_quiz_onepage', 'Variant: All on one page, shuffled, unlimited attempts (avg)',
        ['intro' => 'Every question on a single page, answers shuffled, unlimited attempts, average grading.',
         'preferredbehaviour' => 'deferredfeedback', 'navmethod' => 'free', 'shuffleanswers' => 1,
         'attempts' => 0, 'grademethod' => QUIZ_GRADEAVERAGE], $allkeys, 0);

    // 8. Timed quiz with open/close window and auto-submit.
    $makequiz('bio101_stress_quiz_timed', 'Variant: Timed (5 min limit, open/close window, auto-submit)',
        ['intro' => 'A 5-minute time limit with an open/close window; unanswered work is auto-submitted when time runs out.',
         'preferredbehaviour' => 'deferredfeedback', 'timelimit' => 300,
         'timeopen' => time() - DAYSECS, 'timeclose' => time() + 14 * DAYSECS,
         'overduehandling' => 'autosubmit', 'attempts' => 2, 'grademethod' => QUIZ_GRADEHIGHEST], $demokeys, 1);

    bio_log('created 8 quiz variants');

    // A few real attempts on the two simplest behaviour demos, for liveness.
    $attempt = function ($quiz, array $perstudent) use ($DB, $quizgen, $qgen, $students) {
        $slots = $DB->get_records('quiz_slots', ['quizid' => $quiz->id], 'slot');
        foreach ($perstudent as $i => $answers) {
            if (!isset($students[$i])) {
                continue;
            }
            try {
                \core\session\manager::set_user($students[$i]);
                $at = $quizgen->create_attempt($quiz->id, $students[$i]->id);
                $obj = \mod_quiz\quiz_attempt::create($at->id);
                $quba = \question_engine::load_questions_usage_by_activity($obj->get_uniqueid());
                $postdata = $qgen->get_simulated_post_data_for_questions_in_usage($quba, $answers, false);
                $obj->process_submitted_actions(time(), false, $postdata);
                $obj->process_submit(time(), false);
                $obj->process_grade_submission(time());
            } catch (\Throwable $e) {
                bio_log('WARN attempt: ' . $e->getMessage());
            }
            \core\session\manager::set_user(get_admin());
        }
    };
    // demokeys order: slot1=tf2(True correct), slot2=mc3(One correct), slot3=tf(True correct).
    $attempt($DB->get_record('quiz', ['id' => bio_cm($COURSEID, 'bio101_stress_quiz_immediate')->instance]) ?: $q_sink,
        [0 => [1 => 'True', 2 => 'One', 3 => 'True'], 1 => [1 => 'False', 2 => 'Two', 3 => 'True'],
         2 => [1 => 'True', 2 => 'Three', 3 => 'False']]);
    bio_log('seeded a few attempts on the immediate-feedback quiz');

    rebuild_course_cache($COURSEID, true);
    cli_writeln('Quiz phase complete: 8 variants in the sandbox section.');
}

cli_writeln('DONE (phase=' . $PHASE . ', scale=' . $SCALE . ')');
