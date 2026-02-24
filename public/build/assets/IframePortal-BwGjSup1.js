import{r,j as o,s as l}from"./ui-C-VW1P-5.js";const m=r.forwardRef(({children:i},a)=>{const t=r.useRef(null),[n,d]=r.useState(null);return r.useImperativeHandle(a,()=>({print:()=>{if(!t.current)return;const e=t.current.contentWindow;e.focus(),e.print()}})),r.useEffect(()=>{if(!t.current)return;const e=t.current.contentDocument;e.open(),e.write(`
                <!DOCTYPE html>
                <html>
                    <head>
                        <style>
                            html, body {
                                background: #ffffff;
                                color: #000000;
                                font-family: Lato, sans-serif;
                                color-scheme: light;
                                overflow:hidden;
                            }
                            @media print {
                                body {
                                    -webkit-print-color-adjust: exact;
                                    print-color-adjust: exact;
                                }
                            }
                        </style>
                    </head>
                    <body>
                        <div id="iframe-root"></div>
                    </body>
                </html>
            `),e.close();const f=e.getElementById("iframe-root");d(f);const s=()=>{t.current.style.height=e.body.scrollHeight+"px"};s();const c=new ResizeObserver(s);return c.observe(e.body),()=>c.disconnect()},[]),o.jsxs(o.Fragment,{children:[o.jsx("iframe",{ref:t,style:{width:"100%",border:"none",background:"#fff"}}),n&&l.createPortal(i,n)]})});export{m as I};
