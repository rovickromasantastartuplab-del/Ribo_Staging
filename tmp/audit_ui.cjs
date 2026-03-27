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
    
    // Read the file and parse lines
    try {
        const content = fs.readFileSync(filePath, 'utf8');
        const lines = content.split('\n');
        
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            const lineNum = i + 1;
            const relativePath = path.relative(path.join(__dirname, '..'), filePath).replace(/\\/g, '/');
            
            // 1. Hardcoded widths/heights in px (Tailwind JIT)
            let match = line.match(/\b([m]?[w|h]|min-[w|h]|max-[w|h])-\[\s*\d+px\s*\]/g);
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
            if (line.match(/style=\{.*(?:width|height|fontSize|margin|padding)\s*:\s*['"]?\d+px['"]?.*\}/i)) {
                issues.push({
                    file: relativePath,
                    issue: `Inline style using strict px values which breaks responsiveness.`,
                    severity: 'High',
                    line: lineNum,
                    code: line.trim(),
                    fix: `Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.`
                });
            }
            
            // 4. Fixed widths without responsive fallback
            match = line.match(/className=["']([^"']*)["']/);
            if (match) {
                const classes = match[1].split(/\s+/);
                const hasFixedWidth = classes.find(c => /^w-(48|56|64|72|80|96)$/.test(c));
                const hasResponsiveWidth = classes.find(c => /^(sm|md|lg|xl|2xl):w-/.test(c) || c === 'w-full');
                
                if (hasFixedWidth && !hasResponsiveWidth && !classes.includes('max-w-full')) {
                   issues.push({
                       file: relativePath,
                       issue: `Fixed width (${hasFixedWidth}) without responsive fallback. May overflow on small mobile screens.`,
                       severity: 'Medium',
                       line: lineNum,
                       code: line.trim(),
                       fix: `Use w-full md:${hasFixedWidth} to allow scaling on small screens.`
                   });
                }
                
                // Grid columns without responsiveness
                const hasGrid = classes.includes('grid');
                const hasBadCols = classes.find(c => /^grid-cols-[3-9]$/.test(c) || /^grid-cols-1[0-2]$/.test(c));
                const hasResponsiveCols = classes.find(c => /^(sm|md|lg|xl):grid-cols-/.test(c));
                
                // Don't flag small grids like grid-cols-2 unless it's a known issue normally, but let's flag 3+
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
            if (line.match(/<img\s/)) {
                if (!line.match(/w-full|w-\d+|h-\d+|max-w-|object-cover/)) {
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
    } catch (err) {
        console.error("Error checking file " + filePath + ": " + err);
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
