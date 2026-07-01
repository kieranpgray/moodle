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
 * Course completion progress indicator (MDL-88970).
 *
 * Delegates to the DS ProgressBar with the inline label variant so the
 * percentage count sits beside the track. Title is supplied for the accessible
 * name; count renders the formatted percentage string.
 *
 * @module     block_myoverview/components/ProgressIndicator
 */

import {ProgressBar} from "@moodlehq/design-system";
import {useStrings} from "../state";

type ProgressIndicatorProps = {
    progress: number;
};

/**
 * Render the DS ProgressBar in inline mode for a course card.
 *
 * @param props The progress percentage (0-100).
 * @returns The progress bar element.
 */
export default function ProgressIndicator({progress}: ProgressIndicatorProps) {
    const strings = useStrings();
    const clamped = Math.max(0, Math.min(100, Math.round(progress)));
    return (
        <ProgressBar
            value={clamped}
            labelVariant="inline"
            title={strings.courseprogress}
            count={strings.percentcomplete.replace("{$a}", String(clamped))}
            className="local-co-progress"
        />
    );
}
