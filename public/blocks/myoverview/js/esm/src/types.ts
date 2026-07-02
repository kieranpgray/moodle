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
 * Shared types for the course overview React component.
 *
 * The Course shape mirrors the fields returned by the web service
 * core_course_get_enrolled_courses_by_timeline_classification, so the live data
 * layer (api.ts) maps onto it directly.
 *
 * @module     block_myoverview/types
 */

/** A single enrolled course, shaped like the web-service course summary export. */
export type Course = {
    id: number;
    fullname: string;
    fullnamedisplay: string;
    shortname: string;
    viewurl: string;
    /** Overview image URL, or null when no image is set (fallback rendered). */
    courseimage: string | null;
    /** Optional summary description, shown in summary view. */
    summary: string;
    coursecategory: string;
    showshortname: boolean;
    showcoursecategory: boolean;
    /** Whether the course is visible to students (false renders a hidden badge). */
    visible: boolean;
    isfavourite: boolean;
    /** Completion progress 0-100, or null when not tracked. */
    progress: number | null;
    hasprogress: boolean;
    /** Unix timestamps used by future/past/sort logic. */
    startdate: number;
    enddate: number;
    timeaccess: number;
};

/** The three layout modes (MDL-88966). */
export type View = "card" | "list" | "summary";

/**
 * Course groupings / filters (MDL-88972). 'all' excludes hidden courses;
 * 'allincludinghidden' is the "all (including removed from view)" grouping.
 */
export type Filter =
    "allincludinghidden" | "all" | "inprogress" | "future" | "past"
    | "favourites" | "hidden" | "customfield";

/** Sort orders. Default is "title" (A-Z) per MDL-88972. */
export type Sort = "title" | "shortname" | "lastaccessed" | "startdate";

/** Viewer role. */
export type Role = "student" | "educator";

/** Educator capabilities controlling toolbar buttons (MDL-88976). */
export type Permissions = {
    cancreate: boolean;
    canmanage: boolean;
};

/** All UI strings passed from PHP via get_string(). */
export type Strings = {
    actionsfor: string;
    changelayout: string;
    clearsearch: string;
    courseactions: string;
    courseoverview: string;
    courseprogress: string;
    createcourse: string;
    emptyeducator: string;
    emptynoresults: string;
    emptystudent: string;
    errorloadingcourses: string;
    filterall: string;
    filterallincludinghidden: string;
    filtercustomfield: string;
    filterfavourites: string;
    filterfuture: string;
    filterhidden: string;
    filterinprogress: string;
    filterpast: string;
    filterresults: string;
    filters: string;
    hidecourse: string;
    managecourses: string;
    percentcomplete: string;
    removefromstarred: string;
    requestcoursebutton: string;
    search: string;
    searchcourses: string;
    showcourse: string;
    sortby: string;
    sortcoursename: string;
    sortcourses: string;
    sortlastaccessed: string;
    sortshortname: string;
    sortstartdate: string;
    starcourse: string;
    tooltipfilter: string;
    tooltipsort: string;
    tooltipview: string;
    viewcard: string;
    viewlabel: string;
    viewlist: string;
    viewsummary: string;
};

/** A single button in a rich zero-state (server-computed label + URL). */
export type ZeroStateButton = {
    label: string;
    url: string;
    primary: boolean;
};

/** Rich zero-state data pre-computed in PHP for an empty course list. */
export type ZeroStateData = {
    title: string;
    intro: string;
    buttons: ZeroStateButton[];
};

/** Block configuration derived from admin settings and site config. */
export type Config = {
    enabledviews: View[];
    enabledfilters: Filter[];
    displaycategories: boolean;
    /** $CFG->courselistshortnames — gates the "Short name" sort option. */
    showshortname: boolean;
    customfieldname?: string;
    customfieldvalues?: Array<{value: string; name: string}>;
};

/** Server-provided preferences seeding the initial reducer state. */
export type ServerPreferences = {
    view: View;
    filter: Filter;
    sort: Sort;
    customfieldvalue?: string;
};

/**
 * Props passed from the block's React mount point (templates/main.mustache).
 *
 * The site root URL and session key are intentionally absent: api.ts reads them
 * from @moodle/lms/core/config, the same as core/ajax and core/fetch do internally.
 */
export type LiveAppProps = {
    strings: Strings;
    preferences: ServerPreferences;
    config: Config;
    permissions: Permissions;
    role: Role;
    /**
     * Pre-computed server URLs for persistent toolbar actions (always available
     * regardless of course count, matching the current AMD toolbar behaviour).
     */
    createcourseurl?: string | null;
    managecourseurl?: string | null;
    requestcourseurl?: string | null;
    /** Ids of courses the user has removed from view, to seed the hidden state. */
    hiddencourseids?: number[];
    /**
     * Pre-computed zero-state data for when the course list is empty. The
     * request-course button lives in the persistent toolbar (requestcourseurl),
     * not in zerostate.buttons.
     */
    zerostate?: ZeroStateData;
};

/** Props passed from the Mustache mount point. */
export type AppProps = LiveAppProps;

/** Number of courses per page — 9 for the 3x3 grid (MDL-88977). */
export const PAGE_SIZE = 9;

/** Defaults (MDL-88972): filter = All, sort = A-Z, view = card. */
export const DEFAULT_VIEW: View = "card";
export const DEFAULT_FILTER: Filter = "all";
export const DEFAULT_SORT: Sort = "title";
