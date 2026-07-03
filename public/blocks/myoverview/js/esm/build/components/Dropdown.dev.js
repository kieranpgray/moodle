var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
import { useDismissableMenu } from "../hooks/useDismissableMenu";
import Icon from "./Icon";
function Dropdown({
  label,
  triggerAriaLabel,
  icon,
  options,
  current,
  onSelect,
  active,
  showLabel = false,
  menuTitle,
  tooltip,
  groupOf,
  align = "end"
}) {
  const { open, setOpen, containerRef, triggerRef, menuRef, handleMenuKeyDown } = useDismissableMenu("menuitemradio");
  const selected = options.find((o) => o.value === current);
  return /* @__PURE__ */ jsxDEV("div", { className: "courseoverview-dropdown", ref: containerRef, children: [
    /* @__PURE__ */ jsxDEV(
      "button",
      {
        type: "button",
        ref: triggerRef,
        className: `courseoverview-toolbtn${active ? " is-active" : ""}${showLabel ? " courseoverview-toolbtn--labelled" : ""}`,
        "aria-haspopup": "menu",
        "aria-expanded": open,
        "aria-label": triggerAriaLabel ?? label,
        "data-tooltip": tooltip ?? label,
        onClick: () => setOpen((v) => !v),
        children: [
          /* @__PURE__ */ jsxDEV(Icon, { name: icon }, void 0, false, {
            fileName: "public/blocks/myoverview/js/esm/src/components/Dropdown.tsx",
            lineNumber: 98,
            columnNumber: 17
          }, this),
          showLabel && /* @__PURE__ */ jsxDEV("span", { className: "courseoverview-toolbtn__label", children: selected?.label ?? label }, void 0, false, {
            fileName: "public/blocks/myoverview/js/esm/src/components/Dropdown.tsx",
            lineNumber: 99,
            columnNumber: 31
          }, this),
          showLabel && /* @__PURE__ */ jsxDEV(Icon, { name: "chevron-down", className: "courseoverview-toolbtn__caret" }, void 0, false, {
            fileName: "public/blocks/myoverview/js/esm/src/components/Dropdown.tsx",
            lineNumber: 100,
            columnNumber: 31
          }, this)
        ]
      },
      void 0,
      true,
      {
        fileName: "public/blocks/myoverview/js/esm/src/components/Dropdown.tsx",
        lineNumber: 87,
        columnNumber: 13
      },
      this
    ),
    open && /* @__PURE__ */ jsxDEV(
      "div",
      {
        className: `courseoverview-menu__list${align === "start" ? " courseoverview-menu__list--start" : ""}`,
        role: "menu",
        "aria-label": label,
        ref: menuRef,
        onKeyDown: handleMenuKeyDown,
        children: [
          menuTitle && /* @__PURE__ */ jsxDEV("div", { className: "courseoverview-menu__group-label", "aria-hidden": "true", children: menuTitle }, void 0, false, {
            fileName: "public/blocks/myoverview/js/esm/src/components/Dropdown.tsx",
            lineNumber: 111,
            columnNumber: 25
          }, this),
          /* @__PURE__ */ jsxDEV("div", { role: "group", "aria-label": menuTitle ?? label, children: options.map((opt, i) => {
            const groupEnd = !!groupOf && i < options.length - 1 && groupOf(opt.value) !== groupOf(options[i + 1].value);
            return /* @__PURE__ */ jsxDEV(
              "button",
              {
                type: "button",
                role: "menuitemradio",
                "aria-checked": opt.value === current,
                className: `courseoverview-menu__item${opt.value === current ? " is-selected" : ""}${groupEnd ? " courseoverview-menu__item--group-end" : ""}`,
                onClick: () => {
                  onSelect(opt.value);
                  setOpen(false);
                  triggerRef.current?.focus();
                },
                children: [
                  opt.icon && /* @__PURE__ */ jsxDEV(Icon, { name: opt.icon, className: "courseoverview-menu__icon" }, void 0, false, {
                    fileName: "public/blocks/myoverview/js/esm/src/components/Dropdown.tsx",
                    lineNumber: 131,
                    columnNumber: 46
                  }, this),
                  opt.label,
                  opt.value === current && /* @__PURE__ */ jsxDEV(Icon, { name: "check", className: "courseoverview-menu__check" }, void 0, false, {
                    fileName: "public/blocks/myoverview/js/esm/src/components/Dropdown.tsx",
                    lineNumber: 133,
                    columnNumber: 59
                  }, this)
                ]
              },
              opt.value,
              true,
              {
                fileName: "public/blocks/myoverview/js/esm/src/components/Dropdown.tsx",
                lineNumber: 118,
                columnNumber: 29
              },
              this
            );
          }) }, void 0, false, {
            fileName: "public/blocks/myoverview/js/esm/src/components/Dropdown.tsx",
            lineNumber: 113,
            columnNumber: 21
          }, this)
        ]
      },
      void 0,
      true,
      {
        fileName: "public/blocks/myoverview/js/esm/src/components/Dropdown.tsx",
        lineNumber: 103,
        columnNumber: 17
      },
      this
    )
  ] }, void 0, true, {
    fileName: "public/blocks/myoverview/js/esm/src/components/Dropdown.tsx",
    lineNumber: 86,
    columnNumber: 9
  }, this);
}
__name(Dropdown, "Dropdown");
export {
  Dropdown as default
};
//# sourceMappingURL=Dropdown.dev.js.map
