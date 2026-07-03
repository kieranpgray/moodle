var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
import { useCallback, useEffect, useLayoutEffect, useMemo, useReducer, useRef, useState } from "react";
import { DEFAULT_FILTER, PAGE_SIZE } from "./types";
import {
  getCourses,
  setFavourite,
  setCourseHidden,
  setPreference,
  PREF_VIEW,
  PREF_FILTER,
  PREF_SORT,
  PREF_CFVALUE
} from "./api";
import {
  CourseCallbacksContext,
  CourseMembershipContext,
  StringsContext,
  initState,
  reducer
} from "./state";
import Toolbar from "./components/Toolbar";
import CourseList from "./components/CourseList";
import Pagination from "./components/Pagination";
import EmptyState from "./components/EmptyState";
const SEARCH_DEBOUNCE_MS = 300;
function useSkipFirstEffect(effect, deps) {
  const isFirst = useRef(true);
  useEffect(() => {
    if (isFirst.current) {
      isFirst.current = false;
      return;
    }
    effect();
  }, deps);
}
__name(useSkipFirstEffect, "useSkipFirstEffect");
const WIDTH_BREAKPOINTS = [480, 576, 768, 992];
function useContainerWidthClasses(ref) {
  const [width, setWidth] = useState(0);
  useLayoutEffect(() => {
    const el = ref.current;
    if (!el) {
      return void 0;
    }
    setWidth(el.getBoundingClientRect().width);
    if (typeof ResizeObserver === "undefined") {
      return void 0;
    }
    const observer = new ResizeObserver((entries) => {
      setWidth(entries[0].contentRect.width);
    });
    observer.observe(el);
    return () => observer.disconnect();
  }, [ref]);
  return WIDTH_BREAKPOINTS.filter((bp) => width >= bp).map((bp) => `courseoverview-min-${bp}`).join(" ");
}
__name(useContainerWidthClasses, "useContainerWidthClasses");
function App(props) {
  const {
    strings,
    preferences,
    config,
    permissions,
    role,
    createcourseurl,
    managecourseurl,
    requestcourseurl,
    hiddencourseids,
    zerostate,
    illustrationurl
  } = props;
  const [state, dispatch] = useReducer(
    reducer,
    preferences,
    (prefs) => initState(prefs, hiddencourseids ?? [])
  );
  const rootRef = useRef(null);
  const widthClasses = useContainerWidthClasses(rootRef);
  const {
    view,
    filter,
    sort,
    search,
    page,
    favourites,
    hidden,
    loading,
    error,
    courses,
    customfieldvalue
  } = state;
  const [debouncedSearch, setDebouncedSearch] = useState(search);
  useEffect(() => {
    const timer = setTimeout(() => setDebouncedSearch(search), SEARCH_DEBOUNCE_MS);
    return () => clearTimeout(timer);
  }, [search]);
  const requestIdRef = useRef(0);
  useEffect(() => {
    const requestId = ++requestIdRef.current;
    const searching = debouncedSearch.trim() !== "";
    const isCustomField = filter === "customfield";
    const serverFilter = filter === "inprogress" || filter === "future" || filter === "past" || isCustomField;
    dispatch({ type: "SET_LOADING" });
    const primaryArgs = {
      classification: searching ? "search" : filter,
      sort,
      limit: 0,
      offset: 0,
      view,
      customfieldname: !searching && isCustomField ? config.customfieldname : void 0,
      customfieldvalue: !searching && isCustomField ? customfieldvalue ?? void 0 : void 0,
      searchvalue: searching ? debouncedSearch : void 0
    };
    const load = /* @__PURE__ */ __name(async () => {
      if (searching && serverFilter) {
        const [searchRes, filterRes] = await Promise.all([
          getCourses(primaryArgs),
          getCourses({
            classification: filter,
            sort,
            limit: 0,
            offset: 0,
            view,
            customfieldname: isCustomField ? config.customfieldname : void 0,
            customfieldvalue: isCustomField ? customfieldvalue ?? void 0 : void 0
          })
        ]);
        const allowed = new Set(filterRes.courses.map((c) => c.id));
        return searchRes.courses.filter((c) => allowed.has(c.id));
      }
      const { courses: fetched } = await getCourses(primaryArgs);
      return fetched;
    }, "load");
    load().then((fetched) => {
      if (requestId === requestIdRef.current) {
        dispatch({ type: "SET_COURSES", courses: fetched });
      }
    }).catch(() => {
      if (requestId === requestIdRef.current) {
        dispatch({ type: "SET_ERROR", error: strings.errorloadingcourses });
      }
    });
  }, [filter, sort, customfieldvalue, debouncedSearch, view]);
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
  const toggleFavourite = useCallback((id) => {
    const nowFav = !favourites.has(id);
    dispatch({ type: "TOGGLE_FAVOURITE", id });
    setFavourite(id, nowFav).catch(() => dispatch({ type: "TOGGLE_FAVOURITE", id }));
  }, [favourites]);
  const toggleHidden = useCallback((id) => {
    const nowHidden = !hidden.has(id);
    dispatch({ type: "TOGGLE_HIDDEN", id });
    setCourseHidden(id, nowHidden).catch(() => dispatch({ type: "TOGGLE_HIDDEN", id }));
  }, [hidden]);
  const callbacks = useMemo(() => ({ toggleFavourite, toggleHidden }), [toggleFavourite, toggleHidden]);
  const memberships = useMemo(() => ({ favourites, hidden }), [favourites, hidden]);
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
  const hasActiveQuery = search !== "" || filter !== DEFAULT_FILTER;
  const showControls = loading || courses.length > 0 || hasActiveQuery;
  const pageCount = Math.max(1, Math.ceil(visibleCourses.length / PAGE_SIZE));
  const currentPage = Math.min(page, pageCount);
  const pageCourses = visibleCourses.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE);
  return /* @__PURE__ */ jsxDEV(StringsContext.Provider, { value: strings, children: /* @__PURE__ */ jsxDEV(CourseCallbacksContext.Provider, { value: callbacks, children: /* @__PURE__ */ jsxDEV(CourseMembershipContext.Provider, { value: memberships, children: /* @__PURE__ */ jsxDEV("section", { ref: rootRef, className: `block-myoverview ${widthClasses}`.trim(), children: [
    /* @__PURE__ */ jsxDEV(
      Toolbar,
      {
        role,
        permissions,
        showControls,
        hasnocourses: hasNoCourses,
        view,
        filter,
        sort,
        search,
        config,
        createcourseurl,
        managecourseurl,
        requestcourseurl,
        customfieldvalue,
        onView: (v) => dispatch({ type: "SET_VIEW", view: v }),
        onFilter: (f) => dispatch({ type: "SET_FILTER", filter: f }),
        onSort: (s) => dispatch({ type: "SET_SORT", sort: s }),
        onSearch: (s) => dispatch({ type: "SET_SEARCH", search: s }),
        onCustomFieldValue: (v) => dispatch({ type: "SET_CUSTOMFIELDVALUE", value: v })
      },
      void 0,
      false,
      {
        fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
        lineNumber: 270,
        columnNumber: 25
      },
      this
    ),
    /* @__PURE__ */ jsxDEV("div", { "aria-live": "polite", children: [
      loading && /* @__PURE__ */ jsxDEV("div", { className: "block-myoverview__loading", role: "status", "aria-busy": "true" }, void 0, false, {
        fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
        lineNumber: 296,
        columnNumber: 33
      }, this),
      error && /* @__PURE__ */ jsxDEV("p", { className: "block-myoverview__error", children: error }, void 0, false, {
        fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
        lineNumber: 298,
        columnNumber: 39
      }, this)
    ] }, void 0, true, {
      fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
      lineNumber: 294,
      columnNumber: 25
    }, this),
    hasNoCourses && (hasActiveQuery ? /* @__PURE__ */ jsxDEV(EmptyState, { variant: "no-results", illustrationurl }, void 0, false, {
      fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
      lineNumber: 302,
      columnNumber: 35
    }, this) : /* @__PURE__ */ jsxDEV(EmptyState, { zerostate, illustrationurl }, void 0, false, {
      fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
      lineNumber: 303,
      columnNumber: 35
    }, this)),
    !hasNoCourses && !loading && !error && /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV(
        CourseList,
        {
          courses: pageCourses,
          view,
          role,
          displaycategories: config.displaycategories
        },
        void 0,
        false,
        {
          fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
          lineNumber: 307,
          columnNumber: 33
        },
        this
      ),
      /* @__PURE__ */ jsxDEV(
        Pagination,
        {
          page: currentPage,
          pageCount,
          onPage: (p) => dispatch({ type: "SET_PAGE", page: p })
        },
        void 0,
        false,
        {
          fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
          lineNumber: 313,
          columnNumber: 33
        },
        this
      )
    ] }, void 0, true, {
      fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
      lineNumber: 306,
      columnNumber: 29
    }, this)
  ] }, void 0, true, {
    fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
    lineNumber: 269,
    columnNumber: 21
  }, this) }, void 0, false, {
    fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
    lineNumber: 265,
    columnNumber: 17
  }, this) }, void 0, false, {
    fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
    lineNumber: 264,
    columnNumber: 13
  }, this) }, void 0, false, {
    fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
    lineNumber: 263,
    columnNumber: 9
  }, this);
}
__name(App, "App");
export {
  App as default
};
//# sourceMappingURL=app.dev.js.map
