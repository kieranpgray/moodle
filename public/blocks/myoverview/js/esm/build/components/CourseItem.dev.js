var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
import CourseImage from "./CourseImage";
import CourseControls from "./CourseControls";
import ProgressIndicator from "./ProgressIndicator";
function CourseItem({ course, view, role }) {
  const showProgress = role === "student" && course.hasprogress && course.progress !== null;
  const titleId = `co-title-${course.id}`;
  return /* @__PURE__ */ jsxDEV(
    "article",
    {
      className: `local-co-card local-co-card--${view}`,
      "data-courseid": course.id,
      "aria-labelledby": titleId,
      children: [
        /* @__PURE__ */ jsxDEV("div", { className: "local-co-card__body", children: [
          /* @__PURE__ */ jsxDEV("div", { className: "local-co-card__text", children: [
            /* @__PURE__ */ jsxDEV("a", { id: titleId, className: "local-co-card__title stretched-link", href: course.viewurl, children: course.fullnamedisplay }, void 0, false, {
              fileName: "public/blocks/myoverview/js/esm/src/components/CourseItem.tsx",
              lineNumber: 62,
              columnNumber: 21
            }, this),
            course.showcoursecategory && /* @__PURE__ */ jsxDEV("div", { className: "local-co-card__category", children: course.coursecategory }, void 0, false, {
              fileName: "public/blocks/myoverview/js/esm/src/components/CourseItem.tsx",
              lineNumber: 66,
              columnNumber: 25
            }, this)
          ] }, void 0, true, {
            fileName: "public/blocks/myoverview/js/esm/src/components/CourseItem.tsx",
            lineNumber: 61,
            columnNumber: 17
          }, this),
          view === "summary" && course.summary !== "" && /* @__PURE__ */ jsxDEV("p", { className: "local-co-card__summary", children: course.summary }, void 0, false, {
            fileName: "public/blocks/myoverview/js/esm/src/components/CourseItem.tsx",
            lineNumber: 70,
            columnNumber: 21
          }, this),
          showProgress && /* @__PURE__ */ jsxDEV(ProgressIndicator, { progress: course.progress }, void 0, false, {
            fileName: "public/blocks/myoverview/js/esm/src/components/CourseItem.tsx",
            lineNumber: 72,
            columnNumber: 34
          }, this)
        ] }, void 0, true, {
          fileName: "public/blocks/myoverview/js/esm/src/components/CourseItem.tsx",
          lineNumber: 60,
          columnNumber: 13
        }, this),
        /* @__PURE__ */ jsxDEV("div", { className: "local-co-card__media", children: [
          /* @__PURE__ */ jsxDEV(CourseImage, { src: course.courseimage }, void 0, false, {
            fileName: "public/blocks/myoverview/js/esm/src/components/CourseItem.tsx",
            lineNumber: 75,
            columnNumber: 17
          }, this),
          /* @__PURE__ */ jsxDEV(CourseControls, { course }, void 0, false, {
            fileName: "public/blocks/myoverview/js/esm/src/components/CourseItem.tsx",
            lineNumber: 76,
            columnNumber: 17
          }, this)
        ] }, void 0, true, {
          fileName: "public/blocks/myoverview/js/esm/src/components/CourseItem.tsx",
          lineNumber: 74,
          columnNumber: 13
        }, this)
      ]
    },
    void 0,
    true,
    {
      fileName: "public/blocks/myoverview/js/esm/src/components/CourseItem.tsx",
      lineNumber: 55,
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
