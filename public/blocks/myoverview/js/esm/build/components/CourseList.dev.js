var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
import CourseItem from "./CourseItem";
function CourseList({ courses, view, role, displaycategories }) {
  return /* @__PURE__ */ jsxDEV("div", { className: `local-co-list local-co-list--${view}`, children: courses.map((course) => /* @__PURE__ */ jsxDEV(
    CourseItem,
    {
      course,
      view,
      role,
      displaycategories
    },
    course.id,
    false,
    {
      fileName: "public/blocks/myoverview/js/esm/src/components/CourseList.tsx",
      lineNumber: 45,
      columnNumber: 17
    },
    this
  )) }, void 0, false, {
    fileName: "public/blocks/myoverview/js/esm/src/components/CourseList.tsx",
    lineNumber: 43,
    columnNumber: 9
  }, this);
}
__name(CourseList, "CourseList");
export {
  CourseList as default
};
//# sourceMappingURL=CourseList.dev.js.map
