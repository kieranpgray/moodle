var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
import { CloseButton } from "@moodlehq/design-system";
import { useStrings } from "../state";
import Icon from "./Icon";
function SearchInput({ value, onChange }) {
  const strings = useStrings();
  return /* @__PURE__ */ jsxDEV("div", { className: "local-co-search", children: [
    /* @__PURE__ */ jsxDEV(Icon, { name: "magnifying-glass", className: "local-co-search__icon" }, void 0, false, {
      fileName: "public/blocks/myoverview/js/esm/src/components/SearchInput.tsx",
      lineNumber: 45,
      columnNumber: 13
    }, this),
    /* @__PURE__ */ jsxDEV(
      "input",
      {
        type: "text",
        className: "local-co-search__input",
        placeholder: strings.search,
        "aria-label": strings.searchcourses,
        value,
        onChange: (e) => onChange(e.target.value)
      },
      void 0,
      false,
      {
        fileName: "public/blocks/myoverview/js/esm/src/components/SearchInput.tsx",
        lineNumber: 46,
        columnNumber: 13
      },
      this
    ),
    value !== "" && /* @__PURE__ */ jsxDEV(
      CloseButton,
      {
        "aria-label": strings.clearsearch,
        size: "sm",
        className: "local-co-search__clear",
        onClick: () => onChange("")
      },
      void 0,
      false,
      {
        fileName: "public/blocks/myoverview/js/esm/src/components/SearchInput.tsx",
        lineNumber: 55,
        columnNumber: 17
      },
      this
    )
  ] }, void 0, true, {
    fileName: "public/blocks/myoverview/js/esm/src/components/SearchInput.tsx",
    lineNumber: 44,
    columnNumber: 9
  }, this);
}
__name(SearchInput, "SearchInput");
export {
  SearchInput as default
};
//# sourceMappingURL=SearchInput.dev.js.map
