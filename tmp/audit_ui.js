const fs = require('fs');
const path = require('path');

const jsDir = path.join(__dirname, '../resources/js');
const reportFile = path.join(__dirname, 'audit_results.json');

const issues = [];

const severityMap = {
    'HARDCODED_PX_SIZE': 'High',
    'HARDCODED_PX_TEXT': 'Medium',
    'IMAGE_NO_MAX_WIDTH': 'High',
    'FIXED_WIDTH_NO_BREAKPOINT': 'Medium',
    'GRID_NO_BREAKPOINT': 'High',
    'INLINE_STYLE_PX': 'High',
    'ABSOLUTE_POS_POTENTIAL_ISSUE': 'Medium',
    'TAP_TARGET_POTENTIAL_ISSUE': 'Low'
};

function checkFile(filePath) {
    if (!filePath.endsWith('.tsx') && !filePath.endsWith('.jsx')) return;
    
    const content = fs.readFileSync(filePath, 'utf8');
    const lines = content.split('\n');
    
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        const lineNum = i + 1;
        const relativePath = path.relative(path.join(__dirname, '..'), filePath);
        
        // 1. Hardcoded widths/heights in px (Tailwind JIT)
        let match = line.match(/\b(w|h)-\[\s*\d+px\s*\]/g);
        if (match) {
            issues.push({
                file: relativePath,
                issue: `Hardcoded px value used for dimensions: ${match.join(', ')}`,
                severity: 'High',
                line: lineNum,
                code: line.trim(),
                fix: `Replace ${match[0]} with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.`
            });
        }
        
        // 2. Hardcoded typography in px
        match = line.match(/\btext-\[\s*\d+px\s*\]/g);
        if (match) {
            issues.push({
                file: relativePath,
                issue: `Hardcoded px value used for font size: ${match.join(', ')}`,
                severity: 'Medium',
                line: lineNum,
                code: line.trim(),
                fix: `Replace ${match[0]} with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.`
            });
        }
        
        // 3. Inline styles with px
        if (line.match(/style=\{.*(?:width|height|fontSize)\s*:\s*['"]?\d+px['"]?.*\}/i)) {
            issues.push({
                file: relativePath,
                issue: `Inline style using strict px values which breaks responsiveness.`,
                severity: 'High',
                line: lineNum,
                code: line.trim(),
                fix: `Move inline style to standard Tailwind classes, or use 100% / relative units.`
            });
        }
        
        // 4. Fixed widths without md: or lg: modifiers
        // Looking for w-64, w-96, etc inside a className string but ensuring it doesn't have md:w- accompanying it
        match = line.match(/className=["']([^"']*)["']/);
        if (match) {
            const classes = match[1].split(/\s+/);
            const hasFixedWidth = classes.find(c => /^w-(48|56|64|72|80|96)$/.test(c));
            const hasResponsiveWidth = classes.find(c => /^(sm|md|lg|xl|2xl):w-/.test(c) || c === 'w-full');
            
            if (hasFixedWidth && !hasResponsiveWidth && !classes.includes('max-w-full')) {
               // Too noisy? Just log as medium
               issues.push({
                   file: relativePath,
                   issue: `Fixed width (${hasFixedWidth}) without responsive fallback. May overflow on small mobile screens.`,
                   severity: 'Medium',
                   line: lineNum,
                   code: line.trim(),
                   fix: `Use w-full md:${hasFixedWidth} to allow scaling on small screens.`
               });
            }
            
            // Grid columns without responsiveness (e.g. grid-cols-3 or grid-cols-4 on mobile)
            const hasGrid = classes.includes('grid');
            const hasBadCols = classes.find(c => /^grid-cols-[3-9]$/.test(c) || /^grid-cols-1[0-2]$/.test(c));
            const hasResponsiveCols = classes.find(c => /^(sm|md|lg|xl):grid-cols-/.test(c));
            
            if (hasGrid && hasBadCols && !hasResponsiveCols) {
                issues.push({
                   file: relativePath,
                   issue: `Multiple grid columns (${hasBadCols}) enforced on mobile without responsive stacking.`,
                   severity: 'High',
                   line: lineNum,
                   code: line.trim(),
                   fix: `Change to grid-cols-1 md:${hasBadCols} to enable vertical stacking on mobile.`
               });
            }
        }
        
        // 5. Images missing responsive max-width
        if (line.match(/<img\s/) && !line.includes('w-full') && !line.includes('max-w-') && !line.includes('object-cover')) {
            // Check if it's an avatar or small icon, which is fine
            if (!line.includes('w-8') && !line.includes('h-8') && !line.includes('w-10') && !line.includes('w-12')) {
                issues.push({
                    file: relativePath,
                    issue: `Image element missing max-width logic. Large images will overflow their containers.`,
                    severity: 'High',
                    line: lineNum,
                    code: line.trim(),
                    fix: `Add className="max-w-full h-auto" or "object-cover" constraints.`
                });
            }
        }
    }
}

function walk(dir) {
    const list = fs.readdirSync(dir);
    list.forEach(file => {
        const fullPath = path.join(dir, file);
        const stat = fs.statSync(fullPath);
        if (stat && stat.isDirectory()) {
            walk(fullPath);
        } else {
            checkFile(fullPath);
        }
    });
}

walk(jsDir);

fs.writeFileSync(reportFile, JSON.stringify(issues, null, 2));
console.log(`Report generated with ${issues.length} issues.`);
