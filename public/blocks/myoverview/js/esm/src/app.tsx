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
 * Course overview React component — block root (MDL-88965).
 *
 * Mounted by core/react_autoinit at the @moodle/lms/block_myoverview/app mount
 * point. Owns all UI state and drives the web-service data pipeline: a fetch
 * effect reloads courses whenever the filter, sort, custom-field value, page or
 * debounced search term changes, and preference changes are written back to the
 * server. Favourite/hidden toggles are optimistic with revert-on-error.
 *
 * @module     block_myoverview/app
 */

import {useCallback, useEffect, useLayoutEffect, useMemo, useReducer, useRef, useState} from "react";
import {AppProps, DEFAULT_FILTER, PAGE_SIZE} from "./types";
import {
    getCourses, setFavourite, setCourseHidden, setPreference,
    PREF_VIEW, PREF_FILTER, PREF_SORT, PREF_CFVALUE,
} from "./api";
import {
    CourseCallbacksContext, CourseMembershipContext, StringsContext, initState, reducer,
} from "./state";
import Toolbar from "./components/Toolbar";
import CourseList from "./components/CourseList";
import Pagination from "./components/Pagination";
import EmptyState from "./components/EmptyState";

const SEARCH_DEBOUNCE_MS = 300;

/**
 * Run `effect` on every dependency change EXCEPT the initial mount. Used for the
 * preference-write-back effects, which must not fire just because the component
 * mounted — only when the user actually changes a value.
 *
 * @param effect The effect to run after the first render.
 * @param deps The dependency list.
 */
function useSkipFirstEffect(effect: () => void, deps: unknown[]) {
    const isFirst = useRef(true);
    useEffect(() => {
        if (isFirst.current) {
            isFirst.current = false;
            return;
        }
        effect();
    }, deps);
}

// Container width breakpoints (px). Mobile-first: the base CSS is the narrowest layout and
// each class widens it. Used instead of CSS @container queries because Moodle's plugin CSS
// pipeline strips @container/container rules; see styles.css.
const WIDTH_BREAKPOINTS = [480, 576, 768, 992];

/**
 * Observe an element's width and return the space-separated `co-min-<bp>` classes for every
 * breakpoint it currently meets, so the layout responds to the block's own width (e.g. the
 * narrow block drawer) rather than the viewport.
 *
 * @param ref A ref to the element to observe.
 * @returns The width-tier class string.
 */
function useContainerWidthClasses(ref: React.RefObject<HTMLElement>): string {
    const [width, setWidth] = useState(0);
    useLayoutEffect(() => {
        const el = ref.current;
        if (!el) {
            return undefined;
        }
        setWidth(el.getBoundingClientRect().width);
        if (typeof ResizeObserver === "undefined") {
            return undefined;
        }
        const observer = new ResizeObserver((entries) => {
            setWidth(entries[0].contentRect.width);
        });
        observer.observe(el);
        return () => observer.disconnect();
    }, [ref]);
    return WIDTH_BREAKPOINTS.filter((bp) => width >= bp).map((bp) => `co-min-${bp}`).join(" ");
}

/**
 * The course overview application.
 *
 * @param props Mount props (strings, preferences, config, permissions, role, URLs, zero-state).
 * @returns The rendered course overview.
 */
