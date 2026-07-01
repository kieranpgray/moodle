var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
import { Pagination as DSPagination } from "@moodlehq/design-system";
import { useStrings } from "../state";
function Pagination({ page, pageCount, onPage }) {
  const strings = useStrings();
  return /* @__PURE__ */ jsxDEV("div", { className: "local-co-pagination", children: /* @__PURE__ */ jsxDEV(
    DSPagination,
    {
      totalPages: pageCount,
      currentPage: page,
      onPageChange: onPage,
      ariaLabel: strings.courseoverview
    },
    void 0,
    false,
    {
      fileName: "public/blocks/myoverview/js/esm/src/components/Pagination.tsx",
      lineNumber: 42,
      columnNumber: 13
    },
    this
  ) }, void 0, false, {
    fileName: "public/blocks/myoverview/js/esm/src/components/Pagination.tsx",
    lineNumber: 41,
    columnNumber: 9
  }, this);
}
__name(Pagination, "Pagination");
export {
  Pagination as default
};
//# sourceMappingURL=Pagination.dev.js.map
