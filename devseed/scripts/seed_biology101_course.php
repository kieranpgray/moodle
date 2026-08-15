<?php
/**
 * One-off idempotent seeder that turns course 15 (MYOV-T-02) into a rich,
 * realistic "Biology 101 - Mock data" course demonstrating every major
 * activity type and navigation variant, plus mock learner activity.
 *
 * Run inside the moodle_php container:
 *   php /var/www/html/devseed/scripts/seed_biology101_course.php --phase=all
 *
 * Phases: structure | activities | learners | all
 * Uses the same generator API that admin/tool/generator uses, so it is safe
 * on this live dev site.
 */

define('CLI_SCRIPT', true);
require('/var/www/html/public/config.php');
global $CFG, $DB;
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/completionlib.php');

list($options, $unrecognised) = cli_get_params(
    ['phase' => 'all', 'help' => false, 'courseid' => 15],
    ['h' => 'help']
);
if ($options['help']) {
    cli_writeln("Usage: php biology101_seed.php --phase=structure|activities|learners|all --courseid=15");
    exit(0);
}

$COURSEID = (int)$options['courseid'];
$PHASE = $options['phase'];

// Safety guard: only operate on the known Biology fixture course.
$course = $DB->get_record('course', ['id' => $COURSEID], '*', MUST_EXIST);
if (!in_array($course->shortname, ['MYOV-T-02', 'BIO101-MOCK'])) {
    cli_error("Refusing to run: course {$COURSEID} shortname is '{$course->shortname}', not the expected Biology fixture.");
}

$dg = \core\test\phpunit\phpunit_util::get_data_generator();

// Act as admin so content creation has a valid user + capabilities.
\core\session\manager::set_user(get_admin());

function bio_log(string $msg): void {
    cli_writeln('  ' . $msg);
}

/**
 * Create (or recreate) a module in the course. Idempotent via idnumber:
 * any existing module in this course with the same idnumber is deleted first.
 * Returns the created instance (with ->cmid).
 */
function bio_mod($dg, $course, string $modname, string $idnumber, array $record) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    // Delete any prior instance with this idnumber (re-run safety).
    $existing = $DB->get_records('course_modules', ['course' => $course->id, 'idnumber' => $idnumber]);
    foreach ($existing as $cm) {
        course_delete_module($cm->id);
    }
    $record['course'] = $course->id;
    $record['idnumber'] = $idnumber;
    $gen = $dg->get_plugin_generator('mod_' . $modname);
    return $gen->create_instance($record);
}

/** JSON availability string restricting a cm on completion of another cm. */
function bio_restrict_on_completion(int $cmid): string {
    return json_encode([
        'op' => '&',
        'c' => [['type' => 'completion', 'cm' => $cmid, 'e' => 1]],
        'showc' => [true],
    ]);
}

/**
 * Fetch a user by username or create+enrol a fresh one into the course.
 */
function bio_get_or_create_student($dg, $course, string $username, string $first, string $last): stdClass {
    global $DB;
    $user = $DB->get_record('user', ['username' => $username]);
    if (!$user) {
        $user = $dg->create_user([
            'username' => $username,
            'firstname' => $first,
            'lastname' => $last,
            'email' => $username . '@mock.invalid',
            'password' => 'Test1234!',
        ]);
        bio_log("created user {$username}");
    }
    // Ensure enrolled as student (enrol_user is safe to call repeatedly).
    $dg->enrol_user($user->id, $course->id, 'student');
    return $user;
}

// ---------------------------------------------------------------------------
// PHASE: structure
// ---------------------------------------------------------------------------
if (in_array($PHASE, ['structure', 'all'])) {
    cli_heading('Phase: structure');

    // 1. Rename + course settings.
    $course->fullname = 'Biology 101 - Mock data';
    $course->summary = '<p>Welcome to <strong>Biology 101</strong> — an introductory survey of the '
        . 'science of life, from molecules and cells to genetics, evolution and ecology. This course '
        . 'contains <em>mock data</em> used to demonstrate Moodle activity types and navigation.</p>'
        . '<p>Work through the weekly topics in order. Each topic mixes readings, interactive '
        . 'content, discussion and assessment.</p>';
    $course->summaryformat = FORMAT_HTML;
    $course->enablecompletion = 1;
    $course->showcompletionconditions = 1;
    update_course($course);
    bio_log("renamed course to '{$course->fullname}' + enabled completion");

    // 2. Ensure 13 sections (0..12) exist.
    course_create_sections_if_missing($course, range(0, 12));
    // Bump numsections for topics format if needed.
    $format = course_get_format($course);
    if ($format->get_format() === 'topics') {
        $opts = $format->get_format_options();
        if (($opts['numsections'] ?? 0) < 12) {
            $format->update_course_format_options(['numsections' => 12]);
        }
    }

    // 3. Name + summarise each topic section.
    $sections = [
        1 => ['Introduction to Biology & the Scientific Method', 'What is life? Characteristics of living things, levels of biological organisation, and how scientists ask and answer questions.'],
        2 => ['The Chemistry of Life', 'Atoms, bonds, water, pH, and the four classes of biological macromolecules: carbohydrates, lipids, proteins and nucleic acids.'],
        3 => ['Cell Structure & Function', 'Prokaryotic vs eukaryotic cells, organelles, and the endomembrane system. The cell as the basic unit of life.'],
        4 => ['Membranes & Transport', 'The fluid-mosaic membrane, diffusion, osmosis, and active vs passive transport.'],
        5 => ['Enzymes & Metabolism', 'Energy, ATP, activation energy and how enzymes catalyse the reactions of life.'],
        6 => ['Cellular Respiration', 'Glycolysis, the Krebs cycle and oxidative phosphorylation — how cells harvest energy from glucose.'],
        7 => ['Photosynthesis', 'Light-dependent reactions and the Calvin cycle — how producers capture light energy.'],
        8 => ['Cell Division: Mitosis & Meiosis', 'The cell cycle, mitosis, meiosis, and the origin of genetic variation.'],
        9 => ['Genetics & Mendelian Inheritance', 'Mendel\'s laws, Punnett squares, dominance, and patterns of inheritance.'],
        10 => ['DNA & Protein Synthesis', 'DNA structure, replication, transcription and translation — the central dogma.'],
        11 => ['Evolution & Natural Selection', 'Descent with modification, evidence for evolution, and the mechanisms of change.'],
        12 => ['Ecology & Ecosystems', 'Populations, communities, energy flow, biogeochemical cycles and biodiversity.'],
    ];
    foreach ($sections as $num => [$name, $summary]) {
        $DB->set_field('course_sections', 'name', $name, ['course' => $course->id, 'section' => $num]);
        $DB->set_field('course_sections', 'summary', '<p>' . $summary . '</p>', ['course' => $course->id, 'section' => $num]);
        $DB->set_field('course_sections', 'summaryformat', FORMAT_HTML, ['course' => $course->id, 'section' => $num]);
        $DB->set_field('course_sections', 'visible', 1, ['course' => $course->id, 'section' => $num]);
    }
    bio_log('named + summarised 12 topic sections');

    // 4. Mock students + groups.
    $roster = [
        'bio_ava'     => ['Ava', 'Nguyen'],
        'bio_liam'    => ['Liam', 'Okafor'],
        'bio_sofia'   => ['Sofia', 'Rossi'],
        'bio_noah'    => ['Noah', 'Patel'],
        'bio_mia'     => ['Mia', 'Johansson'],
        'bio_ethan'   => ['Ethan', 'Kim'],
    ];
    $students = [];
    foreach ($roster as $username => [$first, $last]) {
        $students[$username] = bio_get_or_create_student($dg, $course, $username, $first, $last);
    }
    // Also enrol the existing student_busy fixture user so the course works from the student dashboard.
    if ($sb = $DB->get_record('user', ['username' => 'student_busy'])) {
        $dg->enrol_user($sb->id, $course->id, 'student');
        $students['student_busy'] = $sb;
    }
    bio_log('enrolled ' . count($students) . ' students');

    // Groups (idempotent by idnumber).
    foreach (['LABA' => 'Lab Group A', 'LABB' => 'Lab Group B'] as $idnum => $gname) {
        if (!$DB->get_record('groups', ['courseid' => $course->id, 'idnumber' => $idnum])) {
            $dg->create_group(['courseid' => $course->id, 'name' => $gname, 'idnumber' => $idnum]);
        }
    }
    $groupa = $DB->get_record('groups', ['courseid' => $course->id, 'idnumber' => 'LABA']);
    $groupb = $DB->get_record('groups', ['courseid' => $course->id, 'idnumber' => 'LABB']);
    $i = 0;
    foreach ($students as $stu) {
        $g = ($i % 2 === 0) ? $groupa : $groupb;
        if (!$DB->record_exists('groups_members', ['groupid' => $g->id, 'userid' => $stu->id])) {
            $dg->create_group_member(['groupid' => $g->id, 'userid' => $stu->id]);
        }
        $i++;
    }
    bio_log('split students into Lab Group A/B');

    cli_writeln('Structure phase complete.');
}

