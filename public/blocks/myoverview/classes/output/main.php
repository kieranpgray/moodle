<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Class containing data for my overview block.
 *
 * @package    block_myoverview
 * @copyright  2017 Ryan Wyllie <ryan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_myoverview\output;
defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;
use stdClass;

require_once($CFG->dirroot . '/blocks/myoverview/lib.php');

/**
 * Class containing data for my overview block.
 *
 * @copyright  2018 Bas Brands <bas@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class main implements renderable, templatable {

    /**
     * Store the grouping preference.
     *
     * @var string String matching the grouping constants defined in myoverview/lib.php
     */
    private $grouping;

    /**
     * Store the sort preference.
     *
     * @var string String matching the sort constants defined in myoverview/lib.php
     */
    private $sort;

    /**
     * Store the view preference.
     *
     * @var string String matching the view/display constants defined in myoverview/lib.php
     */
    private $view;

    /**
     * Store the display categories config setting.
     *
     * @var boolean
     */
    private $displaycategories;

    /**
     * Store the configuration values for the myoverview block.
     *
     * @var array Array of available layouts matching view/display constants defined in myoverview/lib.php
     */
    private $layouts;

    /**
     * Store a course grouping option setting
     *
     * @var boolean
     */
    private $displaygroupingallincludinghidden;

    /**
     * Store a course grouping option setting.
     *
     * @var boolean
     */
    private $displaygroupingall;

    /**
     * Store a course grouping option setting.
     *
     * @var boolean
     */
    private $displaygroupinginprogress;

    /**
     * Store a course grouping option setting.
     *
     * @var boolean
     */
    private $displaygroupingfuture;

    /**
     * Store a course grouping option setting.
     *
     * @var boolean
     */
    private $displaygroupingpast;

    /**
     * Store a course grouping option setting.
     *
     * @var boolean
     */
    private $displaygroupingfavourites;

    /**
     * Store a course grouping option setting.
     *
     * @var boolean
     */
    private $displaygroupinghidden;

    /**
     * Store a course grouping option setting.
     *
     * @var bool
     */
    private $displaygroupingcustomfield;

    /**
     * Store the custom field used by customfield grouping.
     *
     * @var string
     */
    private $customfiltergrouping;

    /**
     * Store the selected custom field value to group by.
     *
     * @var string
     */
    private $customfieldvalue;

    /** @var bool true if grouping selector should be shown, otherwise false. */
    protected $displaygroupingselector;

    /**
     * main constructor.
     * Initialize the user preferences
     *
     * @param string $grouping Grouping user preference
     * @param string $sort Sort user preference
     * @param string $view Display user preference
     * @param string $customfieldvalue
     *
     * @throws \dml_exception
     */
    public function __construct($grouping, $sort, $view, $customfieldvalue = null) {
        global $CFG;
        // Get plugin config.
        $config = get_config('block_myoverview');

        // Build the course grouping option name to check if the given grouping is enabled afterwards.
        $groupingconfigname = 'displaygrouping'.$grouping;
        // Check the given grouping and remember it if it is enabled.
        if ($grouping && $config->$groupingconfigname == true) {
            $this->grouping = $grouping;

            // Otherwise fall back to another grouping in a reasonable order.
            // This is done to prevent one-time UI glitches in the case when a user has chosen a grouping option previously which
            // was then disabled by the admin in the meantime.
        } else {
            $this->grouping = $this->get_fallback_grouping($config);
        }
        unset ($groupingconfigname);

        // Remember which custom field value we were using, if grouping by custom field.
        $this->customfieldvalue = $customfieldvalue;

        // Check and remember the given sorting.
        if ($sort) {
            $this->sort = $sort;
        } else if ($CFG->courselistshortnames) {
            $this->sort = BLOCK_MYOVERVIEW_SORTING_SHORTNAME;
        } else {
            $this->sort = BLOCK_MYOVERVIEW_SORTING_TITLE;
        }
        // In case sorting remembered is shortname and display extended course names not checked,
        // we should revert sorting to title.
        if (!$CFG->courselistshortnames && $sort == BLOCK_MYOVERVIEW_SORTING_SHORTNAME) {
            $this->sort = BLOCK_MYOVERVIEW_SORTING_TITLE;
        }

        // Check and remember the given view.
        $this->view = $view ? $view : BLOCK_MYOVERVIEW_VIEW_CARD;

        // Check and remember if the course categories should be shown or not.
        if (!$config->displaycategories) {
            $this->displaycategories = BLOCK_MYOVERVIEW_DISPLAY_CATEGORIES_OFF;
        } else {
            $this->displaycategories = BLOCK_MYOVERVIEW_DISPLAY_CATEGORIES_ON;
        }

        // Get and remember the available layouts.
        $this->set_available_layouts();
        $this->view = $view ? $view : reset($this->layouts);

        // Check and remember if the particular grouping options should be shown or not.
        $this->displaygroupingallincludinghidden = $config->displaygroupingallincludinghidden;
        $this->displaygroupingall = $config->displaygroupingall;
        $this->displaygroupinginprogress = $config->displaygroupinginprogress;
        $this->displaygroupingfuture = $config->displaygroupingfuture;
        $this->displaygroupingpast = $config->displaygroupingpast;
        $this->displaygroupingfavourites = $config->displaygroupingfavourites;
        $this->displaygroupinghidden = $config->displaygroupinghidden;
        $this->displaygroupingcustomfield = ($config->displaygroupingcustomfield && $config->customfiltergrouping);
        $this->customfiltergrouping = $config->customfiltergrouping;

        // Check and remember if the grouping selector should be shown at all or not.
        // It will be shown if more than 1 grouping option is enabled.
        $displaygroupingselectors = array($this->displaygroupingallincludinghidden,
                $this->displaygroupingall,
                $this->displaygroupinginprogress,
                $this->displaygroupingfuture,
                $this->displaygroupingpast,
                $this->displaygroupingfavourites,
                $this->displaygroupinghidden);
        $displaygroupingselectorscount = count(array_filter($displaygroupingselectors));
        if ($displaygroupingselectorscount > 1 || $this->displaygroupingcustomfield) {
            $this->displaygroupingselector = true;
        } else {
            $this->displaygroupingselector = false;
        }
        unset ($displaygroupingselectors, $displaygroupingselectorscount);
    }
    /**
     * Determine the most sensible fallback grouping to use (in cases where the stored selection
     * is no longer available).
     * @param object $config
     * @return string
     */
    private function get_fallback_grouping($config) {
        if ($config->displaygroupingall == true) {
            return BLOCK_MYOVERVIEW_GROUPING_ALL;
        }
        if ($config->displaygroupingallincludinghidden == true) {
            return BLOCK_MYOVERVIEW_GROUPING_ALLINCLUDINGHIDDEN;
        }
        if ($config->displaygroupinginprogress == true) {
            return BLOCK_MYOVERVIEW_GROUPING_INPROGRESS;
        }
        if ($config->displaygroupingfuture == true) {
            return BLOCK_MYOVERVIEW_GROUPING_FUTURE;
        }
        if ($config->displaygroupingpast == true) {
            return BLOCK_MYOVERVIEW_GROUPING_PAST;
        }
        if ($config->displaygroupingfavourites == true) {
            return BLOCK_MYOVERVIEW_GROUPING_FAVOURITES;
        }
        if ($config->displaygroupinghidden == true) {
            return BLOCK_MYOVERVIEW_GROUPING_HIDDEN;
        }
        if ($config->displaygroupingcustomfield == true) {
            return BLOCK_MYOVERVIEW_GROUPING_CUSTOMFIELD;
        }
        // In this case, no grouping option is enabled and the grouping is not needed at all.
        // But it's better not to leave $this->grouping unset for any unexpected case.
        return BLOCK_MYOVERVIEW_GROUPING_ALLINCLUDINGHIDDEN;
    }

    /**
     * Set the available layouts based on the config table settings,
     * if none are available, defaults to the cards view.
     *
     * @throws \dml_exception
     *
     */
    public function set_available_layouts() {

        if ($config = get_config('block_myoverview', 'layouts')) {
            $this->layouts = explode(',', $config);
        } else {
            $this->layouts = array(BLOCK_MYOVERVIEW_VIEW_CARD);
        }
    }

    /**
     * Format a layout into an object for export as a Context variable to template.
     *
     * @param string $layoutname
     *
     * @return \stdClass $layout an object representation of a layout
     * @throws \coding_exception
     */
    public function format_layout_for_export($layoutname) {
        $layout = new stdClass();

        $layout->id = $layoutname;
        $layout->name = get_string($layoutname, 'block_myoverview');
        $layout->active = $this->view == $layoutname ? true : false;
        $layout->arialabel = get_string('aria:' . $layoutname, 'block_myoverview');

        return $layout;
    }

    /**
     * Get the available layouts formatted for export.
     *
     * @return array an array of objects representing available layouts
     */
    public function get_formatted_available_layouts_for_export() {

        return array_map(array($this, 'format_layout_for_export'), $this->layouts);

    }

    /**
     * Get the list of values to add to the grouping dropdown
     * @return object[] containing name, value and active fields
     */
    public function get_customfield_values_for_export() {
        global $DB, $USER;
        if (!$this->displaygroupingcustomfield) {
            return [];
        }

        // Get the relevant customfield ID within the core_course/course component/area.
        $fieldid = $DB->get_field_sql("
            SELECT f.id
              FROM {customfield_field} f
              JOIN {customfield_category} c ON c.id = f.categoryid
             WHERE f.shortname = :shortname AND c.component = 'core_course' AND c.area = 'course'
        ", ['shortname' => $this->customfiltergrouping]);
        if (!$fieldid) {
            return [];
        }
        $courses = enrol_get_all_users_courses($USER->id, true);
        if (!$courses) {
            return [];
        }
        list($csql, $params) = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED);
        $select = "instanceid $csql AND fieldid = :fieldid";
        $params['fieldid'] = $fieldid;
        $distinctablevalue = $DB->sql_compare_text('value');
        $values = $DB->get_records_select_menu('customfield_data', $select, $params, '',
            "DISTINCT $distinctablevalue, $distinctablevalue AS value2");
        \core_collator::asort($values, \core_collator::SORT_NATURAL);
        $values = array_filter($values);
        if (!$values) {
            return [];
        }
        $field = \core_customfield\field_controller::create($fieldid);
        $isvisible = $field->get_configdata_property('visibility') == \core_course\customfield\course_handler::VISIBLETOALL;
        // Only visible fields to everybody supporting course grouping will be displayed.
        if (!$field->supports_course_grouping() || !$isvisible) {
            return []; // The field shouldn't have been selectable in the global settings, but just skip it now.
        }
        $values = $field->course_grouping_format_values($values);
        $customfieldactive = ($this->grouping === BLOCK_MYOVERVIEW_GROUPING_CUSTOMFIELD);
        $ret = [];
        foreach ($values as $value => $name) {
            $ret[] = (object)[
                'name' => $name,
                'value' => $value,
                'active' => ($customfieldactive && ($this->customfieldvalue == $value)),
            ];
        }
        return $ret;
    }

    /**
     * Export the block data as JSON props for the React component.
     *
     * @param \renderer_base $output
     * @return array Context variables for the template (propsjson + uniqid)
     * @throws \coding_exception
     */
    public function export_for_template(renderer_base $output) {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $coursecat = \core_course_category::user_top();

        // Persistent toolbar action URLs, gated server-side by capability.
        $createcourseurl = null;
        if ($coursecat && ($category = \core_course_category::get_nearest_editable_subcategory($coursecat, ['create']))) {
            $createcourseurl = (new \moodle_url('/course/edit.php', ['category' => $category->id]))->out(false);
        }
        $managecourseurl = null;
        if ($coursecat && ($category = \core_course_category::get_nearest_editable_subcategory($coursecat, ['manage']))) {
            // Note: course/management.php reads the 'categoryid' parameter.
            $managecourseurl = (new \moodle_url('/course/management.php', ['categoryid' => $category->id]))->out(false);
        }
        $requestcourseurl = $this->get_request_course_url();

        // Resolve the custom field values once, falling back if the stored value is stale.
        $customfieldvalues = $this->get_customfield_values_for_export();
        if ($this->grouping == BLOCK_MYOVERVIEW_GROUPING_CUSTOMFIELD) {
            $found = false;
            foreach ($customfieldvalues as $field) {
                if ($field->value == $this->customfieldvalue) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $this->grouping = $this->get_fallback_grouping(get_config('block_myoverview'));
                if ($this->grouping == BLOCK_MYOVERVIEW_GROUPING_CUSTOMFIELD && ($firstfield = reset($customfieldvalues))) {
                    $this->customfieldvalue = $firstfield->value;
                }
            }
        }

        $caneditcourses = !empty($createcourseurl) || !empty($managecourseurl);

        $props = [
            'strings' => $this->get_strings(),
            'preferences' => [
                'view' => $this->view,
                'filter' => $this->grouping,
                'sort' => $this->sort,
                'customfieldvalue' => $this->customfieldvalue,
            ],
            'config' => [
                'enabledviews' => array_values($this->layouts),
                'enabledfilters' => $this->get_enabled_groupings(),
                'displaycategories' => ($this->displaycategories === BLOCK_MYOVERVIEW_DISPLAY_CATEGORIES_ON),
                'showshortname' => (bool) $CFG->courselistshortnames,
                'customfieldname' => $this->customfiltergrouping ?: null,
                'customfieldvalues' => $this->get_customfield_values_react($customfieldvalues),
            ],
            'permissions' => [
                'cancreate' => !empty($createcourseurl),
                'canmanage' => !empty($managecourseurl),
            ],
            'role' => $this->resolve_role($caneditcourses),
            'createcourseurl' => $createcourseurl,
            'managecourseurl' => $managecourseurl,
            'requestcourseurl' => $requestcourseurl,
            'hiddencourseids' => array_map('intval', get_hidden_courses_on_timeline()),
            'zerostate' => $this->get_zero_state_data(),
        ];

        return [
            'propsjson' => json_encode($props, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG),
            'uniqid' => uniqid(),
        ];
    }

    /**
     * Build the map of UI strings passed to the React component.
     *
     * The keys match the Strings TypeScript type; values reuse existing
     * block_myoverview and core language strings so translations are shared.
     *
     * @return array
     * @throws \coding_exception
     */
    private function get_strings(): array {
        return [
            'actionsfor' => get_string('aria:courseactions', 'block_myoverview'),
            'changelayout' => get_string('aria:displaydropdown', 'block_myoverview'),
            'clearsearch' => get_string('clear'),
            'courseactions' => get_string('aria:courseactions', 'block_myoverview'),
            'courseoverview' => get_string('pluginname', 'block_myoverview'),
            'courseprogress' => get_string('courseprogress', 'block_myoverview'),
            'createcourse' => get_string('createcourse', 'block_myoverview'),
            'emptyeducator' => get_string('zero_default_intro', 'block_myoverview'),
            'emptynoresults' => get_string('zero_default_intro', 'block_myoverview'),
            'emptystudent' => get_string('zero_default_intro', 'block_myoverview'),
            'errorloadingcourses' => get_string('errorloadingcourses', 'block_myoverview'),
            'filterall' => get_string('allcourses', 'block_myoverview'),
            'filterallincludinghidden' => get_string('allincludinghidden', 'block_myoverview'),
            'filtercustomfield' => get_string('customfield', 'block_myoverview'),
            'filterfavourites' => get_string('favourites', 'block_myoverview'),
            'filterfuture' => get_string('future', 'block_myoverview'),
            'filterhidden' => get_string('hiddencourses', 'block_myoverview'),
            'filterinprogress' => get_string('inprogress', 'block_myoverview'),
            'filterpast' => get_string('past', 'block_myoverview'),
            'filterresults' => get_string('aria:groupingdropdown', 'block_myoverview'),
            'filters' => get_string('filters'),
            'hidecourse' => get_string('hidecourse', 'block_myoverview'),
            'managecourses' => get_string('managecourses'),
            'percentcomplete' => get_string('completepercent', 'block_myoverview'),
            'removefromstarred' => get_string('aria:removefromfavourites', 'block_myoverview'),
            'requestcoursebutton' => get_string('requestcoursebutton', 'block_myoverview'),
            'search' => get_string('search'),
            'searchcourses' => get_string('searchcourses', 'block_myoverview'),
            'showcourse' => get_string('show', 'block_myoverview'),
            'sortby' => get_string('sortby'),
            'sortcoursename' => get_string('title', 'block_myoverview'),
            'sortcourses' => get_string('aria:sortingdropdown', 'block_myoverview'),
            'sortlastaccessed' => get_string('lastaccessed', 'block_myoverview'),
            'sortshortname' => get_string('shortname', 'block_myoverview'),
            'sortstartdate' => get_string('startdate'),
            'starcourse' => get_string('aria:addtofavourites', 'block_myoverview'),
            'tooltipfilter' => get_string('filter'),
            'tooltipsort' => get_string('sort'),
            'tooltipview' => get_string('view'),
            'viewcard' => get_string('card', 'block_myoverview'),
            'viewlabel' => get_string('view'),
            'viewlist' => get_string('list', 'block_myoverview'),
            'viewsummary' => get_string('summary', 'block_myoverview'),
        ];
    }

    /**
     * Get the list of enabled grouping constants, in display order.
     *
     * @return string[]
     */
    private function get_enabled_groupings(): array {
        $map = [
            BLOCK_MYOVERVIEW_GROUPING_ALLINCLUDINGHIDDEN => $this->displaygroupingallincludinghidden,
            BLOCK_MYOVERVIEW_GROUPING_ALL => $this->displaygroupingall,
            BLOCK_MYOVERVIEW_GROUPING_INPROGRESS => $this->displaygroupinginprogress,
            BLOCK_MYOVERVIEW_GROUPING_FUTURE => $this->displaygroupingfuture,
            BLOCK_MYOVERVIEW_GROUPING_PAST => $this->displaygroupingpast,
            BLOCK_MYOVERVIEW_GROUPING_FAVOURITES => $this->displaygroupingfavourites,
            BLOCK_MYOVERVIEW_GROUPING_HIDDEN => $this->displaygroupinghidden,
            BLOCK_MYOVERVIEW_GROUPING_CUSTOMFIELD => $this->displaygroupingcustomfield,
        ];
        $enabled = [];
        foreach ($map as $key => $on) {
            if ($on) {
                $enabled[] = $key;
            }
        }
        return $enabled;
    }

    /**
     * Adapt the custom field values for the React component (value + name only).
     *
     * @param array $values Values from {@see get_customfield_values_for_export()}
     * @return array
     */
    private function get_customfield_values_react(array $values): array {
        return array_values(array_map(fn($v) => ['value' => $v->value, 'name' => $v->name], $values));
    }

    /**
     * Resolve the viewer role for the React component.
     *
     * @param bool $caneditcourses Whether the user can create or manage courses.
     * @return string 'educator' or 'student'
     */
    private function resolve_role(bool $caneditcourses): string {
        return $caneditcourses ? 'educator' : 'student';
    }

    /**
     * Compute the "request a course" URL, or null when the user cannot request one.
     *
     * @return string|null
     */
    private function get_request_course_url(): ?string {
        $coursecat = \core_course_category::user_top();
        if (!$coursecat) {
            return null;
        }
        $category = \core_course_category::get_nearest_editable_subcategory($coursecat, ['moodle/course:request']);
        if (!$category || !$category->can_request_course()) {
            return null;
        }
        // Note: course/request.php reads the 'category' parameter (not 'categoryid').
        return (new \moodle_url('/course/request.php', ['category' => $category->id]))->out(false);
    }

    /**
     * Build the pre-computed zero-state data for an empty course list.
     *
     * @return array { title, intro, buttons[] }
     * @throws \coding_exception
     */
    private function get_zero_state_data(): array {
        global $CFG, $DB;

        $coursecat = \core_course_category::user_top();
        if ($coursecat) {
            // Priority 1: the user can request a course. The request button lives in the
            // persistent toolbar (requestcourseurl), so no button is emitted here.
            $category = \core_course_category::get_nearest_editable_subcategory($coursecat, ['moodle/course:request']);
            if ($category && $category->can_request_course()) {
                return [
                    'title' => get_string('zero_request_title', 'block_myoverview'),
                    'intro' => get_string('zero_request_intro_short', 'block_myoverview'),
                    'buttons' => [],
                ];
            }

            $totalcourses = $DB->count_records_select('course', 'category > 0');

            // Priority 2: the user can create a course (with an optional manage button).
            if ($category = \core_course_category::get_nearest_editable_subcategory($coursecat, ['create'])) {
                $buttons = [];
                if ($categorytomanage = \core_course_category::get_nearest_editable_subcategory($coursecat, ['manage'])) {
                    $manageurl = new \moodle_url('/course/management.php', ['categoryid' => $categorytomanage->id]);
                    $buttons[] = [
                        'label' => $totalcourses ? get_string('managecourses') : get_string('managecategories'),
                        'url' => $manageurl->out(false),
                        'primary' => false,
                    ];
                }
                $buttons[] = [
                    'label' => get_string('createcourse', 'block_myoverview'),
                    'url' => (new \moodle_url('/course/edit.php', ['category' => $category->id]))->out(false),
                    'primary' => true,
                ];
                $titlekey = $totalcourses ? 'zero_default_title' : 'zero_nocourses_title';
                $introkey = $totalcourses ? 'zero_default_intro' :
                    ($CFG->coursecreationguide ? 'zero_request_intro' : 'zero_nocourses_intro');
                return [
                    'title' => get_string($titlekey, 'block_myoverview'),
                    'intro' => get_string($introkey, 'block_myoverview', $this->get_zero_state_doc_params()),
                    'buttons' => $buttons,
                ];
            }
        }

        return [
            'title' => get_string('zero_default_title', 'block_myoverview'),
            'intro' => get_string('zero_default_intro', 'block_myoverview'),
            'buttons' => [],
        ];
    }

    /**
     * Build the documentation link parameters used by the zero-state intro strings.
     *
     * @return array
     * @throws \coding_exception
     */
    private function get_zero_state_doc_params(): array {
        global $CFG;
        $dochref = new \moodle_url($CFG->docroot, ['lang' => current_language()]);
        $docparams = [
            'dochref' => $dochref->out(),
            'doctitle' => get_string('documentation'),
            'doctarget' => $CFG->doctonewwindow ? '_blank' : '_self',
        ];
        if ($CFG->coursecreationguide) {
            $quickstart = new \moodle_url($CFG->coursecreationguide, ['lang' => current_language()]);
            $docparams += [
                'quickhref' => $quickstart->out(),
                'quicktitle' => get_string('viewquickstart', 'block_myoverview'),
                'quicktarget' => '_blank',
            ];
        }
        return $docparams;
    }
}
