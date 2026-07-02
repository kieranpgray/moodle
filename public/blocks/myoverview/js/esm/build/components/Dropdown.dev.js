var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
import { useEffect, useRef, useState } from "react";
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
  groupOf
}) {
  const [open, setOpen] = useState(false);
  const containerRef = useRef(null);
  const triggerRef = useRef(null);
  const menuRef = useRef(null);
  const selected = options.find((o) => o.value === current);
  useEffect(() => {
    if (open && menuRef.current) {
      const first = menuRef.current.querySelector('[role="menuitemradio"]');
      first?.focus();
    }
  }, [open]);
  useEffect(() => {
    if (!open) {
      return void 0;
    }
    const onDocClick = /* @__PURE__ */ __name((e) => {
      if (containerRef.current && !containerRef.current.contains(e.target)) {
        setOpen(false);
      }
    }, "onDocClick");
    const onKey = /* @__PURE__ */ __name((e) => {
      if (e.key === "Escape") {
        setOpen(false);
        triggerRef.current?.focus();
      }
    }, "onKey");
    document.addEventListener("click", onDocClick);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("click", onDocClick);
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);
  const handleMenuKeyDown = /* @__PURE__ */ __name((e) => {
    const items = Array.from(
      menuRef.current?.querySelectorAll('[role="menuitemradio"]') ?? []
    );
    const idx = items.indexOf(document.activeElement);
    switch (e.key) {
      case "ArrowDown":
        e.preventDefault();
        items[(idx + 1) % items.length]?.focus();
        break;
      case "ArrowUp":
        e.preventDefault();
        items[(idx - 1 + items.length) % items.length]?.focus();
        break;
      case "Home":
        e.preventDefault();
        items[0]?.focus();
        break;
      case "End":
        e.preventDefault();
        items[items.length - 1]?.focus();
        break;
      case "Tab":
        setOpen(false);
        break;
    }
  }, "handleMenuKeyDown");
  return /* @__PURE__ */ jsxDEV("div", { className: "local-co-dropdown", ref: containerRef, children: [
    /* @__PURE__ */ jsxDEV(
      "button",
      {
        type: "button",
        ref: triggerRef,
        className: `local-co-toolbtn${active ? " is-active" : ""}${showLabel ? " local-co-toolbtn--labelled" : ""}`,
        "aria-haspopup": "menu",
        "aria-expanded": open,
        "aria-label": triggerAriaLabel ?? label,
        "data-tooltip": tooltip ?? label,
        onClick: () => setOpen((v) => !v),
        children: [
          /* @__PURE__ */ jsxDEV(Icon, { name: icon }, void 0, false, {
            fileName: "public/blocks/myoverview/js/esm/src/components/Dropdown.tsx",
            lineNumber: 153,
            columnNumber: 17
          }, this),
          showLabel && /* @__PURE__ */ jsxDEV("span", { className: "local-co-toolbtn__label", children: selected?.label ?? label }, void 0, false, {
            fileName: "public/blocks/myoverview/js/esm/src/components/Dropdown.tsx",
            lineNumber: 154,
            columnNumber: 31
          }, this),
          showLabel && /* @__PURE__ */ jsxDEV(Icon, { name: "chevron-down", className: "local-co-toolbtn__caret" }, void 0, false, {
            fileName: "public/blocks/myoverview/js/esm/src/components/Dropdown.tsx",
            lineNumber: 155,
            columnNumber: 31
          }, this)
        ]
      },
      void 0,
      true,
      {
        fileName: "public/blocks/myoverview/js/esm/src/components/Dropdown.tsx",
        lineNumber: 143,
        columnNumber: 13
      },
      this
    ),
    open && /* @__PURE__ */ jsxDEV(
      "div",
      {
        className: "local-co-menu__list",
        role: "menu",
        "aria-label": label,
        ref: menuRef,
        onKeyDown: handleMenuKeyDown,
        children: [
          menuTitle && /* @__PURE__ */ jsxDEV("div", { className: "local-co-menu__group-label", "aria-hidden": "true", children: menuTitle }, void 0, false, {
            fileName: "public/blocks/myoverview/js/esm/src/components/Dropdown.tsx",
            lineNumber: 166,
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
                className: `local-co-menu__item${opt.value === current ? " is-selected" : ""}${groupEnd ? " local-co-menu__item--group-end" : ""}`,
                onClick: () => {
                  onSelect(opt.value);
                  setOpen(false);
                  triggerRef.current?.focus();
                },
                children: [
                  opt.icon && /* @__PURE__ */ jsxDEV(Icon, { name: opt.icon, className: "local-co-menu__icon" }, void 0, false, {
                    fileName: "public/blocks/myoverview/js/esm/src/components/Dropdown.tsx",
                    lineNumber: 186,
                    columnNumber: 46
                  }, this),
                  opt.label,
                  opt.value === current && /* @__PURE__ */ jsxDEV(Icon, { name: "check", className: "local-co-menu__check" }, void 0, false, {
                    fileName: "public/blocks/myoverview/js/esm/src/components/Dropdown.tsx",
                    lineNumber: 188,
                    columnNumber: 59
                  }, this)
                ]
              },
              opt.value,
              true,
              {
                fileName: "public/blocks/myoverview/js/esm/src/components/Dropdown.tsx",
                lineNumber: 173,
                columnNumber: 29
              },
              this
            );
          }) }, void 0, false, {
            fileName: "public/blocks/myoverview/js/esm/src/components/Dropdown.tsx",
            lineNumber: 168,
            columnNumber: 21
          }, this)
        ]
      },
      void 0,
      true,
      {
        fileName: "public/blocks/myoverview/js/esm/src/components/Dropdown.tsx",
        lineNumber: 158,
        columnNumber: 17
      },
      this
    )
  ] }, void 0, true, {
    fileName: "public/blocks/myoverview/js/esm/src/components/Dropdown.tsx",
    lineNumber: 142,
    columnNumber: 9
  }, this);
}
__name(Dropdown, "Dropdown");
export {
  Dropdown as default
};
//# sourceMappingURL=Dropdown.dev.js.map
