var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Shared keyboard/pointer behaviour for a pop-up menu with a trigger button.
 *
 * Encapsulates the open state, the container/trigger/menu refs, focusing the
 * first item on open, closing on outside click or Escape (Escape returns focus
 * to the trigger), and roving focus across items via arrow/Home/End keys with
 * Tab closing the menu. Used by both the toolbar Dropdown (menuitemradio items)
 * and the card overflow menu (menuitem items).
 *
 * @module     block_myoverview/hooks/useDismissableMenu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {
  useEffect,
  useRef,
  useState
} from "react";
function useDismissableMenu(itemRole) {
  const [open, setOpen] = useState(false);
  const containerReference = useRef(null);
  const triggerReference = useRef(null);
  const menuReference = useRef(null);
  const itemSelector = `[role="${itemRole}"]`;
  useEffect(() => {
    if (open && menuReference.current) {
      menuReference.current.querySelector(itemSelector)?.focus();
    }
  }, [open, itemSelector]);
  useEffect(() => {
    if (!open) {
      return void 0;
    }
    const onDocumentClick = /* @__PURE__ */ __name((event) => {
      if (containerReference.current && !containerReference.current.contains(event.target)) {
        setOpen(false);
      }
    }, "onDocumentClick");
    const onKey = /* @__PURE__ */ __name((event) => {
      if (event.key !== "Escape") {
        return;
      }
      setOpen(false);
      triggerReference.current?.focus();
    }, "onKey");
    document.addEventListener("click", onDocumentClick, { capture: true });
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("click", onDocumentClick, true);
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);
  const handleMenuKeyDown = /* @__PURE__ */ __name((event) => {
    const items = [...menuReference.current?.querySelectorAll(itemSelector) ?? []];
    const index = items.indexOf(document.activeElement);
    switch (event.key) {
      case "ArrowDown": {
        event.preventDefault();
        items[(index + 1) % items.length]?.focus();
        break;
      }
      case "ArrowUp": {
        event.preventDefault();
        items[(index - 1 + items.length) % items.length]?.focus();
        break;
      }
      case "Home": {
        event.preventDefault();
        items[0]?.focus();
        break;
      }
      case "End": {
        event.preventDefault();
        items[items.length - 1]?.focus();
        break;
      }
      case "Tab": {
        setOpen(false);
        break;
      }
      default: {
        break;
      }
    }
  }, "handleMenuKeyDown");
  return {
    open,
    setOpen,
    containerRef: containerReference,
    triggerRef: triggerReference,
    menuRef: menuReference,
    handleMenuKeyDown
  };
}
__name(useDismissableMenu, "useDismissableMenu");
export {
  useDismissableMenu
};
//# sourceMappingURL=useDismissableMenu.dev.js.map
