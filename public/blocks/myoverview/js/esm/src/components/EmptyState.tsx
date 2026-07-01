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
import Icon from "./Icon";

type EmptyVariant = "student" | "educator" | "no-results";


type EmptyStateProps = {
    zerostate?: ZeroStateData;
    variant?: EmptyVariant;
};


/**
 * Render the empty / no-results state.
 *
 * @param props The rich zero-state data, or a simple variant fallback.
 * @returns The empty-state element.
 */
export default function EmptyState({zerostate, variant}: EmptyStateProps) {
    const strings = useStrings();

    if (zerostate) {
        return (
            <div className="local-co-empty" data-variant="zerostate">
                <div className="local-co-empty__illustration" aria-hidden="true">
                    <Icon name="book-open" />
                </div>
                {zerostate.title !== "" && (
                    <h6 className="local-co-empty__title">{zerostate.title}</h6>
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

    const copy: Record<EmptyVariant, string> = {
        student: strings.emptystudent,
        educator: strings.emptyeducator,
        "no-results": strings.emptynoresults,
    };
    return (
        <div className="local-co-empty" data-variant={variant ?? "student"}>
            <div className="local-co-empty__illustration" aria-hidden="true">
                <Icon name="book-open" />
            </div>
            <p className="local-co-empty__text">{copy[variant ?? "student"]}</p>
        </div>
    );
}
