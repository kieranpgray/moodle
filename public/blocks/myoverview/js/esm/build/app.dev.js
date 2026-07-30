var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
/**
 * Course overview React component — block root (MDL-88965).
 *
 * Mounted by core/react_autoinit at the `@moodle/lms/block_myoverview/app` mount
 * point. Owns all UI state and drives the web-service data pipeline: courses
 * are fetched one server-side page at a time (chained through the service's
 * nextoffset, with the next page silently prefetched — see the fetch effect),
 * a query change (filter, sort, custom-field value, debounced search, view)
 * resets the paged data, and preference changes are written back to the
 * server. Favourite/hidden toggles are optimistic with revert-on-error.
 *
 * @module     block_myoverview/app
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {
  useCallback,
  useEffect,
  useLayoutEffect,
  useMemo,
  useReducer,
  useRef,
  useState
} from "react";
import { loadErrorString, loadStrings } from "./strings";
import {
  setFavourite,
  setCourseHidden,
  setPreference,
  PREF_VIEW,
  PREF_FILTER,
  PREF_SORT,
  PREF_CFVALUE
} from "./repository";
import { usePagedCourses } from "./hooks/usePagedCourses";
import {
  CourseCallbacksContext,
  CourseMembershipContext,
  StringsContext,
  hasNextPage,
  initState,
  reducer
} from "./state";
import Toolbar from "@moodle/lms/block_myoverview/components/Toolbar";
import CourseList from "@moodle/lms/block_myoverview/components/CourseList";
import Pagination from "@moodle/lms/block_myoverview/components/Pagination";
import EmptyState from "@moodle/lms/block_myoverview/components/EmptyState";
const SEARCH_DEBOUNCE_MS = 300;
const STRINGS_RETRY_ATTEMPTS = 3;
const STRINGS_RETRY_DELAY_MS = 2e3;
function useSkipFirstEffect(effect, dependencies) {
  const isFirst = useRef(true);
  useEffect(() => {
    if (isFirst.current) {
      isFirst.current = false;
      return;
    }
    effect();
  }, dependencies);
}
__name(useSkipFirstEffect, "useSkipFirstEffect");
const WIDTH_BREAKPOINTS = [480, 576, 992];
function useContainerWidthClasses(reference) {
  const [width, setWidth] = useState(0);
  useLayoutEffect(() => {
    const element = reference.current;
    if (!element) {
      return void 0;
    }
    setWidth(element.getBoundingClientRect().width);
    if (typeof ResizeObserver === "undefined") {
      return void 0;
    }
    const observer = new ResizeObserver((entries) => {
      setWidth(entries[0].contentRect.width);
    });
    observer.observe(element);
    return () => {
      observer.disconnect();
    };
  }, [reference]);
  return WIDTH_BREAKPOINTS.filter((bp) => width >= bp).map((bp) => `courseoverview-min-${bp}`).join(" ");
}
__name(useContainerWidthClasses, "useContainerWidthClasses");
function EmptyStateChoice({ hasActiveQuery, isZeroState, zerostate, illustrationurl }) {
  if (hasActiveQuery) {
    return /* @__PURE__ */ jsxDEV(EmptyState, { variant: "no-results", illustrationurl }, void 0, false, {
      fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
      lineNumber: 149,
      columnNumber: 16
    }, this);
  }
  if (isZeroState) {
    return /* @__PURE__ */ jsxDEV(EmptyState, { zerostate, illustrationurl }, void 0, false, {
      fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
      lineNumber: 153,
      columnNumber: 16
    }, this);
  }
  return /* @__PURE__ */ jsxDEV(EmptyState, { variant: "all-hidden", illustrationurl }, void 0, false, {
    fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
    lineNumber: 156,
    columnNumber: 12
  }, this);
}
__name(EmptyStateChoice, "EmptyStateChoice");
function StringsFallback({ failed, errorText }) {
  if (failed) {
    return /* @__PURE__ */ jsxDEV("p", { className: "block-myoverview__error", role: "alert", children: errorText ?? "An error occurred while loading. Please reload the page." }, void 0, false, {
      fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
      lineNumber: 173,
      columnNumber: 13
    }, this);
  }
  return /* @__PURE__ */ jsxDEV("div", { className: "block-myoverview__loading", "aria-hidden": "true" }, void 0, false, {
    fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
    lineNumber: 182,
    columnNumber: 12
  }, this);
}
__name(StringsFallback, "StringsFallback");
function LiveRegion({ loading, error, announcement, strings }) {
  const hasError = error !== null && error !== "";
  return /* @__PURE__ */ jsxDEV("div", { "aria-live": "polite", children: [
    loading && /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV("div", { className: "block-myoverview__loading", "aria-hidden": "true" }, void 0, false, {
        fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
        lineNumber: 208,
        columnNumber: 21
      }, this),
      /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: strings.loadingcourses }, void 0, false, {
        fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
        lineNumber: 209,
        columnNumber: 21
      }, this)
    ] }, void 0, true, {
      fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
      lineNumber: 207,
      columnNumber: 17
    }, this),
    hasError && /* @__PURE__ */ jsxDEV("p", { className: "block-myoverview__error", children: strings.errorloadingcourses }, void 0, false, {
      fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
      lineNumber: 213,
      columnNumber: 17
    }, this),
    !loading && !hasError && announcement !== null && announcement !== "" && /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: announcement }, void 0, false, {
      fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
      lineNumber: 216,
      columnNumber: 17
    }, this)
  ] }, void 0, true, {
    fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
    lineNumber: 205,
    columnNumber: 9
  }, this);
}
__name(LiveRegion, "LiveRegion");
function fetchTerminalErrorText(onText) {
  loadErrorString().then((text) => {
    onText(text);
    return null;
  }).catch(() => void 0);
}
__name(fetchTerminalErrorText, "fetchTerminalErrorText");
function App(props) {
  const {
    preferences,
    config,
    createcourseurl,
    managecourseurl,
    requestcourseurl,
    hiddencourseids,
    zerostate,
    illustrationurl
  } = props;
  const [strings, setStrings] = useState(null);
  const [stringsFailed, setStringsFailed] = useState(false);
  const [stringsErrorText, setStringsErrorText] = useState(null);
  useEffect(() => {
    let cancelled = false;
    let timer;
    const attempt = /* @__PURE__ */ __name((retriesLeft) => {
      loadStrings().then((loaded) => {
        if (!cancelled) {
          setStrings(loaded);
        }
        return null;
      }).catch(() => {
        if (cancelled) {
          return;
        }
        if (retriesLeft > 0) {
          timer = setTimeout(() => {
            attempt(retriesLeft - 1);
          }, STRINGS_RETRY_DELAY_MS);
        } else {
          setStringsFailed(true);
          fetchTerminalErrorText((text) => {
            if (!cancelled) {
              setStringsErrorText(text);
            }
          });
        }
      });
    }, "attempt");
    attempt(STRINGS_RETRY_ATTEMPTS);
    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, []);
  const [state, dispatch] = useReducer(reducer, preferences, (prefs) => initState(prefs, hiddencourseids ?? []));
  const rootReference = useRef(null);
  const widthClasses = useContainerWidthClasses(rootReference);
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
    customfieldvalue
  } = state;
  const [debouncedSearch, setDebouncedSearch] = useState(search);
  useEffect(() => {
    if (search === "") {
      setDebouncedSearch("");
      return void 0;
    }
    const timer = setTimeout(() => {
      setDebouncedSearch(search);
    }, SEARCH_DEBOUNCE_MS);
    return () => {
      clearTimeout(timer);
    };
  }, [search]);
  const { searching, refetchAfterToggle } = usePagedCourses({
    state,
    dispatch,
    config,
    debouncedSearch
  });
  useSkipFirstEffect(() => {
    void setPreference(PREF_VIEW, view);
  }, [view]);
  useSkipFirstEffect(() => {
    void setPreference(PREF_FILTER, filter);
  }, [filter]);
  useSkipFirstEffect(() => {
    void setPreference(PREF_SORT, sort);
  }, [sort]);
  useSkipFirstEffect(() => {
    if (customfieldvalue !== null) {
      void setPreference(PREF_CFVALUE, customfieldvalue);
    }
  }, [customfieldvalue]);
  const toggleFavourite = useCallback((id) => {
    const nowFav = !favourites.has(id);
    dispatch({ type: "TOGGLE_FAVOURITE", id });
    setFavourite(id, nowFav).then(() => {
      dispatch({ type: "FAVOURITE_SETTLED", id });
      if (filter === "favourites" && !searching) {
        refetchAfterToggle();
      }
      return null;
    }).catch(() => {
      dispatch({ type: "TOGGLE_FAVOURITE", id });
      dispatch({ type: "FAVOURITE_SETTLED", id });
    });
  }, [favourites, filter, searching, refetchAfterToggle]);
  const [announcement, setAnnouncement] = useState(null);
  const toggleHidden = useCallback((id) => {
    const nowHidden = !hidden.has(id);
    dispatch({ type: "TOGGLE_HIDDEN", id });
    setAnnouncement((nowHidden ? strings?.courseremoved : strings?.courserestored) ?? null);
    setCourseHidden(id, nowHidden).then(() => {
      refetchAfterToggle();
    }).catch(() => {
      dispatch({ type: "TOGGLE_HIDDEN", id });
    });
  }, [hidden, refetchAfterToggle, strings]);
  useEffect(() => {
    if (announcement === null || announcement === "") {
      return void 0;
    }
    if (document.activeElement === document.body) {
      rootReference.current?.focus();
    }
    const timer = setTimeout(() => {
      setAnnouncement(null);
    }, 3e3);
    return () => {
      clearTimeout(timer);
    };
  }, [announcement]);
  const callbacks = useMemo(() => ({ toggleFavourite, toggleHidden }), [toggleFavourite, toggleHidden]);
  const memberships = useMemo(() => ({ favourites, hidden }), [favourites, hidden]);
  const pageCourses = (state.pages[page - 1] ?? []).filter((c) => {
    const isHidden = hidden.has(c.id);
    if (searching) {
      return !isHidden;
    }
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
  const hasError = error !== null && error !== "";
  const hasNoCourses = !loading && !hasError && page === 1 && pageCourses.length === 0;
  const hasActiveQuery = debouncedSearch !== "" || filter !== config.defaultfilter;
  const userhascourses = !zerostate;
  const isZeroState = hasNoCourses && !hasActiveQuery && !userhascourses;
  const showControls = loading || state.pages.some((p) => p.length > 0) || hasActiveQuery || userhascourses;
  const hasNext = hasNextPage(state);
  return /* @__PURE__ */ jsxDEV("section", { ref: rootReference, tabIndex: -1, className: `block-myoverview ${widthClasses}`.trim(), children: [
    !strings && /* @__PURE__ */ jsxDEV(StringsFallback, { failed: stringsFailed, errorText: stringsErrorText }, void 0, false, {
      fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
      lineNumber: 481,
      columnNumber: 26
    }, this),
    strings && /* @__PURE__ */ jsxDEV(StringsContext.Provider, { value: strings, children: /* @__PURE__ */ jsxDEV(CourseCallbacksContext.Provider, { value: callbacks, children: /* @__PURE__ */ jsxDEV(CourseMembershipContext.Provider, { value: memberships, children: /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV(
        Toolbar,
        {
          showControls,
          iszerostate: isZeroState,
          view,
          filter,
          sort,
          search,
          config,
          createcourseurl,
          managecourseurl,
          requestcourseurl,
          customfieldvalue,
          onView: (v) => {
            dispatch({ type: "SET_VIEW", view: v });
          },
          onFilter: (f) => {
            dispatch({ type: "SET_FILTER", filter: f });
          },
          onSort: (s) => {
            dispatch({ type: "SET_SORT", sort: s });
          },
          onSearch: (s) => {
            dispatch({ type: "SET_SEARCH", search: s });
          },
          onCustomFieldValue: (v) => {
            dispatch({ type: "SET_CUSTOMFIELDVALUE", value: v });
          }
        },
        void 0,
        false,
        {
          fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
          lineNumber: 490,
          columnNumber: 33
        },
        this
      ),
      /* @__PURE__ */ jsxDEV(
        LiveRegion,
        {
          loading,
          error,
          announcement,
          strings
        },
        void 0,
        false,
        {
          fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
          lineNumber: 518,
          columnNumber: 33
        },
        this
      ),
      hasNoCourses && /* @__PURE__ */ jsxDEV(
        EmptyStateChoice,
        {
          hasActiveQuery,
          isZeroState,
          zerostate,
          illustrationurl
        },
        void 0,
        false,
        {
          fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
          lineNumber: 525,
          columnNumber: 37
        },
        this
      ),
      !hasNoCourses && !loading && !hasError && /* @__PURE__ */ jsxDEV(Fragment, { children: [
        /* @__PURE__ */ jsxDEV(
          CourseList,
          {
            courses: pageCourses,
            view,
            displaycategories: config.displaycategories
          },
          void 0,
          false,
          {
            fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
            lineNumber: 534,
            columnNumber: 41
          },
          this
        ),
        /* @__PURE__ */ jsxDEV(
          Pagination,
          {
            page,
            hasNext,
            onPage: (p) => {
              dispatch({ type: "SET_PAGE", page: p });
            }
          },
          void 0,
          false,
          {
            fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
            lineNumber: 539,
            columnNumber: 41
          },
          this
        )
      ] }, void 0, true, {
        fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
        lineNumber: 533,
        columnNumber: 37
      }, this)
    ] }, void 0, true, {
      fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
      lineNumber: 489,
      columnNumber: 29
    }, this) }, void 0, false, {
      fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
      lineNumber: 485,
      columnNumber: 25
    }, this) }, void 0, false, {
      fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
      lineNumber: 484,
      columnNumber: 21
    }, this) }, void 0, false, {
      fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
      lineNumber: 483,
      columnNumber: 17
    }, this)
  ] }, void 0, true, {
    fileName: "public/blocks/myoverview/js/esm/src/app.tsx",
    lineNumber: 480,
    columnNumber: 9
  }, this);
}
__name(App, "App");
export {
  App as default
};
//# sourceMappingURL=app.dev.js.map
