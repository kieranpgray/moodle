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
 * Course list pagination (MDL-88977).
 *
 * Delegates to the DS Pagination — adaptive viewport narrowing, ellipsis for
 * large page counts, and accessible prev/next labels are all owned by the DS.
 * Hides itself when there is only one page (DS behaviour). The navigation's
 * accessible name is sourced from the localized strings context.
 *
 * @module     block_myoverview/components/Pagination
 */

import {Pagination as DSPagination} from "@moodlehq/design-system";
import {useStrings} from "../state";


type PaginationProps = {
    page: number;
    pageCount: number;
    onPage: (page: number) => void;
};


export default function Pagination({page, pageCount, onPage}: PaginationProps) {
    const strings = useStrings();
    return (
        <div className="courseoverview-pagination">
            <DSPagination
                totalPages={pageCount}
                currentPage={page}
                onPageChange={onPage}
                ariaLabel={strings.courseoverview}
            />
        </div>
    );
}
