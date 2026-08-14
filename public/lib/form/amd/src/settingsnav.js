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
 * Settings form section navigation.
 *
 * Keeps the settings rail in sync with the section currently in view, and moves
 * focus to a section when its link is activated.
 *
 * @module     core_form/settingsnav
 * @copyright  2026 Moodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    nav: '[data-region="form-settings-nav"]',
    link: '[data-settingsnav-target]',
};

const ACTIVE_CLASS = 'active';

/**
 * Initialise the settings section navigation.
 */
export const init = () => {
    const nav = document.querySelector(SELECTORS.nav);
    if (!nav) {
        return;
    }

    const links = Array.from(nav.querySelectorAll(SELECTORS.link));
    // Sections in document order, so the topmost visible one can be found by search order.
    const sections = links
        .map((link) => document.getElementById(link.dataset.settingsnavTarget))
        .filter((section) => section !== null);

    if (!sections.length) {
        return;
    }

    const setActive = (id) => {
        links.forEach((link) => {
            const isActive = link.dataset.settingsnavTarget === id;
            link.classList.toggle(ACTIVE_CLASS, isActive);
            if (isActive) {
                // "location" rather than "true": this marks the section currently in
                // view within the page, not the current page in a set of pages.
                link.setAttribute('aria-current', 'location');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    };

    // Track which sections are in view. The observer only reports elements whose
    // state changed, so the running set is kept here rather than recomputed.
    const visible = new Set();
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                visible.add(entry.target.id);
            } else {
                visible.delete(entry.target.id);
            }
        });

        const current = sections.find((section) => visible.has(section.id));
        if (current) {
            setActive(current.id);
        }
    }, {
        // Bias towards the section occupying the upper part of the viewport.
        rootMargin: '-15% 0px -75% 0px',
    });

    sections.forEach((section) => observer.observe(section));

    nav.addEventListener('click', (event) => {
        const link = event.target.closest(SELECTORS.link);
        if (!link) {
            return;
        }

        const target = document.getElementById(link.dataset.settingsnavTarget);
        if (!target) {
            return;
        }

        event.preventDefault();
        target.scrollIntoView({behavior: 'smooth', block: 'start'});
        setActive(target.id);

        // Move focus to the section so keyboard and screen reader users follow the jump.
        target.setAttribute('tabindex', '-1');
        target.focus({preventScroll: true});

        // Keep the address bar in step without stacking history entries.
        window.history.replaceState(null, '', `#${target.id}`);
    });

    setActive(sections[0].id);
};
