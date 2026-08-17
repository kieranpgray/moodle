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

    // The current section is the last one whose heading has passed a line near the top
    // of the viewport. Deliberately not an IntersectionObserver watching a narrow band:
    // a band can only mark a section that can be scrolled into it, and the final
    // sections of a form sit against the end of the document, so they would never
    // qualify however far the page is scrolled.
    const ACTIVE_LINE = 160;

    const syncActive = () => {
        let current = sections[0];
        sections.forEach((section) => {
            if (section.getBoundingClientRect().top <= ACTIVE_LINE) {
                current = section;
            }
        });
        setActive(current.id);
    };

    // Scroll fires far more often than the rail needs updating, so the work is deferred
    // to the next frame and coalesced.
    let pending = false;
    const onScroll = () => {
        if (pending) {
            return;
        }
        pending = true;
        window.requestAnimationFrame(() => {
            pending = false;
            syncActive();
        });
    };

    window.addEventListener('scroll', onScroll, {passive: true});
    window.addEventListener('resize', onScroll, {passive: true});

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

    // Not simply the first section: the page may open part way down, either from a link
    // carrying a fragment or from the browser restoring a previous scroll position.
    syncActive();
};
