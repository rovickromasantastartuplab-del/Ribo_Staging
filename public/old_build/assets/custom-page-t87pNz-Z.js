import{U as E,j as r}from"./ui-C-VW1P-5.js";import{K as s,k as A}from"./app-BQKKzqf3.js";import H from"./Header-DhRH8Cqn.js";import M from"./Footer-Cvr0ynhy.js";import{u as P}from"./use-favicon-D-Hpb8YY.js";import"./vendor-CxtKjBZA.js";import"./utils-DCj7OOX3.js";import"./menu-DkHdB524.js";import"./mail-DI9tSiFs.js";import"./phone-B9JuG5Em.js";import"./map-pin-BDUdmeW2.js";import"./instagram-Dy7YsGuh.js";import"./twitter-DwqeIVgg.js";function oe(){var u,f,h,x,y,g,b,j,_,v,L,C,k;const I=`
    .prose h1, .prose h2, .prose h3, .prose h4, .prose h5, .prose h6 {
      color: #1f2937;
      font-weight: 600;
      margin-top: 2rem;
      margin-bottom: 1rem;
    }

    .prose h1 { font-size: 2.25rem; }
    .prose h2 { font-size: 1.875rem; }
    .prose h3 { font-size: 1.5rem; }

    .prose p {
      margin-bottom: 1.5rem;
      line-height: 1.75;
    }

    .prose ul, .prose ol {
      margin: 1.5rem 0;
      padding-left: 1.5rem;
    }

    .prose li {
      margin-bottom: 0.5rem;
    }

    .prose a {
      color: var(--primary-color);
      text-decoration: underline;
    }

    .prose blockquote {
      border-left: 4px solid var(--primary-color);
      padding-left: 1rem;
      margin: 1.5rem 0;
      font-style: italic;
      background-color: #f9fafb;
      padding: 1rem;
    }

    .prose img {
      max-width: 100%;
      height: auto;
      border-radius: 0.5rem;
      margin: 1.5rem 0;
    }
  `,{page:n,customPages:R=[],settings:o}=s().props,i=((f=(u=o==null?void 0:o.config_sections)==null?void 0:u.theme)==null?void 0:f.primary_color)||"#3b82f6",m=((x=(h=o==null?void 0:o.config_sections)==null?void 0:h.theme)==null?void 0:x.secondary_color)||"#8b5cf6",l=((g=(y=o==null?void 0:o.config_sections)==null?void 0:y.theme)==null?void 0:g.accent_color)||"#10b77f",e=s().props.globalSettings,d=s().props.userLanguage;P();const p=E.useCallback(()=>{const t=(e==null?void 0:e.is_demo)||!1,a=d||(e==null?void 0:e.defaultLanguage)||"en",F=["ar","he"].includes(a);let c="ltr";const T=(t?(z=>{var D;if(typeof document>"u")return null;const w=`; ${document.cookie}`.split(`; ${z}=`);if(w.length===2){const N=(D=w.pop())==null?void 0:D.split(";").shift();return N?decodeURIComponent(N):null}return null})("layoutDirection"):e==null?void 0:e.layoutDirection)==="right";return(F||T)&&(c="rtl"),document.documentElement.dir=c,document.documentElement.setAttribute("dir",c),document.body.dir=c,c},[d,e==null?void 0:e.defaultLanguage,e==null?void 0:e.is_demo,e==null?void 0:e.layoutDirection]);return E.useLayoutEffect(()=>{const t=p(),a=new MutationObserver(()=>{document.documentElement.dir!==t&&(document.documentElement.dir=t,document.documentElement.setAttribute("dir",t))});return a.observe(document.documentElement,{attributes:!0,attributeFilter:["dir"]}),()=>a.disconnect()},[p]),r.jsxs(r.Fragment,{children:[r.jsxs(A,{children:[r.jsx("title",{children:n.meta_title||n.title}),n.meta_description&&r.jsx("meta",{name:"description",content:n.meta_description}),r.jsx("style",{children:I})]}),r.jsxs("div",{className:"min-h-screen bg-white",style:{"--primary-color":i,"--secondary-color":m,"--accent-color":l,"--primary-color-rgb":((b=i.replace("#","").match(/.{2}/g))==null?void 0:b.map(t=>parseInt(t,16)).join(", "))||"59, 130, 246","--secondary-color-rgb":((j=m.replace("#","").match(/.{2}/g))==null?void 0:j.map(t=>parseInt(t,16)).join(", "))||"139, 92, 246","--accent-color-rgb":((_=l.replace("#","").match(/.{2}/g))==null?void 0:_.map(t=>parseInt(t,16)).join(", "))||"16, 185, 129"},children:[r.jsx(H,{"max-w-7xl":!0,"mx-auto":!0,p:!0,settings:o,customPages:R,sectionData:((L=(v=o==null?void 0:o.config_sections)==null?void 0:v.sections)==null?void 0:L.find(t=>t.key==="header"))||{},brandColor:i}),r.jsx("main",{className:"pt-16",children:r.jsx("div",{className:"max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12",children:r.jsxs("div",{className:"max-w-4xl mx-auto",children:[r.jsxs("header",{className:"text-center mb-12",children:[r.jsx("h1",{className:"text-4xl font-bold text-gray-900 mb-4",children:n.title}),r.jsx("div",{className:"w-24 h-1 bg-gradient-to-r from-blue-500 to-purple-600 mx-auto rounded-full"})]}),r.jsx("article",{className:"prose prose-lg max-w-none",children:r.jsx("div",{className:"text-gray-700 leading-relaxed",dangerouslySetInnerHTML:{__html:n.content}})})]})})}),r.jsx(M,{settings:o,sectionData:((k=(C=o==null?void 0:o.config_sections)==null?void 0:C.sections)==null?void 0:k.find(t=>t.key==="footer"))||{},brandColor:i})]})]})}export{oe as default};
