const fs = require('fs');
const path = require('path');

const resultsFile = path.join(__dirname, 'audit_results.json');
const reportFile = path.join(__dirname, 'ui_responsiveness_audit.md');

const issues = JSON.parse(fs.readFileSync(resultsFile, 'utf8'));

let critical = [];
let high = [];
let medium = [];
let low = [];

issues.forEach(i => {
    if (i.severity === 'Critical') critical.push(i);
    else if (i.severity === 'High') high.push(i);
    else if (i.severity === 'Medium') medium.push(i);
    else low.push(i); // treat Low as fallback
});

let md = `# UI Responsiveness Audit Report\n\n`;

md += `## 1. Summary Table\n\n`;
md += `| Severity | Count | Description |\n`;
md += `| --- | --- | --- |\n`;
md += `| **Critical** | ${critical.length} | Layout-breaking issues that completely prevent usability on mobile. |\n`;
md += `| **High** | ${high.length} | Hardcoded \`px\` dimensions, non-responsive grids, and overflowing images. |\n`;
md += `| **Medium** | ${medium.length} | Hardcoded font sizes and fixed widths that may cause horizontal scrolling. |\n`;
md += `| **Low** | ${low.length} | Minor spacing and touch target optimization opportunities. |\n\n`;
md += `**Total Issues Found:** ${issues.length}\n\n`;

md += `## 2. Priority Fix List (Impact vs Effort)\n\n`;
md += `1. **Replace hardcoded \`px\` widths/heights** (\`w-[300px]\`, \`h-[50px]\`). These are scattered widely and break mobile layouts immediately. Switch to \`w-full max-w-[300px]\` or rem-based \`w-72\`.\n`;
md += `2. **Fix fixed-width grid columns**. Forms and tables using \`grid-cols-2\` or higher without an \`md:\` prefix will squash content on narrow screens. Change to \`grid-cols-1 md:grid-cols-X\`.\n`;
md += `3. **Responsive Images**. Add \`max-w-full h-auto\` or \`object-cover\` to any \`<img />\` tag without width constraints.\n`;
md += `4. **Typography**. Standardize font sizes using Tailwind's \`text-sm\`, \`text-base\` instead of brute-forcing \`text-[14px]\`.\n\n`;

md += `## 3. Global Recommendations\n\n`;
md += `- **Adopt Mobile-First Design**: Start styling for mobile by default (e.g., \`w-full flex-col\`), then add breakpoints for larger screens (e.g., \`md:w-1/2 md:flex-row\`).\n`;
md += `- **Avoid Strict Pixels**: Stop using \`px\` inline styles and arbitrary Tailwind values (\`w-[...px]\`). Use \`rem\` units (Tailwind's default scale) so UI scales with browser font settings.\n`;
md += `- **Safe Container Bounds**: Wrap page content in a \`container mx-auto px-4\` to guarantee breathing room on all devices.\n\n`;

md += `---\n\n`;
md += `## Exhaustive Issue Details\n\n`;

// Group by file for readability, but still keep the format the user wants
const issuesByFile = {};
issues.forEach(i => {
    if (!issuesByFile[i.file]) issuesByFile[i.file] = [];
    issuesByFile[i.file].push(i);
});

for (const [file, fileIssues] of Object.entries(issuesByFile)) {
    md += `### File: \`${file}\`\n\n`;
    fileIssues.forEach(item => {
        md += `**Issue:** ${item.issue}\n`;
        md += `**Severity:** ${item.severity}\n`;
        md += `**Line(s):** ${item.line}\n`;
        md += `**Current Code:** \`${item.code}\`\n`;
        md += `**Fix:** ${item.fix}\n\n`;
    });
}

fs.writeFileSync(reportFile, md);
console.log('Report formatted at ' + reportFile);
