import{useDismissableMenu as $}from"../hooks/useDismissableMenu";import{useDismissableTooltip as A}from"../hooks/useDismissableTooltip";import s from"@moodle/lms/block_myoverview/components/Icon";import{jsx as o,jsxs as l}from"react/jsx-runtime";/**
 * Generic single-select toolbar dropdown (used for filter, sort and layout).
 *
 * Shows a tooltip (title) and an active-state class when the current value is
 * non-default (MDL-88972). Opens on click, closes on outside click or Escape,
 * and returns focus to the trigger. Options are mutually exclusive.
 *
 * Keyboard: ArrowDown/ArrowUp move focus between items; Home/End jump to
 * first/last; Escape closes and returns focus to trigger; Tab closes.
 *
 * @module     block_myoverview/components/Dropdown
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */function P({label:n,triggerAriaLabel:g,icon:u,options:r,current:a,onSelect:w,active:b,showLabel:i=!1,menuTitle:t,tooltip:f,groupOf:v,align:_="end"}){const{open:d,setOpen:c,containerRef:D,triggerRef:m,menuRef:N,handleMenuKeyDown:T}=$("menuitemradio"),{tooltipAttr:y,tooltipTriggerProps:h}=A(),k=r.find(e=>e.value===a);return l("div",{className:`courseoverview-dropdown${i?" courseoverview-dropdown--labelled":""}`,ref:D,children:[l("button",{type:"button",ref:m,className:`courseoverview-toolbtn${b?" is-active":""}`+(i?" courseoverview-toolbtn--labelled":""),"aria-haspopup":"menu","aria-expanded":d,"aria-label":g??n,"data-tooltip":y(f??n),onClick:()=>{c(e=>!e)},...h,children:[u!==void 0&&u!==""&&o(s,{name:u}),i&&o("span",{className:"courseoverview-toolbtn__label",children:k?.label??n}),i&&o(s,{name:"chevron-down",className:"courseoverview-toolbtn__caret"})]}),d&&l("div",{className:`courseoverview-menu__list${_==="start"?" courseoverview-menu__list--start":""}`,role:"menu","aria-label":n,ref:N,onKeyDown:T,children:[t!==void 0&&t!==""&&o("div",{className:"courseoverview-menu__group-label","aria-hidden":"true",children:t}),o("div",{role:"group","aria-label":t??n,children:r.map((e,p)=>{const x=v!==void 0&&p<r.length-1&&v(e.value)!==v(r[p+1].value);return l("button",{type:"button",role:"menuitemradio","aria-checked":e.value===a,className:`courseoverview-menu__item${e.value===a?" is-selected":""}`+(x?" courseoverview-menu__item--group-end":""),onClick:()=>{w(e.value),c(!1),m.current?.focus()},children:[e.icon!==void 0&&e.icon!==""&&o(s,{name:e.icon,className:"courseoverview-menu__icon"}),e.label,e.value===a&&o(s,{name:"check",className:"courseoverview-menu__check"})]},e.value)})})]})]})}export{P as default};
