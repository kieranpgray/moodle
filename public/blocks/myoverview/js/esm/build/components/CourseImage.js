import{useEffect as i,useState as m}from"react";import{jsx as s}from"react/jsx-runtime";/**
 * Course overview image (MDL-88967).
 *
 * Renders the course image cover-cropped into a fixed-height block, or a
 * checkerboard fallback when no image is set. Height follows the Figma redesign
 * (160px, --co-image-h in styles.css).
 *
 * @module     block_myoverview/components/CourseImage
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */function l({src:e,className:a=""}){const[t,r]=m(!1);i(()=>{r(!1)},[e]);const o=e!==null&&!t;return s("div",{className:`courseoverview-image ${o?"":"courseoverview-image--empty"} ${a}`.trim(),children:o&&s("img",{src:e,alt:"",className:"courseoverview-image__img",loading:"lazy",onError:()=>{r(!0)}})})}export{l as default};
