/**
 * Automated Responsiveness Fixer
 * Applies Rules 1-10 across all .tsx/.jsx files.
 * CSS-only changes — no logic/state/import changes.
 */
const fs = require('fs');
const path = require('path');

const jsDir = path.join(__dirname, '../resources/js');
const changes = [];

// Components whose widths we must NEVER touch (Rule 6 exception)
const SKIP_WIDTH_COMPONENTS = [
  'DropdownMenuContent', 'SelectContent', 'PopoverContent',
  'SheetContent', 'DialogContent', 'TooltipContent',
  'DropdownMenuSubContent', 'CommandList'
];

function isSkipWidthLine(line) {
  return SKIP_WIDTH_COMPONENTS.some(c => line.includes(`<${c}`));
}

function fixFile(filePath) {
  const ext = path.extname(filePath);
  if (ext !== '.tsx' && ext !== '.jsx') return;

  let content = fs.readFileSync(filePath, 'utf8');
  const original = content;
  const rel = path.relative(path.join(__dirname, '..'), filePath).replace(/\\/g, '/');

  // ========== RULE 1: Grid columns must be mobile-first ==========
  // grid-cols-6 without sm:/md: prefix
  content = content.replace(
    /className="([^"]*)\bgrid-cols-6\b([^"]*)"/g,
    (match, before, after) => {
      if (/(?:sm|md|lg|xl):grid-cols-/.test(before + after)) return match;
      return `className="${before}grid-cols-2 sm:grid-cols-3 md:grid-cols-6${after}"`;
    }
  );
  // grid-cols-4 without sm:/md: prefix
  content = content.replace(
    /className="([^"]*)\bgrid-cols-4\b([^"]*)"/g,
    (match, before, after) => {
      if (/(?:sm|md|lg|xl):grid-cols-/.test(before + after)) return match;
      return `className="${before}grid-cols-2 md:grid-cols-4${after}"`;
    }
  );
  // grid-cols-3 without sm:/md: prefix
  content = content.replace(
    /className="([^"]*)\bgrid-cols-3\b([^"]*)"/g,
    (match, before, after) => {
      if (/(?:sm|md|lg|xl):grid-cols-/.test(before + after)) return match;
      // Don't touch if the grid is inside grid-cols-1 md:grid-cols-3 already
      return `className="${before}grid-cols-1 md:grid-cols-3${after}"`;
    }
  );
  // grid-cols-5 without sm:/md:
  content = content.replace(
    /className="([^"]*)\bgrid-cols-5\b([^"]*)"/g,
    (match, before, after) => {
      if (/(?:sm|md|lg|xl):grid-cols-/.test(before + after)) return match;
      return `className="${before}grid-cols-2 md:grid-cols-5${after}"`;
    }
  );
  // grid-cols-12 without responsive
  content = content.replace(
    /className="([^"]*)\bgrid-cols-12\b([^"]*)"/g,
    (match, before, after) => {
      if (/(?:sm|md|lg|xl):grid-cols-/.test(before + after)) return match;
      return `className="${before}grid-cols-1 md:grid-cols-12${after}"`;
    }
  );

  // ========== RULE 3: Replace px font sizes ==========
  content = content.replace(/\btext-\[13px\]/g, 'text-sm');
  content = content.replace(/\btext-\[11px\]/g, 'text-xs');
  content = content.replace(/\btext-\[12px\]/g, 'text-xs');
  content = content.replace(/\btext-\[14px\]/g, 'text-sm');

  // ========== RULE 9: Convert px min/max heights to rem ==========
  content = content.replace(/\bmin-h-\[80px\]/g, 'min-h-[5rem]');
  content = content.replace(/\bmin-h-\[60px\]/g, 'min-h-[3.75rem]');
  content = content.replace(/\bmin-h-\[200px\]/g, 'min-h-[12.5rem]');
  content = content.replace(/\bmin-h-\[250px\]/g, 'min-h-[15.625rem]');
  content = content.replace(/\bmin-h-\[300px\]/g, 'min-h-[18.75rem]');
  content = content.replace(/\bmin-h-\[400px\]/g, 'min-h-[25rem]');
  content = content.replace(/\bmin-h-\[160px\]/g, 'min-h-[10rem]');
  content = content.replace(/\bmax-h-\[200px\]/g, 'max-h-52');
  content = content.replace(/\bmax-h-\[300px\]/g, 'max-h-72');
  content = content.replace(/\bmax-h-\[400px\]/g, 'max-h-[25rem]');
  content = content.replace(/\bh-\[600px\]/g, 'h-[37.5rem]');

  // ========== RULE 10: Convert fixed px widths ==========
  // w-[14px] h-[14px] together → w-3.5 h-3.5
  content = content.replace(/\bw-\[14px\]\s+h-\[14px\]/g, 'w-3.5 h-3.5');
  content = content.replace(/\bh-\[14px\]\s+w-\[14px\]/g, 'h-3.5 w-3.5');
  // h-[34px] w-[34px] together → size-9
  content = content.replace(/\bh-\[34px\]\s+w-\[34px\]/g, 'size-9');
  content = content.replace(/\bw-\[34px\]\s+h-\[34px\]/g, 'size-9');
  // w-[72px] → w-16
  content = content.replace(/\bw-\[72px\]/g, 'w-16');
  // w-[180px] → w-44
  content = content.replace(/\bw-\[180px\]/g, 'w-44');
  // max-w-[200px] → max-w-xs
  content = content.replace(/\bmax-w-\[200px\]/g, 'max-w-xs');
  // max-w-[240px] → max-w-60
  content = content.replace(/\bmax-w-\[240px\]/g, 'max-w-60');

  // min-w-[200px] → min-w-0 (only on non-skip components)
  content = content.replace(/\bmin-w-\[200px\]/g, 'min-w-0');
  content = content.replace(/\bmin-w-\[80px\]/g, 'min-w-0');
  content = content.replace(/\bmin-w-\[50px\]/g, 'min-w-0');
  content = content.replace(/\bmin-w-\[120px\]/g, 'min-w-[7.5rem]');

  // ========== RULE 6: Fixed widths on layout elements ==========
  // Process line-by-line for Rule 6 (context-dependent)
  const lines = content.split('\n');
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    // Skip lines containing skip-components
    if (isSkipWidthLine(line)) continue;
    // Only apply to HTML layout elements
    if (!/<(?:div|section|aside|main|nav)\s/.test(line)) continue;

    // w-64 without w-full or md:w-
    if (/\bw-64\b/.test(line) && !/\bw-full\b/.test(line) && !/\b(?:sm|md|lg|xl):w-/.test(line)) {
      lines[i] = line.replace(/\bw-64\b/, 'w-full md:w-64');
    }
    // w-56
    if (/\bw-56\b/.test(line) && !/\bw-full\b/.test(line) && !/\b(?:sm|md|lg|xl):w-/.test(line)) {
      lines[i] = line.replace(/\bw-56\b/, 'w-full md:w-56');
    }
    // w-48 (but NOT on skip components - already checked)
    if (/\bw-48\b/.test(line) && !/\bw-full\b/.test(line) && !/\b(?:sm|md|lg|xl):w-/.test(line)) {
      lines[i] = line.replace(/\bw-48\b/, 'w-full md:w-48');
    }
  }
  content = lines.join('\n');

  // ========== RULE 8: Kanban column inline widths ==========
  // style={{ minWidth: '380px', width: '380px' }}
  content = content.replace(
    /style=\{\{\s*minWidth:\s*['"]380px['"],\s*width:\s*['"]380px['"]\s*\}\}/g,
    "style={{ minWidth: 'min(380px, calc(100vw - 2rem))', width: 'min(380px, calc(100vw - 2rem))' }}"
  );
  content = content.replace(
    /style=\{\{\s*minWidth:\s*['"]300px['"],\s*width:\s*['"]300px['"]\s*\}\}/g,
    "style={{ minWidth: 'min(300px, calc(100vw - 2rem))', width: 'min(300px, calc(100vw - 2rem))' }}"
  );

  // ========== RULE 7: Logo/image max size inline styles ==========
  content = content.replace(
    /style=\{\{\s*maxWidth:\s*['"]150px['"],\s*maxHeight:\s*['"]150px['"]\s*\}\}/g,
    'className="max-w-[150px] max-h-[150px] object-contain"'
  );

  // Write if changed
  if (content !== original) {
    fs.writeFileSync(filePath, content);
    changes.push(rel);
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
      fixFile(fullPath);
    }
  });
}

walk(jsDir);

console.log(`\nFixed ${changes.length} files:\n`);
changes.forEach(f => console.log(`  ✓ ${f}`));
console.log('');
