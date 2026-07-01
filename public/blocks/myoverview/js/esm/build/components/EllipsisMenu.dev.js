var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
import { useEffect, useRef, useState } from "react";
import { useCourseCallbacks, useStrings } from "../state";
import Icon from "./Icon";
function EllipsisMenu({ courseId, courseName, isHidden }) {
  const { toggleHidden } = useCourseCallbacks();
  const strings = useStrings();
  const [open, setOpen] = useState(false);
  const triggerRef = useRef(null);
  const containerRef = useRef(null);
  const menuRef = useRef(null);
  useEffect(() => {
    if (open && menuRef.current) {
      const first = menuRef.current.querySelector('[role="menuitem"]');
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
      menuRef.current?.querySelectorAll('[role="menuitem"]') ?? []
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
  const stop = /* @__PURE__ */ __name((e) => {
    e.preventDefault();
    e.stopPropagation();
  }, "stop");
  const actionsLabel = strings.actionsfor.replace("{$a}", courseName);
  return /* @__PURE__ */ jsxDEV("div", { className: "local-co-menu", ref: containerRef, children: [
    /* @__PURE__ */ jsxDEV(
      "button",
      {
        type: "button",
        ref: triggerRef,
        className: "local-co-iconbtn",
        "aria-haspopup": "menu",
        "aria-expanded": open,
        "aria-label": actionsLabel,
        title: strings.courseactions,
        onClick: (e) => {
          stop(e);
          setOpen((v) => !v);
        },
        children: /* @__PURE__ */ jsxDEV(Icon, { name: "ellipsis-vertical" }, void 0, false, {
          fileName: "public/blocks/myoverview/js/esm/src/components/EllipsisMenu.tsx",
          lineNumber: 140,
          columnNumber: 17
        }, this)
      },
      void 0,
      false,
      {
        fileName: "public/blocks/myoverview/js/esm/src/components/EllipsisMenu.tsx",
        lineNumber: 127,
        columnNumber: 13
      },
      this
    ),
    open && /* @__PURE__ */ jsxDEV(
      "div",
      {
        className: "local-co-menu__list",
        role: "menu",
        "aria-label": actionsLabel,
        ref: menuRef,
        onKeyDown: handleMenuKeyDown,
        children: /* @__PURE__ */ jsxDEV(
          "button",
          {
            type: "button",
            role: "menuitem",
            className: "local-co-menu__item",
            onClick: (e) => {
              stop(e);
              toggleHidden(courseId);
              setOpen(false);
              triggerRef.current?.focus();
            },
            children: [
              /* @__PURE__ */ jsxDEV(Icon, { name: isHidden ? "eye" : "eye-slash", className: "local-co-menu__icon" }, void 0, false, {
                fileName: "public/blocks/myoverview/js/esm/src/components/EllipsisMenu.tsx",
                lineNumber: 161,
                columnNumber: 25
              }, this),
              isHidden ? strings.showcourse : strings.hidecourse
            ]
          },
          void 0,
          true,
          {
            fileName: "public/blocks/myoverview/js/esm/src/components/EllipsisMenu.tsx",
            lineNumber: 150,
            columnNumber: 21
          },
          this
        )
      },
      void 0,
      false,
      {
        fileName: "public/blocks/myoverview/js/esm/src/components/EllipsisMenu.tsx",
        lineNumber: 143,
        columnNumber: 17
      },
      this
    )
  ] }, void 0, true, {
    fileName: "public/blocks/myoverview/js/esm/src/components/EllipsisMenu.tsx",
    lineNumber: 126,
    columnNumber: 9
  }, this);
}
__name(EllipsisMenu, "EllipsisMenu");
export {
  EllipsisMenu as default
};
//# sourceMappingURL=EllipsisMenu.dev.js.map
