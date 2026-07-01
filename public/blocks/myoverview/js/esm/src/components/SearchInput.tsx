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
 * Course search input (MDL-88972).
 *
 * A controlled text field. Search state is independent of filter and sort
 * (MDL-88973): typing here never resets them, and clearing it leaves them
 * intact.
 *
 * @module     block_myoverview/components/SearchInput
 */

import {CloseButton} from "@moodlehq/design-system";
import {useStrings} from "../state";
import Icon from "./Icon";

type SearchInputProps = {
    value: string;
    onChange: (value: string) => void;
};

/**
 * Render the search field with a leading icon and a clear button.
 *
 * @param props The current value and change handler.
 * @returns The search input.
 */
export default function SearchInput({value, onChange}: SearchInputProps) {
    const strings = useStrings();
    return (
        <div className="local-co-search">
            <Icon name="magnifying-glass" className="local-co-search__icon" />
            <input
                type="text"
                className="local-co-search__input"
                placeholder={strings.search}
                aria-label={strings.searchcourses}
                value={value}
                onChange={(e) => onChange(e.target.value)}
            />
            {value !== "" && (
                <CloseButton
                    aria-label={strings.clearsearch}
                    size="sm"
                    className="local-co-search__clear"
                    onClick={() => onChange("")}
                />
            )}
        </div>
    );
}
