# UI Responsiveness Audit Report

## 1. Summary Table

| Severity | Count | Description |
| --- | --- | --- |
| **Critical** | 0 | Layout-breaking issues that completely prevent usability on mobile. |
| **High** | 412 | Hardcoded `px` dimensions, non-responsive grids, and overflowing images. |
| **Medium** | 107 | Hardcoded font sizes and fixed widths that may cause horizontal scrolling. |
| **Low** | 0 | Minor spacing and touch target optimization opportunities. |

**Total Issues Found:** 519

## 2. Priority Fix List (Impact vs Effort)

1. **Replace hardcoded `px` widths/heights** (`w-[300px]`, `h-[50px]`). These are scattered widely and break mobile layouts immediately. Switch to `w-full max-w-[300px]` or rem-based `w-72`.
2. **Fix fixed-width grid columns**. Forms and tables using `grid-cols-2` or higher without an `md:` prefix will squash content on narrow screens. Change to `grid-cols-1 md:grid-cols-X`.
3. **Responsive Images**. Add `max-w-full h-auto` or `object-cover` to any `<img />` tag without width constraints.
4. **Typography**. Standardize font sizes using Tailwind's `text-sm`, `text-base` instead of brute-forcing `text-[14px]`.

## 3. Global Recommendations

- **Adopt Mobile-First Design**: Start styling for mobile by default (e.g., `w-full flex-col`), then add breakpoints for larger screens (e.g., `md:w-1/2 md:flex-row`).
- **Avoid Strict Pixels**: Stop using `px` inline styles and arbitrary Tailwind values (`w-[...px]`). Use `rem` units (Tailwind's default scale) so UI scales with browser font settings.
- **Safe Container Bounds**: Wrap page content in a `container mx-auto px-4` to guarantee breathing room on all devices.

---

## Exhaustive Issue Details

### File: `resources/js/components/ActivityStream/ActivityStream.tsx`

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 111
**Current Code:** `<span className="text-[10px] font-medium">{t('Loading older activities...')}</span>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 118
**Current Code:** `<p className="text-[10px] text-muted-foreground uppercase tracking-widest">{t('End of history')}</p>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

### File: `resources/js/components/app-header.tsx`

**Issue:** Hardcoded px value used for dimensions: h-[34px], w-[34px]
**Severity:** High
**Line(s):** 57
**Current Code:** `<Button variant="ghost" size="icon" className="mr-2 h-[34px] w-[34px]">`
**Fix:** Replace h-[34px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Fixed width (w-64) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 61
**Current Code:** `<SheetContent side="left" className="bg-sidebar flex h-full w-64 flex-col items-stretch justify-between">`
**Fix:** Use w-full md:w-64 to allow scaling on small screens.

**Issue:** Fixed width (w-56) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 166
**Current Code:** `<DropdownMenuContent className="w-56" align="end">`
**Fix:** Use w-full md:w-56 to allow scaling on small screens.

### File: `resources/js/components/CrudColumnRenderers.tsx`

**Issue:** Image element missing max-width logic. Large images will overflow their containers.
**Severity:** High
**Line(s):** 48
**Current Code:** `<img`
**Fix:** Add className="max-w-full h-auto" or "object-cover" constraints.

### File: `resources/js/components/CrudTable.tsx`

**Issue:** Hardcoded px value used for dimensions: min-w-[900px]
**Severity:** High
**Line(s):** 317
**Current Code:** `<Table className="w-full max-w-full min-w-[900px]">`
**Fix:** Replace min-w-[900px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Fixed width (w-48) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 346
**Current Code:** `{hasAnyActionPermission && <TableHead className="w-48 py-2.5 text-right font-semibold">{t('Actions')}</TableHead>}`
**Fix:** Use w-full md:w-48 to allow scaling on small screens.

**Issue:** Fixed width (w-48) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 398
**Current Code:** `{hasAnyActionPermission && <TableCell className="py-2.5 text-right w-48">{renderActionButtons(row)}</TableCell>}`
**Fix:** Use w-full md:w-48 to allow scaling on small screens.

**Issue:** Fixed width (w-48) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 441
**Current Code:** `{hasAnyActionPermission && <TableCell className="py-2.5 text-right w-48">{renderActionButtons(row)}</TableCell>}`
**Fix:** Use w-full md:w-48 to allow scaling on small screens.

### File: `resources/js/components/ImagePreview.tsx`

**Issue:** Image element missing max-width logic. Large images will overflow their containers.
**Severity:** High
**Line(s):** 41
**Current Code:** `<img`
**Fix:** Add className="max-w-full h-auto" or "object-cover" constraints.

### File: `resources/js/components/kanban/CommonKanbanBoard.tsx`

**Issue:** Fixed width (w-48) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 172
**Current Code:** `<DropdownMenuContent align="end" className="w-48 z-50" sideOffset={5}>`
**Fix:** Use w-full md:w-48 to allow scaling on small screens.

### File: `resources/js/components/kanban/KanbanCard.tsx`

**Issue:** Fixed width (w-48) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 111
**Current Code:** `<DropdownMenuContent align="end" className="w-48 z-50" sideOffset={5}>`
**Fix:** Use w-full md:w-48 to allow scaling on small screens.

### File: `resources/js/components/kanban/OpportunityKanbanBoard.tsx`

**Issue:** Fixed width (w-48) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 281
**Current Code:** `<DropdownMenuContent align="end" className="w-48 z-50" sideOffset={5}>`
**Fix:** Use w-full md:w-48 to allow scaling on small screens.

### File: `resources/js/components/kanban/ProjectTaskKanbanBoard.tsx`

**Issue:** Fixed width (w-48) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 143
**Current Code:** `<DropdownMenuContent align="end" className="w-48 z-50" sideOffset={5}>`
**Fix:** Use w-full md:w-48 to allow scaling on small screens.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 172
**Current Code:** `<div key={status.id} className="flex-shrink-0" style={{ minWidth: '380px', width: '380px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/components/language-switcher.tsx`

**Issue:** Fixed width (w-56) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 138
**Current Code:** `<DropdownMenuContent className="w-56" align="end" forceMount>`
**Fix:** Use w-full md:w-56 to allow scaling on small screens.

### File: `resources/js/components/MediaLibraryModal.tsx`

**Issue:** Multiple grid columns (grid-cols-6) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 360
**Current Code:** `<div className="grid grid-cols-6 gap-3">`
**Fix:** Change to grid-cols-1 md:grid-cols-6 to enable vertical stacking on mobile.

### File: `resources/js/components/MediaPicker.tsx`

**Issue:** Multiple grid columns (grid-cols-4) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 134
**Current Code:** `<div className="grid grid-cols-4 gap-2 mt-2">`
**Fix:** Change to grid-cols-1 md:grid-cols-4 to enable vertical stacking on mobile.

### File: `resources/js/components/nav-main.tsx`

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 200
**Current Code:** `<span className="px-1.5 py-0.5 text-[10px] font-medium rounded-full bg-primary text-white">`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

### File: `resources/js/components/PageCrudWrapper.tsx`

**Issue:** Fixed width (w-64) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 450
**Current Code:** `<div className="relative w-64">`
**Fix:** Use w-full md:w-64 to allow scaling on small screens.

### File: `resources/js/components/payment/authorizenet-payment-form.tsx`

**Issue:** Multiple grid columns (grid-cols-3) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 230
**Current Code:** `<div className="grid grid-cols-3 gap-4">`
**Fix:** Change to grid-cols-1 md:grid-cols-3 to enable vertical stacking on mobile.

### File: `resources/js/components/payment/invoice-authorizenet-payment-form.tsx`

**Issue:** Multiple grid columns (grid-cols-3) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 217
**Current Code:** `<div className="grid grid-cols-3 gap-4">`
**Fix:** Change to grid-cols-1 md:grid-cols-3 to enable vertical stacking on mobile.

### File: `resources/js/components/payment/invoice-paymentwall-payment-form.tsx`

**Issue:** Hardcoded px value used for dimensions: min-h-[300px]
**Severity:** High
**Line(s):** 143
**Current Code:** `<div id="paymentwall-form-container" ref={paymentFormRef} className="min-h-[300px]">`
**Fix:** Replace min-h-[300px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/components/payment/invoice-paytr-payment-form.tsx`

**Issue:** Hardcoded px value used for dimensions: h-[600px]
**Severity:** High
**Line(s):** 110
**Current Code:** `<div className="w-full h-[600px] border rounded-lg overflow-hidden">`
**Fix:** Replace h-[600px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/components/payment/paymentwall-payment-form.tsx`

**Issue:** Hardcoded px value used for dimensions: min-h-[300px]
**Severity:** High
**Line(s):** 190
**Current Code:** `<div id="paymentwall-form-container" ref={paymentFormRef} className="min-h-[300px]">`
**Fix:** Replace min-h-[300px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/components/payment/paytr-payment-form.tsx`

**Issue:** Hardcoded px value used for dimensions: h-[600px]
**Severity:** High
**Line(s):** 112
**Current Code:** `<div className="w-full h-[600px] border rounded-lg overflow-hidden">`
**Fix:** Replace h-[600px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/components/profile-menu.tsx`

**Issue:** Fixed width (w-56) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 54
**Current Code:** `<DropdownMenuContent className="w-56" align="end" forceMount>`
**Fix:** Use w-full md:w-56 to allow scaling on small screens.

### File: `resources/js/components/sidebar-style-settings.tsx`

**Issue:** Multiple grid columns (grid-cols-3) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 77
**Current Code:** `<div className="grid grid-cols-3 gap-2">`
**Fix:** Change to grid-cols-1 md:grid-cols-3 to enable vertical stacking on mobile.

**Issue:** Multiple grid columns (grid-cols-3) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 99
**Current Code:** `<div className="grid grid-cols-3 gap-2">`
**Fix:** Change to grid-cols-1 md:grid-cols-3 to enable vertical stacking on mobile.

### File: `resources/js/components/simple-multi-select.tsx`

**Issue:** Hardcoded px value used for dimensions: min-h-[38px]
**Severity:** High
**Line(s):** 73
**Current Code:** `className="flex flex-wrap gap-1 p-2 border rounded-md min-h-[38px] cursor-text"`
**Fix:** Replace min-h-[38px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: min-w-[50px]
**Severity:** High
**Line(s):** 100
**Current Code:** `className="flex-1 outline-none bg-transparent min-w-[50px]"`
**Fix:** Replace min-w-[50px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: max-h-[200px]
**Severity:** High
**Line(s):** 105
**Current Code:** `<div className="absolute z-10 w-full mt-1 bg-white border rounded-md shadow-lg max-h-[200px] overflow-y-auto">`
**Fix:** Replace max-h-[200px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/components/theme-preview.tsx`

**Issue:** Image element missing max-width logic. Large images will overflow their containers.
**Severity:** High
**Line(s):** 65
**Current Code:** `<img`
**Fix:** Add className="max-w-full h-auto" or "object-cover" constraints.

**Issue:** Hardcoded px value used for dimensions: max-w-[60px]
**Severity:** High
**Line(s):** 69
**Current Code:** `className="h-5 max-w-[60px] object-contain"`
**Fix:** Replace max-w-[60px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/components/ui/date-picker.tsx`

**Issue:** Hardcoded px value used for dimensions: w-[240px]
**Severity:** High
**Line(s):** 61
**Current Code:** `className="pl-9 w-[240px]"`
**Fix:** Replace w-[240px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/components/ui/rich-text-editor.tsx`

**Issue:** Hardcoded px value used for dimensions: min-h-[200px]
**Severity:** High
**Line(s):** 270
**Current Code:** `className="w-full p-4 min-h-[200px] font-mono text-sm border-0 resize-none focus:outline-none bg-gray-50"`
**Fix:** Replace min-h-[200px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: min-h-[200px]
**Severity:** High
**Line(s):** 276
**Current Code:** `className="prose prose-sm max-w-none p-4 min-h-[200px] focus-within:outline-none"`
**Fix:** Replace min-h-[200px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/components/ui/search-and-filter-bar.tsx`

**Issue:** Hardcoded px value used for dimensions: min-w-[200px]
**Severity:** High
**Line(s):** 86
**Current Code:** `<div className="relative flex-1 sm:w-64 min-w-[200px]">`
**Fix:** Replace min-w-[200px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/components/ui/sidebar.tsx`

**Issue:** Hardcoded px value used for dimensions: w-[2px]
**Severity:** High
**Line(s):** 293
**Current Code:** `"hover:after:bg-sidebar-border absolute inset-y-0 z-20 hidden w-4 -translate-x-1/2 transition-all ease-linear group-data-[side=left]:-right-4 group-data-[side=right]:left-0 after:absolute after:inset-y-0 after:left-1/2 after:w-[2px] sm:flex",`
**Fix:** Replace w-[2px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/components/ui/textarea.tsx`

**Issue:** Hardcoded px value used for dimensions: min-h-[80px]
**Severity:** High
**Line(s):** 13
**Current Code:** `"flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50",`
**Fix:** Replace min-h-[80px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/components/UpgradePlanModal.tsx`

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 207
**Current Code:** `<p className="text-[10px] leading-tight text-amber-700">`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 310
**Current Code:** `<div className="text-[10px] text-slate-500 font-medium uppercase mt-0.5">{t('Users')}</div>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 314
**Current Code:** `<div className="text-[10px] text-slate-500 font-medium uppercase mt-0.5">{t('Projects')}</div>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 318
**Current Code:** `<div className="text-[10px] text-slate-500 font-medium uppercase mt-0.5">{t('Contacts')}</div>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 322
**Current Code:** `<div className="text-[10px] text-slate-500 font-medium uppercase mt-0.5">{t('Accounts')}</div>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 326
**Current Code:** `<div className="text-[10px] text-slate-500 font-medium uppercase mt-0.5">{t('Storage')}</div>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

### File: `resources/js/layouts/auth/auth-split-layout.tsx`

**Issue:** Hardcoded px value used for dimensions: w-[350px]
**Severity:** High
**Line(s):** 32
**Current Code:** `<div className="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">`
**Fix:** Replace w-[350px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/layouts/auth-layout.tsx`

**Issue:** Image element missing max-width logic. Large images will overflow their containers.
**Severity:** High
**Line(s):** 147
**Current Code:** `<img src={currentLogo} alt="Logo" className="w-auto mx-auto" />`
**Fix:** Add className="max-w-full h-auto" or "object-cover" constraints.

### File: `resources/js/pages/accounts/index.tsx`

**Issue:** Fixed width (w-48) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 613
**Current Code:** `<DropdownMenuContent align="end" className="w-48 z-50" sideOffset={5}>`
**Fix:** Use w-full md:w-48 to allow scaling on small screens.

### File: `resources/js/pages/accounts/show.tsx`

**Issue:** Image element missing max-width logic. Large images will overflow their containers.
**Severity:** High
**Line(s):** 362
**Current Code:** `<img`
**Fix:** Add className="max-w-full h-auto" or "object-cover" constraints.

**Issue:** Image element missing max-width logic. Large images will overflow their containers.
**Severity:** High
**Line(s):** 428
**Current Code:** `<img`
**Fix:** Add className="max-w-full h-auto" or "object-cover" constraints.

### File: `resources/js/pages/auth/login.tsx`

**Issue:** Hardcoded px value used for dimensions: w-[14px], h-[14px]
**Severity:** High
**Line(s):** 165
**Current Code:** `className="w-[14px] h-[14px] border border-gray-300 rounded"`
**Fix:** Replace w-[14px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for font size: text-[13px]
**Severity:** Medium
**Line(s):** 233
**Current Code:** `className="group relative py-2 px-4 border text-[13px] font-medium text-white transition-all duration-200 rounded-md shadow-sm hover:shadow-md transform hover:scale-[1.02]"`
**Fix:** Replace text-[13px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[13px]
**Severity:** Medium
**Line(s):** 251
**Current Code:** `className="group relative py-2 px-4 border text-[13px] font-medium text-white transition-all duration-200 rounded-md shadow-sm hover:shadow-md transform hover:scale-[1.02]"`
**Fix:** Replace text-[13px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[13px]
**Severity:** Medium
**Line(s):** 269
**Current Code:** `className="group relative py-2 px-4 border text-[13px] font-medium text-white transition-all duration-200 rounded-md shadow-sm hover:shadow-md transform hover:scale-[1.02]"`
**Fix:** Replace text-[13px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

### File: `resources/js/pages/auth/register.tsx`

**Issue:** Hardcoded px value used for dimensions: w-[14px], h-[14px]
**Severity:** High
**Line(s):** 120
**Current Code:** `className="w-[14px] h-[14px] border border-gray-300 rounded"`
**Fix:** Replace w-[14px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/pages/campaigns/index.tsx`

**Issue:** Fixed width (w-48) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 553
**Current Code:** `<DropdownMenuContent align="end" className="w-48 z-50" sideOffset={5}>`
**Fix:** Use w-full md:w-48 to allow scaling on small screens.

### File: `resources/js/pages/cases/index.tsx`

**Issue:** Fixed width (w-48) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 593
**Current Code:** `<DropdownMenuContent align="end" className="w-48 z-50" sideOffset={5}>`
**Fix:** Use w-full md:w-48 to allow scaling on small screens.

### File: `resources/js/pages/companies/index.tsx`

**Issue:** Fixed width (w-48) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 727
**Current Code:** `<DropdownMenuContent align="end" className="w-48 z-50" sideOffset={5}>`
**Fix:** Use w-full md:w-48 to allow scaling on small screens.

### File: `resources/js/pages/contacts/index.tsx`

**Issue:** Fixed width (w-48) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 513
**Current Code:** `<DropdownMenuContent align="end" className="w-48 z-50" sideOffset={5}>`
**Fix:** Use w-full md:w-48 to allow scaling on small screens.

### File: `resources/js/pages/conversations/components/conversations-calendar.tsx`

**Issue:** Hardcoded px value used for font size: text-[11px]
**Severity:** Medium
**Line(s):** 79
**Current Code:** `<p className="text-[11px] text-muted-foreground">{t('Upcoming follow-ups')}</p>`
**Fix:** Replace text-[11px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 113
**Current Code:** `className="w-full overflow-hidden cursor-pointer rounded text-[10px] px-1 py-0.5"`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 134
**Current Code:** `<p className="text-[10px] text-muted-foreground">{moreDayEvents.length} {t('follow-ups')}</p>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 155
**Current Code:** `<span className="text-[10px] text-muted-foreground">{evt.extendedProps?.status}</span>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

### File: `resources/js/pages/conversations/index.tsx`

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 122
**Current Code:** `<span className={`text-[10px] px-1 py-0 rounded-full ${selectedFolder === f.key ? 'bg-primary text-primary-foreground' : 'bg-muted-foreground/20'`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for dimensions: w-[180px]
**Severity:** High
**Line(s):** 133
**Current Code:** `<div className="hidden xl:flex w-[180px] border-r flex-col bg-muted/30 shrink-0">`
**Fix:** Replace w-[180px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 171
**Current Code:** `<span className={`text-[10px] px-1.5 py-0 rounded-full ${selectedFolder === f.key ? 'bg-primary text-primary-foreground' : 'bg-muted-foreground/20 text-muted-foreground'`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for dimensions: min-h-[250px]
**Severity:** High
**Line(s):** 276
**Current Code:** `class: 'prose prose-sm focus:outline-none max-w-none min-h-[250px] p-6 text-sm leading-relaxed'`
**Fix:** Replace min-h-[250px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: min-h-[60px], min-h-[80px]
**Severity:** High
**Line(s):** 298
**Current Code:** `class: 'prose prose-sm focus:outline-none max-w-none min-h-[60px] lg:min-h-[80px] p-2.5 text-xs lg:text-sm leading-relaxed'`
**Fix:** Replace min-h-[60px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Fixed width (w-64) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 343
**Current Code:** `<PopoverContent className="w-64 p-0 overflow-hidden border shadow-xl" align="start">`
**Fix:** Use w-full md:w-64 to allow scaling on small screens.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 352
**Current Code:** `<h4 className="text-[10px] font-bold text-muted-foreground/70 uppercase px-1">{cat.name}</h4>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Multiple grid columns (grid-cols-7) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 353
**Current Code:** `<div className="grid grid-cols-7 gap-1">`
**Fix:** Change to grid-cols-1 md:grid-cols-7 to enable vertical stacking on mobile.

**Issue:** Hardcoded px value used for dimensions: min-h-[400px]
**Severity:** High
**Line(s):** 990
**Current Code:** `<div className="flex flex-col h-[calc(100vh-200px)] min-h-[400px] border rounded-xl bg-background shadow-sm overflow-hidden relative">`
**Fix:** Replace min-h-[400px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: max-w-[120px]
**Severity:** High
**Line(s):** 1035
**Current Code:** `<h3 className="text-sm font-bold truncate max-w-[120px] lg:max-w-none">{selectedParticipant.name}</h3>`
**Fix:** Replace max-w-[120px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: max-w-[120px]
**Severity:** High
**Line(s):** 1036
**Current Code:** `<p className="text-[10px] text-muted-foreground truncate max-w-[120px] lg:max-w-none">{selectedParticipant.email}</p>`
**Fix:** Replace max-w-[120px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1036
**Current Code:** `<p className="text-[10px] text-muted-foreground truncate max-w-[120px] lg:max-w-none">{selectedParticipant.email}</p>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Fixed width (w-64) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 1088
**Current Code:** `<div className="relative w-64">`
**Fix:** Use w-full md:w-64 to allow scaling on small screens.

**Issue:** Hardcoded px value used for font size: text-[11px]
**Severity:** Medium
**Line(s):** 1126
**Current Code:** `<p className="text-[11px] text-muted-foreground truncate mb-2">`
**Fix:** Replace text-[11px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1130
**Current Code:** `<div className="flex items-center gap-1.5 text-[10px] text-muted-foreground">`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1145
**Current Code:** `<span className="text-[10px] font-medium">{t('Loading more contacts...')}</span>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1152
**Current Code:** `<p className="text-[10px] text-muted-foreground uppercase tracking-widest">{t('End of contacts')}</p>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 1176
**Current Code:** `style={selectedFolder === 'calendar' ? { width: calendarWidth, minWidth: 240, maxWidth: 560, flexShrink: 0 } : { flex: 1, maxWidth: '400px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Hardcoded px value used for dimensions: max-w-[200px]
**Severity:** High
**Line(s):** 1210
**Current Code:** `<p className="text-xs text-muted-foreground mb-4 max-w-[200px]">`
**Fix:** Replace max-w-[200px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: max-w-[200px]
**Severity:** High
**Line(s):** 1226
**Current Code:** `<p className="text-xs text-muted-foreground mb-4 max-w-[200px]">`
**Fix:** Replace max-w-[200px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1245
**Current Code:** `<AvatarFallback className="bg-primary/5 text-primary text-[10px] font-bold">`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[9px]
**Severity:** Medium
**Line(s):** 1257
**Current Code:** `<span className="text-[9px] text-muted-foreground truncate opacity-70">`
**Fix:** Replace text-[9px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for dimensions: max-w-[80px]
**Severity:** High
**Line(s):** 1262
**Current Code:** `<span className="text-[10px] text-muted-foreground/80 truncate shrink-0 max-w-[80px] text-right">`
**Fix:** Replace max-w-[80px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1262
**Current Code:** `<span className="text-[10px] text-muted-foreground/80 truncate shrink-0 max-w-[80px] text-right">`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[11px]
**Severity:** Medium
**Line(s):** 1269
**Current Code:** `<div className="text-[11px] text-muted-foreground/70 truncate">`
**Fix:** Replace text-[11px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[9px]
**Severity:** Medium
**Line(s):** 1276
**Current Code:** `<Badge variant="outline" className="text-[9px] bg-blue-50/50 text-blue-700 border-blue-100 font-bold px-1 py-0">`
**Fix:** Replace text-[9px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[9px]
**Severity:** Medium
**Line(s):** 1281
**Current Code:** `<Badge variant="outline" className="text-[9px] bg-green-50/50 text-green-700 border-green-100 font-bold px-1 py-0">`
**Fix:** Replace text-[9px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[9px]
**Severity:** Medium
**Line(s):** 1289
**Current Code:** `<Badge variant="outline" className={`text-[9px] font-bold px-1 py-0 ${thread.priority === 'High' ? 'bg-destructive/10 text-destructive border-destructive/20' :`
**Fix:** Replace text-[9px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[6px]
**Severity:** Medium
**Line(s):** 1302
**Current Code:** `<AvatarFallback className="text-[6px] bg-muted">{a.name.charAt(0)}</AvatarFallback>`
**Fix:** Replace text-[6px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[8px]
**Severity:** Medium
**Line(s):** 1307
**Current Code:** `<span className="text-[8px] text-muted-foreground font-bold">+{thread.assignments.length - 2}</span>`
**Fix:** Replace text-[8px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1322
**Current Code:** `<span className="text-[10px] font-medium">{t('Loading more...')}</span>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1328
**Current Code:** `<p className="text-[10px] text-muted-foreground uppercase tracking-widest">{t('All threads loaded')}</p>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for dimensions: max-w-[180px]
**Severity:** High
**Line(s):** 1345
**Current Code:** `<p className="text-xs text-muted-foreground mb-4 max-w-[180px]">`
**Fix:** Replace max-w-[180px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: max-w-[200px]
**Severity:** High
**Line(s):** 1351
**Current Code:** `<div className="p-2 bg-destructive/5 text-destructive border border-destructive/10 rounded-lg text-[10px] mb-3 max-w-[200px]">`
**Fix:** Replace max-w-[200px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1351
**Current Code:** `<div className="p-2 bg-destructive/5 text-destructive border border-destructive/10 rounded-lg text-[10px] mb-3 max-w-[200px]">`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[11px]
**Severity:** Medium
**Line(s):** 1395
**Current Code:** `<div className="text-[11px] text-muted-foreground flex items-center gap-1.5">`
**Fix:** Replace text-[11px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for dimensions: max-w-[200px]
**Severity:** High
**Line(s):** 1396
**Current Code:** `<span className="truncate max-w-[200px]">{selectedThread.participants?.join(', ')}</span>`
**Fix:** Replace max-w-[200px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for font size: text-[11px]
**Severity:** Medium
**Line(s):** 1406
**Current Code:** `<Button variant="outline" size="sm" className="h-8 text-[11px] font-bold gap-1.5 px-2.5">`
**Fix:** Replace text-[11px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[8px]
**Severity:** Medium
**Line(s):** 1441
**Current Code:** `<span className="absolute -top-1 -right-1 bg-primary text-primary-foreground text-[8px] font-bold w-3.5 h-3.5 rounded-full flex items-center justify-center">`
**Fix:** Replace text-[8px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Fixed width (w-56) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 1447
**Current Code:** `<DropdownMenuContent align="end" className="w-56 p-0 overflow-hidden">`
**Fix:** Use w-full md:w-56 to allow scaling on small screens.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1449
**Current Code:** `<p className="text-[10px] font-bold text-muted-foreground uppercase">{t('Assign Staff')}</p>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[8px]
**Severity:** Medium
**Line(s):** 1468
**Current Code:** `<AvatarFallback className="text-[8px]">{u.name.charAt(0)}</AvatarFallback>`
**Fix:** Replace text-[8px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Fixed width (w-48) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 1484
**Current Code:** `<DropdownMenuContent align="end" className="w-48">`
**Fix:** Use w-full md:w-48 to allow scaling on small screens.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1485
**Current Code:** `<DropdownMenuLabel className="text-[10px] uppercase text-muted-foreground">{t('Thread Priority')}</DropdownMenuLabel>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1495
**Current Code:** `<DropdownMenuLabel className="text-[10px] uppercase text-muted-foreground">{t('Follow-up Date')}</DropdownMenuLabel>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1523
**Current Code:** `<AvatarFallback className="bg-muted text-[10px]">`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1534
**Current Code:** `<span className="ml-1 text-[10px] font-normal text-muted-foreground italic">`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for dimensions: max-w-[120px]
**Severity:** High
**Line(s):** 1539
**Current Code:** `<span className="text-[10px] text-muted-foreground truncate shrink-0 max-w-[120px] text-right flex items-center gap-1.5 justify-end">`
**Fix:** Replace max-w-[120px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1539
**Current Code:** `<span className="text-[10px] text-muted-foreground truncate shrink-0 max-w-[120px] text-right flex items-center gap-1.5 justify-end">`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for dimensions: max-w-[180px], max-h-[140px]
**Severity:** High
**Line(s):** 1572
**Current Code:** `className="max-w-[180px] max-h-[140px] rounded-md border object-cover hover:opacity-80 transition-opacity"`
**Fix:** Replace max-w-[180px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: max-w-[220px]
**Severity:** High
**Line(s):** 1581
**Current Code:** `className="flex items-center gap-2 px-3 py-2 border rounded-lg bg-muted/30 hover:bg-muted/60 transition-colors max-w-[220px]"`
**Fix:** Replace max-w-[220px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1596
**Current Code:** `<div className="flex justify-center items-center py-2 text-muted-foreground text-[10px] italic">`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for dimensions: min-h-[160px]
**Severity:** High
**Line(s):** 1609
**Current Code:** `<div className={`max-w-4xl mx-auto border rounded-lg shadow-sm focus-within:ring-1 focus-within:ring-primary/30 overflow-hidden relative ${selectedThread.status === 'Archive' ? 'min-h-[160px]' : ''}`}>`
**Fix:** Replace min-h-[160px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: max-w-[240px]
**Severity:** High
**Line(s):** 1619
**Current Code:** `<p className="text-xs text-muted-foreground max-w-[240px]">`
**Fix:** Replace max-w-[240px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: min-h-[160px]
**Severity:** High
**Line(s):** 1636
**Current Code:** `<div className="absolute inset-0 z-10 bg-background/80 backdrop-blur-[1px] flex items-center justify-center min-h-[160px]">`
**Fix:** Replace min-h-[160px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: max-w-[240px]
**Severity:** High
**Line(s):** 1646
**Current Code:** `<p className="text-xs text-muted-foreground max-w-[240px]">`
**Fix:** Replace max-w-[240px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: min-h-[60px], min-h-[80px]
**Severity:** High
**Line(s):** 1668
**Current Code:** `className="w-full min-h-[60px] lg:min-h-[80px] cursor-text bg-background"`
**Fix:** Replace min-h-[60px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1676
**Current Code:** `<span className="w-10 text-[10px] font-bold text-muted-foreground pt-1.5">CC</span>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for dimensions: min-w-[80px]
**Severity:** High
**Line(s):** 1723
**Current Code:** `className="flex-1 min-w-[80px] bg-transparent border-none text-xs focus:ring-0 p-0 placeholder:text-muted-foreground/50 h-5 outline-none"`
**Fix:** Replace min-w-[80px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1728
**Current Code:** `<span className="w-10 text-[10px] font-bold text-muted-foreground group-focus-within:text-foreground">BCC</span>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for dimensions: min-w-[80px]
**Severity:** High
**Line(s):** 1775
**Current Code:** `className="flex-1 min-w-[80px] bg-transparent border-none text-xs focus:ring-0 p-0 placeholder:text-muted-foreground/50 h-5 outline-none"`
**Fix:** Replace min-w-[80px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: max-w-[120px]
**Severity:** High
**Line(s):** 1787
**Current Code:** `<span className="truncate max-w-[120px]">{file.name}</span>`
**Fix:** Replace max-w-[120px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: w-[280px]
**Severity:** High
**Line(s):** 1902
**Current Code:** `<div className="absolute right-0 top-0 bottom-0 z-30 w-[280px] max-w-[85vw] border-l flex flex-col bg-background shadow-2xl overflow-y-auto">`
**Fix:** Replace w-[280px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for font size: text-[11px]
**Severity:** Medium
**Line(s):** 1928
**Current Code:** `<p className="text-[11px] text-muted-foreground truncate w-full">`
**Fix:** Replace text-[11px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1941
**Current Code:** `<Badge className="bg-blue-600 font-bold text-[10px] px-1.5 py-0">{t('LEAD')}</Badge>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1942
**Current Code:** `<span className="text-[10px] text-blue-600 uppercase font-bold">`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[11px]
**Severity:** Medium
**Line(s):** 1946
**Current Code:** `<div className="space-y-1.5 text-[11px]">`
**Fix:** Replace text-[11px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[9px]
**Severity:** Medium
**Line(s):** 1953
**Current Code:** `<Badge variant="outline" className="h-4 text-[9px]">`
**Fix:** Replace text-[9px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[11px]
**Severity:** Medium
**Line(s):** 1959
**Current Code:** `<Button variant="link" size="sm" className="p-0 h-auto mt-3 text-[11px] font-semibold">`
**Fix:** Replace text-[11px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1971
**Current Code:** `<p className="text-[10px] text-amber-700 font-bold uppercase mb-1 flex items-center gap-1">`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[11px]
**Severity:** Medium
**Line(s):** 1975
**Current Code:** `<p className="text-[11px] text-amber-900 font-medium">`
**Fix:** Replace text-[11px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1978
**Current Code:** `<p className="text-[10px] text-amber-600 truncate">`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 1992
**Current Code:** `<div className="relative flex justify-center text-[10px] uppercase"><span className="bg-background px-2 text-muted-foreground font-bold">{t('Or')}</span></div>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[11px]
**Severity:** Medium
**Line(s):** 1997
**Current Code:** `className="w-full text-[11px] h-7 border-dashed"`
**Fix:** Replace text-[11px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[11px]
**Severity:** Medium
**Line(s):** 2006
**Current Code:** `<p className="text-[11px] text-muted-foreground mb-3 text-center">`
**Fix:** Replace text-[11px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[11px]
**Severity:** Medium
**Line(s):** 2012
**Current Code:** `className="w-full text-[11px] h-7"`
**Fix:** Replace text-[11px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 2025
**Current Code:** `<h4 className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-2">`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[11px]
**Severity:** Medium
**Line(s):** 2031
**Current Code:** `<p className="text-[11px]">`
**Fix:** Replace text-[11px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[11px]
**Severity:** Medium
**Line(s):** 2038
**Current Code:** `<p className="text-[11px]">`
**Fix:** Replace text-[11px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for dimensions: max-w-[700px]
**Severity:** High
**Line(s):** 2055
**Current Code:** `<DialogContent className="sm:max-w-[700px] p-0 overflow-hidden gap-0 border-none shadow-2xl rounded-xl">`
**Fix:** Replace max-w-[700px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: w-[72px]
**Severity:** High
**Line(s):** 2064
**Current Code:** `<Label htmlFor="compose-to" className="w-[72px] text-sm font-bold text-muted-foreground group-focus-within:text-foreground transition-colors">{t('To')}</Label>`
**Fix:** Replace w-[72px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 2076
**Current Code:** `className="h-7 text-[10px] font-bold text-muted-foreground hover:text-primary transition-colors uppercase tracking-tight"`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for dimensions: w-[72px]
**Severity:** High
**Line(s):** 2084
**Current Code:** `<Label htmlFor="compose-cc" className="w-[72px] text-xs font-bold text-muted-foreground group-focus-within:text-foreground">{t('Cc')}</Label>`
**Fix:** Replace w-[72px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: w-[72px]
**Severity:** High
**Line(s):** 2094
**Current Code:** `<Label htmlFor="compose-bcc" className="w-[72px] text-xs font-bold text-muted-foreground group-focus-within:text-foreground">{t('Bcc')}</Label>`
**Fix:** Replace w-[72px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: w-[72px]
**Severity:** High
**Line(s):** 2106
**Current Code:** `<Label htmlFor="compose-subject" className="w-[72px] text-sm font-bold text-muted-foreground group-focus-within:text-foreground transition-colors">{t('Subject')}</Label>`
**Fix:** Replace w-[72px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: min-h-[250px]
**Severity:** High
**Line(s):** 2116
**Current Code:** `<div className="min-h-[250px] cursor-text" onClick={() => composeEditor?.commands.focus()}>`
**Fix:** Replace min-h-[250px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: max-w-[140px]
**Severity:** High
**Line(s):** 2129
**Current Code:** `<span className="truncate max-w-[140px]">{file.name}</span>`
**Fix:** Replace max-w-[140px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/pages/dashboard.tsx`

**Issue:** Hardcoded px value used for dimensions: h-[320px]
**Severity:** High
**Line(s):** 310
**Current Code:** `<div className="flex flex-col items-center justify-center h-[320px] text-muted-foreground">`
**Fix:** Replace h-[320px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 440
**Current Code:** `wrapperStyle={{ paddingTop: '20px', fontSize: '12px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Hardcoded px value used for dimensions: h-[320px]
**Severity:** High
**Line(s):** 459
**Current Code:** `<div className="flex flex-col items-center justify-center h-[320px] text-muted-foreground">`
**Fix:** Replace h-[320px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 523
**Current Code:** `wrapperStyle={{ fontSize: '12px', paddingTop: '10px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Hardcoded px value used for dimensions: h-[320px]
**Severity:** High
**Line(s):** 529
**Current Code:** `<div className="flex flex-col items-center justify-center h-[320px] text-muted-foreground">`
**Fix:** Replace h-[320px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/pages/documents/index.tsx`

**Issue:** Fixed width (w-48) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 631
**Current Code:** `<DropdownMenuContent align="end" className="w-48 z-50" sideOffset={5}>`
**Fix:** Use w-full md:w-48 to allow scaling on small screens.

### File: `resources/js/pages/email-templates/show.tsx`

**Issue:** Hardcoded px value used for dimensions: min-h-[300px]
**Severity:** High
**Line(s):** 226
**Current Code:** `className="min-h-[300px]"`
**Fix:** Replace min-h-[300px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/pages/invoices/templates/Template1.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 129
**Current Code:** `style={{ maxWidth: '150px', maxHeight: '150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 134
**Current Code:** `<h3 style={{ textTransform: 'uppercase', fontSize: '40px', fontWeight: 'bold' }}>{t('INVOICE')}</h3>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 145
**Current Code:** `<p style={{ margin: '10px 0', lineHeight: '1.5' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 215
**Current Code:** `<th style={styles.th}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 248
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(invoice.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 254
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 259
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(paidAmount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 263
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(dueAmount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 267
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(invoice.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/invoices/templates/Template10.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 45
**Current Code:** `<div style={{ padding: '15px 30px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 79
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 89
**Current Code:** `<div style={{ padding: '15px 30px 0' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 104
**Current Code:** `<td style={{ padding: '0', fontSize: '13px', fontWeight: '600', color: `#${color}` }}></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 105
**Current Code:** `<td style={{ padding: '0', textAlign: 'right', fontSize: '13px', fontWeight: '600' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 110
**Current Code:** `<td style={{ padding: '0', fontSize: '13px', fontWeight: '600', color: `#${color}` }}></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 111
**Current Code:** `<td style={{ padding: '0', textAlign: 'right', fontSize: '13px', fontWeight: '600' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 126
**Current Code:** `<div style={{ padding: '30px 25px 30px 25px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 164
**Current Code:** `<th style={{ padding: '0.75rem', textAlign: 'left' }}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 206
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(invoice.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 212
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 217
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(paidAmount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 221
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(dueAmount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 225
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(invoice.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/invoices/templates/Template2.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 126
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 132
**Current Code:** `<p style={{ margin: '10px 0', lineHeight: '1.5' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 145
**Current Code:** `<h3 style={{ textTransform: 'uppercase', fontSize: '25px', fontWeight: 'bold', marginBottom: '15px' }}>{t('INVOICE')}</h3>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 206
**Current Code:** `<th style={styles.th}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 239
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(invoice.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 245
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 250
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(paidAmount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 254
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(dueAmount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 258
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(invoice.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/invoices/templates/Template3.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 124
**Current Code:** `<h3 style={{ textTransform: 'uppercase', fontSize: '20px', fontWeight: 'bold' }}>{t('INVOICE')}</h3>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 137
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 149
**Current Code:** `<p style={{ margin: '10px 0', lineHeight: '1.5' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 207
**Current Code:** `<th style={styles.th}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 240
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(invoice.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 246
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 251
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(paidAmount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 255
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(dueAmount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 259
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(invoice.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/invoices/templates/Template4.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 49
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 65
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top', textAlign: 'right' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 69
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 110
**Current Code:** `<div className="invoice-body" style={{ padding: '30px 25px 30px 25px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 147
**Current Code:** `<th style={{ padding: '0.75rem', textAlign: 'left' }}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 189
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(invoice.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 195
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 200
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(paidAmount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 204
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(dueAmount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 208
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(invoice.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/invoices/templates/Template5.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 49
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 86
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top', textAlign: 'right' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 90
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 106
**Current Code:** `<div style={{ padding: '30px 25px 30px 25px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 143
**Current Code:** `<th style={{ padding: '0.75rem', textAlign: 'left' }}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 185
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(invoice.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 191
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 196
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(paidAmount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 200
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(dueAmount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 204
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(invoice.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/invoices/templates/Template6.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 49
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 57
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top', textAlign: 'right' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 61
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 71
**Current Code:** `<div style={{ padding: '30px 25px 30px 25px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 75
**Current Code:** `<td style={{ fontSize: '13px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 84
**Current Code:** `<td style={{ fontSize: '13px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 95
**Current Code:** `<td style={{ fontSize: '13px', verticalAlign: 'top', textAlign: 'right' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 157
**Current Code:** `<th style={{ padding: '0.75rem', textAlign: 'left' }}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 199
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(invoice.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 205
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 210
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(paidAmount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 214
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(dueAmount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 218
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(invoice.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/invoices/templates/Template7.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 49
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 53
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 58
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top', textAlign: 'right' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 73
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 80
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 117
**Current Code:** `<div style={{ padding: '30px 25px 30px 25px', borderBottom: `15px solid #${color}` }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 155
**Current Code:** `<th style={{ padding: '0.75rem', textAlign: 'left' }}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 197
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(invoice.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 203
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 208
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(paidAmount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 212
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(dueAmount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 216
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(invoice.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/invoices/templates/Template8.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 45
**Current Code:** `<div style={{ padding: '15px 30px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 53
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 72
**Current Code:** `<div style={{ padding: '15px 30px 0' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 104
**Current Code:** `<td style={{ padding: '0', fontSize: '13px', fontWeight: '600' }}></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 105
**Current Code:** `<td style={{ padding: '0', textAlign: 'right', fontSize: '13px', fontWeight: '600' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 110
**Current Code:** `<td style={{ padding: '0', fontSize: '13px', fontWeight: '600' }}></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 111
**Current Code:** `<td style={{ padding: '0', textAlign: 'right', fontSize: '13px', fontWeight: '600' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 123
**Current Code:** `<div style={{ padding: '30px 25px 30px 25px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 161
**Current Code:** `<th style={{ padding: '0.75rem', textAlign: 'left' }}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 203
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(invoice.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 209
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 214
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(paidAmount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 218
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(dueAmount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 222
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(invoice.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/invoices/templates/Template9.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 46
**Current Code:** `<div style={{ padding: '15px 30px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 61
**Current Code:** `<td style={{ padding: '0', fontSize: '13px', fontWeight: '600' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 66
**Current Code:** `<td style={{ padding: '0', fontSize: '13px', fontWeight: '600' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 77
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 87
**Current Code:** `<div style={{ padding: '15px 30px 0' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 127
**Current Code:** `<div style={{ padding: '15px 30px 0' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 154
**Current Code:** `<div style={{ padding: '30px 25px 30px 25px', paddingRight: '0' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 166
**Current Code:** `<th style={{ padding: '0.75rem', textAlign: 'left' }}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 208
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(invoice.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 214
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 219
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(paidAmount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 223
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(dueAmount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 227
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(invoice.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/Invoices-template/TemplatePreview.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 84
**Current Code:** `<h1 style={{ margin: 0, fontSize: '40px', fontWeight: 'bold' }}>INVOICE</h1>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 98
**Current Code:** `<p style={{ margin: '10px 0 0 0' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 107
**Current Code:** `<p style={{ margin: '10px 0 0 0' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 157
**Current Code:** `<div style={{ marginTop: '30px', padding: '15px', background: '#f9f9f9', borderRadius: '5px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 159
**Current Code:** `<p style={{ margin: '10px 0 0 0' }}>{invoice.notes}</p>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/landing-page/components/HeroSection.tsx`

**Issue:** Multiple grid columns (grid-cols-3) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 79
**Current Code:** `<div className="grid grid-cols-3 gap-4 sm:gap-6 lg:gap-8 pt-8 sm:pt-12">`
**Fix:** Change to grid-cols-1 md:grid-cols-3 to enable vertical stacking on mobile.

### File: `resources/js/pages/landing-page/components/NewsletterSection.tsx`

**Issue:** Hardcoded px value used for dimensions: min-w-[120px]
**Severity:** High
**Line(s):** 117
**Current Code:** `className="text-white px-8 py-3 rounded-lg transition-colors font-semibold disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 min-w-[120px]"`
**Fix:** Replace min-w-[120px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/pages/landing-page/components/TemplatePreviewCard.tsx`

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 39
**Current Code:** `<span className="inline-block px-1.5 py-0.5 rounded-full text-[10px] capitalize"`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

### File: `resources/js/pages/landing-page/custom-pages/index.tsx`

**Issue:** Hardcoded px value used for dimensions: min-h-[200px]
**Severity:** High
**Line(s):** 309
**Current Code:** `className="min-h-[200px]"`
**Fix:** Replace min-h-[200px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: min-h-[200px]
**Severity:** High
**Line(s):** 380
**Current Code:** `className="min-h-[200px]"`
**Fix:** Replace min-h-[200px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/pages/landing-page/settings-about.tsx`

**Issue:** Multiple grid columns (grid-cols-3) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 245
**Current Code:** `<div className="grid grid-cols-3 gap-4">`
**Fix:** Change to grid-cols-1 md:grid-cols-3 to enable vertical stacking on mobile.

### File: `resources/js/pages/landing-page/settings.tsx`

**Issue:** Multiple grid columns (grid-cols-3) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 1235
**Current Code:** `<div key={index} className="grid grid-cols-3 gap-4 p-4 border rounded-lg">`
**Fix:** Change to grid-cols-1 md:grid-cols-3 to enable vertical stacking on mobile.

**Issue:** Multiple grid columns (grid-cols-3) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 1667
**Current Code:** `<div key={index} className="grid grid-cols-3 gap-4 p-4 border rounded-lg">`
**Fix:** Change to grid-cols-1 md:grid-cols-3 to enable vertical stacking on mobile.

**Issue:** Multiple grid columns (grid-cols-3) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 2730
**Current Code:** `<div className="grid grid-cols-3 gap-4">`
**Fix:** Change to grid-cols-1 md:grid-cols-3 to enable vertical stacking on mobile.

### File: `resources/js/pages/leads/index.tsx`

**Issue:** Fixed width (w-48) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 1215
**Current Code:** `<DropdownMenuContent align="end" className="w-48 z-50" sideOffset={5}>`
**Fix:** Use w-full md:w-48 to allow scaling on small screens.

### File: `resources/js/pages/leads/show.tsx`

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 801
**Current Code:** `<span className="text-[10px] font-medium">{t('Loading older activities...')}</span>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 808
**Current Code:** `<p className="text-[10px] text-muted-foreground uppercase tracking-widest">{t('End of history')}</p>`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

### File: `resources/js/pages/notification-templates/show.tsx`

**Issue:** Hardcoded px value used for dimensions: min-h-[200px]
**Severity:** High
**Line(s):** 197
**Current Code:** `className="min-h-[200px] focus:ring-2 focus:ring-primary"`
**Fix:** Replace min-h-[200px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/pages/onboarding/plan.tsx`

**Issue:** Hardcoded px value used for dimensions: max-h-[400px]
**Severity:** High
**Line(s):** 79
**Current Code:** `<div className="space-y-3 mb-6 max-h-[400px] overflow-y-auto pr-1">`
**Fix:** Replace max-h-[400px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 101
**Current Code:** `<span className="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-green-100 text-green-700">`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 106
**Current Code:** `<span className="text-[10px] font-semibold px-2 py-0.5 rounded-full" style={{`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[11px]
**Severity:** Medium
**Line(s):** 151
**Current Code:** `<span key={idx} className="text-[11px] text-gray-500 flex items-center gap-1">`
**Fix:** Replace text-[11px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

### File: `resources/js/pages/onboarding/roles.tsx`

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 285
**Current Code:** `<span className="text-[10px] px-1.5 py-0.5 rounded-full font-medium" style={{`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for font size: text-[11px]
**Severity:** Medium
**Line(s):** 312
**Current Code:** `<span className="text-[11px] text-gray-600">`
**Fix:** Replace text-[11px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for dimensions: min-w-[120px]
**Severity:** High
**Line(s):** 375
**Current Code:** `className="text-xs border border-gray-300 rounded-md px-2 py-1.5 bg-white focus:outline-none focus:ring-2 min-w-[120px]"`
**Fix:** Replace min-w-[120px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/pages/opportunities/index.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 764
**Current Code:** `style={{ minWidth: '300px', width: '300px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Fixed width (w-48) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 1011
**Current Code:** `<DropdownMenuContent align="end" className="w-48 z-50" sideOffset={5}>`
**Fix:** Use w-full md:w-48 to allow scaling on small screens.

### File: `resources/js/pages/plans/form.tsx`

**Issue:** Fixed width (w-56) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 325
**Current Code:** `className="flex h-9 w-56 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"`
**Fix:** Use w-full md:w-56 to allow scaling on small screens.

### File: `resources/js/pages/plans/index.tsx`

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 270
**Current Code:** `<p className="text-[10px] text-amber-700">`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for dimensions: w-[400px]
**Severity:** High
**Line(s):** 425
**Current Code:** `className="w-full sm:w-[400px]"`
**Fix:** Replace w-[400px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/pages/products/index.tsx`

**Issue:** Fixed width (w-48) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 623
**Current Code:** `<DropdownMenuContent align="end" className="w-48 z-50" sideOffset={5}>`
**Fix:** Use w-full md:w-48 to allow scaling on small screens.

### File: `resources/js/pages/project-tasks/index.tsx`

**Issue:** Fixed width (w-48) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 549
**Current Code:** `<DropdownMenuContent align="end" className="w-48 z-50" sideOffset={5}>`
**Fix:** Use w-full md:w-48 to allow scaling on small screens.

### File: `resources/js/pages/projects/index.tsx`

**Issue:** Fixed width (w-48) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 567
**Current Code:** `<DropdownMenuContent align="end" className="w-48 z-50" sideOffset={5}>`
**Fix:** Use w-full md:w-48 to allow scaling on small screens.

### File: `resources/js/pages/purchase-orders/show.tsx`

**Issue:** Image element missing max-width logic. Large images will overflow their containers.
**Severity:** High
**Line(s):** 406
**Current Code:** `<img`
**Fix:** Add className="max-w-full h-auto" or "object-cover" constraints.

**Issue:** Image element missing max-width logic. Large images will overflow their containers.
**Severity:** High
**Line(s):** 472
**Current Code:** `<img`
**Fix:** Add className="max-w-full h-auto" or "object-cover" constraints.

### File: `resources/js/pages/quotes/templates/Template1.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 123
**Current Code:** `style={{ maxWidth: '150px', maxHeight: '150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 128
**Current Code:** `<h3 style={{ textTransform: 'uppercase', fontSize: '40px', fontWeight: 'bold' }}>{t("QUOTE")}</h3>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 139
**Current Code:** `<p style={{ margin: '10px 0', lineHeight: '1.5' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 213
**Current Code:** `<th style={styles.th}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 246
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(quote.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 252
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 257
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(quote.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/quotes/templates/Template10.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 40
**Current Code:** `<div style={{ padding: '15px 30px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 74
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 84
**Current Code:** `<div style={{ padding: '15px 30px 0' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 99
**Current Code:** `<td style={{ padding: '0', fontSize: '13px', fontWeight: '600', color: `#${color}` }}></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 100
**Current Code:** `<td style={{ padding: '0', textAlign: 'right', fontSize: '13px', fontWeight: '600' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 105
**Current Code:** `<td style={{ padding: '0', fontSize: '13px', fontWeight: '600', color: `#${color}` }}></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 106
**Current Code:** `<td style={{ padding: '0', textAlign: 'right', fontSize: '13px', fontWeight: '600' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 121
**Current Code:** `<div style={{ padding: '30px 25px 30px 25px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 163
**Current Code:** `<th style={{ padding: '0.75rem', textAlign: 'left' }}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 205
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(quote.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 211
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 216
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(quote.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/quotes/templates/Template2.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 120
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 126
**Current Code:** `<p style={{ margin: '10px 0', lineHeight: '1.5' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 139
**Current Code:** `<h3 style={{ textTransform: 'uppercase', fontSize: '25px', fontWeight: 'bold', marginBottom: '15px' }}>{t('QUOTE')}</h3>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 204
**Current Code:** `<th style={styles.th}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 237
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(quote.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 243
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 248
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(quote.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/quotes/templates/Template3.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 119
**Current Code:** `<h3 style={{ textTransform: 'uppercase', fontSize: '20px', fontWeight: 'bold' }}>{t('QUOTE')}</h3>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 132
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 144
**Current Code:** `<p style={{ margin: '10px 0', lineHeight: '1.5' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 206
**Current Code:** `<th style={styles.th}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 239
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(quote.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 245
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 250
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(quote.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/quotes/templates/Template4.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 44
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 60
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top', textAlign: 'right' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 64
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 105
**Current Code:** `<div className="invoice-body" style={{ padding: '30px 25px 30px 25px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 146
**Current Code:** `<th style={{ padding: '0.75rem', textAlign: 'left' }}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 188
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(quote.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 194
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 199
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(quote.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/quotes/templates/Template5.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 44
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 81
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top', textAlign: 'right' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 85
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 101
**Current Code:** `<div style={{ padding: '30px 25px 30px 25px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 142
**Current Code:** `<th style={{ padding: '0.75rem', textAlign: 'left' }}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 184
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(quote.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 190
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 195
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(quote.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/quotes/templates/Template6.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 44
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 52
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top', textAlign: 'right' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 56
**Current Code:** `style={{ maxWidth: '150px', maxHeight: '150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 66
**Current Code:** `<div style={{ padding: '30px 25px 30px 25px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 70
**Current Code:** `<td style={{ fontSize: '13px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 79
**Current Code:** `<td style={{ fontSize: '13px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 90
**Current Code:** `<td style={{ fontSize: '13px', verticalAlign: 'top', textAlign: 'right' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 156
**Current Code:** `<th style={{ padding: '0.75rem', textAlign: 'left' }}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 198
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(quote.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 204
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 209
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(quote.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/quotes/templates/Template7.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 44
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 48
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 53
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top', textAlign: 'right' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 68
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 75
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 112
**Current Code:** `<div style={{ padding: '30px 25px 30px 25px', borderBottom: `15px solid #${color}`}}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 154
**Current Code:** `<th style={{ padding: '0.75rem', textAlign: 'left' }}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 196
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(quote.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 202
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 207
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(quote.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/quotes/templates/Template8.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 40
**Current Code:** `<div style={{ padding: '15px 30px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 48
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 67
**Current Code:** `<div style={{ padding: '15px 30px 0' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 99
**Current Code:** `<td style={{ padding: '0', fontSize: '13px', fontWeight: '600' }}></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 100
**Current Code:** `<td style={{ padding: '0', textAlign: 'right', fontSize: '13px', fontWeight: '600' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 105
**Current Code:** `<td style={{ padding: '0', fontSize: '13px', fontWeight: '600' }}></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 106
**Current Code:** `<td style={{ padding: '0', textAlign: 'right', fontSize: '13px', fontWeight: '600' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 118
**Current Code:** `<div style={{ padding: '30px 25px 30px 25px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 160
**Current Code:** `<th style={{ padding: '0.75rem', textAlign: 'left' }}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 202
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(quote.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 208
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 213
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(quote.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/quotes/templates/Template9.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 41
**Current Code:** `<div style={{ padding: '15px 30px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 56
**Current Code:** `<td style={{ padding: '0', fontSize: '13px', fontWeight: '600' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 61
**Current Code:** `<td style={{ padding: '0', fontSize: '13px', fontWeight: '600' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 72
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 82
**Current Code:** `<div style={{ padding: '15px 30px 0' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 122
**Current Code:** `<div style={{ padding: '15px 30px 0' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 153
**Current Code:** `<div style={{ padding: '30px 25px 30px 25px', paddingRight: '0' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 165
**Current Code:** `<th style={{ padding: '0.75rem', textAlign: 'left' }}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 207
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(quote.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 213
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 218
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(quote.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/receipt-orders/show.tsx`

**Issue:** Fixed width (w-64) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 259
**Current Code:** `<div className="w-64 space-y-2">`
**Fix:** Use w-full md:w-64 to allow scaling on small screens.

### File: `resources/js/pages/sales-orders/templates/Template1.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 123
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 128
**Current Code:** `<h3 style={{ textTransform: 'uppercase', fontSize: '40px', fontWeight: 'bold' }}>{t('SALES ORDER')}</h3>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 139
**Current Code:** `<p style={{ margin: '10px 0', lineHeight: '1.5' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 213
**Current Code:** `<th style={styles.th}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 246
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(salesOrder.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 252
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 257
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(salesOrder.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/sales-orders/templates/Template10.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 40
**Current Code:** `<div style={{ padding: '15px 30px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 74
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 84
**Current Code:** `<div style={{ padding: '15px 30px 0' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 99
**Current Code:** `<td style={{ padding: '0', fontSize: '13px', fontWeight: '600', color: `#${color}` }}></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 100
**Current Code:** `<td style={{ padding: '0', textAlign: 'right', fontSize: '13px', fontWeight: '600' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 105
**Current Code:** `<td style={{ padding: '0', fontSize: '13px', fontWeight: '600', color: `#${color}` }}></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 106
**Current Code:** `<td style={{ padding: '0', textAlign: 'right', fontSize: '13px', fontWeight: '600' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 121
**Current Code:** `<div style={{ padding: '30px 25px 30px 25px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 163
**Current Code:** `<th style={{ padding: '0.75rem', textAlign: 'left' }}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 205
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(salesOrder.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 211
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 216
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(salesOrder.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/sales-orders/templates/Template2.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 120
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 126
**Current Code:** `<p style={{ margin: '10px 0', lineHeight: '1.5' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 139
**Current Code:** `<h3 style={{ textTransform: 'uppercase', fontSize: '25px', fontWeight: 'bold', marginBottom: '15px' }}>{t('SALES ORDER')}</h3>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 204
**Current Code:** `<th style={styles.th}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 237
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(salesOrder.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 243
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 248
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(salesOrder.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/sales-orders/templates/Template3.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 119
**Current Code:** `<h3 style={{ textTransform: 'uppercase', fontSize: '20px', fontWeight: 'bold' }}>{t('SALES ORDER')}</h3>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 132
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 144
**Current Code:** `<p style={{ margin: '10px 0', lineHeight: '1.5' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 206
**Current Code:** `<th style={styles.th}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 239
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(salesOrder.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 245
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 250
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(salesOrder.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/sales-orders/templates/Template4.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 44
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 60
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top', textAlign: 'right' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 64
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 105
**Current Code:** `<div className="invoice-body" style={{ padding: '30px 25px 30px 25px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 146
**Current Code:** `<th style={{ padding: '0.75rem', textAlign: 'left' }}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 188
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(salesOrder.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 194
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 199
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(salesOrder.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/sales-orders/templates/Template5.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 44
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 81
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top', textAlign: 'right' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 85
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 101
**Current Code:** `<div style={{ padding: '30px 25px 30px 25px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 142
**Current Code:** `<th style={{ padding: '0.75rem', textAlign: 'left' }}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 184
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(salesOrder.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 190
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 195
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(salesOrder.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/sales-orders/templates/Template6.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 44
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 52
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top', textAlign: 'right' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 56
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 66
**Current Code:** `<div style={{ padding: '30px 25px 30px 25px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 70
**Current Code:** `<td style={{ fontSize: '13px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 80
**Current Code:** `<td style={{ fontSize: '13px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 91
**Current Code:** `<td style={{ fontSize: '13px', verticalAlign: 'top', textAlign: 'right' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 157
**Current Code:** `<th style={{ padding: '0.75rem', textAlign: 'left' }}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 199
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(salesOrder.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 205
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 210
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(salesOrder.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/sales-orders/templates/Template7.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 44
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 48
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 53
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top', textAlign: 'right' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 68
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 75
**Current Code:** `<td style={{ padding: '15px 30px', verticalAlign: 'top' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 112
**Current Code:** `<div style={{ padding: '30px 25px 30px 25px', borderBottom: `15px solid #${color}`}}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 154
**Current Code:** `<th style={{ padding: '0.75rem', textAlign: 'left' }}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 196
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(salesOrder.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 202
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 207
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(salesOrder.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/sales-orders/templates/Template8.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 40
**Current Code:** `<div style={{ padding: '15px 30px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 48
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 67
**Current Code:** `<div style={{ padding: '15px 30px 0' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 99
**Current Code:** `<td style={{ padding: '0', fontSize: '13px', fontWeight: '600' }}></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 100
**Current Code:** `<td style={{ padding: '0', textAlign: 'right', fontSize: '13px', fontWeight: '600' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 105
**Current Code:** `<td style={{ padding: '0', fontSize: '13px', fontWeight: '600' }}></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 106
**Current Code:** `<td style={{ padding: '0', textAlign: 'right', fontSize: '13px', fontWeight: '600' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 118
**Current Code:** `<div style={{ padding: '30px 25px 30px 25px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 160
**Current Code:** `<th style={{ padding: '0.75rem', textAlign: 'left' }}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 202
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(salesOrder.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 208
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 213
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(salesOrder.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/sales-orders/templates/Template9.tsx`

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 41
**Current Code:** `<div style={{ padding: '15px 30px' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 56
**Current Code:** `<td style={{ padding: '0', fontSize: '13px', fontWeight: '600' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 61
**Current Code:** `<td style={{ padding: '0', fontSize: '13px', fontWeight: '600' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 72
**Current Code:** `style={{ maxWidth: '150px',maxHeight:'150px' }}`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 82
**Current Code:** `<div style={{ padding: '15px 30px 0' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 122
**Current Code:** `<div style={{ padding: '15px 30px 0' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 153
**Current Code:** `<div style={{ padding: '30px 25px 30px 25px', paddingRight: '0' }}>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 165
**Current Code:** `<th style={{ padding: '0.75rem', textAlign: 'left' }}>{t('Price')} <small style={{ display: 'block', fontSize: '12px' }}>{t('before tax & discount')}</small></th>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 207
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(salesOrder.total_discount)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 213
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}>{formatCurrency(taxPrice as number)}</td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

**Issue:** Inline style using strict px values which breaks responsiveness.
**Severity:** High
**Line(s):** 218
**Current Code:** `<td style={{ padding: '0.75rem 0 0 0', textAlign: 'right', width: '146px' }}><strong>{formatCurrency(salesOrder.total_amount || 0)}</strong></td>`
**Fix:** Move inline style to standard Tailwind classes, or use 100% / relative units like rem/em.

### File: `resources/js/pages/settings/components/brand-settings.tsx`

**Issue:** Multiple grid columns (grid-cols-6) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 559
**Current Code:** `<div className="grid grid-cols-6 gap-2">`
**Fix:** Change to grid-cols-1 md:grid-cols-6 to enable vertical stacking on mobile.

**Issue:** Multiple grid columns (grid-cols-3) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 630
**Current Code:** `<div className="grid grid-cols-3 gap-3">`
**Fix:** Change to grid-cols-1 md:grid-cols-3 to enable vertical stacking on mobile.

**Issue:** Multiple grid columns (grid-cols-3) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 655
**Current Code:** `<div className="grid grid-cols-3 gap-3">`
**Fix:** Change to grid-cols-1 md:grid-cols-3 to enable vertical stacking on mobile.

**Issue:** Multiple grid columns (grid-cols-3) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 740
**Current Code:** `<div className="grid grid-cols-3 gap-2">`
**Fix:** Change to grid-cols-1 md:grid-cols-3 to enable vertical stacking on mobile.

### File: `resources/js/pages/settings/components/currency-settings.tsx`

**Issue:** Hardcoded px value used for dimensions: max-w-[200px]
**Severity:** High
**Line(s):** 183
**Current Code:** `<div className="w-full md:w-auto md:max-w-[200px]">`
**Fix:** Replace max-w-[200px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: max-h-[300px]
**Severity:** High
**Line(s):** 222
**Current Code:** `<div className="max-h-[300px] overflow-y-auto">`
**Fix:** Replace max-h-[300px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/pages/settings/components/integrations-settings.tsx`

**Issue:** Hardcoded px value used for font size: text-[10px]
**Severity:** Medium
**Line(s):** 388
**Current Code:** `<span className="text-[10px] text-muted-foreground">`
**Fix:** Replace text-[10px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Multiple grid columns (grid-cols-12) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 460
**Current Code:** `<div className="grid grid-cols-12 gap-2 text-sm font-medium text-muted-foreground px-1">`
**Fix:** Change to grid-cols-1 md:grid-cols-12 to enable vertical stacking on mobile.

**Issue:** Multiple grid columns (grid-cols-12) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 474
**Current Code:** `<div key={index} className="grid grid-cols-12 gap-2 items-center">`
**Fix:** Change to grid-cols-1 md:grid-cols-12 to enable vertical stacking on mobile.

### File: `resources/js/pages/settings/components/invoice-template-settings.tsx`

**Issue:** Multiple grid columns (grid-cols-6) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 308
**Current Code:** `<div className="grid grid-cols-6 gap-1 w-50">`
**Fix:** Change to grid-cols-1 md:grid-cols-6 to enable vertical stacking on mobile.

### File: `resources/js/pages/settings/components/payment-settings.tsx`

**Issue:** Hardcoded px value used for dimensions: w-[140px]
**Severity:** High
**Line(s):** 424
**Current Code:** `<SelectTrigger className="w-[140px]">`
**Fix:** Replace w-[140px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/pages/settings/components/quote-template-settings.tsx`

**Issue:** Multiple grid columns (grid-cols-6) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 327
**Current Code:** `<div className="grid grid-cols-6 gap-1 w-50">`
**Fix:** Change to grid-cols-1 md:grid-cols-6 to enable vertical stacking on mobile.

### File: `resources/js/pages/settings/components/sales-order-template-settings.tsx`

**Issue:** Multiple grid columns (grid-cols-6) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 319
**Current Code:** `<div className="grid grid-cols-6 gap-1 w-50">`
**Fix:** Change to grid-cols-1 md:grid-cols-6 to enable vertical stacking on mobile.

### File: `resources/js/pages/settings/components/storage-settings.tsx`

**Issue:** Multiple grid columns (grid-cols-4) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 285
**Current Code:** `<div className="grid grid-cols-4 gap-2 p-4 border rounded-md max-h-48 overflow-y-auto">`
**Fix:** Change to grid-cols-1 md:grid-cols-4 to enable vertical stacking on mobile.

**Issue:** Multiple grid columns (grid-cols-3) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 500
**Current Code:** `<TabsList className="grid w-full grid-cols-3">`
**Fix:** Change to grid-cols-1 md:grid-cols-3 to enable vertical stacking on mobile.

### File: `resources/js/pages/users/all-logs.tsx`

**Issue:** Multiple grid columns (grid-cols-3) enforced on mobile without responsive stacking.
**Severity:** High
**Line(s):** 272
**Current Code:** `<div key={key} className="grid grid-cols-3 gap-4 py-2 border-b border-gray-200 dark:border-gray-700">`
**Fix:** Change to grid-cols-1 md:grid-cols-3 to enable vertical stacking on mobile.

### File: `resources/js/pages/users/index.tsx`

**Issue:** Fixed width (w-48) without responsive fallback. May overflow on small mobile screens.
**Severity:** Medium
**Line(s):** 586
**Current Code:** `<DropdownMenuContent align="end" className="w-48 z-50" sideOffset={5}>`
**Fix:** Use w-full md:w-48 to allow scaling on small screens.

### File: `resources/js/pages/welcome.tsx`

**Issue:** Hardcoded px value used for dimensions: max-w-[335px]
**Severity:** High
**Line(s):** 16
**Current Code:** `<header className="mb-6 w-full max-w-[335px] text-sm not-has-[nav]:hidden lg:max-w-4xl">`
**Fix:** Replace max-w-[335px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: max-w-[335px]
**Severity:** High
**Line(s):** 44
**Current Code:** `<main className="flex w-full max-w-[335px] flex-col-reverse lg:max-w-4xl lg:flex-row">`
**Fix:** Replace max-w-[335px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for font size: text-[13px]
**Severity:** Medium
**Line(s):** 45
**Current Code:** `<div className="flex-1 rounded-br-lg rounded-bl-lg bg-white p-6 pb-12 text-[13px] leading-[20px] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] lg:rounded-tl-lg lg:rounded-br-none lg:p-20 dark:bg-[#161615] dark:text-[#EDEDEC] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">`
**Fix:** Replace text-[13px] with a Tailwind typography class (e.g., text-sm, text-base) which uses relative rem units.

**Issue:** Hardcoded px value used for dimensions: w-[438px]
**Severity:** High
**Line(s):** 128
**Current Code:** `<div className="relative -mb-px aspect-[335/376] w-full shrink-0 overflow-hidden rounded-t-lg bg-[#fff2f2] lg:mb-0 lg:-ml-px lg:aspect-auto lg:w-[438px] lg:rounded-t-none lg:rounded-r-lg dark:bg-[#1D0002]">`
**Fix:** Replace w-[438px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: w-[448px]
**Severity:** High
**Line(s):** 156
**Current Code:** `className="relative -mt-[4.9rem] -ml-8 w-[448px] max-w-none lg:-mt-[6.6rem] lg:ml-0 dark:hidden"`
**Fix:** Replace w-[448px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

**Issue:** Hardcoded px value used for dimensions: w-[448px]
**Severity:** High
**Line(s):** 477
**Current Code:** `className="relative -mt-[4.9rem] -ml-8 hidden w-[448px] max-w-none lg:-mt-[6.6rem] lg:ml-0 dark:block"`
**Fix:** Replace w-[448px] with a responsive rem-based tailwind class (e.g., w-full md:w-64) or standard scaling class.

### File: `resources/js/utils/columnRenderers.jsx`

**Issue:** Image element missing max-width logic. Large images will overflow their containers.
**Severity:** High
**Line(s):** 46
**Current Code:** `<img`
**Fix:** Add className="max-w-full h-auto" or "object-cover" constraints.

### File: `resources/js/utils/crudRenderers.tsx`

**Issue:** Image element missing max-width logic. Large images will overflow their containers.
**Severity:** High
**Line(s):** 31
**Current Code:** `<img`
**Fix:** Add className="max-w-full h-auto" or "object-cover" constraints.

