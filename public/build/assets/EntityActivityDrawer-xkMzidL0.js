import{Bt as e,Ft as t,Gt as n,Ht as r,I as i,Rt as a,T as o,U as s,Ut as c,Vt as l,Xt as u,_n as d,a as f,an as p,cn as m,dn as h,en as g,hn as _,in as v,kt as y,m as b,nn as x,rn as S,tn as C,un as w,v as ee,vn as T,xn as E,z as D,zt as O}from"./ripple-CIndIOSS.js";import{n as k}from"./baseicon-CIy754zm.js";import{c as A,n as j,s as M,t as N}from"./overlayeventbus-DJL1peMJ.js";import{d as P,n as F,r as I,s as L}from"./app-D4osEgRX.js";import{t as R}from"./button-CjCQ-G18.js";import{t as z}from"./focustrap-CaAJxwer.js";import{t as B}from"./dialog-CywYvc7u.js";var V=f.extend({name:`confirmpopup`,style:`
    .p-confirmpopup {
        position: absolute;
        margin-top: dt('confirmpopup.gutter');
        top: 0;
        left: 0;
        background: dt('confirmpopup.background');
        color: dt('confirmpopup.color');
        border: 1px solid dt('confirmpopup.border.color');
        border-radius: dt('confirmpopup.border.radius');
        box-shadow: dt('confirmpopup.shadow');
        will-change: transform;
    }

    .p-confirmpopup-content {
        display: flex;
        align-items: center;
        padding: dt('confirmpopup.content.padding');
        gap: dt('confirmpopup.content.gap');
    }

    .p-confirmpopup-icon {
        font-size: dt('confirmpopup.icon.size');
        width: dt('confirmpopup.icon.size');
        height: dt('confirmpopup.icon.size');
        color: dt('confirmpopup.icon.color');
    }

    .p-confirmpopup-footer {
        display: flex;
        justify-content: flex-end;
        gap: dt('confirmpopup.footer.gap');
        padding: dt('confirmpopup.footer.padding');
    }

    .p-confirmpopup-footer button {
        width: auto;
    }

    .p-confirmpopup-footer button:last-child {
        margin: 0;
    }

    .p-confirmpopup-flipped {
        margin-block-start: calc(dt('confirmpopup.gutter') * -1);
        margin-block-end: dt('confirmpopup.gutter');
    }

    .p-confirmpopup:after,
    .p-confirmpopup:before {
        bottom: 100%;
        left: calc(dt('confirmpopup.arrow.offset') + dt('confirmpopup.arrow.left'));
        content: ' ';
        height: 0;
        width: 0;
        position: absolute;
        pointer-events: none;
    }

    .p-confirmpopup:after {
        border-width: calc(dt('confirmpopup.gutter') - 2px);
        margin-left: calc(-1 * (dt('confirmpopup.gutter') - 2px));
        border-style: solid;
        border-color: transparent;
        border-bottom-color: dt('confirmpopup.background');
    }

    .p-confirmpopup:before {
        border-width: dt('confirmpopup.gutter');
        margin-left: calc(-1 * dt('confirmpopup.gutter'));
        border-style: solid;
        border-color: transparent;
        border-bottom-color: dt('confirmpopup.border.color');
    }

    .p-confirmpopup-flipped:after,
    .p-confirmpopup-flipped:before {
        bottom: auto;
        top: 100%;
    }

    .p-confirmpopup-flipped:after {
        border-bottom-color: transparent;
        border-top-color: dt('confirmpopup.background');
    }

    .p-confirmpopup-flipped:before {
        border-bottom-color: transparent;
        border-top-color: dt('confirmpopup.border.color');
    }
`,classes:{root:`p-confirmpopup p-component`,content:`p-confirmpopup-content`,icon:`p-confirmpopup-icon`,message:`p-confirmpopup-message`,footer:`p-confirmpopup-footer`,pcRejectButton:`p-confirmpopup-reject-button`,pcAcceptButton:`p-confirmpopup-accept-button`}}),H={name:`ConfirmPopup`,extends:{name:`BaseConfirmPopup`,extends:k,props:{group:String},style:V,provide:function(){return{$pcConfirmPopup:this,$parentInstance:this}}},inheritAttrs:!1,data:function(){return{visible:!1,confirmation:null,autoFocusAccept:null,autoFocusReject:null,target:null}},target:null,outsideClickListener:null,scrollHandler:null,resizeListener:null,container:null,confirmListener:null,closeListener:null,mounted:function(){var e=this;this.confirmListener=function(t){t&&t.group===e.group&&(e.confirmation=t,e.target=t.target,e.confirmation.onShow&&e.confirmation.onShow(),e.visible=!0)},this.closeListener=function(){e.visible=!1,e.confirmation=null},L.on(`confirm`,this.confirmListener),L.on(`close`,this.closeListener)},beforeUnmount:function(){L.off(`confirm`,this.confirmListener),L.off(`close`,this.closeListener),this.unbindOutsideClickListener(),this.scrollHandler&&=(this.scrollHandler.destroy(),null),this.unbindResizeListener(),this.container&&=(A.clear(this.container),null),this.target=null,this.confirmation=null},methods:{accept:function(){this.confirmation.accept&&this.confirmation.accept(),this.visible=!1},reject:function(){this.confirmation.reject&&this.confirmation.reject(),this.visible=!1},onHide:function(){this.confirmation.onHide&&this.confirmation.onHide(),this.visible=!1},onAcceptKeydown:function(e){(e.code===`Space`||e.code===`Enter`||e.code===`NumpadEnter`)&&(this.accept(),s(this.target),e.preventDefault())},onRejectKeydown:function(e){(e.code===`Space`||e.code===`Enter`||e.code===`NumpadEnter`)&&(this.reject(),s(this.target),e.preventDefault())},onEnter:function(e){this.autoFocusAccept=this.confirmation.defaultFocus===void 0||this.confirmation.defaultFocus===`accept`,this.autoFocusReject=this.confirmation.defaultFocus===`reject`,this.target=this.target||document.activeElement,this.bindOutsideClickListener(),this.bindScrollListener(),this.bindResizeListener(),A.set(`overlay`,e,this.$primevue.config.zIndex.overlay)},onAfterEnter:function(){this.focus()},onLeave:function(){this.autoFocusAccept=null,this.autoFocusReject=null,s(this.target),this.target=null,this.unbindOutsideClickListener(),this.unbindScrollListener(),this.unbindResizeListener()},onAfterLeave:function(e){A.clear(e)},alignOverlay:function(){ee(this.container,this.target,!1);var e=o(this.container),t=o(this.target),n=0;e.left<t.left&&(n=t.left-e.left),this.container.style.setProperty(b(`confirmpopup.arrow.left`).name,`${n}px`),e.top<t.top&&(this.container.setAttribute(`data-p-confirmpopup-flipped`,`true`),!this.isUnstyled&&i(this.container,`p-confirmpopup-flipped`))},bindOutsideClickListener:function(){var e=this;this.outsideClickListener||(this.outsideClickListener=function(t){e.visible&&e.container&&!e.container.contains(t.target)&&!e.isTargetClicked(t)?(e.confirmation.onHide&&e.confirmation.onHide(),e.visible=!1):e.alignOverlay()},document.addEventListener(`click`,this.outsideClickListener))},unbindOutsideClickListener:function(){this.outsideClickListener&&=(document.removeEventListener(`click`,this.outsideClickListener),null)},bindScrollListener:function(){var e=this;this.scrollHandler||=new j(this.target,function(){e.visible&&=!1}),this.scrollHandler.bindScrollListener()},unbindScrollListener:function(){this.scrollHandler&&this.scrollHandler.unbindScrollListener()},bindResizeListener:function(){var e=this;this.resizeListener||(this.resizeListener=function(){e.visible&&!D()&&(e.visible=!1)},window.addEventListener(`resize`,this.resizeListener))},unbindResizeListener:function(){this.resizeListener&&=(window.removeEventListener(`resize`,this.resizeListener),null)},focus:function(){var e=this.container.querySelector(`[autofocus]`);e&&e.focus({preventScroll:!0})},isTargetClicked:function(e){return this.target&&(this.target===e.target||this.target.contains(e.target))},containerRef:function(e){this.container=e},onOverlayClick:function(e){N.emit(`overlay-click`,{originalEvent:e,target:this.target})},onOverlayKeydown:function(e){e.code===`Escape`&&(L.emit(`close`,this.closeListener),s(this.target))}},computed:{message:function(){return this.confirmation?this.confirmation.message:null},acceptLabel:function(){if(this.confirmation){var e=this.confirmation;return e.acceptLabel||e.acceptProps?.label||this.$primevue.config.locale.accept}return this.$primevue.config.locale.accept},rejectLabel:function(){if(this.confirmation){var e=this.confirmation;return e.rejectLabel||e.rejectProps?.label||this.$primevue.config.locale.reject}return this.$primevue.config.locale.reject},acceptIcon:function(){var e;return this.confirmation?this.confirmation.acceptIcon:(e=this.confirmation)!=null&&e.acceptProps?this.confirmation.acceptProps.icon:null},rejectIcon:function(){var e;return this.confirmation?this.confirmation.rejectIcon:(e=this.confirmation)!=null&&e.rejectProps?this.confirmation.rejectProps.icon:null}},components:{Button:R,Portal:M},directives:{focustrap:z}},U=[`aria-modal`];function W(i,a,o,s,d,f){var m=S(`Button`),_=S(`Portal`),b=v(`focustrap`);return g(),e(_,null,{default:w(function(){return[n(y,u({name:`p-anchored-overlay`,onEnter:f.onEnter,onAfterEnter:f.onAfterEnter,onLeave:f.onLeave,onAfterLeave:f.onAfterLeave},i.ptm(`transition`)),{default:w(function(){return[d.visible?h((g(),r(`div`,u({key:0,ref:f.containerRef,role:`alertdialog`,class:i.cx(`root`),"aria-modal":d.visible,onClick:a[2]||=function(){return f.onOverlayClick&&f.onOverlayClick.apply(f,arguments)},onKeydown:a[3]||=function(){return f.onOverlayKeydown&&f.onOverlayKeydown.apply(f,arguments)}},i.ptmi(`root`)),[i.$slots.container?x(i.$slots,`container`,{key:0,message:d.confirmation,acceptCallback:f.accept,rejectCallback:f.reject}):(g(),r(t,{key:1},[i.$slots.message?(g(),e(p(i.$slots.message),{key:1,message:d.confirmation},null,8,[`message`])):(g(),r(`div`,u({key:0,class:i.cx(`content`)},i.ptm(`content`)),[x(i.$slots,`icon`,{},function(){return[i.$slots.icon?(g(),e(p(i.$slots.icon),{key:0,class:T(i.cx(`icon`))},null,8,[`class`])):d.confirmation.icon?(g(),r(`span`,u({key:1,class:[d.confirmation.icon,i.cx(`icon`)]},i.ptm(`icon`)),null,16)):l(``,!0)]}),O(`span`,u({class:i.cx(`message`)},i.ptm(`message`)),E(d.confirmation.message),17)],16)),O(`div`,u({class:i.cx(`footer`)},i.ptm(`footer`)),[n(m,u({class:[i.cx(`pcRejectButton`),d.confirmation.rejectClass],autofocus:d.autoFocusReject,unstyled:i.unstyled,size:d.confirmation.rejectProps?.size||`small`,text:d.confirmation.rejectProps?.text||!1,onClick:a[0]||=function(e){return f.reject()},onKeydown:f.onRejectKeydown},d.confirmation.rejectProps,{label:f.rejectLabel,pt:i.ptm(`pcRejectButton`)}),c({_:2},[f.rejectIcon||i.$slots.rejecticon?{name:`icon`,fn:w(function(e){return[x(i.$slots,`rejecticon`,{},function(){return[O(`span`,u({class:[f.rejectIcon,e.class]},i.ptm(`pcRejectButton`).icon,{"data-pc-section":`rejectbuttonicon`}),null,16)]})]}),key:`0`}:void 0]),1040,[`class`,`autofocus`,`unstyled`,`size`,`text`,`onKeydown`,`label`,`pt`]),n(m,u({class:[i.cx(`pcAcceptButton`),d.confirmation.acceptClass],autofocus:d.autoFocusAccept,unstyled:i.unstyled,size:d.confirmation.acceptProps?.size||`small`,onClick:a[1]||=function(e){return f.accept()},onKeydown:f.onAcceptKeydown},d.confirmation.acceptProps,{label:f.acceptLabel,pt:i.ptm(`pcAcceptButton`)}),c({_:2},[f.acceptIcon||i.$slots.accepticon?{name:`icon`,fn:w(function(e){return[x(i.$slots,`accepticon`,{},function(){return[O(`span`,u({class:[f.acceptIcon,e.class]},i.ptm(`pcAcceptButton`).icon,{"data-pc-section":`acceptbuttonicon`}),null,16)]})]}),key:`0`}:void 0]),1040,[`class`,`autofocus`,`unstyled`,`size`,`onKeydown`,`label`,`pt`])],16)],64))],16,U)),[[b]]):l(``,!0)]}),_:3},16,[`onEnter`,`onAfterEnter`,`onLeave`,`onAfterLeave`])]}),_:3})}H.render=W;var G={key:0,class:`py-12 text-center text-sm text-(--muted-foreground)`},K={key:1,class:`py-12 text-center text-sm text-(--muted-foreground)`},te={key:2,class:`space-y-4`},q={class:`flex flex-wrap items-center justify-between gap-3`},J={class:`flex items-center gap-2`},Y={class:`text-sm font-semibold text-(--foreground)`},X={class:`rounded-full bg-[color-mix(in_oklab,var(--accent)_14%,transparent)] px-2 py-1 text-xs font-medium text-(--foreground)`},Z={class:`text-xs text-(--muted-foreground)`},Q={class:`mt-1 text-sm text-(--muted-foreground)`},ne={class:`mt-3 overflow-x-auto`},re={class:`w-full min-w-[540px] text-sm`},ie={class:`border-b border-(--border)`},ae={class:`px-2 py-2 text-start font-semibold`},oe={class:`px-2 py-2 text-start font-semibold`},$={class:`px-2 py-2 text-start font-semibold`},se={key:0},ce={class:`px-2 py-3 text-(--muted-foreground)`,colspan:`3`},le={class:`px-2 py-2 font-medium`},ue={class:`px-2 py-2 text-(--muted-foreground)`},de={class:`px-2 py-2 text-(--foreground)`},fe={__name:`EntityActivityDrawer`,props:{modelValue:{type:Boolean,default:!1},endpoint:{type:String,default:``},entityName:{type:String,default:``}},emits:[`update:modelValue`],setup(i,{emit:o}){let s=i,c=o,{t:u}=I(),f=F(),p=_(!1),h=_([]),v=a({get:()=>s.modelValue,set:e=>c(`update:modelValue`,e)}),y=e=>e==null||e===``?`-`:typeof e==`string`||typeof e==`number`||typeof e==`boolean`?String(e):JSON.stringify(e),b=e=>{let t=e?.old??{},n=e?.attributes??{};return[...new Set([...Object.keys(t),...Object.keys(n)])].map(e=>({field:e,oldValue:y(t[e]),newValue:y(n[e])}))},x=async()=>{if(!s.endpoint){h.value=[];return}p.value=!0;try{let{data:e}=await P.get(s.endpoint);h.value=(e?.data??[]).map(e=>({...e,diffRows:b(e.changes)}))}catch(e){f.fromAxiosError(e,{summary:u(`notifications.loadFailedTitle`),fallback:u(`notifications.loadFailedDetail`)})}finally{p.value=!1}};return m(()=>s.modelValue,e=>{e&&x()}),(a,o)=>(g(),e(d(B),{visible:v.value,"onUpdate:visible":o[1]||=e=>v.value=e,modal:``,"dismissable-mask":``,header:d(u)(`activityLogs.historyFor`,{name:i.entityName||`-`}),style:{width:`min(980px, 96vw)`}},{footer:w(()=>[n(d(R),{label:d(u)(`common.close`),severity:`secondary`,text:``,onClick:o[0]||=e=>v.value=!1},null,8,[`label`])]),default:w(()=>[p.value?(g(),r(`div`,G,E(d(u)(`common.loading`)),1)):h.value.length===0?(g(),r(`div`,K,E(d(u)(`activityLogs.noHistory`)),1)):(g(),r(`div`,te,[(g(!0),r(t,null,C(h.value,e=>(g(),r(`article`,{key:e.id,class:`rounded-md border border-(--border) bg-(--card) p-4`},[O(`div`,q,[O(`div`,J,[O(`span`,Y,E(e.event||e.description),1),O(`span`,X,E(e.log_name||`default`),1)]),O(`p`,Z,E(e.created_at_formatted),1)]),O(`p`,Q,E(d(u)(`activityLogs.by`))+`: `+E(e.causer_display),1),O(`div`,ne,[O(`table`,re,[O(`thead`,null,[O(`tr`,ie,[O(`th`,ae,E(d(u)(`activityLogs.field`)),1),O(`th`,oe,E(d(u)(`activityLogs.before`)),1),O(`th`,$,E(d(u)(`activityLogs.after`)),1)])]),O(`tbody`,null,[e.diffRows.length===0?(g(),r(`tr`,se,[O(`td`,ce,E(d(u)(`activityLogs.noChanges`)),1)])):l(``,!0),(g(!0),r(t,null,C(e.diffRows,t=>(g(),r(`tr`,{key:`${e.id}-${t.field}`,class:`border-b border-(--border) last:border-0`},[O(`td`,le,E(t.field),1),O(`td`,ue,E(t.oldValue),1),O(`td`,de,E(t.newValue),1)]))),128))])])])]))),128))]))]),_:1},8,[`visible`,`header`]))}};export{H as n,fe as t};