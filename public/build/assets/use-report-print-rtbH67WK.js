import{r as c,j as e}from"./ui-d-Ggzfpe.js";import{u as w,C as m,L as u,I as h,B as f,S as p}from"./app-DxoG6DhJ.js";function v({filters:s,additionalFilters:n}){const{t}=w(),[a,o]=c.useState(s.dateFrom),[d,l]=c.useState(s.dateTo),i=r=>{r.preventDefault(),p.get(window.location.pathname,{date_from:a,date_to:d})},x=()=>{const r=new Date;r.setMonth(r.getMonth()-1);const g=new Date;o(r.toISOString().split("T")[0]),l(g.toISOString().split("T")[0]),p.get(window.location.pathname)};return e.jsx(m,{className:"mb-6 p-4 print:hidden",children:e.jsxs("form",{onSubmit:i,className:"flex flex-col sm:flex-row sm:items-end flex-wrap gap-2 sm:gap-4",children:[e.jsxs("div",{className:"w-full sm:w-auto sm:flex-1",children:[e.jsx(u,{htmlFor:"date_from",children:t("From Date")}),e.jsx(h,{id:"date_from",type:"date",value:a,onChange:r=>o(r.target.value)})]}),e.jsxs("div",{className:"w-full sm:w-auto sm:flex-1",children:[e.jsx(u,{htmlFor:"date_to",children:t("To Date")}),e.jsx(h,{id:"date_to",type:"date",value:d,onChange:r=>l(r.target.value)})]}),n,e.jsx(f,{type:"submit",className:"w-full sm:w-auto",children:t("Apply Filters")}),e.jsx(f,{type:"button",variant:"outline",onClick:x,className:"w-full sm:w-auto",children:t("Clear Filters")})]})})}function j({title:s,value:n,icon:t,iconColor:a,valueColor:o="text-gray-900"}){return e.jsx(m,{className:"p-6",children:e.jsxs("div",{className:"flex items-center",children:[e.jsx("div",{className:`p-2 rounded-lg ${a}`,children:t}),e.jsxs("div",{className:"ml-4",children:[e.jsx("p",{className:"text-sm font-medium text-gray-600",children:s}),e.jsx("p",{className:`text-2xl font-bold ${o}`,children:n})]})]})})}function N({cards:s}){return e.jsx("div",{className:"grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6",children:s.map((n,t)=>e.jsx(j,{...n},t))})}function S({title:s,children:n,actions:t,className:a=""}){return e.jsxs(m,{className:`p-6 ${a}`,children:[e.jsxs("div",{className:"flex items-center justify-between mb-4",children:[e.jsx("h3",{className:"text-lg font-semibold",children:s}),t&&e.jsx("div",{className:"flex gap-2",children:t})]}),n]})}function C(s){const n=c.useRef(null);return{contentRef:n,handlePrint:()=>{const a=n.current;if(!a)return;const o=Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map(i=>`<link rel="stylesheet" href="${i.href}">`).join(`
`),d=Array.from(document.querySelectorAll("style")).map(i=>i.outerHTML).join(`
`),l=window.open("","_blank","width=900,height=700");l&&(l.document.write(`<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>${s}</title>
  ${o}
  ${d}
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
  ${a.innerHTML}
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
</html>`),l.document.close(),l.focus())}}}export{S as C,v as R,N as S,C as u};
