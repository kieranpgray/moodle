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
 * Reducer, state shape and actions context for the course overview component.
 *
 * View, filter, sort and search are stored as independent slices. Changing
 * filter, sort, search or the custom-field value resets pagination to page 1
 * (MDL-88977) but never clears the other slices (MDL-88973). Course data,
 * loading and error are held in state and driven by the web-service layer.
 *
 * @module     block_myoverview/state
 */

import {createContext, useContext} from "react";
import {
    Course, DEFAULT_FILTER, DEFAULT_SORT, DEFAULT_VIEW, Filter, ServerPreferences, Sort, Strings, View,
} from "./types";

/** The full UI state. */
export type State = {
    view: View;
    filter: Filter;
    sort: Sort;
    search: string;
    page: number;
    favourites: Set<number>;
    hidden: Set<number>;
    loading: boolean;
    error: string | null;
    courses: Course[];
    customfieldvalue: string | null;
};


/** All reducer actions. */
export type Action =
    | {type: "SET_VIEW"; view: View}
    | {type: "SET_FILTER"; filter: Filter}
    | {type: "SET_SORT"; sort: Sort}
    | {type: "SET_SEARCH"; search: string}
    | {type: "SET_PAGE"; page: number}
    | {type: "SET_CUSTOMFIELDVALUE"; value: string}
    | {type: "SET_COURSES"; courses: Course[]}
    | {type: "SET_LOADING"}
    | {type: "SET_ERROR"; error: string}
    | {type: "TOGGLE_FAVOURITE"; id: number}
    | {type: "TOGGLE_HIDDEN"; id: number};


/**
 * Build the initial state from the server-provided preferences.
 *
 * @param prefs The user's stored view/filter/sort/customfield preferences.
 * @returns The initial reducer state.
 */
export const initState = (prefs: ServerPreferences, hiddenids: number[] = []): State => ({
    view: prefs.view ?? DEFAULT_VIEW,
    filter: prefs.filter ?? DEFAULT_FILTER,
    sort: prefs.sort ?? DEFAULT_SORT,
    search: "",
    page: 1,
    favourites: new Set<number>(),
    hidden: new Set<number>(hiddenids),
    loading: false,
    error: null,
    courses: [],
    customfieldvalue: prefs.customfieldvalue ?? null,
});

/**
 * Reducer for all course overview state transitions.
 *
 * @param state The current state.
 * @param action The action to apply.
 * @returns The next state.
 */
export const reducer = (state: State, action: Action): State => {
    switch (action.type) {
        case "SET_VIEW":
            return {...state, view: action.view};
        case "SET_FILTER":
            return {...state, filter: action.filter, page: 1};
        case "SET_SORT":
            return {...state, sort: action.sort, page: 1};
        case "SET_SEARCH":
            return {...state, search: action.search, page: 1};
        case "SET_PAGE":
            return {...state, page: action.page};
        case "SET_CUSTOMFIELDVALUE":
            return {...state, customfieldvalue: action.value, page: 1};
        case "SET_COURSES":
            // Reseed favourites from server truth: each course carries its own isfavourite
            // flag, so the star state always reflects what the server returned for this fetch.
            return {
                ...state,
                courses: action.courses,
                favourites: new Set(action.courses.filter((c) => c.isfavourite).map((c) => c.id)),
                loading: false,
                error: null,
            };
        case "SET_LOADING":
            return {...state, loading: true, error: null};
        case "SET_ERROR":
            return {...state, error: action.error, loading: false};
        case "TOGGLE_FAVOURITE": {
            const favourites = new Set(state.favourites);
            if (favourites.has(action.id)) {
                favourites.delete(action.id);
            } else {
                favourites.add(action.id);
            }
            return {...state, favourites};
        }
        case "TOGGLE_HIDDEN": {
            const hidden = new Set(state.hidden);
            if (hidden.has(action.id)) {
                hidden.delete(action.id);
            } else {
                hidden.add(action.id);
            }
            return {...state, hidden, page: 1};
        }
        default:
            return state;
    }
};


/** Stable dispatch-bound callbacks — reference never changes after mount. */
export type CourseCallbacks = {
    toggleFavourite: (id: number) => void;
    toggleHidden: (id: number) => void;
};

/** Live membership sets — reference changes on each star or hide action. */
export type CourseMemberships = {
    favourites: ReadonlySet<number>;
    hidden: ReadonlySet<number>;
};


export const CourseCallbacksContext = createContext<CourseCallbacks | null>(null);
export const CourseMembershipContext = createContext<CourseMemberships | null>(null);

/**
 * Access the stable toggle callbacks.
 *
 * @returns The CourseCallbacks provided by the app root.
 */
export const useCourseCallbacks = (): CourseCallbacks => {
    const ctx = useContext(CourseCallbacksContext);
    if (ctx === null) {
        throw new Error("useCourseCallbacks must be used within CourseCallbacksContext");
    }
    return ctx;
};

/**
 * Access the live starred/hidden membership sets.
 *
 * @returns The CourseMemberships provided by the app root.
 */
export const useCourseMemberships = (): CourseMemberships => {
    const ctx = useContext(CourseMembershipContext);
    if (ctx === null) {
        throw new Error("useCourseMemberships must be used within CourseMembershipContext");
    }
    return ctx;
};

export const StringsContext = createContext<Strings | null>(null);

/**
 * Access the UI strings context.
 *
 * @returns The Strings provided by the app root.
 */
export const useStrings = (): Strings => {
    const ctx = useContext(StringsContext);
    if (ctx === null) {
        throw new Error("useStrings must be used within StringsContext");
    }
    return ctx;
};
