import { useRef } from 'react';

/**
 * Hook that provides a ref to attach to the report content div,
 * and a handlePrint function that opens the content in a clean print window.
 * This approach bypasses the complex app layout (sidebar, header, etc.)
 * and prints only the report content — including Recharts SVGs.
 */
export function useReportPrint(reportTitle: string) {
  const contentRef = useRef<HTMLDivElement>(null);

  const handlePrint = () => {
    const content = contentRef.current;
    if (!content) return;

    // Collect all stylesheet <link> tags from the parent page
    const styleLinks = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
      .map((link) => `<link rel="stylesheet" href="${(link as HTMLLinkElement).href}">`)
      .join('\n');

    // Collect any inline <style> tags (e.g. Tailwind inline critical CSS)
    const inlineStyles = Array.from(document.querySelectorAll('style'))
      .map((style) => style.outerHTML)
      .join('\n');

    const win = window.open('', '_blank', 'width=900,height=700');
    if (!win) return;

    win.document.write(`<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>${reportTitle}</title>
  ${styleLinks}
  ${inlineStyles}
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
  ${content.innerHTML}
  <script>
    // Auto-trigger print once styles are loaded
    window.onload = function() {
      setTimeout(function() {
        window.print();
        window.close();
      }, 600);
    };
  </script>
</body>
</html>`);

    win.document.close();
    win.focus();
  };

  return { contentRef, handlePrint };
}
