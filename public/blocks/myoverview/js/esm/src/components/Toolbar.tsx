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
 * Course overview toolbar (MDL-88972, MDL-88976).
 *
 * Left: server-computed action links (Manage / Create / Request course), shown
 * whenever the matching URL is supplied by PHP (capability-gated server-side).
 * Right: search, a labelled filter dropdown, and icon-only sort and layout
 * dropdowns. When the custom-field grouping is active a value selector appears.
 * Filter/sort/layout show an active state when their value is non-default;
 * defaults are filter = All, sort = A-Z, view = card. The available filter and
 * view options are limited to those enabled in the block's admin settings.
 *
 * @module     block_myoverview/components/Toolbar
 */

import {
    Config, DEFAULT_FILTER, DEFAULT_SORT, DEFAULT_VIEW, Filter, Permissions, Role, Sort, View,
} from "../types";
import {useStrings} from "../state";
import Dropdown from "./Dropdown";
import SearchInput from "./SearchInput";


type ToolbarProps = {
    role: Role;
    permissions: Permissions;
    view: View;
    filter: Filter;
    sort: Sort;
    search: string;
    config: Config;
    createcourseurl?: string | null;
    managecourseurl?: string | null;
    requestcourseurl?: string | null;
    customfieldvalue: string | null;
    onView: (v: View) => void;
    onFilter: (f: Filter) => void;
    onSort: (s: Sort) => void;
    onSearch: (s: string) => void;
    onCustomFieldValue: (v: string) => void;
};


const VIEW_ICON: Record<View, string> = {
    card: "table-cells-large",
    list: "list",
    summary: "bars",
};

/**
 * Render the toolbar.
 *
 * @param props Toolbar state and handlers.
 * @returns The toolbar element.
 */
export default function Toolbar(props: ToolbarProps) {
    const {
        view, filter, sort, search, config,
        createcourseurl, managecourseurl, requestcourseurl, customfieldvalue,
        onView, onFilter, onSort, onSearch, onCustomFieldValue,
    } = props;
    const strings = useStrings();
    const currentViewIcon = VIEW_ICON[view] ?? "table-cells-large";

    // Filter label lookup. The customfield grouping is labelled with the field's
    // configured name when available, falling back to the generic string.
    const filterLabel = (f: Filter): string => {
        switch (f) {
            case "allincludinghidden": return strings.filterallincludinghidden;
            case "all": return strings.filterall;
            case "inprogress": return strings.filterinprogress;
            case "future": return strings.filterfuture;
            case "past": return strings.filterpast;
            case "favourites": return strings.filterfavourites;
            case "hidden": return strings.filterhidden;
            case "customfield": return config.customfieldname ?? strings.filtercustomfield;
            default: return f;
        }
    };

    // Only offer filters/views the admin has enabled.
    const filterOptions = config.enabledfilters.map((f) => ({value: f, label: filterLabel(f)}));

    const sortOptions = [
        {value: "title" as Sort, label: strings.sortcoursename},
        // Short name sort is only available when extended course names are shown.
        ...(config.showshortname ? [{value: "shortname" as Sort, label: strings.sortshortname}] : []),
        {value: "lastaccessed" as Sort, label: strings.sortlastaccessed},
        {value: "startdate" as Sort, label: strings.sortstartdate},
    ];

    const viewLabels: Record<View, string> = {
        card: strings.viewcard,
        list: strings.viewlist,
        summary: strings.viewsummary,
    };
    const viewOptions = config.enabledviews.map((v) => ({value: v, label: viewLabels[v]}));

    const customfieldvalues = config.customfieldvalues ?? [];
    const showCustomFieldSelector = filter === "customfield" && customfieldvalues.length > 0;
    const customFieldOptions = customfieldvalues.map((v) => ({value: v.value, label: v.name}));
    const selectedCustomFieldName = customfieldvalues.find((v) => v.value === customfieldvalue)?.name;

    const selectedFilter = filterOptions.find((o) => o.value === filter);
    const selectedSort = sortOptions.find((o) => o.value === sort);
    const selectedView = viewOptions.find((o) => o.value === view);

    const showActions = !!(managecourseurl || createcourseurl || requestcourseurl);

    return (
        <div className="local-co-toolbar">
            {showActions && (
                <div className="local-co-toolbar__group local-co-toolbar__group--actions">
                    {managecourseurl && (
                        <a className="btn btn-outline-primary btn-sm" href={managecourseurl}>
                            {strings.managecourses}
                        </a>
                    )}
                    {requestcourseurl && (
                        <a className="btn btn-primary btn-sm" href={requestcourseurl}>
                            {strings.requestcoursebutton}
                        </a>
                    )}
                    {createcourseurl && (
                        <a className="btn btn-primary btn-sm" href={createcourseurl}>
                            <i className="fa-solid fa-plus" aria-hidden="true" /> {strings.createcourse}
                        </a>
                    )}
                </div>
            )}
            {showActions && (
                <div className="local-co-toolbar__divider" aria-hidden="true" />
            )}
            <div className="local-co-toolbar__group local-co-toolbar__group--search">
                <SearchInput value={search} onChange={onSearch} />
            </div>
            <div className="local-co-toolbar__group local-co-toolbar__group--tools">
                {filterOptions.length > 1 && (
                    <Dropdown<Filter>
                        label={strings.filterresults}
                        triggerAriaLabel={`${strings.filterresults}: ${selectedFilter?.label ?? ""}`}
                        icon="filter"
                        options={filterOptions}
                        current={filter}
                        onSelect={onFilter}
                        active={filter !== DEFAULT_FILTER}
                        showLabel
                    />
                )}
                {showCustomFieldSelector && (
                    <Dropdown<string>
                        label={config.customfieldname ?? strings.filtercustomfield}
                        triggerAriaLabel={
                            `${config.customfieldname ?? strings.filtercustomfield}: ${selectedCustomFieldName ?? ""}`
                        }
                        icon="tag"
                        options={customFieldOptions}
                        current={customfieldvalue ?? ""}
                        onSelect={onCustomFieldValue}
                        active={customfieldvalue !== null}
                        showLabel
                    />
                )}
                <Dropdown<Sort>
                    label={strings.sortcourses}
                    triggerAriaLabel={`${strings.sortcourses}: ${selectedSort?.label ?? ""}`}
                    icon="sort"
                    options={sortOptions}
                    current={sort}
                    onSelect={onSort}
                    active={sort !== DEFAULT_SORT}
                    menuTitle={strings.sortby}
                />
                {viewOptions.length > 1 && (
                    <Dropdown<View>
                        label={strings.changelayout}
                        triggerAriaLabel={`${strings.changelayout}: ${selectedView?.label ?? ""}`}
                        icon={currentViewIcon}
                        options={viewOptions}
                        current={view}
                        onSelect={onView}
                        active={view !== DEFAULT_VIEW}
                        menuTitle={strings.viewlabel}
                    />
                )}
            </div>
        </div>
    );
}
