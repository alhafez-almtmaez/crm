import{At as e,B as t,Bt as n,Ct as r,Et as i,Ft as a,Gt as o,Ht as s,M as c,Q as l,Qt as u,R as d,Rt as f,Tt as ee,U as p,Vt as m,Wt as h,Xt as g,Zt as te,_n as _,_t as v,a as y,an as b,cn as x,dn as S,en as C,et as w,gt as T,hn as E,in as D,kt as O,ln as ne,nn as k,rn as A,t as j,tn as M,un as N,v as P,vn as F,wt as I,xn as L,yn as re,z as ie,zt as R}from"./ripple-CIndIOSS.js";import{n as z,r as B,t as V}from"./baseicon-CIy754zm.js";import{a as H,c as U,n as ae,o as W,s as G,t as oe}from"./overlayeventbus-DJL1peMJ.js";import{a as K,i as se,n as ce,r as le,t as ue}from"./app-D4osEgRX.js";var de=[{labelKey:`nav.dashboard`,href:`/admin/dashboard`,icon:`pi pi-th-large`,permissions:[`view admin dashboard`]},{groupId:`management`,groupKey:`nav.management`,labelKey:`nav.users`,href:`/admin/users`,icon:`pi pi-users`,permissions:[`users.view`]},{groupId:`management`,groupKey:`nav.management`,labelKey:`nav.roles`,href:`/admin/roles`,icon:`pi pi-shield`,permissions:[`roles.view`]},{groupId:`management`,groupKey:`nav.management`,labelKey:`nav.plans`,href:`/admin/plans`,icon:`pi pi-bookmark`,permissions:[`plans.view`]},{groupId:`management`,groupKey:`nav.management`,labelKey:`nav.activityLogs`,href:`/admin/activity-logs`,icon:`pi pi-history`,permissions:[`activity_logs.view`]},{labelKey:`nav.whatsapp`,href:`/admin/whatsapp`,icon:`pi pi-whatsapp`,roles:[`admin`]},{labelKey:`nav.settings`,href:`/admin/settings`,icon:`pi pi-cog`,roles:[`admin`]}],fe=(e,t={})=>{let n=new Set(t.roles??[]),r=new Set(t.permissions??[]);return(e??[]).filter(e=>{let t=!e.roles?.length||e.roles.some(e=>n.has(e)),i=!e.permissions?.length||e.permissions.some(e=>r.has(e));return t&&i})},pe=`
    .p-toast {
        width: dt('toast.width');
        white-space: pre-line;
        word-break: break-word;
    }

    .p-toast-message {
        margin: 0 0 1rem 0;
        display: grid;
        grid-template-rows: 1fr;
    }

    .p-toast-message-icon {
        flex-shrink: 0;
        font-size: dt('toast.icon.size');
        width: dt('toast.icon.size');
        height: dt('toast.icon.size');
    }

    .p-toast-message-content {
        display: flex;
        align-items: flex-start;
        padding: dt('toast.content.padding');
        gap: dt('toast.content.gap');
        min-height: 0;
        overflow: hidden;
        transition: padding 250ms ease-in;
    }

    .p-toast-message-text {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        gap: dt('toast.text.gap');
    }

    .p-toast-summary {
        font-weight: dt('toast.summary.font.weight');
        font-size: dt('toast.summary.font.size');
    }

    .p-toast-detail {
        font-weight: dt('toast.detail.font.weight');
        font-size: dt('toast.detail.font.size');
    }

    .p-toast-close-button {
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
        cursor: pointer;
        background: transparent;
        transition:
            background dt('toast.transition.duration'),
            color dt('toast.transition.duration'),
            outline-color dt('toast.transition.duration'),
            box-shadow dt('toast.transition.duration');
        outline-color: transparent;
        color: inherit;
        width: dt('toast.close.button.width');
        height: dt('toast.close.button.height');
        border-radius: dt('toast.close.button.border.radius');
        margin: -25% 0 0 0;
        right: -25%;
        padding: 0;
        border: none;
        user-select: none;
    }

    .p-toast-close-button:dir(rtl) {
        margin: -25% 0 0 auto;
        left: -25%;
        right: auto;
    }

    .p-toast-message-info,
    .p-toast-message-success,
    .p-toast-message-warn,
    .p-toast-message-error,
    .p-toast-message-secondary,
    .p-toast-message-contrast {
        border-width: dt('toast.border.width');
        border-style: solid;
        backdrop-filter: blur(dt('toast.blur'));
        border-radius: dt('toast.border.radius');
    }

    .p-toast-close-icon {
        font-size: dt('toast.close.icon.size');
        width: dt('toast.close.icon.size');
        height: dt('toast.close.icon.size');
    }

    .p-toast-close-button:focus-visible {
        outline-width: dt('focus.ring.width');
        outline-style: dt('focus.ring.style');
        outline-offset: dt('focus.ring.offset');
    }

    .p-toast-message-info {
        background: dt('toast.info.background');
        border-color: dt('toast.info.border.color');
        color: dt('toast.info.color');
        box-shadow: dt('toast.info.shadow');
    }

    .p-toast-message-info .p-toast-detail {
        color: dt('toast.info.detail.color');
    }

    .p-toast-message-info .p-toast-close-button:focus-visible {
        outline-color: dt('toast.info.close.button.focus.ring.color');
        box-shadow: dt('toast.info.close.button.focus.ring.shadow');
    }

    .p-toast-message-info .p-toast-close-button:hover {
        background: dt('toast.info.close.button.hover.background');
    }

    .p-toast-message-success {
        background: dt('toast.success.background');
        border-color: dt('toast.success.border.color');
        color: dt('toast.success.color');
        box-shadow: dt('toast.success.shadow');
    }

    .p-toast-message-success .p-toast-detail {
        color: dt('toast.success.detail.color');
    }

    .p-toast-message-success .p-toast-close-button:focus-visible {
        outline-color: dt('toast.success.close.button.focus.ring.color');
        box-shadow: dt('toast.success.close.button.focus.ring.shadow');
    }

    .p-toast-message-success .p-toast-close-button:hover {
        background: dt('toast.success.close.button.hover.background');
    }

    .p-toast-message-warn {
        background: dt('toast.warn.background');
        border-color: dt('toast.warn.border.color');
        color: dt('toast.warn.color');
        box-shadow: dt('toast.warn.shadow');
    }

    .p-toast-message-warn .p-toast-detail {
        color: dt('toast.warn.detail.color');
    }

    .p-toast-message-warn .p-toast-close-button:focus-visible {
        outline-color: dt('toast.warn.close.button.focus.ring.color');
        box-shadow: dt('toast.warn.close.button.focus.ring.shadow');
    }

    .p-toast-message-warn .p-toast-close-button:hover {
        background: dt('toast.warn.close.button.hover.background');
    }

    .p-toast-message-error {
        background: dt('toast.error.background');
        border-color: dt('toast.error.border.color');
        color: dt('toast.error.color');
        box-shadow: dt('toast.error.shadow');
    }

    .p-toast-message-error .p-toast-detail {
        color: dt('toast.error.detail.color');
    }

    .p-toast-message-error .p-toast-close-button:focus-visible {
        outline-color: dt('toast.error.close.button.focus.ring.color');
        box-shadow: dt('toast.error.close.button.focus.ring.shadow');
    }

    .p-toast-message-error .p-toast-close-button:hover {
        background: dt('toast.error.close.button.hover.background');
    }

    .p-toast-message-secondary {
        background: dt('toast.secondary.background');
        border-color: dt('toast.secondary.border.color');
        color: dt('toast.secondary.color');
        box-shadow: dt('toast.secondary.shadow');
    }

    .p-toast-message-secondary .p-toast-detail {
        color: dt('toast.secondary.detail.color');
    }

    .p-toast-message-secondary .p-toast-close-button:focus-visible {
        outline-color: dt('toast.secondary.close.button.focus.ring.color');
        box-shadow: dt('toast.secondary.close.button.focus.ring.shadow');
    }

    .p-toast-message-secondary .p-toast-close-button:hover {
        background: dt('toast.secondary.close.button.hover.background');
    }

    .p-toast-message-contrast {
        background: dt('toast.contrast.background');
        border-color: dt('toast.contrast.border.color');
        color: dt('toast.contrast.color');
        box-shadow: dt('toast.contrast.shadow');
    }
    
    .p-toast-message-contrast .p-toast-detail {
        color: dt('toast.contrast.detail.color');
    }

    .p-toast-message-contrast .p-toast-close-button:focus-visible {
        outline-color: dt('toast.contrast.close.button.focus.ring.color');
        box-shadow: dt('toast.contrast.close.button.focus.ring.shadow');
    }

    .p-toast-message-contrast .p-toast-close-button:hover {
        background: dt('toast.contrast.close.button.hover.background');
    }

    .p-toast-top-center {
        transform: translateX(-50%);
    }

    .p-toast-bottom-center {
        transform: translateX(-50%);
    }

    .p-toast-center {
        min-width: 20vw;
        transform: translate(-50%, -50%);
    }

    .p-toast-message-enter-active {
        animation: p-animate-toast-enter 300ms ease-out;
    }

    .p-toast-message-leave-active {
        animation: p-animate-toast-leave 250ms ease-in;
    }

    .p-toast-message-leave-to .p-toast-message-content {
        padding-top: 0;
        padding-bottom: 0;
    }

    @keyframes p-animate-toast-enter {
        from {
            opacity: 0;
            transform: scale(0.6);
        }
        to {
            opacity: 1;
            grid-template-rows: 1fr;
        }
    }

     @keyframes p-animate-toast-leave {
        from {
            opacity: 1;
        }
        to {
            opacity: 0;
            margin-bottom: 0;
            grid-template-rows: 0fr;
            transform: translateY(-100%) scale(0.6);
        }
    }
`;function q(e){"@babel/helpers - typeof";return q=typeof Symbol==`function`&&typeof Symbol.iterator==`symbol`?function(e){return typeof e}:function(e){return e&&typeof Symbol==`function`&&e.constructor===Symbol&&e!==Symbol.prototype?`symbol`:typeof e},q(e)}function J(e,t,n){return(t=me(t))in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}function me(e){var t=he(e,`string`);return q(t)==`symbol`?t:t+``}function he(e,t){if(q(e)!=`object`||!e)return e;var n=e[Symbol.toPrimitive];if(n!==void 0){var r=n.call(e,t);if(q(r)!=`object`)return r;throw TypeError(`@@toPrimitive must return a primitive value.`)}return(t===`string`?String:Number)(e)}var ge=y.extend({name:`toast`,style:pe,classes:{root:function(e){return[`p-toast p-component p-toast-`+e.props.position]},message:function(e){var t=e.props;return[`p-toast-message`,{"p-toast-message-info":t.message.severity===`info`||t.message.severity===void 0,"p-toast-message-warn":t.message.severity===`warn`,"p-toast-message-error":t.message.severity===`error`,"p-toast-message-success":t.message.severity===`success`,"p-toast-message-secondary":t.message.severity===`secondary`,"p-toast-message-contrast":t.message.severity===`contrast`}]},messageContent:`p-toast-message-content`,messageIcon:function(e){var t=e.props;return[`p-toast-message-icon`,J(J(J(J({},t.infoIcon,t.message.severity===`info`),t.warnIcon,t.message.severity===`warn`),t.errorIcon,t.message.severity===`error`),t.successIcon,t.message.severity===`success`)]},messageText:`p-toast-message-text`,summary:`p-toast-summary`,detail:`p-toast-detail`,closeButton:`p-toast-close-button`,closeIcon:`p-toast-close-icon`},inlineStyles:{root:function(e){var t=e.position;return{position:`fixed`,top:t===`top-right`||t===`top-left`||t===`top-center`?`20px`:t===`center`?`50%`:null,right:(t===`top-right`||t===`bottom-right`)&&`20px`,bottom:(t===`bottom-left`||t===`bottom-right`||t===`bottom-center`)&&`20px`,left:t===`top-left`||t===`bottom-left`?`20px`:t===`center`||t===`top-center`||t===`bottom-center`?`50%`:null}}}}),_e={name:`ExclamationTriangleIcon`,extends:V};function ve(e){return Se(e)||xe(e)||be(e)||ye()}function ye(){throw TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function be(e,t){if(e){if(typeof e==`string`)return Ce(e,t);var n={}.toString.call(e).slice(8,-1);return n===`Object`&&e.constructor&&(n=e.constructor.name),n===`Map`||n===`Set`?Array.from(e):n===`Arguments`||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?Ce(e,t):void 0}}function xe(e){if(typeof Symbol<`u`&&e[Symbol.iterator]!=null||e[`@@iterator`]!=null)return Array.from(e)}function Se(e){if(Array.isArray(e))return Ce(e)}function Ce(e,t){(t==null||t>e.length)&&(t=e.length);for(var n=0,r=Array(t);n<t;n++)r[n]=e[n];return r}function we(e,t,n,r,i,a){return C(),s(`svg`,g({width:`14`,height:`14`,viewBox:`0 0 14 14`,fill:`none`,xmlns:`http://www.w3.org/2000/svg`},e.pti()),ve(t[0]||=[R(`path`,{d:`M13.4018 13.1893H0.598161C0.49329 13.189 0.390283 13.1615 0.299143 13.1097C0.208003 13.0578 0.131826 12.9832 0.0780112 12.8932C0.0268539 12.8015 0 12.6982 0 12.5931C0 12.4881 0.0268539 12.3848 0.0780112 12.293L6.47985 1.08982C6.53679 1.00399 6.61408 0.933574 6.70484 0.884867C6.7956 0.836159 6.897 0.810669 7 0.810669C7.103 0.810669 7.2044 0.836159 7.29516 0.884867C7.38592 0.933574 7.46321 1.00399 7.52015 1.08982L13.922 12.293C13.9731 12.3848 14 12.4881 14 12.5931C14 12.6982 13.9731 12.8015 13.922 12.8932C13.8682 12.9832 13.792 13.0578 13.7009 13.1097C13.6097 13.1615 13.5067 13.189 13.4018 13.1893ZM1.63046 11.989H12.3695L7 2.59425L1.63046 11.989Z`,fill:`currentColor`},null,-1),R(`path`,{d:`M6.99996 8.78801C6.84143 8.78594 6.68997 8.72204 6.57787 8.60993C6.46576 8.49782 6.40186 8.34637 6.39979 8.18784V5.38703C6.39979 5.22786 6.46302 5.0752 6.57557 4.96265C6.68813 4.85009 6.84078 4.78686 6.99996 4.78686C7.15914 4.78686 7.31179 4.85009 7.42435 4.96265C7.5369 5.0752 7.60013 5.22786 7.60013 5.38703V8.18784C7.59806 8.34637 7.53416 8.49782 7.42205 8.60993C7.30995 8.72204 7.15849 8.78594 6.99996 8.78801Z`,fill:`currentColor`},null,-1),R(`path`,{d:`M6.99996 11.1887C6.84143 11.1866 6.68997 11.1227 6.57787 11.0106C6.46576 10.8985 6.40186 10.7471 6.39979 10.5885V10.1884C6.39979 10.0292 6.46302 9.87658 6.57557 9.76403C6.68813 9.65147 6.84078 9.58824 6.99996 9.58824C7.15914 9.58824 7.31179 9.65147 7.42435 9.76403C7.5369 9.87658 7.60013 10.0292 7.60013 10.1884V10.5885C7.59806 10.7471 7.53416 10.8985 7.42205 11.0106C7.30995 11.1227 7.15849 11.1866 6.99996 11.1887Z`,fill:`currentColor`},null,-1)]),16)}_e.render=we;var Te={name:`InfoCircleIcon`,extends:V};function Ee(e){return Ae(e)||ke(e)||Oe(e)||De()}function De(){throw TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function Oe(e,t){if(e){if(typeof e==`string`)return je(e,t);var n={}.toString.call(e).slice(8,-1);return n===`Object`&&e.constructor&&(n=e.constructor.name),n===`Map`||n===`Set`?Array.from(e):n===`Arguments`||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?je(e,t):void 0}}function ke(e){if(typeof Symbol<`u`&&e[Symbol.iterator]!=null||e[`@@iterator`]!=null)return Array.from(e)}function Ae(e){if(Array.isArray(e))return je(e)}function je(e,t){(t==null||t>e.length)&&(t=e.length);for(var n=0,r=Array(t);n<t;n++)r[n]=e[n];return r}function Me(e,t,n,r,i,a){return C(),s(`svg`,g({width:`14`,height:`14`,viewBox:`0 0 14 14`,fill:`none`,xmlns:`http://www.w3.org/2000/svg`},e.pti()),Ee(t[0]||=[R(`path`,{"fill-rule":`evenodd`,"clip-rule":`evenodd`,d:`M3.11101 12.8203C4.26215 13.5895 5.61553 14 7 14C8.85652 14 10.637 13.2625 11.9497 11.9497C13.2625 10.637 14 8.85652 14 7C14 5.61553 13.5895 4.26215 12.8203 3.11101C12.0511 1.95987 10.9579 1.06266 9.67879 0.532846C8.3997 0.00303296 6.99224 -0.13559 5.63437 0.134506C4.2765 0.404603 3.02922 1.07129 2.05026 2.05026C1.07129 3.02922 0.404603 4.2765 0.134506 5.63437C-0.13559 6.99224 0.00303296 8.3997 0.532846 9.67879C1.06266 10.9579 1.95987 12.0511 3.11101 12.8203ZM3.75918 2.14976C4.71846 1.50879 5.84628 1.16667 7 1.16667C8.5471 1.16667 10.0308 1.78125 11.1248 2.87521C12.2188 3.96918 12.8333 5.45291 12.8333 7C12.8333 8.15373 12.4912 9.28154 11.8502 10.2408C11.2093 11.2001 10.2982 11.9478 9.23232 12.3893C8.16642 12.8308 6.99353 12.9463 5.86198 12.7212C4.73042 12.4962 3.69102 11.9406 2.87521 11.1248C2.05941 10.309 1.50384 9.26958 1.27876 8.13803C1.05367 7.00647 1.16919 5.83358 1.61071 4.76768C2.05222 3.70178 2.79989 2.79074 3.75918 2.14976ZM7.00002 4.8611C6.84594 4.85908 6.69873 4.79698 6.58977 4.68801C6.48081 4.57905 6.4187 4.43185 6.41669 4.27776V3.88888C6.41669 3.73417 6.47815 3.58579 6.58754 3.4764C6.69694 3.367 6.84531 3.30554 7.00002 3.30554C7.15473 3.30554 7.3031 3.367 7.4125 3.4764C7.52189 3.58579 7.58335 3.73417 7.58335 3.88888V4.27776C7.58134 4.43185 7.51923 4.57905 7.41027 4.68801C7.30131 4.79698 7.1541 4.85908 7.00002 4.8611ZM7.00002 10.6945C6.84594 10.6925 6.69873 10.6304 6.58977 10.5214C6.48081 10.4124 6.4187 10.2652 6.41669 10.1111V6.22225C6.41669 6.06754 6.47815 5.91917 6.58754 5.80977C6.69694 5.70037 6.84531 5.63892 7.00002 5.63892C7.15473 5.63892 7.3031 5.70037 7.4125 5.80977C7.52189 5.91917 7.58335 6.06754 7.58335 6.22225V10.1111C7.58134 10.2652 7.51923 10.4124 7.41027 10.5214C7.30131 10.6304 7.1541 10.6925 7.00002 10.6945Z`,fill:`currentColor`},null,-1)]),16)}Te.render=Me;var Y={name:`TimesCircleIcon`,extends:V};function Ne(e){return Le(e)||Ie(e)||Fe(e)||Pe()}function Pe(){throw TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function Fe(e,t){if(e){if(typeof e==`string`)return Re(e,t);var n={}.toString.call(e).slice(8,-1);return n===`Object`&&e.constructor&&(n=e.constructor.name),n===`Map`||n===`Set`?Array.from(e):n===`Arguments`||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?Re(e,t):void 0}}function Ie(e){if(typeof Symbol<`u`&&e[Symbol.iterator]!=null||e[`@@iterator`]!=null)return Array.from(e)}function Le(e){if(Array.isArray(e))return Re(e)}function Re(e,t){(t==null||t>e.length)&&(t=e.length);for(var n=0,r=Array(t);n<t;n++)r[n]=e[n];return r}function ze(e,t,n,r,i,a){return C(),s(`svg`,g({width:`14`,height:`14`,viewBox:`0 0 14 14`,fill:`none`,xmlns:`http://www.w3.org/2000/svg`},e.pti()),Ne(t[0]||=[R(`path`,{"fill-rule":`evenodd`,"clip-rule":`evenodd`,d:`M7 14C5.61553 14 4.26215 13.5895 3.11101 12.8203C1.95987 12.0511 1.06266 10.9579 0.532846 9.67879C0.00303296 8.3997 -0.13559 6.99224 0.134506 5.63437C0.404603 4.2765 1.07129 3.02922 2.05026 2.05026C3.02922 1.07129 4.2765 0.404603 5.63437 0.134506C6.99224 -0.13559 8.3997 0.00303296 9.67879 0.532846C10.9579 1.06266 12.0511 1.95987 12.8203 3.11101C13.5895 4.26215 14 5.61553 14 7C14 8.85652 13.2625 10.637 11.9497 11.9497C10.637 13.2625 8.85652 14 7 14ZM7 1.16667C5.84628 1.16667 4.71846 1.50879 3.75918 2.14976C2.79989 2.79074 2.05222 3.70178 1.61071 4.76768C1.16919 5.83358 1.05367 7.00647 1.27876 8.13803C1.50384 9.26958 2.05941 10.309 2.87521 11.1248C3.69102 11.9406 4.73042 12.4962 5.86198 12.7212C6.99353 12.9463 8.16642 12.8308 9.23232 12.3893C10.2982 11.9478 11.2093 11.2001 11.8502 10.2408C12.4912 9.28154 12.8333 8.15373 12.8333 7C12.8333 5.45291 12.2188 3.96918 11.1248 2.87521C10.0308 1.78125 8.5471 1.16667 7 1.16667ZM4.66662 9.91668C4.58998 9.91704 4.51404 9.90209 4.44325 9.87271C4.37246 9.84333 4.30826 9.8001 4.2544 9.74557C4.14516 9.6362 4.0838 9.48793 4.0838 9.33335C4.0838 9.17876 4.14516 9.0305 4.2544 8.92113L6.17553 7L4.25443 5.07891C4.15139 4.96832 4.09529 4.82207 4.09796 4.67094C4.10063 4.51982 4.16185 4.37563 4.26872 4.26876C4.3756 4.16188 4.51979 4.10066 4.67091 4.09799C4.82204 4.09532 4.96829 4.15142 5.07887 4.25446L6.99997 6.17556L8.92106 4.25446C9.03164 4.15142 9.1779 4.09532 9.32903 4.09799C9.48015 4.10066 9.62434 4.16188 9.73121 4.26876C9.83809 4.37563 9.89931 4.51982 9.90198 4.67094C9.90464 4.82207 9.84855 4.96832 9.74551 5.07891L7.82441 7L9.74554 8.92113C9.85478 9.0305 9.91614 9.17876 9.91614 9.33335C9.91614 9.48793 9.85478 9.6362 9.74554 9.74557C9.69168 9.8001 9.62748 9.84333 9.55669 9.87271C9.4859 9.90209 9.40996 9.91704 9.33332 9.91668C9.25668 9.91704 9.18073 9.90209 9.10995 9.87271C9.03916 9.84333 8.97495 9.8001 8.9211 9.74557L6.99997 7.82444L5.07884 9.74557C5.02499 9.8001 4.96078 9.84333 4.88999 9.87271C4.81921 9.90209 4.74326 9.91704 4.66662 9.91668Z`,fill:`currentColor`},null,-1)]),16)}Y.render=ze;var Be={name:`BaseToast`,extends:z,props:{group:{type:String,default:null},position:{type:String,default:`top-right`},autoZIndex:{type:Boolean,default:!0},baseZIndex:{type:Number,default:0},breakpoints:{type:Object,default:null},closeIcon:{type:String,default:void 0},infoIcon:{type:String,default:void 0},warnIcon:{type:String,default:void 0},errorIcon:{type:String,default:void 0},successIcon:{type:String,default:void 0},closeButtonProps:{type:null,default:null},onMouseEnter:{type:Function,default:void 0},onMouseLeave:{type:Function,default:void 0},onClick:{type:Function,default:void 0}},style:ge,provide:function(){return{$pcToast:this,$parentInstance:this}}};function X(e){"@babel/helpers - typeof";return X=typeof Symbol==`function`&&typeof Symbol.iterator==`symbol`?function(e){return typeof e}:function(e){return e&&typeof Symbol==`function`&&e.constructor===Symbol&&e!==Symbol.prototype?`symbol`:typeof e},X(e)}function Ve(e,t,n){return(t=He(t))in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}function He(e){var t=Ue(e,`string`);return X(t)==`symbol`?t:t+``}function Ue(e,t){if(X(e)!=`object`||!e)return e;var n=e[Symbol.toPrimitive];if(n!==void 0){var r=n.call(e,t);if(X(r)!=`object`)return r;throw TypeError(`@@toPrimitive must return a primitive value.`)}return(t===`string`?String:Number)(e)}var We={name:`ToastMessage`,hostName:`Toast`,extends:z,emits:[`close`],closeTimeout:null,createdAt:null,lifeRemaining:null,props:{message:{type:null,default:null},templates:{type:Object,default:null},closeIcon:{type:String,default:null},infoIcon:{type:String,default:null},warnIcon:{type:String,default:null},errorIcon:{type:String,default:null},successIcon:{type:String,default:null},closeButtonProps:{type:null,default:null},onMouseEnter:{type:Function,default:void 0},onMouseLeave:{type:Function,default:void 0},onClick:{type:Function,default:void 0}},mounted:function(){this.message.life&&(this.lifeRemaining=this.message.life,this.startTimeout())},beforeUnmount:function(){this.clearCloseTimeout()},methods:{startTimeout:function(){var e=this;this.createdAt=new Date().valueOf(),this.closeTimeout=setTimeout(function(){e.close({message:e.message,type:`life-end`})},this.lifeRemaining)},close:function(e){this.$emit(`close`,e)},onCloseClick:function(){this.clearCloseTimeout(),this.close({message:this.message,type:`close`})},clearCloseTimeout:function(){this.closeTimeout&&=(clearTimeout(this.closeTimeout),null)},onMessageClick:function(e){var t;(t=this.onClick)==null||t.call(this,{originalEvent:e,message:this.message})},handleMouseEnter:function(e){if(this.onMouseEnter){if(this.onMouseEnter({originalEvent:e,message:this.message}),e.defaultPrevented)return;this.message.life&&(this.lifeRemaining=this.createdAt+this.lifeRemaining-new Date().valueOf(),this.createdAt=null,this.clearCloseTimeout())}},handleMouseLeave:function(e){if(this.onMouseLeave){if(this.onMouseLeave({originalEvent:e,message:this.message}),e.defaultPrevented)return;this.message.life&&this.startTimeout()}}},computed:{iconComponent:function(){return{info:!this.infoIcon&&Te,success:!this.successIcon&&W,warn:!this.warnIcon&&_e,error:!this.errorIcon&&Y}[this.message.severity]},closeAriaLabel:function(){return this.$primevue.config.locale.aria?this.$primevue.config.locale.aria.close:void 0},dataP:function(){return B(Ve({},this.message.severity,this.message.severity))}},components:{TimesIcon:H,InfoCircleIcon:Te,CheckIcon:W,ExclamationTriangleIcon:_e,TimesCircleIcon:Y},directives:{ripple:j}};function Z(e){"@babel/helpers - typeof";return Z=typeof Symbol==`function`&&typeof Symbol.iterator==`symbol`?function(e){return typeof e}:function(e){return e&&typeof Symbol==`function`&&e.constructor===Symbol&&e!==Symbol.prototype?`symbol`:typeof e},Z(e)}function Ge(e,t){var n=Object.keys(e);if(Object.getOwnPropertySymbols){var r=Object.getOwnPropertySymbols(e);t&&(r=r.filter(function(t){return Object.getOwnPropertyDescriptor(e,t).enumerable})),n.push.apply(n,r)}return n}function Ke(e){for(var t=1;t<arguments.length;t++){var n=arguments[t]==null?{}:arguments[t];t%2?Ge(Object(n),!0).forEach(function(t){qe(e,t,n[t])}):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(n)):Ge(Object(n)).forEach(function(t){Object.defineProperty(e,t,Object.getOwnPropertyDescriptor(n,t))})}return e}function qe(e,t,n){return(t=Je(t))in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}function Je(e){var t=Ye(e,`string`);return Z(t)==`symbol`?t:t+``}function Ye(e,t){if(Z(e)!=`object`||!e)return e;var n=e[Symbol.toPrimitive];if(n!==void 0){var r=n.call(e,t);if(Z(r)!=`object`)return r;throw TypeError(`@@toPrimitive must return a primitive value.`)}return(t===`string`?String:Number)(e)}var Xe=[`data-p`],Ze=[`data-p`],Qe=[`data-p`],$e=[`data-p`],et=[`aria-label`,`data-p`];function tt(e,t,r,i,o,c){var l=D(`ripple`);return C(),s(`div`,g({class:[e.cx(`message`),r.message.styleClass],role:`alert`,"aria-live":`assertive`,"aria-atomic":`true`,"data-p":c.dataP},e.ptm(`message`),{onClick:t[1]||=function(){return c.onMessageClick&&c.onMessageClick.apply(c,arguments)},onMouseenter:t[2]||=function(){return c.handleMouseEnter&&c.handleMouseEnter.apply(c,arguments)},onMouseleave:t[3]||=function(){return c.handleMouseLeave&&c.handleMouseLeave.apply(c,arguments)}}),[r.templates.container?(C(),n(b(r.templates.container),{key:0,message:r.message,closeCallback:c.onCloseClick},null,8,[`message`,`closeCallback`])):(C(),s(`div`,g({key:1,class:[e.cx(`messageContent`),r.message.contentStyleClass]},e.ptm(`messageContent`)),[r.templates.message?(C(),n(b(r.templates.message),{key:1,message:r.message},null,8,[`message`])):(C(),s(a,{key:0},[(C(),n(b(r.templates.messageicon?r.templates.messageicon:r.templates.icon?r.templates.icon:c.iconComponent&&c.iconComponent.name?c.iconComponent:`span`),g({class:e.cx(`messageIcon`)},e.ptm(`messageIcon`)),null,16,[`class`])),R(`div`,g({class:e.cx(`messageText`),"data-p":c.dataP},e.ptm(`messageText`)),[R(`span`,g({class:e.cx(`summary`),"data-p":c.dataP},e.ptm(`summary`)),L(r.message.summary),17,Qe),r.message.detail?(C(),s(`div`,g({key:0,class:e.cx(`detail`),"data-p":c.dataP},e.ptm(`detail`)),L(r.message.detail),17,$e)):m(``,!0)],16,Ze)],64)),r.message.closable===!1?m(``,!0):(C(),s(`div`,re(g({key:2},e.ptm(`buttonContainer`))),[S((C(),s(`button`,g({class:e.cx(`closeButton`),type:`button`,"aria-label":c.closeAriaLabel,onClick:t[0]||=function(){return c.onCloseClick&&c.onCloseClick.apply(c,arguments)},autofocus:``,"data-p":c.dataP},Ke(Ke({},r.closeButtonProps),e.ptm(`closeButton`))),[(C(),n(b(r.templates.closeicon||`TimesIcon`),g({class:[e.cx(`closeIcon`),r.closeIcon]},e.ptm(`closeIcon`)),null,16,[`class`]))],16,et)),[[l]])],16))],16))],16,Xe)}We.render=tt;function Q(e){"@babel/helpers - typeof";return Q=typeof Symbol==`function`&&typeof Symbol.iterator==`symbol`?function(e){return typeof e}:function(e){return e&&typeof Symbol==`function`&&e.constructor===Symbol&&e!==Symbol.prototype?`symbol`:typeof e},Q(e)}function nt(e,t,n){return(t=rt(t))in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}function rt(e){var t=it(e,`string`);return Q(t)==`symbol`?t:t+``}function it(e,t){if(Q(e)!=`object`||!e)return e;var n=e[Symbol.toPrimitive];if(n!==void 0){var r=n.call(e,t);if(Q(r)!=`object`)return r;throw TypeError(`@@toPrimitive must return a primitive value.`)}return(t===`string`?String:Number)(e)}function at(e){return lt(e)||ct(e)||st(e)||ot()}function ot(){throw TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function st(e,t){if(e){if(typeof e==`string`)return ut(e,t);var n={}.toString.call(e).slice(8,-1);return n===`Object`&&e.constructor&&(n=e.constructor.name),n===`Map`||n===`Set`?Array.from(e):n===`Arguments`||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?ut(e,t):void 0}}function ct(e){if(typeof Symbol<`u`&&e[Symbol.iterator]!=null||e[`@@iterator`]!=null)return Array.from(e)}function lt(e){if(Array.isArray(e))return ut(e)}function ut(e,t){(t==null||t>e.length)&&(t=e.length);for(var n=0,r=Array(t);n<t;n++)r[n]=e[n];return r}var dt=0,ft={name:`Toast`,extends:Be,inheritAttrs:!1,emits:[`close`,`life-end`],data:function(){return{messages:[]}},styleElement:null,mounted:function(){K.on(`add`,this.onAdd),K.on(`remove`,this.onRemove),K.on(`remove-group`,this.onRemoveGroup),K.on(`remove-all-groups`,this.onRemoveAllGroups),this.breakpoints&&this.createStyle()},beforeUnmount:function(){this.destroyStyle(),this.$refs.container&&this.autoZIndex&&U.clear(this.$refs.container),K.off(`add`,this.onAdd),K.off(`remove`,this.onRemove),K.off(`remove-group`,this.onRemoveGroup),K.off(`remove-all-groups`,this.onRemoveAllGroups)},methods:{add:function(e){e.id??=dt++,this.messages=[].concat(at(this.messages),[e])},remove:function(e){var t=this.messages.findIndex(function(t){return t.id===e.message.id});t!==-1&&(this.messages.splice(t,1),this.$emit(e.type,{message:e.message}))},onAdd:function(e){this.group==e.group&&this.add(e)},onRemove:function(e){this.remove({message:e,type:`close`})},onRemoveGroup:function(e){this.group===e&&(this.messages=[])},onRemoveAllGroups:function(){var e=this;this.messages.forEach(function(t){return e.$emit(`close`,{message:t})}),this.messages=[]},onEnter:function(){this.autoZIndex&&U.set(`modal`,this.$refs.container,this.baseZIndex||this.$primevue.config.zIndex.modal)},onLeave:function(){var e=this;this.$refs.container&&this.autoZIndex&&T(this.messages)&&setTimeout(function(){U.clear(e.$refs.container)},200)},createStyle:function(){if(!this.styleElement&&!this.isUnstyled){var e;this.styleElement=document.createElement(`style`),this.styleElement.type=`text/css`,t(this.styleElement,`nonce`,(e=this.$primevue)==null||(e=e.config)==null||(e=e.csp)==null?void 0:e.nonce),document.head.appendChild(this.styleElement);var n=``;for(var r in this.breakpoints){var i=``;for(var a in this.breakpoints[r])i+=a+`:`+this.breakpoints[r][a]+`!important;`;n+=`
                        @media screen and (max-width: ${r}) {
                            .p-toast[${this.$attrSelector}] {
                                ${i}
                            }
                        }
                    `}this.styleElement.innerHTML=n}},destroyStyle:function(){this.styleElement&&=(document.head.removeChild(this.styleElement),null)}},computed:{dataP:function(){return B(nt({},this.position,this.position))}},components:{ToastMessage:We,Portal:G}};function $(e){"@babel/helpers - typeof";return $=typeof Symbol==`function`&&typeof Symbol.iterator==`symbol`?function(e){return typeof e}:function(e){return e&&typeof Symbol==`function`&&e.constructor===Symbol&&e!==Symbol.prototype?`symbol`:typeof e},$(e)}function pt(e,t){var n=Object.keys(e);if(Object.getOwnPropertySymbols){var r=Object.getOwnPropertySymbols(e);t&&(r=r.filter(function(t){return Object.getOwnPropertyDescriptor(e,t).enumerable})),n.push.apply(n,r)}return n}function mt(e){for(var t=1;t<arguments.length;t++){var n=arguments[t]==null?{}:arguments[t];t%2?pt(Object(n),!0).forEach(function(t){ht(e,t,n[t])}):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(n)):pt(Object(n)).forEach(function(t){Object.defineProperty(e,t,Object.getOwnPropertyDescriptor(n,t))})}return e}function ht(e,t,n){return(t=gt(t))in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}function gt(e){var t=_t(e,`string`);return $(t)==`symbol`?t:t+``}function _t(e,t){if($(e)!=`object`||!e)return e;var n=e[Symbol.toPrimitive];if(n!==void 0){var r=n.call(e,t);if($(r)!=`object`)return r;throw TypeError(`@@toPrimitive must return a primitive value.`)}return(t===`string`?String:Number)(e)}var vt=[`data-p`];function yt(t,r,i,c,l,u){var d=A(`ToastMessage`),f=A(`Portal`);return C(),n(f,null,{default:N(function(){return[R(`div`,g({ref:`container`,class:t.cx(`root`),style:t.sx(`root`,!0,{position:t.position}),"data-p":u.dataP},t.ptmi(`root`)),[o(e,g({name:`p-toast-message`,tag:`div`,onEnter:u.onEnter,onLeave:u.onLeave},mt({},t.ptm(`transition`))),{default:N(function(){return[(C(!0),s(a,null,M(l.messages,function(e){return C(),n(d,{key:e.id,message:e,templates:t.$slots,closeIcon:t.closeIcon,infoIcon:t.infoIcon,warnIcon:t.warnIcon,errorIcon:t.errorIcon,successIcon:t.successIcon,closeButtonProps:t.closeButtonProps,onMouseEnter:t.onMouseEnter,onMouseLeave:t.onMouseLeave,onClick:t.onClick,unstyled:t.unstyled,onClose:r[0]||=function(e){return u.remove(e)},pt:t.pt},null,8,[`message`,`templates`,`closeIcon`,`infoIcon`,`warnIcon`,`errorIcon`,`successIcon`,`closeButtonProps`,`onMouseEnter`,`onMouseLeave`,`onClick`,`unstyled`,`pt`])}),128))]}),_:1},16,[`onEnter`,`onLeave`])],16,vt)]}),_:1})}ft.render=yt;var bt={class:`flex h-full flex-col`},xt={class:`relative shrink-0 p-5`},St=[`title`,`aria-label`],Ct=[`title`,`aria-label`],wt={key:0,class:`lg:flex lg:items-center lg:justify-center`},Tt=[`src`,`alt`],Et=[`src`,`alt`],Dt={key:1,class:`text-2xl font-bold leading-tight`},Ot={class:`min-h-0 flex-1 space-y-4 overflow-y-auto px-3 pb-4`},kt=[`onClick`],At=`vita_admin_sidebar_groups_v1`,jt=`vita_admin_sidebar_hover_pinned_v1`,Mt={__name:`AdminSidebar`,props:{collapsed:{type:Boolean,default:!1},items:{type:Array,default:()=>[]},mobileOpen:{type:Boolean,default:!1},behavior:{type:String,default:`default`}},emits:[`closeMobile`],setup(e,{emit:t}){let i=new Set([`small_hover`,`small_hover_active`]),o={default:{desktopMode:`persistent`,expandedWidthClass:`lg:w-(--sidebar-width)`,collapsedWidthClass:`lg:w-(--sidebar-collapsed-width)`,autoHover:!1,hoverRequiresActive:!1},condensed:{desktopMode:`persistent`,expandedWidthClass:`lg:w-[15rem]`,collapsedWidthClass:`lg:w-[4.5rem]`,autoHover:!1,hoverRequiresActive:!1},hidden:{desktopMode:`drawer`,expandedWidthClass:`lg:w-(--sidebar-width)`,collapsedWidthClass:`lg:w-(--sidebar-collapsed-width)`,autoHover:!1,hoverRequiresActive:!1},small_hover:{desktopMode:`persistent`,expandedWidthClass:`lg:w-(--sidebar-width)`,collapsedWidthClass:`lg:w-(--sidebar-collapsed-width)`,autoHover:!0,hoverRequiresActive:!1},small_hover_active:{desktopMode:`persistent`,expandedWidthClass:`lg:w-(--sidebar-width)`,collapsedWidthClass:`lg:w-(--sidebar-collapsed-width)`,autoHover:!0,hoverRequiresActive:!0}},c=e,l=t,d=ee(),{t:p}=le(),{settings:h}=ue(),{mode:g}=se(),v=f(()=>d.url.split(`?`)[0]),y=f(()=>h.value.brandName||d.props.app?.name||p(`common.app`)),b=f(()=>g.value===`dark`?h.value.logoDarkUrl??h.value.logoLightUrl??h.value.logoUrl??``:h.value.logoLightUrl??h.value.logoDarkUrl??h.value.logoUrl??``),S=f(()=>h.value.iconUrl??``),w=f(()=>{let e=h.value.direction===`rtl`;return O.value?e?`pi pi-angle-double-left`:`pi pi-angle-double-right`:e?`pi pi-angle-double-right`:`pi pi-angle-double-left`}),T=E({}),D=E(!1),O=E(!1),k=E(!1),A=()=>{typeof window>`u`||(k.value=window.innerWidth>=1024)},j=f(()=>o[c.behavior]??o.default),P=f(()=>j.value.autoHover),I=f(()=>j.value.desktopMode===`drawer`),re=f(()=>V.value.some(e=>U(e))),ie=f(()=>j.value.expandedWidthClass),z=f(()=>j.value.collapsedWidthClass),B=f(()=>k.value?I.value?!c.mobileOpen:P.value?O.value?!1:j.value.hoverRequiresActive&&!re.value?!0:!D.value:c.collapsed:!1),V=f(()=>{let e=c.items??[];if(!Array.isArray(e)||e.length===0)return[];if(e.every(e=>Array.isArray(e?.items)))return e.map((e,t)=>({id:e.id??e.groupId??e.group??e.groupKey??`group-${t}`,label:e.labelKey?p(e.labelKey):e.label??`Navigation`,collapsible:!0,items:(e.items??[]).map(e=>({...e,label:e.labelKey?p(e.labelKey):e.label}))}));let t=[],n=new Map;return e.forEach(e=>{if(!e.group&&!e.groupKey){t.push({id:`single:${e.href}`,label:``,collapsible:!1,items:[{...e,label:e.labelKey?p(e.labelKey):e.label}]});return}let r=e.groupId??e.group??e.groupKey,i=e.groupKey?p(e.groupKey):e.group;n.has(r)||(n.set(r,t.length),t.push({id:r,label:i,collapsible:!0,items:[]})),t[n.get(r)].items.push({...e,label:e.labelKey?p(e.labelKey):e.label})}),t}),H=e=>v.value===e||v.value.startsWith(`${e}/`),U=e=>e.items.some(e=>H(e.href)),ae=e=>{T.value[e]=!T.value[e]};ne(()=>{let e={...T.value};V.value.forEach(t=>{!t.collapsible||Object.prototype.hasOwnProperty.call(e,t.id)||(e[t.id]=U(t))}),T.value=e});let W=e=>{typeof window<`u`&&window.innerWidth>=1024&&P.value&&(D.value=e)},G=()=>{P.value&&(O.value=!O.value,O.value&&(D.value=!0))};u(()=>{if(typeof window>`u`)return;A(),window.addEventListener(`resize`,A);let e=window.localStorage.getItem(At);if(e)try{let t=JSON.parse(e);t&&typeof t==`object`&&!Array.isArray(t)&&(T.value=t)}catch{}O.value=window.localStorage.getItem(jt)===`true`}),te(()=>{typeof window>`u`||window.removeEventListener(`resize`,A)}),x(T,e=>{typeof window>`u`||window.localStorage.setItem(At,JSON.stringify(e))},{deep:!0}),x(()=>c.collapsed,e=>{e||(D.value=!1)}),x(O,e=>{typeof window>`u`||window.localStorage.setItem(jt,String(e))}),x(()=>c.behavior,e=>{i.has(e)||(O.value=!1,D.value=!1)});let oe=f(()=>h.value.direction===`rtl`?`translate-x-full lg:translate-x-0`:`-translate-x-full lg:translate-x-0`),K=f(()=>h.value.direction===`rtl`?`translate-x-full`:`-translate-x-full`),ce=f(()=>I.value?`fixed inset-y-0 z-40 w-(--sidebar-width) shrink-0 overflow-hidden bg-(--card) [inset-inline-start:0] [border-inline-end:1px_solid_var(--border)] h-screen transition-[transform,width] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]`:`fixed inset-y-0 z-40 w-(--sidebar-width) shrink-0 overflow-hidden bg-(--card) [inset-inline-start:0] [border-inline-end:1px_solid_var(--border)] lg:sticky lg:top-0 lg:z-30 lg:h-screen transition-[transform,width] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]`),de=f(()=>c.mobileOpen),fe=f(()=>I.value?`fixed inset-0 z-30 bg-black/40`:`fixed inset-0 z-30 bg-black/40 lg:hidden`),pe=f(()=>I.value?c.mobileOpen?`translate-x-0`:K.value:c.mobileOpen?`translate-x-0`:oe.value);return(t,i)=>(C(),s(a,null,[de.value?(C(),s(`div`,{key:0,class:F(fe.value),onClick:i[0]||=e=>l(`closeMobile`)},null,2)):m(``,!0),R(`aside`,{class:F([ce.value,pe.value,B.value?z.value:ie.value]),onMouseenter:i[3]||=e=>W(!0),onMouseleave:i[4]||=e=>W(!1)},[R(`div`,bt,[R(`div`,xt,[P.value&&!B.value&&!e.mobileOpen?(C(),s(`button`,{key:0,type:`button`,class:`absolute top-3 inline-flex h-9 w-9 items-center justify-center rounded-sm text-(--muted-foreground) transition hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] hover:text-(--foreground) inset-e-3`,title:O.value?_(p)(`topbar.collapseSidebar`):_(p)(`topbar.expandSidebar`),"aria-label":O.value?_(p)(`topbar.collapseSidebar`):_(p)(`topbar.expandSidebar`),onClick:G},[R(`i`,{class:F(w.value)},null,2)],8,St)):m(``,!0),e.mobileOpen?(C(),s(`button`,{key:1,type:`button`,class:`absolute top-3 inline-flex h-9 w-9 items-center justify-center rounded-sm text-(--muted-foreground) transition hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] hover:text-(--foreground) inset-e-3`,title:_(p)(`common.close`),"aria-label":_(p)(`common.close`),onClick:i[1]||=e=>l(`closeMobile`)},[...i[5]||=[R(`i`,{class:`pi pi-times`},null,-1)]],8,Ct)):m(``,!0),R(`div`,{class:F([`flex items-center gap-3`,B.value?`lg:justify-center`:``])},[B.value?(C(),s(`div`,wt,[S.value?(C(),s(`img`,{key:0,src:S.value,alt:_(p)(`settings.currentAppIcon`),class:`h-10 w-10 object-contain`},null,8,Tt)):m(``,!0)])):m(``,!0),R(`div`,{class:F(B.value?`lg:hidden`:``)},[b.value?(C(),s(`img`,{key:0,src:b.value,alt:`${y.value} logo`,class:`h-9 w-auto max-w-44 object-contain`},null,8,Et)):(C(),s(`p`,Dt,L(y.value),1))],2)],2)]),R(`nav`,Ot,[(C(!0),s(a,null,M(V.value,e=>(C(),s(`section`,{key:e.id,class:`space-y-1`},[!B.value&&e.collapsible?(C(),s(`button`,{key:0,type:`button`,class:`flex w-full items-center justify-between rounded-sm px-2 py-2 text-sm font-semibold tracking-wide text-(--muted-foreground) uppercase transition-colors duration-200 hover:bg-[color-mix(in_oklab,var(--accent)_8%,transparent)]`,onClick:t=>ae(e.id)},[R(`span`,null,L(e.label),1),R(`i`,{class:F(T.value[e.id]?`pi pi-angle-down`:`pi pi-angle-right`)},null,2)],8,kt)):m(``,!0),B.value||!e.collapsible||T.value[e.id]?(C(),s(`div`,{key:1,class:F([`relative space-y-1`,B.value||!e.collapsible?``:`before:absolute before:inset-y-2 before:inset-s-[0.85rem] before:w-px before:bg-[color-mix(in_oklab,var(--border)_88%,transparent)]`])},[(C(!0),s(a,null,M(e.items,t=>(C(),n(_(r),{key:t.href,href:t.href,class:F([`relative flex items-center gap-3 rounded-[calc(var(--radius-base)-0.25rem)] py-3.5 text-base font-medium transition-all duration-200`,[B.value?`justify-center px-2`:e.collapsible?`ps-7 pe-3`:`px-3`,H(t.href)?`bg-(--accent) text-(--accent-contrast) shadow-[inset_0_0_0_1px_color-mix(in_oklab,var(--accent)_65%,white)]`:`text-(--muted-foreground) hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] hover:text-(--foreground)`]]),title:t.label,onClick:i[2]||=e=>l(`closeMobile`)},{default:N(()=>[R(`i`,{class:F([t.icon??`pi pi-circle`,`text-sm`])},null,2),R(`span`,{class:F([`transition-opacity duration-200`,B.value?`hidden opacity-0`:`opacity-100`])},L(t.label),3)]),_:2},1032,[`href`,`title`,`class`]))),128))],2)):m(``,!0)]))),128))])])],34)],64))}},Nt=y.extend({name:`menu`,style:`
    .p-menu {
        background: dt('menu.background');
        color: dt('menu.color');
        border: 1px solid dt('menu.border.color');
        border-radius: dt('menu.border.radius');
        min-width: 12.5rem;
    }

    .p-menu-list {
        margin: 0;
        padding: dt('menu.list.padding');
        outline: 0 none;
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: dt('menu.list.gap');
    }

    .p-menu-item-content {
        transition:
            background dt('menu.transition.duration'),
            color dt('menu.transition.duration');
        border-radius: dt('menu.item.border.radius');
        color: dt('menu.item.color');
        overflow: hidden;
    }

    .p-menu-item-link {
        cursor: pointer;
        display: flex;
        align-items: center;
        text-decoration: none;
        overflow: hidden;
        position: relative;
        color: inherit;
        padding: dt('menu.item.padding');
        gap: dt('menu.item.gap');
        user-select: none;
        outline: 0 none;
    }

    .p-menu-item-label {
        line-height: 1;
    }

    .p-menu-item-icon {
        color: dt('menu.item.icon.color');
    }

    .p-menu-item.p-focus .p-menu-item-content {
        color: dt('menu.item.focus.color');
        background: dt('menu.item.focus.background');
    }

    .p-menu-item.p-focus .p-menu-item-icon {
        color: dt('menu.item.icon.focus.color');
    }

    .p-menu-item:not(.p-disabled) .p-menu-item-content:hover {
        color: dt('menu.item.focus.color');
        background: dt('menu.item.focus.background');
    }

    .p-menu-item:not(.p-disabled) .p-menu-item-content:hover .p-menu-item-icon {
        color: dt('menu.item.icon.focus.color');
    }

    .p-menu-overlay {
        box-shadow: dt('menu.shadow');
    }

    .p-menu-submenu-label {
        background: dt('menu.submenu.label.background');
        padding: dt('menu.submenu.label.padding');
        color: dt('menu.submenu.label.color');
        font-weight: dt('menu.submenu.label.font.weight');
    }

    .p-menu-separator {
        border-block-start: 1px solid dt('menu.separator.border.color');
    }
`,classes:{root:function(e){var t=e.props;return[`p-menu p-component`,{"p-menu-overlay":t.popup}]},start:`p-menu-start`,list:`p-menu-list`,submenuLabel:`p-menu-submenu-label`,separator:`p-menu-separator`,end:`p-menu-end`,item:function(e){var t=e.instance;return[`p-menu-item`,{"p-focus":t.id===t.focusedOptionId,"p-disabled":t.disabled()}]},itemContent:`p-menu-item-content`,itemLink:`p-menu-item-link`,itemIcon:`p-menu-item-icon`,itemLabel:`p-menu-item-label`}}),Pt={name:`BaseMenu`,extends:z,props:{popup:{type:Boolean,default:!1},model:{type:Array,default:null},appendTo:{type:[String,Object],default:`body`},autoZIndex:{type:Boolean,default:!0},baseZIndex:{type:Number,default:0},tabindex:{type:Number,default:0},ariaLabel:{type:String,default:null},ariaLabelledby:{type:String,default:null}},style:Nt,provide:function(){return{$pcMenu:this,$parentInstance:this}}},Ft={name:`Menuitem`,hostName:`Menu`,extends:z,inheritAttrs:!1,emits:[`item-click`,`item-mousemove`],props:{item:null,templates:null,id:null,focusedOptionId:null,index:null},methods:{getItemProp:function(e,t){return e&&e.item?v(e.item[t]):void 0},getPTOptions:function(e){return this.ptm(e,{context:{item:this.item,index:this.index,focused:this.isItemFocused(),disabled:this.disabled()}})},isItemFocused:function(){return this.focusedOptionId===this.id},onItemClick:function(e){var t=this.getItemProp(this.item,`command`);t&&t({originalEvent:e,item:this.item.item}),this.$emit(`item-click`,{originalEvent:e,item:this.item,id:this.id})},onItemMouseMove:function(e){this.$emit(`item-mousemove`,{originalEvent:e,item:this.item,id:this.id})},visible:function(){return typeof this.item.visible==`function`?this.item.visible():this.item.visible!==!1},disabled:function(){return typeof this.item.disabled==`function`?this.item.disabled():this.item.disabled},label:function(){return typeof this.item.label==`function`?this.item.label():this.item.label},getMenuItemProps:function(e){return{action:g({class:this.cx(`itemLink`),tabindex:`-1`},this.getPTOptions(`itemLink`)),icon:g({class:[this.cx(`itemIcon`),e.icon]},this.getPTOptions(`itemIcon`)),label:g({class:this.cx(`itemLabel`)},this.getPTOptions(`itemLabel`))}}},computed:{dataP:function(){return B({focus:this.isItemFocused(),disabled:this.disabled()})}},directives:{ripple:j}},It=[`id`,`aria-label`,`aria-disabled`,`data-p-focused`,`data-p-disabled`,`data-p`],Lt=[`data-p`],Rt=[`href`,`target`],zt=[`data-p`],Bt=[`data-p`];function Vt(e,t,r,i,a,o){var c=D(`ripple`);return o.visible()?(C(),s(`li`,g({key:0,id:r.id,class:[e.cx(`item`),r.item.class],role:`menuitem`,style:r.item.style,"aria-label":o.label(),"aria-disabled":o.disabled(),"data-p-focused":o.isItemFocused(),"data-p-disabled":o.disabled()||!1,"data-p":o.dataP},o.getPTOptions(`item`)),[R(`div`,g({class:e.cx(`itemContent`),onClick:t[0]||=function(e){return o.onItemClick(e)},onMousemove:t[1]||=function(e){return o.onItemMouseMove(e)},"data-p":o.dataP},o.getPTOptions(`itemContent`)),[r.templates.item?r.templates.item?(C(),n(b(r.templates.item),{key:1,item:r.item,label:o.label(),props:o.getMenuItemProps(r.item)},null,8,[`item`,`label`,`props`])):m(``,!0):S((C(),s(`a`,g({key:0,href:r.item.url,class:e.cx(`itemLink`),target:r.item.target,tabindex:`-1`},o.getPTOptions(`itemLink`)),[r.templates.itemicon?(C(),n(b(r.templates.itemicon),{key:0,item:r.item,class:F(e.cx(`itemIcon`))},null,8,[`item`,`class`])):r.item.icon?(C(),s(`span`,g({key:1,class:[e.cx(`itemIcon`),r.item.icon],"data-p":o.dataP},o.getPTOptions(`itemIcon`)),null,16,zt)):m(``,!0),R(`span`,g({class:e.cx(`itemLabel`),"data-p":o.dataP},o.getPTOptions(`itemLabel`)),L(o.label()),17,Bt)],16,Rt)),[[c]])],16,Lt)],16,It)):m(``,!0)}Ft.render=Vt;function Ht(e){return Kt(e)||Gt(e)||Wt(e)||Ut()}function Ut(){throw TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function Wt(e,t){if(e){if(typeof e==`string`)return qt(e,t);var n={}.toString.call(e).slice(8,-1);return n===`Object`&&e.constructor&&(n=e.constructor.name),n===`Map`||n===`Set`?Array.from(e):n===`Arguments`||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?qt(e,t):void 0}}function Gt(e){if(typeof Symbol<`u`&&e[Symbol.iterator]!=null||e[`@@iterator`]!=null)return Array.from(e)}function Kt(e){if(Array.isArray(e))return qt(e)}function qt(e,t){(t==null||t>e.length)&&(t=e.length);for(var n=0,r=Array(t);n<t;n++)r[n]=e[n];return r}var Jt={name:`Menu`,extends:Pt,inheritAttrs:!1,emits:[`show`,`hide`,`focus`,`blur`],data:function(){return{overlayVisible:!1,focused:!1,focusedOptionIndex:-1,selectedOptionIndex:-1}},target:null,outsideClickListener:null,scrollHandler:null,resizeListener:null,container:null,list:null,mounted:function(){this.popup||(this.bindResizeListener(),this.bindOutsideClickListener())},beforeUnmount:function(){this.unbindResizeListener(),this.unbindOutsideClickListener(),this.scrollHandler&&=(this.scrollHandler.destroy(),null),this.target=null,this.container&&this.autoZIndex&&U.clear(this.container),this.container=null},methods:{itemClick:function(e){var t=e.item;this.disabled(t)||(t.command&&t.command(e),this.overlayVisible&&this.hide(),!this.popup&&this.focusedOptionIndex!==e.id&&(this.focusedOptionIndex=e.id))},itemMouseMove:function(e){this.focused&&(this.focusedOptionIndex=e.id)},onListFocus:function(e){this.focused=!0,!this.popup&&this.changeFocusedOptionIndex(0),this.$emit(`focus`,e)},onListBlur:function(e){this.focused=!1,this.focusedOptionIndex=-1,this.$emit(`blur`,e)},onListKeyDown:function(e){switch(e.code){case`ArrowDown`:this.onArrowDownKey(e);break;case`ArrowUp`:this.onArrowUpKey(e);break;case`Home`:this.onHomeKey(e);break;case`End`:this.onEndKey(e);break;case`Enter`:case`NumpadEnter`:this.onEnterKey(e);break;case`Space`:this.onSpaceKey(e);break;case`Escape`:this.popup&&(p(this.target),this.hide());case`Tab`:this.overlayVisible&&this.hide();break}},onArrowDownKey:function(e){var t=this.findNextOptionIndex(this.focusedOptionIndex);this.changeFocusedOptionIndex(t),e.preventDefault()},onArrowUpKey:function(e){if(e.altKey&&this.popup)p(this.target),this.hide(),e.preventDefault();else{var t=this.findPrevOptionIndex(this.focusedOptionIndex);this.changeFocusedOptionIndex(t),e.preventDefault()}},onHomeKey:function(e){this.changeFocusedOptionIndex(0),e.preventDefault()},onEndKey:function(e){this.changeFocusedOptionIndex(d(this.container,`li[data-pc-section="item"][data-p-disabled="false"]`).length-1),e.preventDefault()},onEnterKey:function(e){var t=w(this.list,`li[id="${`${this.focusedOptionIndex}`}"]`),n=t&&w(t,`a[data-pc-section="itemlink"]`);this.popup&&p(this.target),n?n.click():t&&t.click(),e.preventDefault()},onSpaceKey:function(e){this.onEnterKey(e)},findNextOptionIndex:function(e){var t=Ht(d(this.container,`li[data-pc-section="item"][data-p-disabled="false"]`)).findIndex(function(t){return t.id===e});return t>-1?t+1:0},findPrevOptionIndex:function(e){var t=Ht(d(this.container,`li[data-pc-section="item"][data-p-disabled="false"]`)).findIndex(function(t){return t.id===e});return t>-1?t-1:0},changeFocusedOptionIndex:function(e){var t=d(this.container,`li[data-pc-section="item"][data-p-disabled="false"]`),n=e>=t.length?t.length-1:e<0?0:e;n>-1&&(this.focusedOptionIndex=t[n].getAttribute(`id`))},toggle:function(e,t){this.overlayVisible?this.hide():this.show(e,t)},show:function(e,t){this.overlayVisible=!0,this.target=t??e.currentTarget},hide:function(){this.overlayVisible=!1,this.target=null},onEnter:function(e){c(e,{position:`absolute`,top:`0`}),this.alignOverlay(),this.bindOutsideClickListener(),this.bindResizeListener(),this.bindScrollListener(),this.autoZIndex&&U.set(`menu`,e,this.baseZIndex+this.$primevue.config.zIndex.menu),this.popup&&p(this.list),this.$emit(`show`)},onLeave:function(){this.unbindOutsideClickListener(),this.unbindResizeListener(),this.unbindScrollListener(),this.$emit(`hide`)},onAfterLeave:function(e){this.autoZIndex&&U.clear(e)},alignOverlay:function(){P(this.container,this.target),l(this.target)>l(this.container)&&(this.container.style.minWidth=l(this.target)+`px`)},bindOutsideClickListener:function(){var e=this;this.outsideClickListener||(this.outsideClickListener=function(t){var n=e.container&&!e.container.contains(t.target),r=!(e.target&&(e.target===t.target||e.target.contains(t.target)));e.overlayVisible&&n&&r?e.hide():!e.popup&&n&&r&&(e.focusedOptionIndex=-1)},document.addEventListener(`click`,this.outsideClickListener,!0))},unbindOutsideClickListener:function(){this.outsideClickListener&&=(document.removeEventListener(`click`,this.outsideClickListener,!0),null)},bindScrollListener:function(){var e=this;this.scrollHandler||=new ae(this.target,function(){e.overlayVisible&&e.hide()}),this.scrollHandler.bindScrollListener()},unbindScrollListener:function(){this.scrollHandler&&this.scrollHandler.unbindScrollListener()},bindResizeListener:function(){var e=this;this.resizeListener||(this.resizeListener=function(){e.overlayVisible&&!ie()&&e.hide()},window.addEventListener(`resize`,this.resizeListener))},unbindResizeListener:function(){this.resizeListener&&=(window.removeEventListener(`resize`,this.resizeListener),null)},visible:function(e){return typeof e.visible==`function`?e.visible():e.visible!==!1},disabled:function(e){return typeof e.disabled==`function`?e.disabled():e.disabled},label:function(e){return typeof e.label==`function`?e.label():e.label},onOverlayClick:function(e){oe.emit(`overlay-click`,{originalEvent:e,target:this.target})},containerRef:function(e){this.container=e},listRef:function(e){this.list=e}},computed:{focusedOptionId:function(){return this.focusedOptionIndex===-1?null:this.focusedOptionIndex},dataP:function(){return B({popup:this.popup})}},components:{PVMenuitem:Ft,Portal:G}},Yt=[`id`,`data-p`],Xt=[`id`,`tabindex`,`aria-activedescendant`,`aria-label`,`aria-labelledby`],Zt=[`id`];function Qt(e,t,r,i,c,l){var u=A(`PVMenuitem`),d=A(`Portal`);return C(),n(d,{appendTo:e.appendTo,disabled:!e.popup},{default:N(function(){return[o(O,g({name:`p-anchored-overlay`,onEnter:l.onEnter,onLeave:l.onLeave,onAfterLeave:l.onAfterLeave},e.ptm(`transition`)),{default:N(function(){return[!e.popup||c.overlayVisible?(C(),s(`div`,g({key:0,ref:l.containerRef,id:e.$id,class:e.cx(`root`),onClick:t[3]||=function(){return l.onOverlayClick&&l.onOverlayClick.apply(l,arguments)},"data-p":l.dataP},e.ptmi(`root`)),[e.$slots.start?(C(),s(`div`,g({key:0,class:e.cx(`start`)},e.ptm(`start`)),[k(e.$slots,`start`)],16)):m(``,!0),R(`ul`,g({ref:l.listRef,id:e.$id+`_list`,class:e.cx(`list`),role:`menu`,tabindex:e.tabindex,"aria-activedescendant":c.focused?l.focusedOptionId:void 0,"aria-label":e.ariaLabel,"aria-labelledby":e.ariaLabelledby,onFocus:t[0]||=function(){return l.onListFocus&&l.onListFocus.apply(l,arguments)},onBlur:t[1]||=function(){return l.onListBlur&&l.onListBlur.apply(l,arguments)},onKeydown:t[2]||=function(){return l.onListKeyDown&&l.onListKeyDown.apply(l,arguments)}},e.ptm(`list`)),[(C(!0),s(a,null,M(e.model,function(t,r){return C(),s(a,{key:l.label(t)+r.toString()},[t.items&&l.visible(t)&&!t.separator?(C(),s(a,{key:0},[t.items?(C(),s(`li`,g({key:0,id:e.$id+`_`+r,class:[e.cx(`submenuLabel`),t.class],role:`none`},{ref_for:!0},e.ptm(`submenuLabel`)),[k(e.$slots,e.$slots.submenulabel?`submenulabel`:`submenuheader`,{item:t},function(){return[h(L(l.label(t)),1)]})],16,Zt)):m(``,!0),(C(!0),s(a,null,M(t.items,function(i,o){return C(),s(a,{key:i.label+r+`_`+o},[l.visible(i)&&!i.separator?(C(),n(u,{key:0,id:e.$id+`_`+r+`_`+o,item:i,templates:e.$slots,focusedOptionId:l.focusedOptionId,unstyled:e.unstyled,onItemClick:l.itemClick,onItemMousemove:l.itemMouseMove,pt:e.pt},null,8,[`id`,`item`,`templates`,`focusedOptionId`,`unstyled`,`onItemClick`,`onItemMousemove`,`pt`])):l.visible(i)&&i.separator?(C(),s(`li`,g({key:`separator`+r+o,class:[e.cx(`separator`),t.class],style:i.style,role:`separator`},{ref_for:!0},e.ptm(`separator`)),null,16)):m(``,!0)],64)}),128))],64)):l.visible(t)&&t.separator?(C(),s(`li`,g({key:`separator`+r.toString(),class:[e.cx(`separator`),t.class],style:t.style,role:`separator`},{ref_for:!0},e.ptm(`separator`)),null,16)):(C(),n(u,{key:l.label(t)+r.toString(),id:e.$id+`_`+r,item:t,index:r,templates:e.$slots,focusedOptionId:l.focusedOptionId,unstyled:e.unstyled,onItemClick:l.itemClick,onItemMousemove:l.itemMouseMove,pt:e.pt},null,8,[`id`,`item`,`index`,`templates`,`focusedOptionId`,`unstyled`,`onItemClick`,`onItemMousemove`,`pt`]))],64)}),128))],16,Xt),e.$slots.end?(C(),s(`div`,g({key:1,class:e.cx(`end`)},e.ptm(`end`)),[k(e.$slots,`end`)],16)):m(``,!0)],16,Yt)):m(``,!0)]}),_:3},16,[`onEnter`,`onLeave`,`onAfterLeave`])]}),_:3},8,[`appendTo`,`disabled`])}Jt.render=Qt;var $t={class:`sticky top-0 z-20 border-b border-(--border) bg-(--card)/95 px-4 py-3 text-(--card-foreground) backdrop-blur sm:px-6`},en={class:`flex min-h-(--topbar-height) items-center justify-between gap-3`},tn={class:`flex min-w-0 items-center gap-2`},nn=[`aria-label`,`title`],rn=[`aria-label`,`title`],an={class:`min-w-0`},on={class:`truncate text-2xl font-semibold`},sn={class:`flex items-center gap-2 sm:gap-3`},cn=[`aria-label`,`title`],ln=[`aria-label`,`title`],un=[`aria-label`,`title`],dn=[`aria-label`],fn={class:`flex h-10 w-10 items-center justify-center rounded-full bg-(--accent) text-sm font-semibold text-(--accent-contrast)`},pn={class:`border-b border-(--border) px-4 py-3`},mn={class:`text-sm font-semibold`},hn={class:`text-xs text-(--muted-foreground)`},gn={class:`text-current`},_n={__name:`AdminTopbar`,props:{collapsed:{type:Boolean,default:!1},title:{type:String,default:``},behavior:{type:String,default:`default`}},emits:[`toggleSidebar`,`toggleMobileSidebar`],setup(e,{emit:t}){let n=e,r=t,a=ee(),{t:c}=le(),l=f(()=>a.props.auth?.user?.name??c(`topbar.admin`)),d=f(()=>a.props.auth?.user?.email??``),p=f(()=>l.value.trim().split(/\s+/).filter(Boolean).slice(0,2).map(e=>e[0]).join(``).toUpperCase()||`AD`),h=I({}),v=E(),{mode:y,toggleMode:b}=se(),{settings:x,setLanguage:w,setDirection:T}=ue(),O=f(()=>y.value===`dark`?`pi pi-sun`:`pi pi-moon`),ne=f(()=>y.value===`dark`?c(`topbar.lightMode`):c(`topbar.darkMode`)),k=E(!1),A=f(()=>x.value.language===`ar`?`en`:`ar`),j=f(()=>A.value===`ar`?`AR`:`EN`),M=f(()=>A.value===`ar`?c(`settings.arabic`):c(`settings.english`)),P=f(()=>{if(n.behavior===`condensed`&&n.collapsed)return`pi pi-bars`;let e=x.value.direction===`rtl`;return n.collapsed?e?`pi pi-angle-double-left`:`pi pi-angle-double-right`:e?`pi pi-angle-double-right`:`pi pi-angle-double-left`}),re=f(()=>n.behavior===`hidden`),ie=f(()=>n.behavior===`condensed`),z=()=>{typeof document>`u`||(k.value=!!document.fullscreenElement)},B=f(()=>k.value?`pi pi-window-minimize`:`pi pi-window-maximize`),V=f(()=>k.value?c(`topbar.exitFullscreen`):c(`topbar.enterFullscreen`)),H=async()=>{typeof document>`u`||(document.fullscreenElement?await document.exitFullscreen?.():await document.documentElement.requestFullscreen?.())},U=e=>{w(e),e===`ar`&&T(`rtl`),e===`en`&&T(`ltr`)},ae=()=>{U(A.value)},W=f(()=>[{label:c(`profile.updatePassword`),icon:`pi pi-key`,command:()=>i.get(`/admin/password`)},{label:c(`topbar.logout`),icon:`pi pi-sign-out`,severity:`danger`,command:()=>h.post(`/admin/logout`)}]),G=e=>{v.value?.toggle(e)};return u(()=>{z(),document.addEventListener(`fullscreenchange`,z)}),te(()=>{document.removeEventListener(`fullscreenchange`,z)}),(e,t)=>{let i=D(`ripple`);return C(),s(`header`,$t,[R(`div`,en,[R(`div`,tn,[R(`button`,{type:`button`,class:F([`inline-flex h-11 w-11 items-center justify-center rounded-md bg-transparent text-base text-(--foreground) transition hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)]`,re.value?``:`lg:hidden`]),"aria-label":_(c)(`topbar.openSidebar`),title:_(c)(`topbar.openSidebar`),onClick:t[0]||=e=>r(`toggleMobileSidebar`)},[...t[3]||=[R(`i`,{class:`pi pi-bars`},null,-1)]],10,nn),ie.value?(C(),s(`button`,{key:0,type:`button`,class:`hidden h-11 w-11 items-center justify-center rounded-md bg-transparent text-base text-(--foreground) transition hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] lg:inline-flex`,"aria-label":n.collapsed?_(c)(`topbar.expandSidebar`):_(c)(`topbar.collapseSidebar`),title:n.collapsed?_(c)(`topbar.expandSidebar`):_(c)(`topbar.collapseSidebar`),onClick:t[1]||=e=>r(`toggleSidebar`)},[R(`i`,{class:F(P.value)},null,2)],8,rn)):m(``,!0),R(`div`,an,[R(`h1`,on,L(n.title),1)])]),R(`div`,sn,[R(`button`,{type:`button`,class:`inline-flex h-11 items-center gap-2 rounded-md bg-(--background) px-3 text-sm font-semibold text-(--foreground) transition hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)]`,"aria-label":M.value,title:M.value,onClick:ae},[t[4]||=R(`i`,{class:`pi pi-language text-sm text-(--muted-foreground)`},null,-1),R(`span`,null,L(j.value),1)],8,cn),R(`button`,{type:`button`,class:`inline-flex h-11 w-11 items-center justify-center rounded-md bg-transparent text-base text-(--foreground) transition hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)]`,"aria-label":ne.value,title:ne.value,onClick:t[2]||=(...e)=>_(b)&&_(b)(...e)},[R(`i`,{class:F(O.value)},null,2)],8,ln),R(`button`,{type:`button`,class:`hidden h-11 w-11 items-center justify-center rounded-md bg-transparent text-base text-(--foreground) transition hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)] lg:inline-flex`,"aria-label":V.value,title:V.value,onClick:H},[R(`i`,{class:F(B.value)},null,2)],8,un),R(`button`,{type:`button`,class:`rounded-full border-0 bg-transparent p-0 hover:bg-transparent`,"aria-label":_(c)(`topbar.openProfileMenu`),onClick:G},[R(`div`,fn,L(p.value),1)],8,dn),o(_(Jt),{ref_key:`profileMenu`,ref:v,model:W.value,popup:``},{start:N(()=>[R(`div`,pn,[R(`p`,mn,L(l.value),1),R(`p`,hn,L(d.value),1)])]),item:N(({item:e,props:t})=>[S((C(),s(`a`,g(t.action,{class:[`flex items-center gap-2 rounded-sm px-3 py-2 text-base font-medium transition-colors`,e.severity===`danger`?`text-(--foreground) hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-900/30 dark:hover:text-rose-300`:`text-(--foreground) hover:bg-[color-mix(in_oklab,var(--accent)_12%,transparent)]`]}),[R(`i`,{class:F([e.icon,`text-base text-current`])},null,2),R(`span`,gn,L(e.label),1)],16)),[[i]])]),_:1},8,[`model`])])])])}}},vn={class:`min-h-screen bg-(--background) text-(--foreground) transition-colors`},yn={class:`flex min-h-screen w-full flex-col lg:flex-row`},bn={class:`min-w-0 flex-1`},xn={class:`p-4 sm:p-6`},Sn=`vita_admin_sidebar_collapsed`,Cn={__name:`AdminLayout`,props:{pageTitle:{type:String,default:`Dashboard`},navItems:{type:Array,default:()=>[]}},setup(e){let t=e,n=E(!1),r=ee(),i=ce(),{settings:a}=ue(),c=e=>{let t=e?.success??``,n=e?.error??``;t&&i.success(t),n&&i.error(n)},l=f(()=>{let e=r.props.auth?.user;return fe(t.navItems,{roles:e?.roles??[],permissions:e?.permissions??[]})}),d=f(()=>a.value.sidebarBehavior??`default`),p=f(()=>[`hidden`,`small_hover_active`,`small_hover`].includes(d.value)),m=f(()=>d.value===`default`?!1:d.value===`condensed`?h.value:!0),h=E(!0),g=()=>{p.value||d.value===`default`||(h.value=!h.value,typeof window<`u`&&window.localStorage.setItem(Sn,String(h.value)))},v=()=>{n.value=!n.value},y=()=>{n.value=!1},b=()=>{typeof window<`u`&&window.innerWidth>=1024&&(n.value=!1)};return u(()=>{if(typeof window<`u`){let e=window.localStorage.getItem(Sn);h.value=e===null?!1:e===`true`}window.addEventListener(`resize`,b),c(r.props.flash)}),x(d,e=>{e!==`default`&&(h.value=!0)},{immediate:!0}),te(()=>{window.removeEventListener(`resize`,b)}),x(()=>r.props.flash,e=>{c(e)},{deep:!0}),(t,r)=>(C(),s(`main`,vn,[o(_(ft),{position:`bottom-right`}),R(`div`,yn,[o(Mt,{items:l.value,collapsed:m.value,behavior:d.value,"mobile-open":n.value,onCloseMobile:y},null,8,[`items`,`collapsed`,`behavior`,`mobile-open`]),R(`section`,bn,[o(_n,{title:e.pageTitle,collapsed:m.value,behavior:d.value,onToggleSidebar:g,onToggleMobileSidebar:v},null,8,[`title`,`collapsed`,`behavior`]),R(`div`,xn,[k(t.$slots,`default`)])])])]))}};export{Y as n,de as r,Cn as t};