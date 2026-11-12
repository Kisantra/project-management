# Product

## Register

product

## Users

Kisantra's internal staff: the project, tax and client team of an Indonesian consultancy. They work in a bright office during the day, on laptops and external monitors, and spend most of the working day inside the admin panel (`/`). Their jobs: move SOP-based projects through their steps and documents, keep client records current, and keep every client's monthly filings (PPN, PPh 21, Unifikasi, PPh Badan) on time. Clients use a separate, lighter portal (`/klien`); directors mostly read summaries. Staff comfort over a full workday comes first.

## Product Purpose

Project Management is Kisantra's operational hub: projects (grouped by SOP, status, department), clients, tax reports, daily tasks, SOPs and the team activity log. Tax deadlines (`TaxDeadlineService`: 10th, 20th, 30th) are one part of it, not the whole. Success is that staff can scan their projects, clients and filings quickly and nobody misses a problem hidden in a busy screen.

Current redesign scope: **visual only**. The information on every screen (widgets, columns, filters, counts, actions) stays exactly as it is today; only how it looks changes.

## Brand Personality

Modern and digital: a precise, confident tool, not a bureaucratic form. Calm by default, sharp when something is wrong. User-visible copy is Bahasa Indonesia, direct and short.

Reference: Stripe's dashboard, specifically its precision, crisp type and confident use of color. The palette is deliberately limited to a primary and a secondary brand color plus at most one or two supporting roles (3–4 colors total, excluding neutrals).

Family: this is one app in the Kisantra suite. It should look like a sibling of `~/Projects/Kisantra-Platform` (the launcher / SSO) and `~/Projects/client-management` (design "Lembut"): Manrope, a light ground with the sidebar sitting directly on it, content in one inset rounded panel, filter pills with counts, and the per-app colour system where Project Management's colour is tile `#1790b5` / arm `#10657f`.

## Anti-references

- The default Filament admin look (white cards with gray rings, amber/primary pills everywhere, stock sidebar). The product must not read as "another Filament panel".
- Generic SaaS template dashboards: identical KPI card grids, pastel tiles, decorative gradients.
- Pale, flat screens where everything is the same light gray and nothing has hierarchy.
- Rainbow status coloring (every state its own pill color), which is what the current Clients and Tax Report screens do.

## Design Principles

1. **Color means something.** Neutral is normal. Color is reserved for actions, selection, and problems that need attention. A report filed on time does not deserve a color.
2. **Same information, new skin.** Redesigns keep every widget, column, filter and action the screen already has. Improve hierarchy, spacing and colour; don't silently drop or invent content.
3. **Density with hierarchy.** Staff work all day; show more rows and fewer containers, and let type weight and spacing carry structure instead of boxes.
4. **Precise over decorative.** Tabular figures, aligned columns, exact dates. No ornament that competes with numbers.
5. **Family resemblance.** Identity lives in the shell (app logo tile, Manrope, ground + inset panel, app colour), shared with the other Kisantra apps, so it feels like Kisantra rather than a Filament default.

## Accessibility & Inclusion

WCAG 2.1 AA, matching the contrast targets already documented in `resources/css/filament/admin/theme.css` (body text ≥ 4.5:1, faint text only at large sizes). Status is never conveyed by color alone: every colored state also has text or a glyph. Respect `prefers-reduced-motion`.
