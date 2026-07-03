var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
import { useStrings } from "../state";
function EmptyState({ zerostate, variant, illustrationurl }) {
  const strings = useStrings();
  const illustration = /* @__PURE__ */ jsxDEV("div", { className: "courseoverview-empty__illustration", "aria-hidden": "true", children: /* @__PURE__ */ jsxDEV("img", { src: illustrationurl, alt: "" }, void 0, false, {
    fileName: "public/blocks/myoverview/js/esm/src/components/EmptyState.tsx",
    lineNumber: 56,
    columnNumber: 13
  }, this) }, void 0, false, {
    fileName: "public/blocks/myoverview/js/esm/src/components/EmptyState.tsx",
    lineNumber: 55,
    columnNumber: 9
  }, this);
  if (zerostate) {
    return /* @__PURE__ */ jsxDEV("div", { className: "courseoverview-empty", "data-variant": "zerostate", children: [
      illustration,
      zerostate.title !== "" && // H2 keeps a valid heading order after the page's h1 (axe heading-order); the
      // Figma "H6" look is applied through the courseoverview-empty__title styles, not the tag.
      /* @__PURE__ */ jsxDEV("h2", { className: "courseoverview-empty__title", children: zerostate.title }, void 0, false, {
        fileName: "public/blocks/myoverview/js/esm/src/components/EmptyState.tsx",
        lineNumber: 67,
        columnNumber: 21
      }, this),
      zerostate.intro !== "" && /* @__PURE__ */ jsxDEV(
        "p",
        {
          className: "courseoverview-empty__text",
          dangerouslySetInnerHTML: { __html: zerostate.intro }
        },
        void 0,
        false,
        {
          fileName: "public/blocks/myoverview/js/esm/src/components/EmptyState.tsx",
          lineNumber: 70,
          columnNumber: 21
        },
        this
      ),
      zerostate.buttons.length > 0 && /* @__PURE__ */ jsxDEV("div", { className: "courseoverview-empty__actions", children: zerostate.buttons.map((button) => /* @__PURE__ */ jsxDEV(
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
          lineNumber: 78,
          columnNumber: 29
        },
        this
      )) }, void 0, false, {
        fileName: "public/blocks/myoverview/js/esm/src/components/EmptyState.tsx",
        lineNumber: 76,
        columnNumber: 21
      }, this)
    ] }, void 0, true, {
      fileName: "public/blocks/myoverview/js/esm/src/components/EmptyState.tsx",
      lineNumber: 62,
      columnNumber: 13
    }, this);
  }
  if (variant === "no-results") {
    return /* @__PURE__ */ jsxDEV("div", { className: "courseoverview-empty", "data-variant": "no-results", children: [
      illustration,
      /* @__PURE__ */ jsxDEV("h2", { className: "courseoverview-empty__title", children: strings.emptynoresultstitle }, void 0, false, {
        fileName: "public/blocks/myoverview/js/esm/src/components/EmptyState.tsx",
        lineNumber: 98,
        columnNumber: 17
      }, this),
      /* @__PURE__ */ jsxDEV("p", { className: "courseoverview-empty__text", children: strings.emptynoresults }, void 0, false, {
        fileName: "public/blocks/myoverview/js/esm/src/components/EmptyState.tsx",
        lineNumber: 99,
        columnNumber: 17
      }, this)
    ] }, void 0, true, {
      fileName: "public/blocks/myoverview/js/esm/src/components/EmptyState.tsx",
      lineNumber: 96,
      columnNumber: 13
    }, this);
  }
  const copy = {
    student: strings.emptystudent,
    educator: strings.emptyeducator,
    "no-results": strings.emptynoresults
  };
  return /* @__PURE__ */ jsxDEV("div", { className: "courseoverview-empty", "data-variant": variant ?? "student", children: [
    illustration,
    /* @__PURE__ */ jsxDEV("p", { className: "courseoverview-empty__text", children: copy[variant ?? "student"] }, void 0, false, {
      fileName: "public/blocks/myoverview/js/esm/src/components/EmptyState.tsx",
      lineNumber: 112,
      columnNumber: 13
    }, this)
  ] }, void 0, true, {
    fileName: "public/blocks/myoverview/js/esm/src/components/EmptyState.tsx",
    lineNumber: 110,
    columnNumber: 9
  }, this);
}
__name(EmptyState, "EmptyState");
export {
  EmptyState as default
};
//# sourceMappingURL=EmptyState.dev.js.map
