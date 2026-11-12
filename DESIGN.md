---
name: Kisantra Project Management
description: A calm operations desk for Kisantra staff; projects, clients and tax filings in one light, precise workspace that belongs to the Kisantra app family.
colors:
  ground: "#f1f7f9"
  surface: "#fdfeff"
  subtle: "#ebf2f4"
  subtle-strong: "#e4ecee"
  line: "#dee5e7"
  line-strong: "#ccd4d7"
  ink: "#141c20"
  ink-secondary: "#485156"
  ink-muted: "#626a6f"
  tile-blue: "#1790b5"
  arm-blue: "#10657f"
  action-blue: "#157a9c"
  action-blue-soft: "#dff4fe"
  action-blue-text: "#006681"
  signal-red: "#c13e2e"
  signal-red-text: "#af3628"
  signal-red-soft: "#ffedea"
  on-action: "#f8fdfe"
  dark-ground: "#0d1417"
  dark-surface: "#151c20"
  dark-subtle: "#1c2428"
  dark-line: "#272f34"
  dark-line-strong: "#353f44"
  dark-ink: "#eaeff1"
  dark-ink-secondary: "#b7bfc3"
  dark-ink-muted: "#919a9e"
  dark-action-blue-text: "#6bc6e1"
  dark-action-blue-soft: "#0a3341"
  dark-signal-red-text: "#f58d7c"
  dark-signal-red-soft: "#43201a"
typography:
  headline:
    fontFamily: "Manrope, ui-sans-serif, system-ui, sans-serif"
    fontSize: "25px"
    fontWeight: 800
    lineHeight: 1.15
    letterSpacing: "-0.03em"
  figure:
    fontFamily: "Manrope, ui-sans-serif, system-ui, sans-serif"
    fontSize: "30px"
    fontWeight: 800
    lineHeight: 1
    letterSpacing: "-0.035em"
    fontFeature: "tnum"
  title:
    fontFamily: "Manrope, ui-sans-serif, system-ui, sans-serif"
    fontSize: "15px"
    fontWeight: 800
    lineHeight: 1.3
    letterSpacing: "-0.015em"
  body:
    fontFamily: "Manrope, ui-sans-serif, system-ui, sans-serif"
    fontSize: "14px"
    fontWeight: 500
    lineHeight: 1.45
    letterSpacing: "normal"
  row:
    fontFamily: "Manrope, ui-sans-serif, system-ui, sans-serif"
    fontSize: "13.5px"
    fontWeight: 700
    lineHeight: 1.35
    letterSpacing: "normal"
  control:
    fontFamily: "Manrope, ui-sans-serif, system-ui, sans-serif"
    fontSize: "13.5px"
    fontWeight: 700
    lineHeight: 1
    letterSpacing: "normal"
  caption:
    fontFamily: "Manrope, ui-sans-serif, system-ui, sans-serif"
    fontSize: "12.5px"
    fontWeight: 600
    lineHeight: 1.4
    letterSpacing: "normal"
  badge:
    fontFamily: "Manrope, ui-sans-serif, system-ui, sans-serif"
    fontSize: "12px"
    fontWeight: 700
    lineHeight: 1
    letterSpacing: "normal"
  label:
    fontFamily: "Manrope, ui-sans-serif, system-ui, sans-serif"
    fontSize: "11px"
    fontWeight: 800
    lineHeight: 1.2
    letterSpacing: "0.08em"
rounded:
  micro: "5px"
  badge: "7px"
  chip: "9px"
  control: "11px"
  card: "14px"
  panel: "18px"
  full: "9999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "20px"
  page: "26px"
