var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
import { useStrings } from "../state";
import Icon from "./Icon";
function EmptyState({ zerostate, variant }) {
  const strings = useStrings();
  if (zerostate) {
    return /* @__PURE__ */ jsxDEV("div", { className: "local-co-empty", "data-variant": "zerostate", children: [
      /* @__PURE__ */ jsxDEV("div", { className: "local-co-empty__illustration", "aria-hidden": "true", children: /* @__PURE__ */ jsxDEV(Icon, { name: "book-open" }, void 0, false, {
        fileName: "public/blocks/myoverview/js/esm/src/components/EmptyState.tsx",
        lineNumber: 54,
        columnNumber: 21
      }, this) }, void 0, false, {
        fileName: "public/blocks/myoverview/js/esm/src/components/EmptyState.tsx",
        lineNumber: 53,
        columnNumber: 17
      }, this),
      zerostate.title !== "" && // H2 keeps a valid heading order after the page's h1 (axe heading-order); the
      // Figma "H6" look is applied through the local-co-empty__title styles, not the tag.
      /* @__PURE__ */ jsxDEV("h2", { className: "local-co-empty__title", children: zerostate.title }, void 0, false, {
        fileName: "public/blocks/myoverview/js/esm/src/components/EmptyState.tsx",
        lineNumber: 59,
        columnNumber: 21
      }, this),
      zerostate.intro !== "" && /* @__PURE__ */ jsxDEV(
        "p",
        {
          className: "local-co-empty__text",
          dangerouslySetInnerHTML: { __html: zerostate.intro }
        },
        void 0,
        false,
        {
          fileName: "public/blocks/myoverview/js/esm/src/components/EmptyState.tsx",
          lineNumber: 62,
          columnNumber: 21
        },
        this
      ),
      zerostate.buttons.length > 0 && /* @__PURE__ */ jsxDEV("div", { className: "local-co-empty__actions", children: zerostate.buttons.map((button) => /* @__PURE__ */ jsxDEV(
        "a",
        {
          className: `btn ${button.primary ? "btn-primary" : "btn-outline-primary"}`,
          href: button.url,
          children: button.label
        },
        button.url,
        false,
        {
          fileName: "public/blocks/myoverview/js/esm/src/components/EmptyState.tsx",
          lineNumber: 70,
          columnNumber: 29
        },
        this
      )) }, void 0, false, {
        fileName: "public/blocks/myoverview/js/esm/src/components/EmptyState.tsx",
        lineNumber: 68,
        columnNumber: 21
      }, this)
    ] }, void 0, true, {
      fileName: "public/blocks/myoverview/js/esm/src/components/EmptyState.tsx",
      lineNumber: 52,
      columnNumber: 13
    }, this);
  }
  const copy = {
    student: strings.emptystudent,
    educator: strings.emptyeducator,
    "no-results": strings.emptynoresults
  };
  return /* @__PURE__ */ jsxDEV("div", { className: "local-co-empty", "data-variant": variant ?? "student", children: [
    /* @__PURE__ */ jsxDEV("div", { className: "local-co-empty__illustration", "aria-hidden": "true", children: /* @__PURE__ */ jsxDEV(Icon, { name: "book-open" }, void 0, false, {
      fileName: "public/blocks/myoverview/js/esm/src/components/EmptyState.tsx",
      lineNumber: 92,
      columnNumber: 17
    }, this) }, void 0, false, {
      fileName: "public/blocks/myoverview/js/esm/src/components/EmptyState.tsx",
      lineNumber: 91,
      columnNumber: 13
    }, this),
    /* @__PURE__ */ jsxDEV("p", { className: "local-co-empty__text", children: copy[variant ?? "student"] }, void 0, false, {
      fileName: "public/blocks/myoverview/js/esm/src/components/EmptyState.tsx",
      lineNumber: 94,
      columnNumber: 13
    }, this)
  ] }, void 0, true, {
    fileName: "public/blocks/myoverview/js/esm/src/components/EmptyState.tsx",
    lineNumber: 90,
    columnNumber: 9
  }, this);
}
__name(EmptyState, "EmptyState");
export {
  EmptyState as default
};
//# sourceMappingURL=EmptyState.dev.js.map
