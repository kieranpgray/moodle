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
 * Generic single-select toolbar dropdown (used for filter, sort and layout).
 *
 * Shows a tooltip (title) and an active-state class when the current value is
 * non-default (MDL-88972). Opens on click, closes on outside click or Escape,
 * and returns focus to the trigger. Options are mutually exclusive.
 *
 * Keyboard: ArrowDown/ArrowUp move focus between items; Home/End jump to
 * first/last; Escape closes and returns focus to trigger; Tab closes.
 *
 * @module     block_myoverview/components/Dropdown
 */

import {KeyboardEvent, useEffect, useRef, useState} from "react";
import Icon from "./Icon";

export type DropdownOption<T extends string> = {
    value: T;
    label: string;
    icon?: string;
};


type DropdownProps<T extends string> = {
    /** Trigger tooltip / accessible name for the menu. */
    label: string;
    /** Override for the trigger button's aria-label (e.g. include current selection). */
    triggerAriaLabel?: string;
    /** Leading icon on the trigger. */
    icon: string;
    options: DropdownOption<T>[];
    current: T;
    onSelect: (value: T) => void;
    /** Highlight the trigger as active (current value differs from default). */
    active: boolean;
    /** When true, show the selected option's label on the trigger (filter). */
    showLabel?: boolean;
    /** Optional heading shown at the top of the open menu (Figma group label). */
    menuTitle?: string;
};


/**
 * Render a labelled or icon-only single-select dropdown.
 *
 * @param props Dropdown configuration.
 * @returns The dropdown element.
 */
export default function Dropdown<T extends string>({
    label, triggerAriaLabel, icon, options, current, onSelect, active, showLabel = false, menuTitle,
}: DropdownProps<T>) {
    const [open, setOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);
    const selected = options.find((o) => o.value === current);

    // Focus the first item whenever the menu opens.
    useEffect(() => {
        if (open && menuRef.current) {
            const first = menuRef.current.querySelector<HTMLElement>('[role="menuitemradio"]');
            first?.focus();
        }
    }, [open]);

    // Close on outside click or Escape; Escape also returns focus to trigger.
    useEffect(() => {
        if (!open) {
            return undefined;
        }
        const onDocClick = (e: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
                setOpen(false);
            }
        };
        const onKey = (e: globalThis.KeyboardEvent) => {
            if (e.key === "Escape") {
                setOpen(false);
                triggerRef.current?.focus();
            }
        };
        document.addEventListener("click", onDocClick);
        document.addEventListener("keydown", onKey);
        return () => {
            document.removeEventListener("click", onDocClick);
            document.removeEventListener("keydown", onKey);
        };
    }, [open]);

    const handleMenuKeyDown = (e: KeyboardEvent<HTMLDivElement>) => {
        const items = Array.from(
            menuRef.current?.querySelectorAll<HTMLElement>('[role="menuitemradio"]') ?? []
        );
        const idx = items.indexOf(document.activeElement as HTMLElement);
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
    };

    return (
        <div className="local-co-dropdown" ref={containerRef}>
            <button
                type="button"
                ref={triggerRef}
                className={`local-co-toolbtn${active ? " is-active" : ""}${showLabel ? " local-co-toolbtn--labelled" : ""}`}
                aria-haspopup="menu"
                aria-expanded={open}
                aria-label={triggerAriaLabel ?? label}
                data-tooltip={label}
                onClick={() => setOpen((v) => !v)}
            >
                <Icon name={icon} />
                {showLabel && <span className="local-co-toolbtn__label">{selected?.label ?? label}</span>}
                {showLabel && <Icon name="chevron-down" className="local-co-toolbtn__caret" />}
            </button>
            {open && (
                <div
                    className="local-co-menu__list"
                    role="menu"
                    aria-label={label}
                    ref={menuRef}
                    onKeyDown={handleMenuKeyDown}
                >
                    {menuTitle && (
                        <div className="local-co-menu__group-label" aria-hidden="true">{menuTitle}</div>
                    )}
                    <div role="group" aria-label={menuTitle ?? label}>
                        {options.map((opt) => (
                            <button
                                key={opt.value}
                                type="button"
                                role="menuitemradio"
                                aria-checked={opt.value === current}
                                className={`local-co-menu__item${opt.value === current ? " is-selected" : ""}`}
                                onClick={() => {
                                    onSelect(opt.value);
                                    setOpen(false);
                                    triggerRef.current?.focus();
                                }}
                            >
                                {opt.icon && <Icon name={opt.icon} className="local-co-menu__icon" />}
                                {opt.label}
                                {opt.value === current && <Icon name="check" className="local-co-menu__check" />}
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
