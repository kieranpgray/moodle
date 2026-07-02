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
    showControls,
    hasnocourses,
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
        return strings.filtercustomfield;
      default:
        return f;
    }
  }, "filterLabel");
  const filterOptions = [];
  for (const f of config.enabledfilters) {
    if (f === "customfield") {
      for (const cfv of config.customfieldvalues ?? []) {
        filterOptions.push({ value: `cf:${cfv.value}`, label: cfv.name });
      }
    } else {
      filterOptions.push({ value: f, label: filterLabel(f) });
    }
  }
  const currentFilterValue = filter === "customfield" ? `cf:${customfieldvalue ?? ""}` : filter;
  const filterGroup = /* @__PURE__ */ __name((val) => {
    if (val.startsWith("cf:")) {
      return "customfield";
    }
    switch (val) {
      case "all":
      case "allincludinghidden":
        return "default";
      case "inprogress":
      case "future":
      case "past":
        return "timeline";
      case "favourites":
        return "favourites";
      case "hidden":
        return "removed";
      default:
        return val;
    }
  }, "filterGroup");
  const onFilterSelect = /* @__PURE__ */ __name((val) => {
    if (val.startsWith("cf:")) {
      onCustomFieldValue(val.slice(3));
      onFilter("customfield");
    } else {
      onFilter(val);
    }
  }, "onFilterSelect");
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
  const selectedFilter = filterOptions.find((o) => o.value === currentFilterValue);
  const selectedSort = sortOptions.find((o) => o.value === sort);
  const selectedView = viewOptions.find((o) => o.value === view);
  const showManage = !!managecourseurl && !hasnocourses;
  const showCreate = !!createcourseurl && !hasnocourses;
  const showRequest = !!requestcourseurl;
  const showActions = showManage || showCreate || showRequest;
  return /* @__PURE__ */ jsxDEV("div", { className: "local-co-toolbar", children: [
    showActions && /* @__PURE__ */ jsxDEV("div", { className: "local-co-toolbar__group local-co-toolbar__group--actions", children: [
      showManage && /* @__PURE__ */ jsxDEV("a", { className: "btn btn-outline-primary btn-sm", href: managecourseurl, children: strings.managecourses }, void 0, false, {
        fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
        lineNumber: 171,
        columnNumber: 25
      }, this),
      showRequest && /* @__PURE__ */ jsxDEV("a", { className: "btn btn-primary btn-sm", href: requestcourseurl, children: strings.requestcoursebutton }, void 0, false, {
        fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
        lineNumber: 176,
        columnNumber: 25
      }, this),
      showCreate && /* @__PURE__ */ jsxDEV("a", { className: "btn btn-primary btn-sm", href: createcourseurl, children: [
        /* @__PURE__ */ jsxDEV("i", { className: "fa-solid fa-plus", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
          lineNumber: 182,
          columnNumber: 29
        }, this),
        " ",
        strings.createcourse
      ] }, void 0, true, {
        fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
        lineNumber: 181,
        columnNumber: 25
      }, this)
    ] }, void 0, true, {
      fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
      lineNumber: 169,
      columnNumber: 17
    }, this),
    showActions && showControls && /* @__PURE__ */ jsxDEV("div", { className: "local-co-toolbar__divider", "aria-hidden": "true" }, void 0, false, {
      fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
      lineNumber: 188,
      columnNumber: 17
    }, this),
    showControls && /* @__PURE__ */ jsxDEV("div", { className: "local-co-toolbar__group local-co-toolbar__group--search", children: /* @__PURE__ */ jsxDEV(SearchInput, { value: search, onChange: onSearch }, void 0, false, {
      fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
      lineNumber: 192,
      columnNumber: 17
    }, this) }, void 0, false, {
      fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
      lineNumber: 191,
      columnNumber: 13
    }, this),
    showControls && /* @__PURE__ */ jsxDEV("div", { className: "local-co-toolbar__group local-co-toolbar__group--tools", children: [
      filterOptions.length > 1 && /* @__PURE__ */ jsxDEV(
        Dropdown,
        {
          label: strings.filterresults,
          triggerAriaLabel: `${strings.filterresults}: ${selectedFilter?.label ?? ""}`,
          tooltip: strings.tooltipfilter,
          menuTitle: strings.filters,
          icon: "filter",
          options: filterOptions,
          current: currentFilterValue,
          onSelect: onFilterSelect,
          active: filter !== DEFAULT_FILTER,
          groupOf: filterGroup,
          showLabel: true
        },
        void 0,
        false,
        {
          fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
          lineNumber: 198,
          columnNumber: 21
        },
        this
      ),
      /* @__PURE__ */ jsxDEV(
        Dropdown,
        {
          label: strings.sortcourses,
          triggerAriaLabel: `${strings.sortcourses}: ${selectedSort?.label ?? ""}`,
          tooltip: strings.tooltipsort,
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
          lineNumber: 212,
          columnNumber: 17
        },
        this
      ),
      viewOptions.length > 1 && /* @__PURE__ */ jsxDEV(
        Dropdown,
        {
          label: strings.changelayout,
          triggerAriaLabel: `${strings.changelayout}: ${selectedView?.label ?? ""}`,
          tooltip: strings.tooltipview,
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
          lineNumber: 224,
          columnNumber: 21
        },
        this
      )
    ] }, void 0, true, {
      fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
      lineNumber: 196,
      columnNumber: 13
    }, this)
  ] }, void 0, true, {
    fileName: "public/blocks/myoverview/js/esm/src/components/Toolbar.tsx",
    lineNumber: 167,
    columnNumber: 9
  }, this);
}
__name(Toolbar, "Toolbar");
export {
  Toolbar as default
};
//# sourceMappingURL=Toolbar.dev.js.map