components:
  button-primary:
    backgroundColor: "{colors.action-blue}"
    textColor: "{colors.on-action}"
    typography: "{typography.control}"
    rounded: "{rounded.control}"
    padding: "0 16px"
    height: "38px"
  button-primary-hover:
    backgroundColor: "{colors.arm-blue}"
    textColor: "{colors.on-action}"
  button-outline:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    typography: "{typography.control}"
    rounded: "{rounded.control}"
    padding: "0 16px"
    height: "38px"
  button-outline-hover:
    backgroundColor: "{colors.subtle}"
    textColor: "{colors.ink}"
  input-search:
    backgroundColor: "{colors.subtle}"
    textColor: "{colors.ink-muted}"
    typography: "{typography.body}"
    rounded: "{rounded.control}"
    padding: "0 14px"
    height: "38px"
  nav-item:
    textColor: "{colors.ink-secondary}"
    typography: "{typography.control}"
    rounded: "{rounded.control}"
    padding: "0 10px"
    height: "36px"
  nav-item-active:
    backgroundColor: "{colors.action-blue-soft}"
    textColor: "{colors.action-blue-text}"
    typography: "{typography.control}"
    rounded: "{rounded.control}"
  filter-chip:
    textColor: "{colors.ink-secondary}"
    typography: "{typography.control}"
    rounded: "{rounded.chip}"
    padding: "0 11px"
    height: "32px"
  filter-chip-active:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.action-blue-text}"
    rounded: "{rounded.chip}"
  filter-pill-active:
    backgroundColor: "{colors.action-blue}"
    textColor: "{colors.on-action}"
    typography: "{typography.control}"
    rounded: "{rounded.control}"
    height: "36px"
  badge-neutral:
    backgroundColor: "{colors.subtle}"
    textColor: "{colors.ink-secondary}"
    typography: "{typography.badge}"
    rounded: "{rounded.badge}"
    padding: "0 9px"
    height: "24px"
  badge-blue:
    backgroundColor: "{colors.action-blue-soft}"
    textColor: "{colors.action-blue-text}"
    typography: "{typography.badge}"
    rounded: "{rounded.badge}"
    padding: "0 9px"
    height: "24px"
  badge-red:
    backgroundColor: "{colors.signal-red-soft}"
    textColor: "{colors.signal-red-text}"
    typography: "{typography.badge}"
    rounded: "{rounded.badge}"
    padding: "0 9px"
    height: "24px"
  month-cell:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    typography: "{typography.row}"
    rounded: "{rounded.control}"
    height: "60px"
  month-cell-selected:
    backgroundColor: "{colors.action-blue-soft}"
    textColor: "{colors.action-blue-text}"
  card:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.card}"
  workspace-panel:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.panel}"
  table-header:
    textColor: "{colors.ink-muted}"
    typography: "{typography.label}"
---

# Design System: Kisantra Project Management

Approved reference mockup: [`docs/mockups/redesign-project-management.html`](docs/mockups/redesign-project-management.html) (11 November 2026). Strategy, users and principles live in [`PRODUCT.md`](PRODUCT.md); this file is strictly visual.

## 1. Overview

**Creative North Star: "The Calm Operations Desk"** (Meja Operasional Tenang)

Kisantra staff sit at this desk for the whole working day: moving SOP projects forward, keeping client records straight, and watching monthly tax filings. The desk is quiet by default. Surfaces are light and tinted toward a cool blue-gray, type is crisp and dense, and nothing competes for attention until something is actually wrong. When it is (a filing not reported, an overdue project, a payable balance), one signal red appears, always with words beside it. Calm is the default; sharpness is reserved for problems.

The desk is one of a family. It shares its frame with Kisantra Platform and Client Management: Manrope, a light ground with the sidebar sitting directly on it, all content inside a single inset rounded panel, filter pills with counts, and the per-app colour system in which Project Management's own colour is the blue tile `#1790b5` with a deep `#10657f` arm. Identity comes from that shared frame, not from decoration on individual screens.

