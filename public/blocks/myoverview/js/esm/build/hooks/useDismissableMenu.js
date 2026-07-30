import{useEffect as i,useRef as a,useState as m}from"react";/**
 * Shared keyboard/pointer behaviour for a pop-up menu with a trigger button.
 *
 * Encapsulates the open state, the container/trigger/menu refs, focusing the
 * first item on open, closing on outside click or Escape (Escape returns focus
 * to the trigger), and roving focus across items via arrow/Home/End keys with
 * Tab closing the menu. Used by both the toolbar Dropdown (menuitemradio items)
 * and the card overflow menu (menuitem items).
 *
 * @module     block_myoverview/hooks/useDismissableMenu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */function v(f){const[r,o]=m(!1),u=a(null),l=a(null),c=a(null),s=`[role="${f}"]`;return i(()=>{r&&c.current&&c.current.querySelector(s)?.focus()},[r,s]),i(()=>{if(!r)return;const n=t=>{u.current&&!u.current.contains(t.target)&&o(!1)},e=t=>{t.key==="Escape"&&(o(!1),l.current?.focus())};return document.addEventListener("click",n,{capture:!0}),document.addEventListener("keydown",e),()=>{document.removeEventListener("click",n,!0),document.removeEventListener("keydown",e)}},[r]),{open:r,setOpen:o,containerRef:u,triggerRef:l,menuRef:c,handleMenuKeyDown:n=>{const e=[...c.current?.querySelectorAll(s)??[]],t=e.indexOf(document.activeElement);switch(n.key){case"ArrowDown":{n.preventDefault(),e[(t+1)%e.length]?.focus();break}case"ArrowUp":{n.preventDefault(),e[(t-1+e.length)%e.length]?.focus();break}case"Home":{n.preventDefault(),e[0]?.focus();break}case"End":{n.preventDefault(),e[e.length-1]?.focus();break}case"Tab":{o(!1);break}default:break}}}}export{v as useDismissableMenu};