// ---------------------------------------------------------------------------
// PHASE: activities
// ---------------------------------------------------------------------------
if (in_array($PHASE, ['activities', 'all'])) {
    cli_heading('Phase: activities');
    $forumgen = $dg->get_plugin_generator('mod_forum');
    $bookgen = $dg->get_plugin_generator('mod_book');
    $lessongen = $dg->get_plugin_generator('mod_lesson');
    $glossarygen = $dg->get_plugin_generator('mod_glossary');
    $datagen = $dg->get_plugin_generator('mod_data');
    $wikigen = $dg->get_plugin_generator('mod_wiki');

    // ===== SECTION 0: General =====
    bio_mod($dg, $course, 'page', 'bio101_page_start', [
        'section' => 0, 'name' => 'Start Here: How this course works',
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1,
        'intro' => 'Read this first.', 'introformat' => FORMAT_HTML,
        'content' => '<h3>Welcome to Biology 101</h3>'
            . '<p>This course runs across <strong>12 weekly topics</strong>. Each week has readings, '
            . 'at least one interactive or collaborative activity, and something to submit or discuss.</p>'
            . '<ul><li>Use the <em>course index</em> (left) to jump between topics.</li>'
            . '<li>Watch your <em>completion</em> ticks — they track your progress.</li>'
            . '<li>Post questions in the <strong>Course Q&amp;A</strong> forum.</li></ul>',
        'contentformat' => FORMAT_HTML,
    ]);

    $qaforum = bio_mod($dg, $course, 'forum', 'bio101_forum_qa', [
        'section' => 0, 'type' => 'qanda', 'name' => 'Course Q&A',
        'intro' => 'Ask anything about the course here. This is a Q&amp;A forum: you must post your own '
            . 'answer before you can see other students\' replies.', 'introformat' => FORMAT_HTML,
    ]);

    // Syllabus book (multi-chapter -> demonstrates Book TOC + prev/next navigation).
    $syllabus = bio_mod($dg, $course, 'book', 'bio101_book_syllabus', [
        'section' => 0, 'name' => 'Course Syllabus & Study Guide',
        'intro' => 'Everything you need to know about how this course is run and assessed.',
        'introformat' => FORMAT_HTML,
    ]);
    $syllabuschapters = [
        ['Course Overview', '<p>Biology 101 is a one-semester introduction to the science of life. By the end you '
            . 'should be able to explain how living systems are organised from molecules to ecosystems.</p>'],
        ['Learning Outcomes', '<ul><li>Describe the structure and function of cells.</li>'
            . '<li>Explain energy flow through respiration and photosynthesis.</li>'
            . '<li>Apply Mendelian genetics to predict inheritance.</li>'
            . '<li>Summarise the evidence for evolution by natural selection.</li></ul>'],
        ['Assessment', '<p>Your grade is made up of:</p><table border="1" cellpadding="6"><tr><th>Component</th><th>Weight</th></tr>'
            . '<tr><td>Quizzes (2)</td><td>20%</td></tr><tr><td>Assignments (2)</td><td>30%</td></tr>'
            . '<tr><td>Peer-reviewed essay</td><td>20%</td></tr><tr><td>Capstone case study</td><td>25%</td></tr>'
            . '<tr><td>Participation</td><td>5%</td></tr></table>'],
        ['Weekly Schedule', '<p>Topics 1&ndash;12 run one per week. Readings should be completed before the '
            . 'associated quiz or assignment opens.</p>'],
        ['Academic Integrity', '<p>All submitted work must be your own. Collaboration is encouraged on wikis and '
            . 'databases, but assignments and quizzes are individual.</p>'],
    ];
    foreach ($syllabuschapters as $i => [$title, $content]) {
        $bookgen->create_content($syllabus, ['title' => $title, 'content' => $content, 'pagenum' => $i + 1]);
    }

    // Course glossary (auto-linking, main + global) -> demonstrates inline term navigation.
    $glossary = bio_mod($dg, $course, 'glossary', 'bio101_glossary_main', [
        'section' => 0, 'name' => 'Biology Glossary', 'mainglossary' => 1, 'globalglossary' => 1,
        'usedynalink' => 1, 'defaultapproval' => 1, 'displayformat' => 'dictionary',
        'intro' => 'Key terms used throughout Biology 101. Terms are auto-linked wherever they appear in the course.',
        'introformat' => FORMAT_HTML,
    ]);
    $glossaryterms = [
        ['Cell', 'The basic structural and functional unit of all living organisms.'],
        ['Organelle', 'A specialised subunit within a cell that has a specific function, e.g. the mitochondrion.'],
        ['Mitochondrion', 'The organelle where aerobic respiration occurs; often called the "powerhouse" of the cell.'],
        ['Enzyme', 'A biological catalyst, usually a protein, that speeds up a reaction by lowering activation energy.'],
        ['Photosynthesis', 'The process by which plants convert light energy into chemical energy stored in glucose.'],
        ['Mitosis', 'Nuclear division producing two genetically identical diploid daughter cells.'],
        ['Meiosis', 'Nuclear division producing four genetically varied haploid gametes.'],
        ['Allele', 'One of two or more alternative forms of a gene.'],
        ['Genotype', 'The genetic makeup of an organism.'],
        ['Phenotype', 'The observable characteristics of an organism, resulting from genotype and environment.'],
        ['Natural selection', 'The process by which organisms better adapted to their environment tend to survive and reproduce more.'],
        ['Ecosystem', 'A community of interacting organisms together with their physical environment.'],
    ];
    foreach ($glossaryterms as [$concept, $definition]) {
        $glossarygen->create_content($glossary, ['concept' => $concept, 'definition' => $definition]);
    }

    bio_mod($dg, $course, 'url', 'bio101_url_crashcourse', [
        'section' => 0, 'name' => 'Recommended channel: Crash Course Biology',
        'externalurl' => 'https://www.youtube.com/playlist?list=PL3EED4C1D684D3ADF',
        'intro' => 'An optional but excellent video series covering the whole course.', 'introformat' => FORMAT_HTML,
    ]);

    // A downloadable resource (auto-generated file) in the General section.
    bio_mod($dg, $course, 'resource', 'bio101_resource_syllabuspdf', [
        'section' => 0, 'name' => 'Printable syllabus (file)',
        'intro' => 'Download and keep a copy of the syllabus.', 'introformat' => FORMAT_HTML,
    ]);

    bio_log('Section 0 (General) built');

    // ===== SECTION 1: Intro & Scientific Method =====
    bio_mod($dg, $course, 'page', 'bio101_page_whatislife', [
        'section' => 1, 'name' => 'Reading: What is Life?',
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1,
        'intro' => 'Core reading for Topic 1.', 'introformat' => FORMAT_HTML,
        'content' => '<h3>The characteristics of life</h3><p>Biologists identify life by a shared set of '
            . 'properties: <strong>order</strong>, response to the environment, reproduction, growth and '
            . 'development, regulation (homeostasis), energy processing, and evolutionary adaptation.</p>'
            . '<p>Living things are organised into a hierarchy: atom &rarr; molecule &rarr; organelle &rarr; '
            . '<em>cell</em> &rarr; tissue &rarr; organ &rarr; organism &rarr; population &rarr; community &rarr; '
            . '<em>ecosystem</em> &rarr; biosphere.</p>'
            . '<h3>The scientific method</h3><p>Observation &rarr; question &rarr; hypothesis &rarr; prediction '
            . '&rarr; experiment &rarr; analysis. A good hypothesis is testable and falsifiable.</p>',
        'contentformat' => FORMAT_HTML,
    ]);
    bio_mod($dg, $course, 'choice', 'bio101_choice_topic', [
        'section' => 1, 'name' => 'Poll: Which topic are you most excited about?',
        'intro' => 'Help shape optional extra sessions.', 'introformat' => FORMAT_HTML,
        'option' => ['Genetics', 'Evolution', 'Ecology', 'Cell biology', 'Human physiology'],
        'allowupdate' => 1, 'showresults' => 1,
    ]);
    bio_mod($dg, $course, 'forum', 'bio101_forum_intro', [
        'section' => 1, 'type' => 'single', 'name' => 'Introduce yourself',
        'intro' => 'Tell the class who you are and why you are taking Biology 101.', 'introformat' => FORMAT_HTML,
    ]);
    bio_log('Section 1 built');

    // ===== SECTION 2: Chemistry of Life =====
    $chembook = bio_mod($dg, $course, 'book', 'bio101_book_chem', [
        'section' => 2, 'name' => 'Textbook: The Chemistry of Life',
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1,
        'intro' => 'A short textbook chapter with sub-chapters — use the table of contents to navigate.',
        'introformat' => FORMAT_HTML,
    ]);
    $chemchapters = [
        ['Atoms & Bonds', 0, '<p>All matter is made of atoms. Chemical bonds &mdash; <strong>covalent</strong>, '
            . '<strong>ionic</strong> and <strong>hydrogen</strong> bonds &mdash; hold molecules together and store energy.</p>'],
        ['Water: the medium of life', 0, '<p>Water\'s polarity gives it cohesion, adhesion, high specific heat and '
            . 'its power as the universal biological solvent. pH measures hydrogen-ion concentration.</p>'],
        ['Carbohydrates', 1, '<p>Sugars and polysaccharides (starch, glycogen, cellulose) provide energy and structure.</p>'],
        ['Lipids', 1, '<p>Fats, phospholipids and steroids store energy and build membranes.</p>'],
        ['Proteins', 1, '<p>Polymers of amino acids that fold into shapes enabling enzymes, structure and transport.</p>'],
        ['Nucleic acids', 1, '<p>DNA and RNA store and transmit genetic information.</p>'],
    ];
    $pn = 1;
    foreach ($chemchapters as [$title, $sub, $content]) {
        $chembook->__unused = null;
        $bookgen->create_content($chembook, ['title' => $title, 'content' => $content, 'subchapter' => $sub, 'pagenum' => $pn++]);
    }
    bio_mod($dg, $course, 'url', 'bio101_url_water', [
        'section' => 2, 'name' => 'Video: The properties of water',
        'externalurl' => 'https://www.youtube.com/watch?v=HVT3Y3_gHGg',
        'intro' => 'A 10-minute primer on why water is so important to life.', 'introformat' => FORMAT_HTML,
    ]);
    bio_mod($dg, $course, 'label', 'bio101_label_macro', [
        'section' => 2, 'intro' => '<h4>Quick reference: the four macromolecules</h4>'
            . '<table border="1" cellpadding="6"><tr><th>Class</th><th>Monomer</th><th>Function</th></tr>'
            . '<tr><td>Carbohydrate</td><td>Monosaccharide</td><td>Energy, structure</td></tr>'
            . '<tr><td>Lipid</td><td>(none / glycerol+fatty acids)</td><td>Energy store, membranes</td></tr>'
            . '<tr><td>Protein</td><td>Amino acid</td><td>Enzymes, structure, transport</td></tr>'
            . '<tr><td>Nucleic acid</td><td>Nucleotide</td><td>Information storage</td></tr></table>',
        'introformat' => FORMAT_HTML,
    ]);
    bio_log('Section 2 built');

    // ===== SECTION 3: Cell Structure & Function =====
    $celllesson = bio_mod($dg, $course, 'lesson', 'bio101_lesson_cell', [
        'section' => 3, 'name' => 'Interactive: A Tour of the Cell',
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1,
        'intro' => 'A self-paced lesson. Move through the pages using the navigation buttons.',
        'introformat' => FORMAT_HTML,
    ]);
    // Chain content pages so prev/next navigation works (insert last-first).
    $lessonpages = [
        ['The Cell Theory', '<p>All living things are made of cells; the cell is the basic unit of life; all cells '
            . 'come from pre-existing cells.</p>'],
        ['Prokaryotes vs Eukaryotes', '<p>Prokaryotic cells (bacteria, archaea) lack a nucleus. Eukaryotic cells '
            . '(plants, animals, fungi, protists) keep their DNA in a membrane-bound nucleus.</p>'],
        ['The Nucleus & Ribosomes', '<p>The nucleus stores DNA; ribosomes read mRNA to build proteins.</p>'],
        ['Mitochondria & Chloroplasts', '<p>Mitochondria release energy; chloroplasts (in plants) capture light.</p>'],
    ];
    $prevpageid = 0;
    foreach ($lessonpages as $idx => [$title, $content]) {
        $page = $lessongen->create_content($celllesson, [
            'title' => $title, 'pageid' => $prevpageid,
            'contents_editor' => ['text' => $content, 'format' => FORMAT_HTML, 'itemid' => 0],
        ]);
        $prevpageid = $page->id;
    }
    // Interactive H5P activity (uses a bundled fixture package).
    bio_mod($dg, $course, 'h5pactivity', 'bio101_h5p_organelles', [
        'section' => 3, 'name' => 'Interactive: Identify the organelle',
        'intro' => 'A short interactive multiple-choice activity.', 'introformat' => FORMAT_HTML,
        'packagefilepath' => $CFG->dirroot . '/h5p/tests/fixtures/multiple-choice-2-6.h5p',
    ]);
    bio_mod($dg, $course, 'folder', 'bio101_folder_micro', [
        'section' => 3, 'name' => 'Lab resources: Microscopy',
        'intro' => 'Slides, worksheets and a microscopy checklist for this week\'s lab.', 'introformat' => FORMAT_HTML,
    ]);
    bio_log('Section 3 built');

    // ===== SECTION 4: Membranes & Transport (subsection = nested navigation) =====
    bio_mod($dg, $course, 'page', 'bio101_page_membrane', [
        'section' => 4, 'name' => 'Reading: The Fluid Mosaic Model',
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1,
        'intro' => 'Core reading for Topic 4.', 'introformat' => FORMAT_HTML,
        'content' => '<p>The plasma membrane is a <strong>phospholipid bilayer</strong> studded with proteins. '
            . 'Its selective permeability controls what enters and leaves the cell.</p>',
        'contentformat' => FORMAT_HTML,
    ]);
    // The subsection module creates a delegated (nested) section.
    $subsection = bio_mod($dg, $course, 'subsection', 'bio101_subsection_transport', [
        'section' => 4, 'name' => 'Transport mechanisms (nested)',
        'intro' => 'Open this sub-section to explore passive and active transport.', 'introformat' => FORMAT_HTML,
    ]);
    // Find the delegated section that the subsection created, and add modules INTO it.
    $delegated = $DB->get_record('course_sections', [
        'course' => $course->id, 'component' => 'mod_subsection', 'itemid' => $subsection->id,
    ]);
    if ($delegated) {
        bio_mod($dg, $course, 'page', 'bio101_page_passive', [
            'section' => $delegated->section, 'name' => 'Passive transport (diffusion & osmosis)',
            'intro' => 'Nested reading.', 'introformat' => FORMAT_HTML,
            'content' => '<p>Diffusion and osmosis move substances <em>down</em> their concentration gradient with no '
                . 'energy cost. Osmosis is the diffusion of water across a semi-permeable membrane.</p>',
            'contentformat' => FORMAT_HTML,
        ]);
        bio_mod($dg, $course, 'page', 'bio101_page_active', [
            'section' => $delegated->section, 'name' => 'Active transport & bulk transport',
            'intro' => 'Nested reading.', 'introformat' => FORMAT_HTML,
            'content' => '<p>Active transport pumps substances <em>against</em> their gradient using ATP '
                . '(e.g. the sodium&ndash;potassium pump). Endocytosis and exocytosis move bulk cargo.</p>',
            'contentformat' => FORMAT_HTML,
        ]);
        bio_log('  subsection populated (delegated section #' . $delegated->section . ')');
    } else {
        bio_log('  WARNING: delegated subsection section not found');
    }
    bio_log('Section 4 built');

    // ===== SECTION 5: Enzymes & Metabolism =====
    bio_mod($dg, $course, 'page', 'bio101_page_enzymes', [
        'section' => 5, 'name' => 'Reading: How enzymes work',
        'intro' => 'Core reading for Topic 5.', 'introformat' => FORMAT_HTML,
        'content' => '<p>An <strong>enzyme</strong> lowers the activation energy of a reaction. The '
            . 'lock-and-key and induced-fit models describe how a substrate binds the active site. Temperature, '
            . 'pH and inhibitors all affect enzyme activity.</p>',
        'contentformat' => FORMAT_HTML,
    ]);
    $assign1 = bio_mod($dg, $course, 'assign', 'bio101_assign_enzyme', [
        'section' => 5, 'name' => 'Assignment 1: Enzyme lab report',
        'intro' => '<p>Submit your write-up of the catalase enzyme practical. Include a hypothesis, method, '
            . 'results table and discussion. Online text <em>and</em> a file attachment are accepted.</p>',
        'introformat' => FORMAT_HTML,
        'assignsubmission_onlinetext_enabled' => 1, 'assignsubmission_file_enabled' => 1,
        'assignsubmission_file_maxfiles' => 2, 'submissiondrafts' => 0,
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionsubmit' => 1, 'grade' => 100,
        'duedate' => time() + 14 * DAYSECS,
    ]);
    bio_log('Section 5 built');

    // ===== SECTION 6: Cellular Respiration (quiz restricted on Assignment 1) =====
    bio_mod($dg, $course, 'page', 'bio101_page_respiration', [
        'section' => 6, 'name' => 'Reading: From glucose to ATP',
        'intro' => 'Core reading for Topic 6.', 'introformat' => FORMAT_HTML,
        'content' => '<p>Aerobic respiration has three stages: <strong>glycolysis</strong> (cytoplasm), the '
            . '<strong>Krebs cycle</strong> (mitochondrial matrix) and <strong>oxidative phosphorylation</strong> '
            . '(inner mitochondrial membrane), yielding up to ~30&ndash;32 ATP per glucose.</p>',
        'contentformat' => FORMAT_HTML,
    ]);
    // Build a question category + a few questions, then a quiz.
    $qgen = $dg->get_plugin_generator('core_question');
    $coursecontext = context_course::instance($course->id);
    $qcat = $DB->get_record('question_categories', ['name' => 'Biology 101 questions', 'contextid' => $coursecontext->id]);
    if (!$qcat) {
        $qcat = $qgen->create_question_category(['name' => 'Biology 101 questions', 'contextid' => $coursecontext->id]);
    }
    $quiz1 = bio_mod($dg, $course, 'quiz', 'bio101_quiz_resp', [
        'section' => 6, 'name' => 'Quiz 1: Cellular Respiration',
        'intro' => 'Ten-minute check on respiration. You may attempt it twice; your highest score counts.',
        'introformat' => FORMAT_HTML, 'grade' => 100, 'attempts' => 2, 'grademethod' => 1,
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1,
        'availability' => bio_restrict_on_completion($assign1->cmid),
    ]);
    $q1a = $qgen->create_question('truefalse', null, ['category' => $qcat->id,
        'name' => 'Respiration TF1', 'questiontext' => ['text' => 'Glycolysis occurs in the cytoplasm.', 'format' => FORMAT_HTML]]);
    $q1b = $qgen->create_question('truefalse', null, ['category' => $qcat->id,
        'name' => 'Respiration TF2', 'questiontext' => ['text' => 'Oxidative phosphorylation generates most of a cell\'s ATP.', 'format' => FORMAT_HTML]]);
    $q1c = $qgen->create_question('multichoice', 'one_of_four', ['category' => $qcat->id,
        'name' => 'Respiration MC1', 'questiontext' => ['text' => 'Where does oxidative phosphorylation take place?', 'format' => FORMAT_HTML]]);
    quiz_add_quiz_question($q1a->id, $quiz1, 0, 1);
    quiz_add_quiz_question($q1b->id, $quiz1, 0, 1);
    quiz_add_quiz_question($q1c->id, $quiz1, 0, 1);
    \mod_quiz\quiz_settings::create($quiz1->id)->get_grade_calculator()->recompute_quiz_sumgrades();
    bio_log('Section 6 built (quiz restricted until Assignment 1 complete)');

    // ===== SECTION 7: Photosynthesis =====
    $wiki = bio_mod($dg, $course, 'wiki', 'bio101_wiki_photo', [
        'section' => 7, 'name' => 'Class Wiki: Photosynthesis notes', 'wikimode' => 'collaborative',
        'firstpagetitle' => 'Photosynthesis', 'defaultformat' => 'html',
        'intro' => 'Build a shared set of notes on photosynthesis. Everyone can edit.', 'introformat' => FORMAT_HTML,
    ]);
    $wikigen->create_first_page($wiki, ['title' => 'Photosynthesis',
        'content' => '<h3>Photosynthesis</h3><p>6CO&#8322; + 6H&#8322;O + light &rarr; C&#8326;H&#8321;&#8322;O&#8326; + 6O&#8322;.</p>'
            . '<p>Edit this page to add the <a>light-dependent reactions</a> and the <a>Calvin cycle</a>.</p>']);
    bio_mod($dg, $course, 'forum', 'bio101_forum_photo', [
        'section' => 7, 'type' => 'general', 'name' => 'Discussion: C3, C4 and CAM plants',
        'intro' => 'Why did different photosynthetic strategies evolve? Share an example plant.', 'introformat' => FORMAT_HTML,
    ]);
    bio_log('Section 7 built');

    // ===== SECTION 8: Cell Division =====
    $celldb = bio_mod($dg, $course, 'data', 'bio101_data_cellcycle', [
        'section' => 8, 'name' => 'Database: Cell-cycle stage catalogue',
        'intro' => 'Add a card for each stage of the cell cycle with a description and what happens to the DNA.',
        'introformat' => FORMAT_HTML,
    ]);
    $celldb_fields = [];
    $celldb_fields['stage'] = $datagen->create_field((object)['type' => 'text', 'name' => 'Stage', 'description' => 'Name of the stage'], $celldb);
    $celldb_fields['summary'] = $datagen->create_field((object)['type' => 'textarea', 'name' => 'Summary', 'description' => 'What happens'], $celldb);
    $celldb_fields['dna'] = $datagen->create_field((object)['type' => 'text', 'name' => 'DNA state', 'description' => 'Chromosome/DNA state'], $celldb);
    bio_mod($dg, $course, 'assign', 'bio101_assign_mitosis', [
        'section' => 8, 'name' => 'Assignment 2: Mitosis vs Meiosis diagram',
        'intro' => 'Upload a labelled diagram (image or PDF) comparing mitosis and meiosis.', 'introformat' => FORMAT_HTML,
        'assignsubmission_file_enabled' => 1, 'assignsubmission_file_maxfiles' => 1, 'assignsubmission_onlinetext_enabled' => 0,
        'grade' => 100, 'duedate' => time() + 21 * DAYSECS,
    ]);
    bio_log('Section 8 built');

    // ===== SECTION 9: Genetics =====
    bio_mod($dg, $course, 'url', 'bio101_url_phet', [
        'section' => 9, 'name' => 'Simulation: Natural selection & genetics (PhET)',
        'externalurl' => 'https://phet.colorado.edu/en/simulations/natural-selection',
        'intro' => 'Interactive simulation — experiment with alleles and selection pressures.', 'introformat' => FORMAT_HTML,
    ]);
    $genlesson = bio_mod($dg, $course, 'lesson', 'bio101_lesson_genetics', [
        'section' => 9, 'name' => 'Interactive: Monohybrid crosses',
        'intro' => 'Work through a monohybrid cross step by step.', 'introformat' => FORMAT_HTML,
    ]);
    $genpages = [
        ['Dominant & recessive alleles', '<p>Capital letters denote dominant alleles (e.g. <em>T</em>), lower case '
            . 'recessive (<em>t</em>). A recessive phenotype only appears in the homozygous <em>tt</em> genotype.</p>'],
        ['Setting up a Punnett square', '<p>Cross <em>Tt</em> &times; <em>Tt</em>. The 2&times;2 grid predicts a '
            . '3:1 phenotypic ratio and a 1:2:1 genotypic ratio.</p>'],
    ];
    $prev = 0;
    foreach ($genpages as [$title, $content]) {
        $p = $lessongen->create_content($genlesson, ['title' => $title, 'pageid' => $prev,
            'contents_editor' => ['text' => $content, 'format' => FORMAT_HTML, 'itemid' => 0]]);
        $prev = $p->id;
    }
    $quiz2 = bio_mod($dg, $course, 'quiz', 'bio101_quiz_genetics', [
        'section' => 9, 'name' => 'Quiz 2: Mendelian genetics',
        'intro' => 'Apply Punnett squares and Mendel\'s laws.', 'introformat' => FORMAT_HTML,
        'grade' => 100, 'attempts' => 1, 'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1,
    ]);
    $q2a = $qgen->create_question('truefalse', null, ['category' => $qcat->id,
        'name' => 'Genetics TF1', 'questiontext' => ['text' => 'A Tt x Tt cross gives a 3:1 phenotypic ratio.', 'format' => FORMAT_HTML]]);
    $q2b = $qgen->create_question('multichoice', 'one_of_four', ['category' => $qcat->id,
        'name' => 'Genetics MC1', 'questiontext' => ['text' => 'What is the genotype of a true-breeding tall pea plant?', 'format' => FORMAT_HTML]]);
    quiz_add_quiz_question($q2a->id, $quiz2, 0, 1);
    quiz_add_quiz_question($q2b->id, $quiz2, 0, 1);
    \mod_quiz\quiz_settings::create($quiz2->id)->get_grade_calculator()->recompute_quiz_sumgrades();
    bio_log('Section 9 built');

    // ===== SECTION 10: DNA & Protein Synthesis =====
    $dnabook = bio_mod($dg, $course, 'book', 'bio101_book_dna', [
        'section' => 10, 'name' => 'Textbook: DNA & Protein Synthesis',
        'intro' => 'The central dogma, chapter by chapter.', 'introformat' => FORMAT_HTML,
    ]);
    $dnachapters = [
        ['The structure of DNA', '<p>A double helix of nucleotides; A pairs with T, C pairs with G.</p>'],
        ['Replication', '<p>Semi-conservative copying by DNA polymerase produces two identical strands.</p>'],
        ['Transcription', '<p>RNA polymerase builds mRNA from a DNA template in the nucleus.</p>'],
        ['Translation', '<p>Ribosomes read codons and tRNA delivers amino acids to build a protein.</p>'],
    ];
    $pn = 1;
    foreach ($dnachapters as [$title, $content]) {
        $bookgen->create_content($dnabook, ['title' => $title, 'content' => $content, 'pagenum' => $pn++]);
    }
    bio_mod($dg, $course, 'page', 'bio101_page_codon', [
        'section' => 10, 'name' => 'Reference: The genetic code',
        'intro' => 'Codon reference.', 'introformat' => FORMAT_HTML,
        'content' => '<p>Each three-base <strong>codon</strong> specifies one amino acid. AUG is the start codon; '
            . 'UAA, UAG and UGA are stop codons.</p>', 'contentformat' => FORMAT_HTML,
    ]);
    bio_log('Section 10 built');

    // ===== SECTION 11: Evolution (workshop = peer assessment) =====
    bio_mod($dg, $course, 'workshop', 'bio101_workshop_evo', [
        'section' => 11, 'name' => 'Peer review: Evolution essay',
        'intro' => '<p>Write a 500-word essay: <em>"Describe two lines of evidence for evolution by natural '
            . 'selection."</em> You will then peer-assess two classmates\' essays against the rubric.</p>',
        'introformat' => FORMAT_HTML, 'grade' => 80, 'gradinggrade' => 20,
    ]);
    bio_mod($dg, $course, 'forum', 'bio101_forum_debate', [
        'section' => 11, 'type' => 'general', 'name' => 'Debate: Is the peppered moth the best example of natural selection?',
        'intro' => 'Take a side and back it with evidence.', 'introformat' => FORMAT_HTML,
    ]);
    bio_log('Section 11 built');

    // ===== SECTION 12: Ecology (capstone + feedback) =====
    $speciesdb = bio_mod($dg, $course, 'data', 'bio101_data_species', [
        'section' => 12, 'name' => 'Database: Local species catalogue',
        'intro' => 'Contribute a species you have observed locally: common name, trophic level and a note.',
        'introformat' => FORMAT_HTML,
    ]);
    $species_fields = [];
    $species_fields['common'] = $datagen->create_field((object)['type' => 'text', 'name' => 'Common name', 'description' => 'Everyday name'], $speciesdb);
    $species_fields['trophic'] = $datagen->create_field((object)['type' => 'menu', 'name' => 'Trophic level',
        'description' => 'Feeding level', 'param1' => "Producer\nPrimary consumer\nSecondary consumer\nDecomposer"], $speciesdb);
    $species_fields['note'] = $datagen->create_field((object)['type' => 'textarea', 'name' => 'Field note', 'description' => 'Observation'], $speciesdb);
    bio_mod($dg, $course, 'choice', 'bio101_choice_fieldtrip', [
        'section' => 12, 'name' => 'Vote: Field-trip destination',
        'intro' => 'Where should our ecology field trip go?', 'introformat' => FORMAT_HTML,
        'option' => ['Coastal rock pools', 'Deciduous woodland', 'Freshwater pond', 'Urban park transect'],
        'allowupdate' => 1, 'showresults' => 1,
    ]);
    bio_mod($dg, $course, 'assign', 'bio101_assign_capstone', [
        'section' => 12, 'name' => 'Capstone: Ecosystem case study',
        'intro' => '<p>Choose a local ecosystem and analyse its energy flow, key species interactions and one '
            . 'human impact. 1000 words plus a food-web diagram.</p>', 'introformat' => FORMAT_HTML,
        'assignsubmission_onlinetext_enabled' => 1, 'assignsubmission_file_enabled' => 1,
        'grade' => 100, 'duedate' => time() + 35 * DAYSECS,
    ]);
    $feedback = bio_mod($dg, $course, 'feedback', 'bio101_feedback_course', [
        'section' => 12, 'name' => 'End-of-course feedback',
        'intro' => 'Your anonymous feedback helps improve Biology 101.', 'introformat' => FORMAT_HTML,
    ]);
    $feedbackgen = $dg->get_plugin_generator('mod_feedback');
    $feedbackgen->create_item_multichoice($feedback, [
        'name' => 'Overall, how would you rate this course?', 'values' => "Excellent\nGood\nAverage\nPoor"]);
    $feedbackgen->create_item_multichoice($feedback, [
        'name' => 'Which topic did you find most valuable?', 'values' => "Cells\nGenetics\nEvolution\nEcology"]);
    $feedbackgen->create_item_textarea($feedback, ['name' => 'What would you change?']);
    bio_log('Section 12 built');

    rebuild_course_cache($course->id, true);
    cli_writeln('Activities phase complete.');
}

