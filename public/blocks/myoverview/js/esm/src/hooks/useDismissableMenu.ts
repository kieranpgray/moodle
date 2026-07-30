// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

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
    type KeyboardEvent,
    useEffect,
    useRef,
    useState,
} from 'react';

/**
 * Provide dismissable-menu state and handlers for a given item role.
 *
 * @param itemRole The ARIA role of the focusable menu items, e.g. "menuitem" or "menuitemradio".
 * @returns Open state, refs to wire onto the container/trigger/menu, and the menu keydown handler.
 */
export function useDismissableMenu(itemRole: 'menuitem' | 'menuitemradio') {
    const [open, setOpen] = useState(false);
    const containerReference = useRef<HTMLDivElement>(null);
    const triggerReference = useRef<HTMLButtonElement>(null);
    const menuReference = useRef<HTMLDivElement>(null);
    const itemSelector = `[role="${itemRole}"]`;

    // Focus the first item whenever the menu opens.
    useEffect(() => {
        if (open && menuReference.current) {
            menuReference.current.querySelector<HTMLElement>(itemSelector)?.focus();
        }
    }, [open, itemSelector]);

    // Close on outside click or Escape; Escape also returns focus to the trigger.
    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const onDocumentClick = (event: MouseEvent) => {
            if (containerReference.current && !containerReference.current.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        const onKey = (event: globalThis.KeyboardEvent) => {
            if (event.key !== 'Escape') {
                return;
            }

            setOpen(false);
            triggerReference.current?.focus();
        };

        // Capture phase: the card controls stop propagation (so clicks don't trigger the card's
        // stretched link), which would swallow bubble-phase clicks and leave this menu open when
        // another card's menu is opened. Capture listeners run before any target handler can
        // stop the event, so exactly one menu can be open at a time.
        document.addEventListener('click', onDocumentClick, {capture: true});
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('click', onDocumentClick, true);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    const handleMenuKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        const items = [...menuReference.current?.querySelectorAll<HTMLElement>(itemSelector) ?? []];
        const index = items.indexOf(document.activeElement as HTMLElement);
        switch (event.key) {
            case 'ArrowDown': {
                event.preventDefault();
                items[(index + 1) % items.length]?.focus();
                break;
            }

            case 'ArrowUp': {
                event.preventDefault();
                items[(index - 1 + items.length) % items.length]?.focus();
                break;
            }

            case 'Home': {
                event.preventDefault();
                items[0]?.focus();
                break;
            }

            case 'End': {
                event.preventDefault();
                items[items.length - 1]?.focus();
                break;
            }

            case 'Tab': {
                setOpen(false);
                break;
            }

            default: {
                // Any other key keeps its native behaviour.
                break;
            }
        }
    };

    return {
        open, setOpen, containerRef: containerReference, triggerRef: triggerReference, menuRef: menuReference, handleMenuKeyDown,
    };
}