export default function App(props: AppProps) {
    const {
        strings, preferences, config, permissions, role,
        createcourseurl, managecourseurl, requestcourseurl, hiddencourseids, zerostate,
    } = props;

    const [state, dispatch] = useReducer(
        reducer, preferences, (prefs) => initState(prefs, hiddencourseids ?? []));

    // Width-tier classes so the layout responds to the block's own width (e.g. the block drawer).
    const rootRef = useRef<HTMLElement>(null);
    const widthClasses = useContainerWidthClasses(rootRef);

    const {
        view, filter, sort, search, page, favourites, hidden,
        loading, error, courses, customfieldvalue,
    } = state;

    // Debounce the value that triggers a fetch — search itself stays in state
    // immediately so the input stays responsive, but the fetch effect only
    // reacts once typing pauses.
    const [debouncedSearch, setDebouncedSearch] = useState(search);
    useEffect(() => {
        const timer = setTimeout(() => setDebouncedSearch(search), SEARCH_DEBOUNCE_MS);
        return () => clearTimeout(timer);
    }, [search]);

    // Fetch the full matching course set whenever filter/sort/customfieldvalue/debouncedSearch/view
    // changes (NOT on page change). Pagination is client-side: the timeline web service returns no
    // total count, so numbered pagination needs the whole result set to know how many pages there
    // are. limit:0 asks the web service for all matching courses. requestIdRef guards against an
    // older, slower request overwriting a newer one's result — getCourses() is built on core/ajax's
    // fetchOne, which has no per-call cancellation signal, so a superseded request can't be
    // cancelled, only its result ignored once it resolves.
    const requestIdRef = useRef(0);
    useEffect(() => {
        const requestId = ++requestIdRef.current;
        // A search uses the 'search' classification with a searchvalue (the web service treats
        // classifications as mutually exclusive, so it cannot combine search with a grouping).
        // To keep the active filter applied during a search we intersect the search results with
        // the filter's own set. Favourites/hidden/all are applied client-side (see visibleCourses);
        // timeline and custom-field membership can't be derived from the card payload, so for those
        // we fetch the filter's set and intersect by id, reusing the server's own logic.
        const searching = debouncedSearch.trim() !== "";
        const isCustomField = filter === "customfield";
        const serverFilter = filter === "inprogress" || filter === "future"
            || filter === "past" || isCustomField;
        dispatch({type: "SET_LOADING"});

        const primaryArgs = {
            classification: searching ? "search" : filter,
            sort,
            limit: 0,
            offset: 0,
            view,
            customfieldname: !searching && isCustomField ? config.customfieldname : undefined,
            customfieldvalue: !searching && isCustomField ? (customfieldvalue ?? undefined) : undefined,
            searchvalue: searching ? debouncedSearch : undefined,
        };

        const load = async(): Promise<typeof state.courses> => {
            if (searching && serverFilter) {
                const [searchRes, filterRes] = await Promise.all([
                    getCourses(primaryArgs),
                    getCourses({
                        classification: filter,
                        sort,
                        limit: 0,
                        offset: 0,
                        view,
                        customfieldname: isCustomField ? config.customfieldname : undefined,
                        customfieldvalue: isCustomField ? (customfieldvalue ?? undefined) : undefined,
                    }),
                ]);
                const allowed = new Set(filterRes.courses.map((c) => c.id));
                return searchRes.courses.filter((c) => allowed.has(c.id));
            }
            const {courses: fetched} = await getCourses(primaryArgs);
            return fetched;
        };

        load()
            .then((fetched) => {
                if (requestId === requestIdRef.current) {
                    dispatch({type: "SET_COURSES", courses: fetched});
                }
            })
            .catch(() => {
                if (requestId === requestIdRef.current) {
                    dispatch({type: "SET_ERROR", error: strings.errorloadingcourses});
                }
            });
    }, [filter, sort, customfieldvalue, debouncedSearch, view]);

    // Write preferences back to the server on real changes only — useSkipFirstEffect
    // prevents these from firing on initial mount (they would just re-write the value
    // the server already sent).
    useSkipFirstEffect(() => {
        setPreference(PREF_VIEW, view);
    }, [view]);
    useSkipFirstEffect(() => {
        setPreference(PREF_FILTER, filter);
    }, [filter]);
    useSkipFirstEffect(() => {
        setPreference(PREF_SORT, sort);
    }, [sort]);
    useSkipFirstEffect(() => {
        if (customfieldvalue !== null) {
            setPreference(PREF_CFVALUE, customfieldvalue);
        }
    }, [customfieldvalue]);

    const toggleFavourite = useCallback((id: number) => {
        const nowFav = !favourites.has(id);
        dispatch({type: "TOGGLE_FAVOURITE", id});
        setFavourite(id, nowFav).catch(() => dispatch({type: "TOGGLE_FAVOURITE", id}));
    }, [favourites]);

    const toggleHidden = useCallback((id: number) => {
        const nowHidden = !hidden.has(id);
        dispatch({type: "TOGGLE_HIDDEN", id});
        setCourseHidden(id, nowHidden).catch(() => dispatch({type: "TOGGLE_HIDDEN", id}));
    }, [hidden]);

    const callbacks = useMemo(() => ({toggleFavourite, toggleHidden}), [toggleFavourite, toggleHidden]);
    const memberships = useMemo(() => ({favourites, hidden}), [favourites, hidden]);

    // Apply the active filter as a client-side filter on top of the server results. This keeps the
    // filter applied during a search (search results are intersected with the filter) and lets a
    // hide/restore or star/unstar update the visible list immediately, before any refetch:
    // 'hidden' shows only hidden courses, 'allincludinghidden' shows everything, 'favourites' shows
    // only starred courses, and every other filter (all, timeline, custom-field) excludes hidden.
    const visibleCourses = courses.filter((c) => {
        const isHidden = hidden.has(c.id);
        if (filter === "hidden") {
            return isHidden;
        }
        if (filter === "allincludinghidden") {
            return true;
        }
        if (filter === "favourites") {
            return favourites.has(c.id);
        }
        return !isHidden;
    });

    const hasNoCourses = !loading && !error && visibleCourses.length === 0;
    // Hide the search/filter/sort/view controls in a genuine zero-state (no courses and no active
    // search or non-default filter), matching the old block and the Figma zero-state. They stay
    // visible when a search or filter is active so the user can always undo it.
    const showControls = loading || courses.length > 0 || search !== "" || filter !== DEFAULT_FILTER;
    // Client-side pagination over the (hidden-filtered) result set (see the fetch effect above).
    const pageCount = Math.max(1, Math.ceil(visibleCourses.length / PAGE_SIZE));
    const currentPage = Math.min(page, pageCount);
    const pageCourses = visibleCourses.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE);

    return (
        <StringsContext.Provider value={strings}>
            <CourseCallbacksContext.Provider value={callbacks}>
                <CourseMembershipContext.Provider value={memberships}>
                    {/* No aria-label here: the Moodle block wrapper is already a "Course overview"
                        region landmark, so naming this section too would create a duplicate landmark
                        (axe landmark-unique). */}
                    <section ref={rootRef} className={`block-myoverview ${widthClasses}`.trim()}>
                        <Toolbar
                            role={role}
                            permissions={permissions}
                            showControls={showControls}
                            hasnocourses={hasNoCourses}
                            view={view}
                            filter={filter}
                            sort={sort}
                            search={search}
                            config={config}
                            createcourseurl={createcourseurl}
                            managecourseurl={managecourseurl}
                            requestcourseurl={requestcourseurl}
                            customfieldvalue={customfieldvalue}
                            onView={(v) => dispatch({type: "SET_VIEW", view: v})}
                            onFilter={(f) => dispatch({type: "SET_FILTER", filter: f})}
                            onSort={(s) => dispatch({type: "SET_SORT", sort: s})}
                            onSearch={(s) => dispatch({type: "SET_SEARCH", search: s})}
                            onCustomFieldValue={(v) => dispatch({type: "SET_CUSTOMFIELDVALUE", value: v})}
                        />
                        {/* Aria-live announces loading/error to screen readers — the old block
                            rendered synchronously server-side and never had a client loading/error
                            state to announce, so this is new UI that must independently meet
                            WCAG 2.1 AA. */}
                        <div aria-live="polite">
                            {loading && (
                                <div className="block-myoverview__loading" role="status" aria-busy="true" />
                            )}
                            {error && <p className="block-myoverview__error">{error}</p>}
                        </div>
                        {hasNoCourses && <EmptyState zerostate={zerostate} />}
                        {!hasNoCourses && !loading && !error && (
                            <>
                                <CourseList
                                    courses={pageCourses}
                                    view={view}
                                    role={role}
                                    displaycategories={config.displaycategories}
                                />
                                <Pagination
                                    page={currentPage}
                                    pageCount={pageCount}
                                    onPage={(p) => dispatch({type: "SET_PAGE", page: p})}
                                />
                            </>
                        )}
                    </section>
                </CourseMembershipContext.Provider>
            </CourseCallbacksContext.Provider>
        </StringsContext.Provider>
    );
}
