var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
import { ProgressBar } from "@moodlehq/design-system";
import { useStrings } from "../state";
function ProgressIndicator({ progress }) {
  const strings = useStrings();
  const clamped = Math.max(0, Math.min(100, Math.round(progress)));
  return /* @__PURE__ */ jsxDEV(
    ProgressBar,
    {
      value: clamped,
      labelVariant: "inline",
      title: strings.courseprogress,
      count: strings.percentcomplete.replace("{$a}", String(clamped)),
      className: "courseoverview-progress"
    },
    void 0,
    false,
    {
      fileName: "public/blocks/myoverview/js/esm/src/components/ProgressIndicator.tsx",
      lineNumber: 43,
      columnNumber: 9
    },
    this
  );
}
__name(ProgressIndicator, "ProgressIndicator");
export {
  ProgressIndicator as default
};
//# sourceMappingURL=ProgressIndicator.dev.js.map
