var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { createContext, useContext } from "react";
import {
  DEFAULT_FILTER,
  DEFAULT_SORT,
  DEFAULT_VIEW
} from "./types";
const initState = /* @__PURE__ */ __name((prefs, hiddenids = []) => ({
  view: prefs.view ?? DEFAULT_VIEW,
  filter: prefs.filter ?? DEFAULT_FILTER,
  sort: prefs.sort ?? DEFAULT_SORT,
  search: "",
  page: 1,
  favourites: /* @__PURE__ */ new Set(),
  hidden: new Set(hiddenids),
  loading: false,
  error: null,
  courses: [],
  customfieldvalue: prefs.customfieldvalue ?? null
}), "initState");
const reducer = /* @__PURE__ */ __name((state, action) => {
  switch (action.type) {
    case "SET_VIEW":
      return { ...state, view: action.view };
    case "SET_FILTER":
      return { ...state, filter: action.filter, page: 1 };
    case "SET_SORT":
      return { ...state, sort: action.sort, page: 1 };
    case "SET_SEARCH":
      return { ...state, search: action.search, page: 1 };
    case "SET_PAGE":
      return { ...state, page: action.page };
    case "SET_CUSTOMFIELDVALUE":
      return { ...state, customfieldvalue: action.value, page: 1 };
    case "SET_COURSES":
      return {
        ...state,
        courses: action.courses,
        favourites: new Set(action.courses.filter((c) => c.isfavourite).map((c) => c.id)),
        loading: false,
        error: null
      };
    case "SET_LOADING":
      return { ...state, loading: true, error: null };
    case "SET_ERROR":
      return { ...state, error: action.error, loading: false };
    case "TOGGLE_FAVOURITE": {
      const favourites = new Set(state.favourites);
      if (favourites.has(action.id)) {
        favourites.delete(action.id);
      } else {
        favourites.add(action.id);
      }
      return { ...state, favourites };
    }
    case "TOGGLE_HIDDEN": {
      const hidden = new Set(state.hidden);
      if (hidden.has(action.id)) {
        hidden.delete(action.id);
      } else {
        hidden.add(action.id);
      }
      return { ...state, hidden, page: 1 };
    }
    default:
      return state;
  }
}, "reducer");
const CourseCallbacksContext = createContext(null);
const CourseMembershipContext = createContext(null);
const useCourseCallbacks = /* @__PURE__ */ __name(() => {
  const ctx = useContext(CourseCallbacksContext);
  if (ctx === null) {
    throw new Error("useCourseCallbacks must be used within CourseCallbacksContext");
  }
  return ctx;
}, "useCourseCallbacks");
const useCourseMemberships = /* @__PURE__ */ __name(() => {
  const ctx = useContext(CourseMembershipContext);
  if (ctx === null) {
    throw new Error("useCourseMemberships must be used within CourseMembershipContext");
  }
  return ctx;
}, "useCourseMemberships");
const StringsContext = createContext(null);
const useStrings = /* @__PURE__ */ __name(() => {
  const ctx = useContext(StringsContext);
  if (ctx === null) {
    throw new Error("useStrings must be used within StringsContext");
  }
  return ctx;
}, "useStrings");
export {
  CourseCallbacksContext,
  CourseMembershipContext,
  StringsContext,
  initState,
  reducer,
  useCourseCallbacks,
  useCourseMemberships,
  useStrings
};
//# sourceMappingURL=state.dev.js.map
