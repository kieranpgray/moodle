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
 * A single course as a card, list row or summary row (MDL-88966).
 *
 * One component renders all three views; CSS keys off the view modifier class
 * for layout. Anatomy (top to bottom in card view): image with star + ellipsis
 * at top-right, course name, category, then progress (students only).
 *
 * The whole surface is clickable (MDL-88971) via a stretched link on the title:
 * clicking anywhere navigates to the course, except on the star/ellipsis which
 * stop propagation. DOM order is body-first so tab order is link -> star ->
 * ellipsis (MDL-88978); CSS `order`/grid restores the visual layout.
 *
 * @module     block_myoverview/components/CourseItem
 */

import {Course, Role, View} from "../types";
import CourseImage from "./CourseImage";
import CourseControls from "./CourseControls";
import ProgressIndicator from "./ProgressIndicator";

type CourseItemProps = {
    course: Course;
    view: View;
    role: Role;
    displaycategories: boolean;
};

/**
 * Render one course in the active view.
 *
 * @param props The course, view mode, viewer role and category display flag.
 * @returns The course item element.
 */
export default function CourseItem({course, view, role, displaycategories}: CourseItemProps) {
    // Progress is shown for students only (educator cards omit it per Figma).
    const showProgress = role === "student" && course.hasprogress && course.progress !== null;

    const titleId = `co-title-${course.id}`;

    return (
        <article
            className={`courseoverview-card courseoverview-card--${view}`}
            data-courseid={course.id}
            aria-labelledby={titleId}
        >
            <div className="courseoverview-card__body">
                <div className="courseoverview-card__text">
                    <a id={titleId} className="courseoverview-card__title stretched-link" href={course.viewurl}>
                        {course.fullnamedisplay}
                    </a>
                    {displaycategories && course.coursecategory && (
                        <div className="courseoverview-card__category">{course.coursecategory}</div>
                    )}
                </div>
                {view === "summary" && course.summary !== "" && (
                    <p className="courseoverview-card__summary">{course.summary}</p>
                )}
                {showProgress && <ProgressIndicator progress={course.progress as number} />}
            </div>
            <div className="courseoverview-card__media">
                <CourseImage src={course.courseimage} />
                <CourseControls course={course} />
            </div>
        </article>
    );
}