This system explicitly rejects the default Filament admin look (white cards with gray rings, amber/primary pills everywhere, stock sidebar), generic SaaS template dashboards (identical KPI card grids, pastel tiles, decorative gradients), pale flat screens where everything is the same light gray, and rainbow status colouring where every state gets its own pill colour. It is a light-first system with a matched dark mode; the light theme is the reference, dark mode maps the same roles to dark tokens.

**Key Characteristics:**
- Sidebar on the ground (244px), content in one inset panel (18px radius, 10px gutter), 60px topbar inside the panel, 24–26px page padding.
- Three voices only: action blue, ink, signal red, plus tinted neutrals.
- Dense rows (≥ 48px), two-level row text (name + meta), tabular figures right-aligned.
- Depth from 1px borders and tonal layers; shadows only on floating layers.
- Filters stay on one row; overflow goes into a searchable popover; the active filter is always visible.
- Same information as the current screens, new skin. Never drop or invent widgets, columns, filters or actions.

## 2. Colors

A restrained, cool palette: tinted neutrals carry the surface, one action blue does the work, and one signal red speaks only when something needs attention. Canonical values are OKLCH (hue 215–230 for neutrals and blue, 30 for red); the frontmatter holds their sRGB hex equivalents.

### Primary
- **Action Blue** (#157a9c, between the tile and the arm): primary buttons, active filter pills, the selected count chip in the sidebar, focus rings. White text on it measures 4.77:1. Hover deepens to **Arm Blue**.
- **Action Blue Text** (oklch(0.47 0.095 222)): links, active nav label and icon, selected filter chip text, "Paid / sudah lapor" badges. 6.46:1 on surface, 5.75:1 on its soft tint.
- **Action Blue Soft** (oklch(0.955 0.026 225)): background for the active nav item, selected month cells, blue badges, active filter chips.

### Secondary
- **Tile Blue** (#1790b5): the Project Management app colour. Logo tile, chart bars, sparklines, progress fills, the "bulan ini" dot. Decoration only.
- **Arm Blue** (#10657f): the logo's arm and the hover state of primary buttons.

### Tertiary
- **Signal Red** (oklch(0.55 0.17 30)): status dots and bars for problems (lewat tenggat, belum lapor), the notification count bubble.
- **Signal Red Text** (oklch(0.51 0.16 30)) on **Signal Red Soft** (oklch(0.962 0.022 30)): red badges (Belum Lapor, Kurang Bayar, Urgent priority text). 5.49:1.

### Neutral
- **Desk Ground** (oklch(0.972 0.007 215)): page ground behind the sidebar and around the panel.
- **Panel Surface** (oklch(0.997 0.002 215)): the inset workspace panel, cards, popovers, inputs with borders.
- **Quiet Wash** (oklch(0.956 0.008 215)) and **Quiet Wash Strong** (oklch(0.937 0.009 215)): search fields, table group rows, segmented rails, avatar tiles, hover.
- **Hairline** (oklch(0.917 0.008 215)) and **Hairline Strong** (oklch(0.865 0.01 215)): 1px borders, row dividers, input outlines.
- **Ink** (oklch(0.22 0.014 230)): primary text, figures, page titles. 17:1.
- **Ink Secondary** (oklch(0.43 0.014 230)): nav labels, secondary values, client names in tables. 8:1.
- **Ink Muted** (oklch(0.52 0.013 230)): meta, captions, table headers, counts. Tuned to stay ≥ 4.6:1 on every neutral, including the wash rows; do not lighten it.

### Dark mode
Dark mode keeps every role and swaps the token: `dark-ground` (oklch(0.185 0.012 230)), `dark-surface` (oklch(0.22 0.013 230)), `dark-subtle`, `dark-line`, `dark-line-strong`; text `dark-ink` (14.9:1), `dark-ink-secondary` (9.2:1), `dark-ink-muted` (6.0:1); blue text `dark-action-blue-text` on `dark-action-blue-soft` (6.9:1); red text `dark-signal-red-text` on `dark-signal-red-soft` (6.2:1). Primary buttons keep Action Blue with white text in both themes. Neutrals stay cool-tinted charcoal, never pure black.

### Named Rules
**The Meaning Rule.** Neutral is normal. Colour appears only for an action, a selection, or a problem. A report filed on time, an active client, a Nihil balance: all neutral or quiet blue, never green.

**The Three Voices Rule.** Blue, ink and red, plus neutrals. No green, amber, violet or indigo status colours anywhere in the panel. If a new state seems to need a fourth colour, it needs a label instead.

**The Tile Is Decoration Rule.** Tile Blue (#1790b5) never carries small text: white on it is only 3.6:1. Buttons and text use Action Blue or Action Blue Text.

## 3. Typography

**Display Font:** Manrope (with ui-sans-serif, system-ui)
**Body Font:** Manrope
**Label/Mono Font:** Manrope (no mono face; identifiers like NPWP use Manrope with tabular figures)

**Character:** One geometric-humanist family across the whole desk, shared with the other Kisantra apps. Weight does the hierarchy: 800 for titles and figures, 700 for rows and controls, 500–600 for reading text and meta.

### Hierarchy
- **Headline** (800, 25px, 1.15, −0.03em): page titles ("Projects", "Laporan Pajak") and the dashboard greeting.
- **Figure** (800, 30px, 1, −0.035em, tabular): summary-strip values on the dashboard.
- **Title** (800, 15px, 1.3, −0.015em): panel and section titles ("Proyek selesai", "Log Aktivitas").
- **Body** (500, 14px, 1.45): default text, descriptions, log entries. Prose lines stay within 65–75ch; tables may run wider.
- **Row** (700, 13.5px, 1.35): the primary text of a table row or list item (client, project, SOP names).
- **Control** (700, 13.5px): buttons, pills, nav items, segmented options.
- **Caption** (600, 12.5px, 1.4): meta lines, panel subtitles, footers, filter labels.
- **Badge** (700, 12px; 10.5px in compact cells): status and category badges.
- **Label** (800, 11px, 0.08em, UPPERCASE): table column headers, group section labels ("NOT STARTED"), the "SOP" prefix.

### Named Rules
**The One Family Rule.** Manrope only. No display face, no serif, no mono. Hierarchy comes from weight and size, never from a second family.

**The Aligned Figures Rule.** Money, counts, dates in columns and KPI values use tabular figures and right alignment (`Rp 62.769.500` with Indonesian grouping). Prose never does.

## 4. Elevation

Flat and layered. Depth comes from the ground → panel → wash tonal steps and 1px hairlines; containers at rest carry at most a whisper of shadow. Real shadows are reserved for things that float above the page: popovers (month picker, SOP list), the raised segment in a segmented control, and the primary button.

### Shadow Vocabulary
- **Panel whisper** (`box-shadow: 0 1px 2px oklch(0.3 0.02 230 / .04)`): the inset workspace panel.
- **Floating layer** (`box-shadow: 0 24px 48px -16px oklch(0.25 0.03 230 / .28), 0 2px 6px oklch(0.25 0.02 230 / .06)`): popovers and dropdowns.
- **Raised segment** (`box-shadow: 0 1px 2px oklch(0.3 0.03 230 / .12), 0 0 0 1px oklch(0.47 0.095 222 / .22)`): the active chip in a segmented rail.
- **Primary press** (`box-shadow: 0 1px 2px oklch(0.35 0.08 225 / .25)`): primary buttons.
- **Tile glow** (`box-shadow: 0 1px 1px oklch(0.3 0.02 230 / .08), 0 8px 18px -10px #1790b5`): the app logo tile only.

### Named Rules
**The Border-First Rule.** If two things need separating, use a hairline or a tonal step first. A shadow on a resting card is a bug.

## 5. Components

Controls are firm and quiet: 38px tall, 11px corners, weight 700, no gradients. Motion is 150–250ms on `cubic-bezier(0.22, 1, 0.36, 1)`, only on colour, opacity, transform and shadow, and is disabled under `prefers-reduced-motion`.

### Buttons
- **Shape:** gently rounded (11px), 38px tall; compact variant 32px with 9px corners.
- **Primary:** Action Blue fill, near-white text, 16px horizontal padding, Primary press shadow. One primary action per page header ("New Project", "Buat Laporan Pajak").
- **Hover / Focus:** hover deepens to Arm Blue; focus shows a 2px Action Blue outline with 2px offset.
- **Outline:** surface fill, Hairline Strong border, ink text, muted icon ("Export", "Impor", "Kredensial" in compact size).

### Chips
- **Filter pills (client-management style):** 36px, 11px corners, surface with hairline border, count in a small wash chip. Active pill is solid Action Blue with the count on a 20% white chip.
- **Segmented rail (SOP filter, Board/List style toggles, period switches):** a wash rail with 4px inset; options are borderless text with muted counts; the active option is a raised surface chip with Action Blue Text and the count on a soft blue chip.
- **Active-filter chips:** soft blue with an × to remove, followed by "Reset semua". Default filters (e.g. Kontrak Aktif) are shown here, never hidden inside a menu. Implementation: `<x-kis.active-filters>` + `<x-kis.filter-chip>`.

### Badges
- **Shape:** 7px corners, 24px tall (20px compact), soft tint background, same-hue text, 1px inset ring at ~20% of the hue.
- **Tones:** blue (Active, In Progress, Lebih Bayar, Paid, sudah lapor with date), neutral (Draft, Nihil, N/A, type and department categories), red (Belum Lapor, Kurang Bayar). Outline-blue for in-between states (Analysis, Review, PKP).
- **Dot:** statuses get a leading 6px dot; categories don't.
- **Implementation:** `<x-kis.badge tone="…" dot>`; pick the tone with `App\Support\Ui\Tone` (`forPaymentStatus`, `forReportStatus`, `forClientStatus`, `forProjectStatus`), and use `Tone::filamentColor()` for native Filament badge columns.

### Cards / Containers
- **Workspace panel:** surface, 18px corners, 1px hairline, Panel whisper; holds the topbar and the page.
- **Cards and panels:** surface, 14px corners, 1px hairline, no shadow. Header 16–20px padding with title + caption subtitle; optional footer on a hairline with a right-aligned "Lihat … →" link.
- **Summary strip:** one card split into equal cells by internal hairlines (label, delta chip, figure, context line, sparkline). Never a grid of separate coloured KPI cards.

### Inputs / Fields
- **Search:** 38px, wash background, no border inside the topbar; surface with hairline in toolbars; leading search icon, ⌘K or `/` hint as a small key cap.
- **Popover triggers (Periode, SOP lainnya):** outline control with label + value + count bubble + chevron; open state gets an Action Blue border and a 3px soft ring.
- **Focus:** 2px Action Blue outline, offset 2px, always visible. **Error:** Signal Red Text message under the field (13px). **Disabled:** wash background, muted text.

### Navigation
- **Sidebar:** sits on Desk Ground, no border. App logo tile (38px, Tile Blue with white K and Arm Blue arm, drawn from the Kisantra Platform SVG) + "Project Management / Kisantra".
- **Items:** 36px, control type, Ink Secondary; hover on Quiet Wash Strong; active on Action Blue Soft with Action Blue Text and icon, count as a solid Action Blue pill.
- **Groups:** caption-weight labels in Ink Muted (original group names kept). Department sub-items under Projects hang from a 1px tree line.
- **Account:** avatar (ink circle, presence dot) + name + role at the bottom, then "© 2026 Kisantra Management".
- **Topbar (inside the panel):** sidebar toggle, breadcrumb, search, notification bell in a round wash button with a red count bubble, avatar.

### Data table (signature)
- Uppercase Label headers in Ink Muted, no header fill; rows ≥ 48px with hairline dividers and a faint hover wash.
- Group rows on Quiet Wash (`Draft · 65`, `Oktober 2026 · 2 laporan · 1 belum lapor`); section labels in uppercase Label. Implementation: `<x-kis.group-row>` (`variant="section"` for the uppercase label).
- Laporan Pajak: list only (no board). Status Pajak is four fixed columns (PPN, PPh, Bupot, PPh Badan) under one group header; each cell is a compact badge ("✓ 28 Okt" blue + paid icon, "Belum" red) or a faint "–" when there is no contract. Saldo PPN and Status Bayar are separate columns; amounts right-aligned, zero balances muted.

### Month multi-select (signature)
- Trigger: "Periode" + range label ("Sep – Nov 2026", "Jan, Mar, Okt 2026", "5 bulan · 2026") + count bubble.
- Popover (384px): year stepper with yearly total, Q1–Q4 / Setahun presets, 4×3 grid of month cells (60px, count per month, checkbox mark, "bulan ini" dot, disabled when empty), Shift+click range tip, footer with selection count, Reset and Selesai. Selection applies live. Implementation: `<x-kis.month-picker wire:model.live="period" :counts="…">` with value `['year' => 2026, 'months' => [9, 10, 11]]`.

### SOP filter (signature)
- One segmented rail; as many chips as fit, in the fixed order set by admins; the rest behind "+N lainnya", a searchable listbox with counts, match highlighting and ↑ ↓ Enter / Esc keyboard support. A SOP chosen from the overflow is pinned at the end of the rail so the active filter is never hidden. Implementation: `<x-kis.filter-rail wire:model.live="sop" :options="…">`; behaviour in `resources/js/kis-ui.js`, live gallery at `/dev/ui-kit` (local only).

### Named Rules
**The Status-Only Badge Rule.** Badges are for statuses and categories. Names, groups and priorities stay as text (Urgent is red text with its flag icon, not a badge).

**The One-Row Filter Rule.** A filter with many options never wraps to a second row. Show what fits, put the rest in a searchable popover, keep the active choice visible.

## 6. Do's and Don'ts

### Do:
- **Do** keep every widget, column, filter and action the current screen has; change hierarchy, spacing and colour only.
- **Do** put the sidebar directly on Desk Ground and all content in one 18px-radius inset panel, like Kisantra Platform and Client Management.
- **Do** use Action Blue (#157a9c) for actions and selection, Tile Blue (#1790b5) for the logo, charts and progress only.
- **Do** pair every coloured state with words or a glyph ("Belum", "✓ 28 Okt", "Kurang Bayar").
- **Do** right-align money and counts with tabular figures and Indonesian grouping.
- **Do** keep text at ≥ 4.5:1 in both themes; use the tuned Ink Muted (oklch(0.52 0.013 230)) for small meta text.
- **Do** write user-facing copy in Bahasa Indonesia, short and direct.

### Don't:
- **Don't** ship the default Filament admin look (white cards with gray rings, amber/primary pills everywhere, stock sidebar).
- **Don't** build generic SaaS template dashboards: identical KPI card grids, pastel tiles, decorative gradients.
- **Don't** make pale, flat screens where everything is the same light gray and nothing has hierarchy.
- **Don't** use rainbow status colouring (every state its own pill colour), as the old Clients and Tax Report screens did; no gradient card headers.
- **Don't** use a dark sidebar; the frame stays light in light mode.
- **Don't** use `border-left` or `border-right` wider than 1px as a coloured accent on cards, rows or callouts.
- **Don't** use gradient text, glassmorphism, or a modal where an inline panel or popover works.
- **Don't** use native `<select>` for filters with many options or multi-select; use the popover patterns above.
- **Don't** put white text on Tile Blue, or a fourth status colour anywhere.
- **Don't** use em dashes in UI copy.
