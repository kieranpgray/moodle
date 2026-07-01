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
 * Card overflow (ellipsis) menu (MDL-88968).
 *
 * Always visible (not hover-reveal). Opens on click/tap, closes on outside
 * click or Escape, and returns focus to the trigger on close. The star/favourite
 * action is intentionally NOT here (it is the standalone StarButton, MDL-88969);
 * the menu retains Hide/Show course, which drives the "removed from view" filter.
 *
 * Keyboard: Tab closes the menu; Escape closes and returns focus to trigger.
 * Arrow keys are handled but wrap within the single item (future-proofing).
 *
 * Receives isHidden as a prop (resolved by CourseControls from the membership
 * context) so this component subscribes only to the stable callbacks context and
 * does not re-render when unrelated courses are toggled.
 *
 * @module     block_myoverview/components/EllipsisMenu
 */

import {KeyboardEvent, useEffect, useRef, useState} from "react";
import {useCourseCallbacks, useStrings} from "../state";
import Icon from "./Icon";

type EllipsisMenuProps = {
    courseId: number;
    courseName: string;
    isHidden: boolean;
};

/**
 * Render the per-card overflow menu.
 *
 * @param props The course id, name, and current hidden state.
 * @returns The ellipsis trigger and (when open) its menu.
 */
export default function EllipsisMenu({courseId, courseName, isHidden}: EllipsisMenuProps) {
    const {toggleHidden} = useCourseCallbacks();
    const strings = useStrings();
    const [open, setOpen] = useState(false);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const containerRef = useRef<HTMLDivElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);

    // Focus the first item whenever the menu opens.
    useEffect(() => {
        if (open && menuRef.current) {
            const first = menuRef.current.querySelector<HTMLElement>('[role="menuitem"]');
            first?.focus();
        }
    }, [open]);

    // Close on outside click or Escape; Escape returns focus to trigger.
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
            menuRef.current?.querySelectorAll<HTMLElement>('[role="menuitem"]') ?? []
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

    const stop = (e: {preventDefault: () => void; stopPropagation: () => void}) => {
        e.preventDefault();
        e.stopPropagation();
    };

    const actionsLabel = strings.actionsfor.replace("{$a}", courseName);

    return (
        <div className="local-co-menu" ref={containerRef}>
            <button
                type="button"
                ref={triggerRef}
                className="local-co-iconbtn"
                aria-haspopup="menu"
                aria-expanded={open}
                aria-label={actionsLabel}
                title={strings.courseactions}
                onClick={(e) => {
                    stop(e);
                    setOpen((v) => !v);
                }}
            >
                <Icon name="ellipsis-vertical" />
            </button>
            {open && (
                <div
                    className="local-co-menu__list"
                    role="menu"
                    aria-label={actionsLabel}
                    ref={menuRef}
                    onKeyDown={handleMenuKeyDown}
                >
                    <button
                        type="button"
                        role="menuitem"
                        className="local-co-menu__item"
                        onClick={(e) => {
                            stop(e);
                            toggleHidden(courseId);
                            setOpen(false);
                            triggerRef.current?.focus();
                        }}
                    >
                        <Icon name={isHidden ? "eye" : "eye-slash"} className="local-co-menu__icon" />
                        {isHidden ? strings.showcourse : strings.hidecourse}
                    </button>
                </div>
            )}
        </div>
    );
}
