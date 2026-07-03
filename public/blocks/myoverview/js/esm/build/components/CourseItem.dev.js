var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
import CourseImage from "./CourseImage";
import CourseControls from "./CourseControls";
import ProgressIndicator from "./ProgressIndicator";
function CourseItem({ course, view, role, displaycategories }) {
  const showProgress = role === "student" && course.hasprogress && course.progress !== null;
  const titleId = `co-title-${course.id}`;
  return /* @__PURE__ */ jsxDEV(
    "article",
    {
      className: `courseoverview-card courseoverview-card--${view}`,
      "data-courseid": course.id,
      "aria-labelledby": titleId,
      children: [
        /* @__PURE__ */ jsxDEV("div", { className: "courseoverview-card__body", children: [
          /* @__PURE__ */ jsxDEV("div", { className: "courseoverview-card__text", children: [
            /* @__PURE__ */ jsxDEV("a", { id: titleId, className: "courseoverview-card__title stretched-link", href: course.viewurl, children: course.fullnamedisplay }, void 0, false, {
              fileName: "public/blocks/myoverview/js/esm/src/components/CourseItem.tsx",
              lineNumber: 63,
              columnNumber: 21
            }, this),
            displaycategories && course.coursecategory && /* @__PURE__ */ jsxDEV("div", { className: "courseoverview-card__category", children: course.coursecategory }, void 0, false, {
              fileName: "public/blocks/myoverview/js/esm/src/components/CourseItem.tsx",
              lineNumber: 67,
              columnNumber: 25
            }, this)
          ] }, void 0, true, {
            fileName: "public/blocks/myoverview/js/esm/src/components/CourseItem.tsx",
            lineNumber: 62,
            columnNumber: 17
          }, this),
          view === "summary" && course.summary !== "" && /* @__PURE__ */ jsxDEV("p", { className: "courseoverview-card__summary", children: course.summary }, void 0, false, {
            fileName: "public/blocks/myoverview/js/esm/src/components/CourseItem.tsx",
            lineNumber: 71,
            columnNumber: 21
          }, this),
          showProgress && /* @__PURE__ */ jsxDEV(ProgressIndicator, { progress: course.progress }, void 0, false, {
            fileName: "public/blocks/myoverview/js/esm/src/components/CourseItem.tsx",
            lineNumber: 73,
            columnNumber: 34
          }, this)
        ] }, void 0, true, {
          fileName: "public/blocks/myoverview/js/esm/src/components/CourseItem.tsx",
          lineNumber: 61,
          columnNumber: 13
        }, this),
        /* @__PURE__ */ jsxDEV("div", { className: "courseoverview-card__media", children: [
          /* @__PURE__ */ jsxDEV(CourseImage, { src: course.courseimage }, void 0, false, {
            fileName: "public/blocks/myoverview/js/esm/src/components/CourseItem.tsx",
            lineNumber: 76,
            columnNumber: 17
          }, this),
          /* @__PURE__ */ jsxDEV(CourseControls, { course }, void 0, false, {
            fileName: "public/blocks/myoverview/js/esm/src/components/CourseItem.tsx",
            lineNumber: 77,
            columnNumber: 17
          }, this)
        ] }, void 0, true, {
          fileName: "public/blocks/myoverview/js/esm/src/components/CourseItem.tsx",
          lineNumber: 75,
          columnNumber: 13
        }, this)
      ]
    },
    void 0,
    true,
    {
      fileName: "public/blocks/myoverview/js/esm/src/components/CourseItem.tsx",
      lineNumber: 56,
      columnNumber: 9
    },
    this
  );
}
__name(CourseItem, "CourseItem");
export {
  CourseItem as default
};
//# sourceMappingURL=CourseItem.dev.js.map
