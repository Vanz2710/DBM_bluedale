# UI Design Standards — BGOC CRM

This document defines the design system for all new pages and components.
**Every new feature must follow these standards exactly.**

---

## CSS Variables (always use these — never hardcode equivalents)

```css
/* Backgrounds */
--app-bg:       #f6f7fb   /* page background */
--surface:      #ffffff   /* cards, panels */
--surface-2:    #f9fafb   /* table rows, inputs, secondary surfaces */

/* Borders */
--border:       #e5e7eb
--border-soft:  #eef0f4

/* Text */
--text-1:       #1e293b   /* primary text, headings */
--text-2:       #64748b   /* secondary text, labels */
--text-3:       #94a3b8   /* muted text, placeholders */

/* Primary (Bluedale navy blue) */
--primary:        #1d4ed8
--primary-hover:  #1e40af
--primary-soft:   #dbeafe
--primary-on:     #ffffff
--primary-text:   #0f2456

/* Radii */
--radius-sm:  6px
--radius:     10px
--radius-lg:  14px
--radius-xl:  20px

/* Shadows */
--shadow-xs:  0 1px 2px rgba(15,23,42,0.04)
--shadow-sm:  0 1px 2px rgba(15,23,42,0.04), 0 1px 3px rgba(15,23,42,0.06)
--shadow-md:  0 4px 6px -1px rgba(15,23,42,0.08), 0 2px 4px -2px rgba(15,23,42,0.05)
--shadow-lg:  0 10px 15px -3px rgba(15,23,42,0.10), 0 4px 6px -4px rgba(15,23,42,0.06)
```

---

## Page Shell

Every page uses this exact shell:

```css
.page        { padding: 28px 32px; }
.page-header { margin-bottom: 24px; }
.page-title  { font-size: 28px; font-weight: 800; color: var(--text-1);
               letter-spacing: -0.5px; margin: 0 0 4px; }
.page-subtitle { font-size: 13.5px; color: var(--text-3); margin: 0; }
```

**Responsive padding is required** — every new page must include these breakpoints so it looks clean at all zoom levels and screen sizes:

```css
@media (max-width: 768px) { .page { padding: 20px 16px; } }
@media (max-width: 640px) { .page { padding: 16px 12px; } }
```

---

## Tab Bar

Used on any multi-section page (Performance, RbacPanel, AdminPanel pattern):

```css
.tab-bar {
  display: flex; gap: 4px;
  border-bottom: 2px solid var(--border);
  margin-bottom: 24px;
}
.tab-btn {
  padding: 9px 18px; border: none; background: none; cursor: pointer;
  font-size: 13px; font-weight: 600; color: var(--text-2);
  border-bottom: 2px solid transparent; margin-bottom: -2px;
  transition: color 0.15s, border-color 0.15s; border-radius: var(--radius-sm) var(--radius-sm) 0 0;
}
.tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
.tab-btn:hover:not(.active) { color: var(--text-1); background: var(--surface-2); }
```

---

## Cards / Panels

```css
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);        /* 10px */
  box-shadow: var(--shadow-sm);
}
/* Large modal/form cards */
.card-lg { border-radius: var(--radius-lg); } /* 14px */
```

---

## Tables

```css
.table-wrap {
  overflow-x: auto;
  border-radius: var(--radius);
  border: 1px solid var(--border);
}
.data-table   { width: 100%; border-collapse: collapse; font-size: 13px; }
thead tr      { background: var(--surface-2); }
th {
  padding: 10px 14px; text-align: left;
  font-size: 11px; font-weight: 700; color: var(--text-2);
  text-transform: uppercase; letter-spacing: 0.6px;
  border-bottom: 1px solid var(--border); white-space: nowrap;
}
td            { padding: 12px 14px; color: var(--text-1); border-bottom: 1px solid var(--border-soft); }
tr:last-child td { border-bottom: none; }
tr:hover td   { background: var(--surface-2); }
```

---

## Buttons

