var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
import {
  DEFAULT_FILTER,
  DEFAULT_SORT,
  DEFAULT_VIEW
} from "../types";
import { useStrings } from "../state";
import Dropdown from "./Dropdown";
import SearchInput from "./SearchInput";
const VIEW_ICON = {
  card: "table-cells-large",
  list: "list",
  summary: "bars"
};
function Toolbar(props) {
  const {
    view,
    filter,
    sort,
    search,
    config,
    createcourseurl,
    managecourseurl,
    requestcourseurl,
    customfieldvalue,
    onView,
    onFilter,
    onSort,
    onSearch,
    onCustomFieldValue
  } = props;
  const strings = useStrings();
  const currentViewIcon = VIEW_ICON[view] ?? "table-cells-large";
  const filterLabel = /* @__PURE__ */ __name((f) => {
    switch (f) {
      case "allincludinghidden":
        return strings.filterallincludinghidden;
      case "all":
        return strings.filterall;
      case "inprogress":
        return strings.filterinprogress;
      case "future":
        return strings.filterfuture;
      case "past":
        return strings.filterpast;
      case "favourites":
        return strings.filterfavourites;
      case "hidden":
        return strings.filterhidden;
      case "customfield":
        return config.customfieldname ?? strings.filtercustomfield;
      default:
        return f;
    }
  }, "filterLabel");
  const filterOptions = config.enabledfilters.map((f) => ({ value: f, label: filterLabel(f) }));
  const sortOptions = [
    { value: "title", label: strings.sortcoursename },
    // Short name sort is only available when extended course names are shown.
    ...config.showshortname ? [{ value: "shortname", label: strings.sortshortname }] : [],
    { value: "lastaccessed", label: strings.sortlastaccessed },
    { value: "startdate", label: strings.sortstartdate }
  ];
  const viewLabels = {
    card: strings.viewcard,
    list: strings.viewlist,
    summary: strings.viewsummary
  };
  const viewOptions = config.enabledviews.map((v) => ({ value: v, label: viewLabels[v] }));
  const customfieldvalues = config.customfieldvalues ?? [];
  const showCustomFieldSelector = filter === "customfield" && customfieldvalues.length > 0;
  const customFieldOptions = customfieldvalues.map((v) => ({ value: v.value, label: v.name }));
  const selectedCustomFieldName = customfieldvalues.find((v) => v.value === customfieldvalue)?.name;
  const selectedFilter = filterOptions.find((o) => o.value === filter);
  const selectedSort = sortOptions.find((o) => o.value === sort);
  const selectedView = viewOptions.find((o) => o.value === view);
  const showActions = !!(managecourseurl || createcourseurl || requestcourseurl);
  return /* @__PURE__ */ jsxDEV("div", { className: "local-co-toolbar", children: [
    showActions && /* @__PURE__ */ jsxDEV("div", { className: "local-co-toolbar__group local-co-toolbar__group--actions", children: [
      managecourseurl && /* @__PURE__ */ jsxDEV("a", { className: "btn btn-outline-primary btn-sm", href: managecourseurl, children: strings.managecourses }, void 0, false, {
        fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
        lineNumber: 129,
        columnNumber: 25
      }, this),
      requestcourseurl && /* @__PURE__ */ jsxDEV("a", { className: "btn btn-primary btn-sm", href: requestcourseurl, children: strings.requestcoursebutton }, void 0, false, {
        fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
        lineNumber: 134,
        columnNumber: 25
      }, this),
      createcourseurl && /* @__PURE__ */ jsxDEV("a", { className: "btn btn-primary btn-sm", href: createcourseurl, children: [
        /* @__PURE__ */ jsxDEV("i", { className: "fa-solid fa-plus", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
          lineNumber: 140,
          columnNumber: 29
        }, this),
        " ",
        strings.createcourse
      ] }, void 0, true, {
        fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
        lineNumber: 139,
        columnNumber: 25
      }, this)
    ] }, void 0, true, {
      fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
      lineNumber: 127,
      columnNumber: 17
    }, this),
    showActions && /* @__PURE__ */ jsxDEV("div", { className: "local-co-toolbar__divider", "aria-hidden": "true" }, void 0, false, {
      fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
      lineNumber: 146,
      columnNumber: 17
    }, this),
    /* @__PURE__ */ jsxDEV("div", { className: "local-co-toolbar__group local-co-toolbar__group--search", children: /* @__PURE__ */ jsxDEV(SearchInput, { value: search, onChange: onSearch }, void 0, false, {
      fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
      lineNumber: 149,
      columnNumber: 17
    }, this) }, void 0, false, {
      fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
      lineNumber: 148,
      columnNumber: 13
    }, this),
    /* @__PURE__ */ jsxDEV("div", { className: "local-co-toolbar__group local-co-toolbar__group--tools", children: [
      filterOptions.length > 1 && /* @__PURE__ */ jsxDEV(
        Dropdown,
        {
          label: strings.filterresults,
          triggerAriaLabel: `${strings.filterresults}: ${selectedFilter?.label ?? ""}`,
          icon: "filter",
          options: filterOptions,
          current: filter,
          onSelect: onFilter,
          active: filter !== DEFAULT_FILTER,
          showLabel: true
        },
        void 0,
        false,
        {
          fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
          lineNumber: 153,
          columnNumber: 21
        },
        this
      ),
      showCustomFieldSelector && /* @__PURE__ */ jsxDEV(
        Dropdown,
        {
          label: config.customfieldname ?? strings.filtercustomfield,
          triggerAriaLabel: `${config.customfieldname ?? strings.filtercustomfield}: ${selectedCustomFieldName ?? ""}`,
          icon: "tag",
          options: customFieldOptions,
          current: customfieldvalue ?? "",
          onSelect: onCustomFieldValue,
          active: customfieldvalue !== null,
          showLabel: true
        },
        void 0,
        false,
        {
          fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
          lineNumber: 165,
          columnNumber: 21
        },
        this
      ),
      /* @__PURE__ */ jsxDEV(
        Dropdown,
        {
          label: strings.sortcourses,
          triggerAriaLabel: `${strings.sortcourses}: ${selectedSort?.label ?? ""}`,
          icon: "sort",
          options: sortOptions,
          current: sort,
          onSelect: onSort,
          active: sort !== DEFAULT_SORT,
          menuTitle: strings.sortby
        },
        void 0,
        false,
        {
          fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
          lineNumber: 178,
          columnNumber: 17
        },
        this
      ),
      viewOptions.length > 1 && /* @__PURE__ */ jsxDEV(
        Dropdown,
        {
          label: strings.changelayout,
          triggerAriaLabel: `${strings.changelayout}: ${selectedView?.label ?? ""}`,
          icon: currentViewIcon,
          options: viewOptions,
          current: view,
          onSelect: onView,
          active: view !== DEFAULT_VIEW,
          menuTitle: strings.viewlabel
        },
        void 0,
        false,
        {
          fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
          lineNumber: 189,
          columnNumber: 21
        },
        this
      )
    ] }, void 0, true, {
      fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
      lineNumber: 151,
      columnNumber: 13
    }, this)
  ] }, void 0, true, {
    fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
    lineNumber: 125,
    columnNumber: 9
  }, this);
}
__name(Toolbar, "Toolbar");
export {
  Toolbar as default
};
//# sourceMappingURL=Toolbar.dev.js.map
