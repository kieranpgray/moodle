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
 * Empty and no-results states (MDL-88974, MDL-88975, MDL-88979).
 *
 * When a server-built `zerostate` is supplied, renders the rich variant: an
 * illustration, an H6 title, a small intro paragraph, and any contextual action
 * links (Create / Manage course). The intro is server-rendered lang-string HTML
 * (never user input) so it is injected via dangerouslySetInnerHTML. When no
 * zero-state is supplied it falls back to a simple single-message variant.
 *
 * @module     block_myoverview/components/EmptyState
 */

import {ZeroStateData} from "../types";
import {useStrings} from "../state";

type EmptyVariant = "student" | "educator" | "no-results";


type EmptyStateProps = {
    zerostate?: ZeroStateData;
    variant?: EmptyVariant;
    /** URL of the shared empty-state illustration (block_myoverview/pix/courses.svg). */
    illustrationurl: string;
};


/**
 * Render the empty / no-results state.
 *
 * All states share the same decorative illustration; it carries no meaning (the
 * title and text do), so it is exposed to assistive tech as empty (alt="").
 *
 * @param props The rich zero-state data, or a simple variant fallback.
 * @returns The empty-state element.
 */
export default function EmptyState({zerostate, variant, illustrationurl}: EmptyStateProps) {
    const strings = useStrings();

    const illustration = (
        <div className="local-co-empty__illustration" aria-hidden="true">
            <img src={illustrationurl} alt="" />
        </div>
    );

    if (zerostate) {
        return (
            <div className="local-co-empty" data-variant="zerostate">
                {illustration}
                {zerostate.title !== "" && (
                    // H2 keeps a valid heading order after the page's h1 (axe heading-order); the
                    // Figma "H6" look is applied through the local-co-empty__title styles, not the tag.
                    <h2 className="local-co-empty__title">{zerostate.title}</h2>
                )}
                {zerostate.intro !== "" && (
                    <p
                        className="local-co-empty__text"
                        dangerouslySetInnerHTML={{__html: zerostate.intro}}
                    />
                )}
                {zerostate.buttons.length > 0 && (
                    <div className="local-co-empty__actions">
                        {zerostate.buttons.map((button) => (
                            <a
                                key={button.url}
                                className={`btn ${button.primary ? "btn-primary" : "btn-outline-primary"}`}
                                href={button.url}
                            >
                                {button.label}
                            </a>
                        ))}
                    </div>
                )}
            </div>
        );
    }

    // No-results is a rich state (title + text) shown when a search or filter matches
    // nothing, distinct from the genuine "not enrolled" zero-state (MDL-88974).
    if (variant === "no-results") {
        return (
            <div className="local-co-empty" data-variant="no-results">
                {illustration}
                <h2 className="local-co-empty__title">{strings.emptynoresultstitle}</h2>
                <p className="local-co-empty__text">{strings.emptynoresults}</p>
            </div>
        );
    }

    const copy: Record<EmptyVariant, string> = {
        student: strings.emptystudent,
        educator: strings.emptyeducator,
        "no-results": strings.emptynoresults,
    };
    return (
        <div className="local-co-empty" data-variant={variant ?? "student"}>
            {illustration}
            <p className="local-co-empty__text">{copy[variant ?? "student"]}</p>
        </div>
    );
}
