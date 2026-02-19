import{j as e}from"./ui-C-VW1P-5.js";import{K as l}from"./app-BQKKzqf3.js";import"./vendor-CxtKjBZA.js";import"./utils-DCj7OOX3.js";function j(){const{invoice:i,templateId:c,templateColor:d,settings:t}=l().props;t!=null&&t.defaultCurrency;const a=r=>`${r.toFixed(2)}`,n=r=>new Date(r).toLocaleDateString();return e.jsxs("html",{children:[e.jsxs("head",{children:[e.jsx("meta",{charSet:"utf-8"}),e.jsx("meta",{name:"viewport",content:"width=device-width, initial-scale=1"}),e.jsx("style",{dangerouslySetInnerHTML:{__html:`
                        body {
                            font-family: 'Lato', sans-serif;
                            margin: 0;
                            padding: 20px;
                            background: #f5f5f5;
                        }
                        .invoice-preview-main {
                            max-width: 700px;
                            width: 100%;
                            margin: 0 auto;
                            background: #fff;
                            box-shadow: 0 0 10px #ddd;
                        }
                        .invoice-header {
                            background: ${d};
                            color: #fff;
                            padding: 30px;
                        }
                        .invoice-header table {
                            width: 100%;
                            border-collapse: collapse;
                        }
                        .invoice-header td {
                            padding: 10px 0;
                        }
                        .text-right {
                            text-align: right;
                        }
                        .invoice-body {
                            padding: 30px;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                        }
                        table th, table td {
                            padding: 12px;
                            text-align: left;
                        }
                        .invoice-summary {
                            margin-top: 30px;
                        }
                        .invoice-summary thead {
                            background: ${d};
                            color: #fff;
                        }
                        .invoice-summary tbody tr {
                            border-bottom: 1px solid ${d};
                        }
                        .total-table {
                            margin-top: 20px;
                        }
                        .total-table td {
                            padding: 8px 0;
                        }
                    `}})]}),e.jsx("body",{children:e.jsxs("div",{className:"invoice-preview-main",children:[e.jsxs("div",{className:"invoice-header",children:[e.jsx("table",{children:e.jsx("tbody",{children:e.jsxs("tr",{children:[e.jsx("td",{children:e.jsx("h1",{style:{margin:0,fontSize:"40px",fontWeight:"bold"},children:"INVOICE"})}),e.jsxs("td",{className:"text-right",children:[e.jsxs("div",{children:["Number: ",i.invoice_number]}),e.jsxs("div",{children:["Issue Date: ",n(i.invoice_date)]})]})]})})}),e.jsx("table",{style:{marginTop:"20px"},children:e.jsx("tbody",{children:e.jsxs("tr",{children:[e.jsxs("td",{children:[e.jsx("strong",{children:"From:"}),e.jsxs("p",{style:{margin:"10px 0 0 0"},children:[(t==null?void 0:t.companyName)||"Your Company",e.jsx("br",{}),(t==null?void 0:t.companyAddress)||"123 Business St",e.jsx("br",{}),(t==null?void 0:t.companyCity)||"City",", ",(t==null?void 0:t.companyState)||"State"," ",(t==null?void 0:t.companyZipcode)||"12345",e.jsx("br",{}),(t==null?void 0:t.companyCountry)||"Country"]})]}),e.jsxs("td",{className:"text-right",children:[e.jsx("strong",{children:"Bill To:"}),e.jsxs("p",{style:{margin:"10px 0 0 0"},children:[i.account.name,e.jsx("br",{}),i.account.email,e.jsx("br",{}),i.account.phone]})]})]})})})]}),e.jsxs("div",{className:"invoice-body",children:[e.jsxs("table",{className:"invoice-summary",children:[e.jsx("thead",{children:e.jsxs("tr",{children:[e.jsx("th",{children:"Item"}),e.jsx("th",{children:"Quantity"}),e.jsx("th",{children:"Rate"}),e.jsx("th",{children:"Tax"}),e.jsx("th",{children:"Price"})]})}),e.jsx("tbody",{children:i.products.map((r,s)=>e.jsxs("tr",{children:[e.jsx("td",{children:r.name}),e.jsx("td",{children:r.pivot.quantity}),e.jsx("td",{children:a(r.pivot.unit_price)}),e.jsxs("td",{children:[r.tax.name," (",r.tax.rate,"%)"]}),e.jsx("td",{children:a(r.pivot.total_price)})]},s))})]}),e.jsx("table",{className:"total-table",children:e.jsxs("tbody",{children:[e.jsxs("tr",{children:[e.jsx("td",{className:"text-right",children:e.jsx("strong",{children:"Subtotal:"})}),e.jsx("td",{className:"text-right",children:a(i.subtotal)})]}),e.jsxs("tr",{children:[e.jsx("td",{className:"text-right",children:e.jsx("strong",{children:"Tax:"})}),e.jsx("td",{className:"text-right",children:a(i.tax_amount)})]}),e.jsxs("tr",{children:[e.jsx("td",{className:"text-right",children:e.jsx("strong",{children:"Total:"})}),e.jsx("td",{className:"text-right",children:e.jsx("strong",{children:a(i.total_amount)})})]})]})}),i.notes&&e.jsxs("div",{style:{marginTop:"30px",padding:"15px",background:"#f9f9f9",borderRadius:"5px"},children:[e.jsx("strong",{children:"Notes:"}),e.jsx("p",{style:{margin:"10px 0 0 0"},children:i.notes})]})]})]})})]})}export{j as default};