```css
/* Primary */
.btn-primary {
  padding: 8px 18px; background: var(--primary); color: var(--primary-on);
  border: none; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600;
  cursor: pointer; box-shadow: 0 6px 18px -6px rgba(124,58,237,0.45);
  transition: background 0.15s, box-shadow 0.15s;
}
.btn-primary:hover { background: var(--primary-hover); }

/* Ghost / secondary */
.btn-ghost {
  padding: 8px 14px; background: var(--surface-2); color: var(--text-2);
  border: 1px solid var(--border); border-radius: var(--radius-sm);
  font-size: 13px; font-weight: 500; cursor: pointer;
  transition: background 0.15s;
}
.btn-ghost:hover { background: var(--border); color: var(--text-1); }

/* Danger (small inline) */
.btn-danger-sm { background: #fee2e2; color: #991b1b; border: none; border-radius: var(--radius-sm); }
.btn-danger-sm:hover { background: #fca5a5; }

/* Success (small inline) */
.btn-success-sm { background: #dcfce7; color: #166534; border: none; border-radius: var(--radius-sm); }
```

---

## Status / Role Badges (pills)

```css
.badge {
  display: inline-block; padding: 3px 10px;
  border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap;
}
/* Colour variants */
.badge-green  { background: #dcfce7; color: #15803d; }
.badge-blue   { background: #dbeafe; color: #1d4ed8; }
.badge-purple { background: #ede9fe; color: #6d28d9; }
.badge-orange { background: #fff7ed; color: #c2410c; }
.badge-red    { background: #fee2e2; color: #991b1b; }
.badge-amber  { background: #fef3c7; color: #92400e; }
.badge-gray   { background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); }
```

---

## Form Inputs

```css
.field-label { font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px; display: block; }
.field-input {
  width: 100%; height: 38px; padding: 0 12px;
  border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  font-size: 13px; color: var(--text-1); background: var(--surface);
  transition: border-color 0.15s, box-shadow 0.15s; outline: none;
}
.field-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
select.field-input { cursor: pointer; }
```

---

## Modals

```css
.modal-backdrop {
  position: fixed; inset: 0; background: rgba(15,23,42,0.45);
  display: flex; align-items: center; justify-content: center; z-index: 2000;
}
.modal-box {
  background: var(--surface); border-radius: var(--radius-lg); /* 14px */
  width: 460px; max-width: 95vw; max-height: 85vh;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2); overflow-y: auto;
}
.modal-header { padding: 18px 20px 14px; border-bottom: 1px solid var(--border); }
.modal-title  { font-size: 15px; font-weight: 700; color: var(--text-1); margin: 0; }
.modal-body   { padding: 20px; }
.modal-footer { padding: 14px 20px; border-top: 1px solid var(--border);
                display: flex; justify-content: flex-end; gap: 8px; }
```

---

## User Avatars

```css
.avatar {
  width: 32px; height: 32px; border-radius: 50%;
  background: var(--primary-soft); color: var(--primary);
  font-size: 11px; font-weight: 700;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
```

---

## Loading / Empty States

```css
/* Always centre the spinner in available space */
.loading-wrap { display: flex; justify-content: center; align-items: center; padding: 60px 0; }

/* Empty state */
.empty-state {
  text-align: center; padding: 48px 24px;
  color: var(--text-3); font-size: 14px;
}
```

---

## Feedback Messages (success / error banners)

```css
.msg-box     { padding: 12px 16px; border-radius: var(--radius-sm); font-size: 14px; margin-bottom: 20px; }
.success-box { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.error-box   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
```

---

## Colour Reference (semantic use)

| Meaning     | Background | Text      |
|-------------|-----------|-----------|
| Active/Good | `#dcfce7` | `#15803d` |
| Warning     | `#fef9c3` | `#854d0e` |
| Danger/Bad  | `#fee2e2` | `#991b1b` |
| Info        | `#dbeafe` | `#1d4ed8` |
| Primary     | `#ede9fe` | `#6d28d9` |
| Amber alert | `#fef3c7` | `#92400e` |
| Neutral     | `var(--surface-2)` | `var(--text-2)` |

---

## Rules

1. **Always use CSS variables** — never hardcode a colour that has a variable equivalent.
2. **Page title is always 28px / 800 weight** — not 22px or 700.
3. **Border-radius from tokens only** — `--radius-sm` (6px), `--radius` (10px), `--radius-lg` (14px).
4. **Scoped CSS per component** — no global class pollution.
5. **No Tailwind** — this project uses plain scoped CSS.
6. **Dark mode is automatic** — CSS variables handle it; never write `@media (prefers-color-scheme)` manually.
7. **Tables always inside `.table-wrap`** — for overflow and consistent border-radius.
8. **Badges always use 999px border-radius** (pill shape).
9. **Spacing in 4px increments**: 4, 8, 12, 16, 20, 24, 28, 32px.
10. **Buttons**: primary uses `var(--primary)` + purple shadow; ghost uses `var(--surface-2)` + border.