// ---------------------------------------------------------------------------
// PHASE: learners  (forum posts, choices, entries, submissions, attempts, grades)
// ---------------------------------------------------------------------------
if (in_array($PHASE, ['learners', 'all'])) {
    cli_heading('Phase: learners');
    require_once($CFG->dirroot . '/mod/assign/locallib.php');
    require_once($CFG->dirroot . '/mod/forum/lib.php');
    require_once($CFG->dirroot . '/mod/data/lib.php');
    require_once($CFG->dirroot . '/mod/data/locallib.php');

    // Roster (must match structure phase).
    $usernames = ['bio_ava', 'bio_liam', 'bio_sofia', 'bio_noah', 'bio_mia', 'bio_ethan', 'student_busy'];
    $stu = [];
    foreach ($usernames as $un) {
        if ($u = $DB->get_record('user', ['username' => $un])) {
            $stu[$un] = $u;
        }
    }
    $studs = array_values($stu);

    // Helper: resolve a bio101 module (cm + instance) by idnumber.
    $cmof = function (string $idnumber) use ($DB) {
        $cm = $DB->get_record('course_modules', ['course' => 15, 'idnumber' => $idnumber]);
        return $cm ?: null;
    };

    $forumgen = $dg->get_plugin_generator('mod_forum');
    $choicegen = $dg->get_plugin_generator('mod_choice');
    $datagen = $dg->get_plugin_generator('mod_data');
    $feedbackgen = $dg->get_plugin_generator('mod_feedback');
    $glossarygen = $dg->get_plugin_generator('mod_glossary');
    $workshopgen = $dg->get_plugin_generator('mod_workshop');
    $assigngen = $dg->get_plugin_generator('mod_assign');
    $quizgen = $dg->get_plugin_generator('mod_quiz');
    $qgen = $dg->get_plugin_generator('core_question');

    $try = function (string $label, callable $fn) {
        try {
            $fn();
            bio_log($label);
        } catch (\Throwable $e) {
            bio_log("WARN {$label}: " . $e->getMessage());
        }
        \core\session\manager::set_user(get_admin());
    };

    // ---- Forums: introductions (single), Q&A, photosynthesis, debate ----
    $try('forum: introductions', function () use ($DB, $forumgen, $cmof, $studs) {
        $cm = $cmof('bio101_forum_intro');
        $disc = $DB->get_record('forum_discussions', ['forum' => $cm->instance], '*', IGNORE_MULTIPLE);
        $intros = [
            'Hi all, I\'m taking Biology 101 because I want to study nursing. Excited for the genetics unit!',
            'Hello! Long-time nature photographer, first-time biology student. The ecology topic looks great.',
            'Hi everyone — hoping this course helps me with my agriculture diploma.',
            'Greetings! I loved biology in school and want to refresh the fundamentals.',
        ];
        foreach ($intros as $i => $msg) {
            $author = $studs[$i % count($studs)];
            $forumgen->create_post(['discussion' => $disc->id, 'userid' => $author->id,
                'parent' => $disc->firstpost, 'subject' => 'Re: Introduce yourself', 'message' => $msg]);
        }
    });

    $try('forum: Q&A', function () use ($forumgen, $cmof, $studs) {
        $cm = $cmof('bio101_forum_qa');
        $disc = $forumgen->create_discussion(['course' => 15, 'forum' => $cm->instance,
            'userid' => $studs[0]->id, 'name' => 'Is the mid-term cumulative?',
            'message' => 'Does the Quiz 1 cover only respiration, or earlier topics too?']);
        $forumgen->create_post(['discussion' => $disc->id, 'userid' => $studs[1]->id,
            'parent' => $disc->firstpost, 'subject' => 'Re: Is the mid-term cumulative?',
            'message' => 'I think Quiz 1 is just respiration — the syllabus book says quizzes are topic-based.']);
    });

    $try('forum: photosynthesis discussion', function () use ($forumgen, $cmof, $studs) {
        $cm = $cmof('bio101_forum_photo');
        $disc = $forumgen->create_discussion(['course' => 15, 'forum' => $cm->instance,
            'userid' => $studs[2]->id, 'name' => 'CAM plants are amazing',
            'message' => 'Cacti open their stomata at night to save water — CAM photosynthesis. Any other examples?']);
        $forumgen->create_post(['discussion' => $disc->id, 'userid' => $studs[3]->id,
            'parent' => $disc->firstpost, 'subject' => 'Re: CAM plants are amazing',
            'message' => 'Pineapples and many succulents use CAM too. C4 (like maize) is a different water-saving trick.']);
    });

    $try('forum: evolution debate', function () use ($forumgen, $cmof, $studs) {
        $cm = $cmof('bio101_forum_debate');
        $d1 = $forumgen->create_discussion(['course' => 15, 'forum' => $cm->instance,
            'userid' => $studs[0]->id, 'name' => 'For: the peppered moth is the clearest example',
            'message' => 'Industrial melanism is directly observed and reversible — hard to beat as evidence.']);
        $forumgen->create_post(['discussion' => $d1->id, 'userid' => $studs[4]->id,
            'parent' => $d1->firstpost, 'subject' => 'Re: For', 'message' => 'Agreed, and the data spans decades.']);
        $forumgen->create_discussion(['course' => 15, 'forum' => $cm->instance,
            'userid' => $studs[1]->id, 'name' => 'Against: antibiotic resistance is more compelling',
            'message' => 'Resistance evolving in real time under clear selection pressure is more convincing to me.']);
    });

    // ---- Choices ----
    $try('choice: topic poll responses', function () use ($DB, $choicegen, $cmof, $studs) {
        $cm = $cmof('bio101_choice_topic');
        $opts = array_values($DB->get_records_select('choice_options', 'choiceid = ? AND ' . $DB->sql_isnotempty('choice_options', 'text', false, false), [$cm->instance]));
        foreach ($studs as $i => $s) {
            $choicegen->create_response(['choiceid' => $cm->instance, 'responses' => $opts[$i % count($opts)]->id, 'userid' => $s->id]);
        }
    });
    $try('choice: field-trip vote', function () use ($DB, $choicegen, $cmof, $studs) {
        $cm = $cmof('bio101_choice_fieldtrip');
        $opts = array_values($DB->get_records_select('choice_options', 'choiceid = ? AND ' . $DB->sql_isnotempty('choice_options', 'text', false, false), [$cm->instance]));
        foreach ($studs as $i => $s) {
            $choicegen->create_response(['choiceid' => $cm->instance, 'responses' => $opts[($i + 1) % count($opts)]->id, 'userid' => $s->id]);
        }
    });

    // ---- Glossary: student-contributed terms ----
    $try('glossary: student entries', function () use ($DB, $glossarygen, $cmof, $studs) {
        $cm = $cmof('bio101_glossary_main');
        $glossary = $DB->get_record('glossary', ['id' => $cm->instance]);
        $entries = [
            ['Homeostasis', 'The maintenance of a stable internal environment despite external changes.'],
            ['Diffusion', 'Net movement of particles from high to low concentration.'],
            ['ATP', 'Adenosine triphosphate — the universal energy currency of the cell.'],
            ['Codon', 'A sequence of three mRNA bases coding for one amino acid.'],
        ];
        foreach ($entries as $i => [$concept, $definition]) {
            \core\session\manager::set_user($studs[$i % count($studs)]);
            $glossarygen->create_content($glossary, ['concept' => $concept, 'definition' => $definition,
                'userid' => $studs[$i % count($studs)]->id]);
        }
    });

    // ---- Databases: cell-cycle catalogue + species catalogue ----
    $try('database: cell-cycle entries', function () use ($DB, $datagen, $cmof, $studs) {
        $cm = $cmof('bio101_data_cellcycle');
        $data = $DB->get_record('data', ['id' => $cm->instance]);
        $f = [];
        foreach ($DB->get_records('data_fields', ['dataid' => $data->id]) as $fld) {
            $f[$fld->name] = $fld->id;
        }
        $rows = [
            ['G1 phase', 'Cell grows and carries out normal functions; organelles duplicate.', '2n, single copies'],
            ['S phase', 'DNA replication — each chromosome is copied.', '2n, DNA doubled'],
            ['Mitosis (M)', 'Nucleus divides; sister chromatids separate.', '2n in each daughter'],
            ['Cytokinesis', 'Cytoplasm divides, producing two daughter cells.', '2n, single copies'],
        ];
        foreach ($rows as $i => [$stage, $summary, $dna]) {
            $datagen->create_entry($data,
                [$f['Stage'] => $stage, $f['Summary'] => $summary, $f['DNA state'] => $dna],
                0, [], ['approved' => true], $studs[$i % count($studs)]->id);
        }
    });
    $try('database: species entries', function () use ($DB, $datagen, $cmof, $studs) {
        $cm = $cmof('bio101_data_species');
        $data = $DB->get_record('data', ['id' => $cm->instance]);
        $f = [];
        foreach ($DB->get_records('data_fields', ['dataid' => $data->id]) as $fld) {
            $f[$fld->name] = $fld->id;
        }
        $rows = [
            ['Common oak', 'Producer', 'Dominant canopy tree in the woodland behind campus.'],
            ['Grey squirrel', 'Primary consumer', 'Seen caching acorns in autumn.'],
            ['Red fox', 'Secondary consumer', 'Tracks found near the pond at dawn.'],
            ['Earthworm', 'Decomposer', 'Turns leaf litter into soil.'],
        ];
        foreach ($rows as $i => [$common, $trophic, $note]) {
            $datagen->create_entry($data,
                [$f['Common name'] => $common, $f['Trophic level'] => $trophic, $f['Field note'] => $note],
                0, [], ['approved' => true], $studs[$i % count($studs)]->id);
        }
    });

    // ---- Workshop submissions ----
    $try('workshop: essay submissions', function () use ($DB, $workshopgen, $cmof, $studs) {
        $cm = $cmof('bio101_workshop_evo');
        foreach (array_slice($studs, 0, 4) as $i => $s) {
            $workshopgen->create_submission($cm->instance, $s->id, [
                'title' => 'Evidence for evolution — essay ' . ($i + 1),
                'content' => 'Two lines of evidence: (1) the fossil record shows transitional forms such as '
                    . 'Archaeopteryx; (2) comparative anatomy reveals homologous structures like the pentadactyl limb.',
                'contentformat' => FORMAT_HTML,
            ]);
        }
    });

    // ---- Feedback responses ----
    $try('feedback: responses', function () use ($feedbackgen, $cmof, $studs) {
        $cm = $cmof('bio101_feedback_course');
        $ratings = ['Excellent', 'Good', 'Excellent', 'Average', 'Good'];
        $topics = ['Genetics', 'Evolution', 'Cells', 'Ecology', 'Genetics'];
        foreach (array_slice($studs, 0, 5) as $i => $s) {
            $feedbackgen->create_response([
                'cmid' => $cm->id, 'userid' => $s->id, 'anonymous' => false,
                'Overall, how would you rate this course?' => $ratings[$i],
                'Which topic did you find most valuable?' => $topics[$i],
                'What would you change?' => 'More lab time and worked examples for Punnett squares.',
            ]);
        }
    });

    // ---- Assignment submissions + grades ----
    $gradeassign = function (string $idnumber, array $grades) use ($DB, $assigngen, $cmof, $studs) {
        $cm = $cmof($idnumber);
        [$course, $cminfo] = get_course_and_cm_from_cmid($cm->id, 'assign');
        $context = context_module::instance($cm->id);
        $assign = new assign($context, $cminfo, $course);
        foreach ($studs as $i => $s) {
            if (!array_key_exists($i, $grades)) {
                continue; // Leave some students unsubmitted for realism.
            }
            $assigngen->create_submission([
                'cmid' => $cm->id, 'userid' => $s->id, 'onlinetext' => 'Please find my submission attached and inline. '
                    . 'I followed the method described in the practical and discussed my results.',
                'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
            ]);
            \core\session\manager::set_user(get_admin());
            if ($grades[$i] !== null) {
                $gd = (object)['grade' => (float)$grades[$i], 'attemptnumber' => -1, 'addattempt' => 0,
                    'workflowstate' => '', 'sendstudentnotifications' => 0, 'applytoall' => 0];
                $assign->save_grade($s->id, $gd);
            }
        }
    };
    $try('assignment 1 (enzyme): submissions + grades', function () use ($gradeassign) {
        // index => grade (null = submitted but ungraded).
        $gradeassign('bio101_assign_enzyme', [0 => 88, 1 => 74, 2 => 95, 3 => 66, 4 => null, 5 => 81]);
    });
    $try('assignment 2 (mitosis): submissions + grades', function () use ($gradeassign) {
        $gradeassign('bio101_assign_mitosis', [0 => 79, 1 => 91, 2 => null, 4 => 70]);
    });
    $try('capstone: submissions + grades', function () use ($gradeassign) {
        $gradeassign('bio101_assign_capstone', [0 => 84, 3 => 77]);
    });

    // ---- Quiz attempts (real, auto-graded) ----
    $doquiz = function (string $idnumber, array $perstudentanswers) use ($DB, $quizgen, $qgen, $cmof, $studs) {
        $cm = $cmof($idnumber);
        $numslots = $DB->count_records('quiz_slots', ['quizid' => $cm->instance]);
        if (!$numslots) {
            return;
        }
        foreach ($perstudentanswers as $i => $answers) {
            if (!isset($studs[$i])) {
                continue;
            }
            // Start a real attempt for the student.
            \core\session\manager::set_user($studs[$i]);
            $attempt = $quizgen->create_attempt($cm->instance, $studs[$i]->id);
            // Drive the attempt via the question engine directly (avoids the
            // unit-test-only quiz_attempt::get_question_usage() guard).
            $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
            $quba = \question_engine::load_questions_usage_by_activity($attemptobj->get_uniqueid());
            // $answers is slot => response summary string (e.g. 'True', 'One').
            $postdata = $qgen->get_simulated_post_data_for_questions_in_usage($quba, $answers, false);
            $attemptobj->process_submitted_actions(time(), false, $postdata);
            $attemptobj->process_submit(time(), false);
            $attemptobj->process_grade_submission(time());
            \core\session\manager::set_user(get_admin());
        }
    };
    $try('quiz 1 (respiration): attempts', function () use ($doquiz) {
        // Slots: 1=TF (correct True), 2=TF (correct False), 3=MC (correct 'One').
        $doquiz('bio101_quiz_resp', [
            0 => [1 => 'True',  2 => 'True',  3 => 'One'],   // 2/3
            1 => [1 => 'True',  2 => 'False', 3 => 'One'],   // 3/3
            2 => [1 => 'False', 2 => 'False', 3 => 'Two'],   // 1/3
            3 => [1 => 'True',  2 => 'True',  3 => 'Two'],   // 1/3
        ]);
    });
    $try('quiz 2 (genetics): attempts', function () use ($doquiz) {
        // Slots: 1=TF (correct True), 2=MC (correct 'One').
        $doquiz('bio101_quiz_genetics', [
            0 => [1 => 'True',  2 => 'One'],   // 2/2
            1 => [1 => 'False', 2 => 'Two'],   // 0/2
            2 => [1 => 'True',  2 => 'Four'],  // 1/2
        ]);
    });

    // ---- Completion: give students partial, varied progress ----
    $try('completion: mark progress', function () use ($DB, $course, $studs) {
        $completion = new completion_info($course);
        if (!$completion->is_enabled()) {
            return;
        }
        $trackable = ['bio101_page_start', 'bio101_page_whatislife', 'bio101_book_chem',
            'bio101_lesson_cell', 'bio101_page_membrane'];
        foreach ($studs as $i => $s) {
            // Each student completes a decreasing number of the trackable activities.
            $howmany = max(1, count($trackable) - $i);
            foreach (array_slice($trackable, 0, $howmany) as $idnum) {
                $cm = $DB->get_record('course_modules', ['course' => 15, 'idnumber' => $idnum]);
                if ($cm) {
                    $modcm = get_fast_modinfo($course)->get_cm($cm->id);
                    if ($completion->is_enabled($modcm) != COMPLETION_TRACKING_NONE) {
                        // Override so the forced state sticks for automatic (view-based) activities.
                        $completion->update_state($modcm, COMPLETION_COMPLETE, $s->id, true);
                    }
                }
            }
        }
    });

    // ---- Recent access so the course looks used ----
    $try('lastaccess timestamps', function () use ($DB, $studs) {
        foreach ($studs as $i => $s) {
            $la = ['userid' => $s->id, 'courseid' => 15];
            $rec = $DB->get_record('user_lastaccess', $la);
            $time = time() - ($i * DAYSECS);
            if ($rec) {
                $DB->set_field('user_lastaccess', 'timeaccess', $time, $la);
            } else {
                $DB->insert_record('user_lastaccess', (object)($la + ['timeaccess' => $time]));
            }
        }
    });

    cli_writeln('Learners phase complete.');
}

cli_writeln('DONE (phase=' . $PHASE . ')');
