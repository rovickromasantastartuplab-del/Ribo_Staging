import{r as u,j as e}from"./ui-Ll5EvUTk.js";import{u as y,C as f,L as h,I as g,B as j,S as w}from"./app-iTLUTOyd.js";import{S as N,a as C,b as F,c as T,d as S}from"./select-r_yiUpFE.js";function A({filters:s,staff:a,additionalFilters:n}){var p;const{t}=y(),[i,o]=u.useState(s.dateFrom),[r,d]=u.useState(s.dateTo),[c,x]=u.useState(((p=s.staffId)==null?void 0:p.toString())||"all"),b=l=>{l.preventDefault();const m={date_from:i,date_to:r};c&&c!=="all"&&(m.staff_id=c),w.get(window.location.pathname,m)},v=()=>{const l=new Date;l.setMonth(l.getMonth()-1);const m=new Date;o(l.toISOString().split("T")[0]),d(m.toISOString().split("T")[0]),x("all"),w.get(window.location.pathname)};return e.jsx(f,{className:"mb-6 p-4 print:hidden",children:e.jsxs("form",{onSubmit:b,className:"flex flex-col sm:flex-row sm:items-end flex-wrap gap-2 sm:gap-4",children:[e.jsxs("div",{className:"w-full sm:w-auto sm:flex-1",children:[e.jsx(h,{htmlFor:"date_from",children:t("From Date")}),e.jsx(g,{id:"date_from",type:"date",value:i,onChange:l=>o(l.target.value)})]}),e.jsxs("div",{className:"w-full sm:w-auto sm:flex-1",children:[e.jsx(h,{htmlFor:"date_to",children:t("To Date")}),e.jsx(g,{id:"date_to",type:"date",value:r,onChange:l=>d(l.target.value)})]}),a&&a.length>0&&e.jsxs("div",{className:"w-full sm:w-auto sm:flex-1 min-w-[200px]",children:[e.jsx(h,{htmlFor:"staff_id",children:t("Staff")}),e.jsxs(N,{value:c,onValueChange:x,children:[e.jsx(C,{id:"staff_id",children:e.jsx(F,{placeholder:t("Select Staff")})}),e.jsxs(T,{children:[e.jsx(S,{value:"all",children:t("All Staff")}),a.map(l=>e.jsx(S,{value:l.id.toString(),children:l.name},l.id))]})]})]}),n,e.jsx(j,{type:"submit",className:"w-full sm:w-auto",children:t("Apply Filters")}),e.jsx(j,{type:"button",variant:"outline",onClick:v,className:"w-full sm:w-auto",children:t("Clear Filters")})]})})}function R({title:s,filters:a,staff:n}){const{t}=y(),i=n==null?void 0:n.find(o=>{var r;return o.id.toString()===((r=a.staffId)==null?void 0:r.toString())});return e.jsxs("div",{className:"hidden print:block mb-8 border-b pb-4",children:[e.jsx("h1",{className:"text-3xl font-bold text-gray-900 mb-2",children:s}),e.jsxs("div",{className:"flex flex-wrap gap-x-8 gap-y-2 text-sm text-gray-600",children:[e.jsxs("div",{children:[e.jsxs("span",{className:"font-semibold text-gray-800",children:[t("Date Range"),":"]})," ",a.dateFrom," ",t("to")," ",a.dateTo]}),i&&e.jsxs("div",{children:[e.jsxs("span",{className:"font-semibold text-gray-800",children:[t("Staff"),":"]})," ",i.name]})]})]})}function D({title:s,value:a,icon:n,iconColor:t,valueColor:i="text-gray-900"}){return e.jsx(f,{className:"p-6",children:e.jsxs("div",{className:"flex items-center",children:[e.jsx("div",{className:`p-2 rounded-lg ${t}`,children:n}),e.jsxs("div",{className:"ml-4",children:[e.jsx("p",{className:"text-sm font-medium text-gray-600",children:s}),e.jsx("p",{className:`text-2xl font-bold ${i}`,children:a})]})]})})}function $({cards:s}){return e.jsx("div",{className:"grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6",children:s.map((a,n)=>e.jsx(D,{...a},n))})}function L({title:s,children:a,actions:n,className:t=""}){return e.jsxs(f,{className:`p-6 ${t}`,children:[e.jsxs("div",{className:"flex items-center justify-between mb-4",children:[e.jsx("h3",{className:"text-lg font-semibold",children:s}),n&&e.jsx("div",{className:"flex gap-2",children:n})]}),a]})}function E(s){const a=u.useRef(null);return{contentRef:a,handlePrint:()=>{const t=a.current;if(!t)return;const i=Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map(d=>`<link rel="stylesheet" href="${d.href}">`).join(`
`),o=Array.from(document.querySelectorAll("style")).map(d=>d.outerHTML).join(`
`),r=window.open("","_blank","width=900,height=700");r&&(r.document.write(`<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>${s}</title>
  ${i}
  ${o}
  <style>
    /* Force light mode for print */
    * { color-scheme: light !important; }
    body {
      background: #ffffff !important;
      color: #000000 !important;
      padding: 2rem;
      font-family: Inter, sans-serif;
    }
    /* Ensure SVG charts render with colour */
    svg { display: block; }
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    @media print {
      body { padding: 0; }
      @page { size: A4; margin: 0.5in; }
    }
  </style>
</head>
<body>
  ${t.innerHTML}
  <script>
    // Auto-trigger print once styles are loaded
    window.onload = function() {
      setTimeout(function() {
        window.print();
        window.close();
      }, 600);
    };
  <\/script>
</body>
</html>`),r.document.close(),r.focus())}}}export{L as C,A as R,$ as S,R as a,E as u};
