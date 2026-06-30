# SecureBounty — UI Design System

## Philosophy

Stripped-back, functional, typographically driven. Every element earns its place.
No gradients. No drop shadows heavier than `0 1px 2px`. No decorative illustrations.
The interface is a tool — sharp, quiet, confident.

Inspired by: shadcn/ui density, Linear's restraint, Vercel's typography, Stripe's data presentation.

---

## Design Tokens

### Color Palette

#### Light Mode

| Token | Hex | Usage |
|-------|-----|-------|
| `--background` | `#fafafa` | Page background |
| `--foreground` | `#0a0a0a` | Primary text |
| `--card` | `#ffffff` | Card/panel background |
| `--card-foreground` | `#0a0a0a` | Card text |
| `--muted` | `#f4f4f5` | Subtle backgrounds, disabled states |
| `--muted-foreground` | `#71717a` | Secondary text, placeholders |
| `--border` | `#e4e4e7` | Borders, dividers |
| `--input` | `#e4e4e7` | Input borders |
| `--ring` | `#f59e0b` | Focus ring (amber) |
| `--accent` | `#f59e0b` | Primary action color (amber) |
| `--accent-foreground` | `#0a0a0a` | Text on amber backgrounds |
| `--destructive` | `#ef4444` | Danger/delete actions |
| `--success` | `#22c55e` | Success states |

#### Dark Mode

| Token | Hex | Usage |
|-------|-----|-------|
| `--background` | `#09090b` | Page background |
| `--foreground` | `#fafafa` | Primary text |
| `--card` | `#18181b` | Card/panel background |
| `--card-foreground` | `#fafafa` | Card text |
| `--muted` | `#27272a` | Subtle backgrounds |
| `--muted-foreground` | `#a1a1aa` | Secondary text |
| `--border` | `#27272a` | Borders, dividers |
| `--input` | `#27272a` | Input borders |
| `--ring` | `#f59e0b` | Focus ring (amber) |
| `--accent` | `#f59e0b` | Primary action color |
| `--accent-foreground` | `#0a0a0a` | Text on amber |
| `--destructive` | `#dc2626` | Danger |
| `--success` | `#16a34a` | Success |

### Severity Colors (flat, no gradient)

| Severity | Light Mode | Dark Mode | Usage |
|----------|-----------|-----------|-------|
| Critical | `#dc2626` bg + `#fef2f2` surface | `#dc2626` bg + `#1c0a0a` surface | Badge, border-left accent |
| High | `#ea580c` bg + `#fff7ed` surface | `#ea580c` bg + `#1c1008` surface | Badge, border-left accent |
| Medium | `#d97706` bg + `#fffbeb` surface | `#d97706` bg + `#1c1505` surface | Badge, border-left accent |
| Low | `#2563eb` bg + `#eff6ff` surface | `#2563eb` bg + `#0a1628` surface | Badge, border-left accent |
| Informational | `#71717a` bg + `#f4f4f5` surface | `#71717a` bg + `#1a1a1e` surface | Badge, border-left accent |

### Typography

Font stack: `Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif`
Monospace: `'JetBrains Mono', 'Fira Code', monospace`

| Scale | Size | Weight | Line Height | Usage |
|-------|------|--------|-------------|-------|
| `display` | 30px | 700 | 1.2 | Page titles (dashboard) |
| `heading` | 20px | 600 | 1.3 | Section headers |
| `subheading` | 16px | 600 | 1.4 | Card titles, table headers |
| `body` | 14px | 400 | 1.5 | Default text |
| `small` | 13px | 400 | 1.4 | Meta info, timestamps |
| `caption` | 12px | 500 | 1.3 | Labels, badges, overlines |
| `mono` | 13px | 400 | 1.5 | CVSS vectors, code, IDs |

### Spacing Scale

Base unit: 4px

| Token | Value | Usage |
|-------|-------|-------|
| `xs` | 4px | Tight gaps (badge padding) |
| `sm` | 8px | Inline spacing |
| `md` | 16px | Component padding |
| `lg` | 24px | Section gaps |
| `xl` | 32px | Page margins |
| `2xl` | 48px | Major section breaks |

### Border Radius

| Token | Value | Usage |
|-------|-------|-------|
| `sm` | 4px | Badges, small chips |
| `md` | 6px | Buttons, inputs, cards |
| `lg` | 8px | Modals, large panels |
| `full` | 9999px | Avatars, toggles |

---

## Component Library

### Buttons

Three variants. No shadows. No gradients. Transitions on `background-color` and `opacity` only.

```
Primary:    bg: --accent | text: --accent-foreground | hover: opacity 0.9
Secondary:  bg: --muted  | text: --foreground | border: 1px --border | hover: bg --border
Ghost:      bg: transparent | text: --muted-foreground | hover: bg --muted
Destructive: bg: --destructive | text: white | hover: opacity 0.9
```

Sizes: `sm` (h-8, px-3, text-13px), `default` (h-9, px-4, text-14px), `lg` (h-10, px-5, text-14px)

States: disabled → opacity 0.5, cursor not-allowed. Focus → 2px ring offset in `--ring` color.

### Input Fields

```
Default:    bg: transparent | border: 1px --input | rounded-md | h-9 | px-3
            focus: border --accent, ring 1px --ring
            placeholder: --muted-foreground
Error:      border --destructive, ring 1px --destructive
Disabled:   bg --muted, opacity 0.5
```

No inner shadows. No floating labels (use top-aligned labels in `caption` style).

### Cards

```
bg: --card | border: 1px --border | rounded-lg | p-md
No shadow in dark mode.
Light mode: box-shadow: 0 1px 2px rgba(0,0,0,0.04) — barely perceptible.
```

### Badges (Severity & Status)

Flat, small, pill-shaped (`rounded-sm`, `px-2`, `py-0.5`, `text-caption`, `font-medium`).

```
Severity badges: bg uses severity surface color, text uses severity main color, border 1px severity color at 20% opacity.
Status badges:
  - pending:  bg --muted, text --muted-foreground
  - triaged:  bg blue-50/blue-950, text blue-600/blue-400
  - accepted: bg green-50/green-950, text green-600/green-400
  - rejected: bg red-50/red-950, text red-600/red-400
  - resolved: bg --muted, text --foreground, strike-through style
```

### Tables

```
Header row:   bg: --muted | text: --muted-foreground | caption style | uppercase tracking-wide
Body rows:    border-b 1px --border | py-sm | hover: bg --muted (subtle)
No zebra striping. No rounded corners on rows.
```

### Navigation (Sidebar)

```
Width: 240px collapsed to icon-only 56px on mobile/toggle.
bg: --card | border-right: 1px --border
Items: px-3 py-2 | rounded-md | text-body
Active: bg --muted | text --foreground | left border 2px --accent
Hover: bg --muted
Icons: 18px, stroke-width 1.5, --muted-foreground (active: --accent)
```

### Modals / Dialogs

```
Overlay: bg black/50 (dark) or black/30 (light), backdrop-blur-sm
Panel: bg --card | border 1px --border | rounded-lg | max-w-md | p-lg
No shadow in dark. Light: box-shadow 0 4px 12px rgba(0,0,0,0.08)
```

### Toast Notifications

```
Position: bottom-right, stacked.
bg: --card | border 1px --border | rounded-md | p-md
Left accent: 3px border-left in severity color.
Auto-dismiss: 5s with progress bar (1px height, --accent, shrinking left-to-right)
```

---

## Layout System

### Page Structure

```
┌─────────────────────────────────────────────────────────┐
│ Top Bar (h-14, border-b, logo left, user menu right)    │
├──────────┬──────────────────────────────────────────────┤
│ Sidebar  │ Main Content                                  │
│ (240px)  │ ┌──────────────────────────────────────────┐ │
│          │ │ Page Header (display title + actions)     │ │
│          │ ├──────────────────────────────────────────┤ │
│          │ │ Content Area (max-w-6xl, mx-auto)        │ │
│          │ │                                          │ │
│          │ │                                          │ │
│          │ └──────────────────────────────────────────┘ │
└──────────┴──────────────────────────────────────────────┘
```

- Top bar: fixed, z-50. Logo text (not image) in `heading` weight + `--foreground`. Theme toggle icon button on the right.
- Sidebar: fixed left, scrollable. Grouped nav items with `caption` overline labels (PROGRAMS, REPORTS, ADMIN).
- Content: scrollable, padded `xl` on desktop, `md` on mobile.

### Responsive Breakpoints

| Breakpoint | Width | Behavior |
|-----------|-------|----------|
| `sm` | 640px | Stack cards, full-width tables scroll horizontal |
| `md` | 768px | Sidebar collapses to icon-only |
| `lg` | 1024px | Full layout |
| `xl` | 1280px | Content max-width kicks in |

---

## Key Page Layouts

### Dashboard (Role-specific)

```
┌─ Page Header ──────────────────────────────────────┐
│ "Dashboard"                        [Theme Toggle]   │
├────────────────────────────────────────────────────┤
│                                                     │
│ ┌─ Stat Cards (grid 4-col) ──────────────────────┐ │
│ │ [Programs]  [Reports]  [Pending]  [Resolved]   │ │
│ │  12          47         8           39         │ │
│ └────────────────────────────────────────────────┘ │
│                                                     │
│ ┌─ Recent Activity ─────────────────────────────┐  │
│ │ Compact list, mono timestamp left,             │  │
│ │ action description right, no avatars           │  │
│ └────────────────────────────────────────────────┘  │
│                                                     │
│ ┌─ Pending Reports ─────────────────────────────┐  │
│ │ Table: Title | Program | Severity | Submitted  │  │
│ │ Row click → report detail                      │  │
│ └────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────┘
```

Stat cards: border-left 3px `--accent`. Number in `display` size. Label in `caption` + uppercase.

### Program Listing (Researcher View)

```
No cards grid. Just a clean table/list:

┌────────────────────────────────────────────────────┐
│ Program Title          | Reward Range | Status     │
├────────────────────────────────────────────────────┤
│ Acme Corp Web App      | $500–$5,000  | ● Active  │
│ CloudStore API         | $200–$3,000  | ● Active  │
│ ...                                                │
└────────────────────────────────────────────────────┘

Each row: hover → bg --muted. Click → program detail.
Bookmark icon (outline) on the right, fills on click (amber).
```

### Report Detail

```
┌─ Breadcrumb ───────────────────────────────────────┐
│ Programs / Acme Corp / Reports / #47               │
├────────────────────────────────────────────────────┤
│                                                     │
│ ┌─ Header ──────────────────────────────────────┐  │
│ │ [Critical] SQL Injection in /api/users         │  │
│ │ Status: [Triaged]  Submitted: 2 days ago       │  │
│ └────────────────────────────────────────────────┘  │
│                                                     │
│ ┌─ CVSS Section ────────────────────────────────┐  │
│ │ Score: 9.8 ███████████████████████████░ vector │  │
│ │ CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H│  │
│ │ [Edit CVSS] (program owner only)               │  │
│ ├────────────────────────────────────────────────┤  │
│ │ Final Severity: [dropdown] (program owner)     │  │
│ └────────────────────────────────────────────────┘  │
│                                                     │
│ ┌─ Description ─────────────────────────────────┐  │
│ │ Prose content, full width                      │  │
│ ├── Steps to Reproduce ─────────────────────────┤  │
│ │ Numbered list, mono font for code snippets     │  │
│ ├── Impact ─────────────────────────────────────┤  │
│ │ Prose                                          │  │
│ ├── Attachments ────────────────────────────────┤  │
│ │ file-icon filename.png (12KB) [download]       │  │
│ └────────────────────────────────────────────────┘  │
│                                                     │
│ ┌─ Comments Thread ─────────────────────────────┐  │
│ │ [timestamp] user: message                      │  │
│ │   └─ [reply] nested reply                     │  │
│ │ ─────────────────────────────────              │  │
│ │ [New comment input] [Reply]                    │  │
│ └────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────┘
```

CVSS bar: a single flat bar, filled portion in severity color (no gradient), background in `--muted`.
Vector displayed in `mono` font below the bar.

### CVSS Calculator (Inline/Modal)

```
┌─ CVSS Calculator ──────────────────────────────────┐
│                                                     │
│ Attack Vector        [N] [A] [L] [P]               │
│ Attack Complexity    [L] [H]                        │
│ Privileges Required  [N] [L] [H]                    │
│ User Interaction     [N] [R]                        │
│ Scope               [U] [C]                         │
│                                                     │
│ ─── Impact ───────────────────────────────────────  │
│ Confidentiality     [N] [L] [H]                     │
│ Integrity           [N] [L] [H]                     │
│ Availability        [N] [L] [H]                     │
│                                                     │
│ ─── Result ───────────────────────────────────────  │
│ Vector: CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/...  │
│ Score: 9.8  Severity: Critical                      │
│                                                     │
│ [Cancel]                             [Apply CVSS]   │
└────────────────────────────────────────────────────┘
```

Metric buttons: toggle group style. Selected = `bg --accent, text --accent-foreground`. Unselected = `bg --muted, text --muted-foreground`. No outlines on unselected — just flat color difference.

---

## Interaction Patterns

### Micro-interactions

- Button press: `transform: scale(0.98)` for 100ms, then back. Subtle.
- Page transitions: none (PHP server-rendered, no SPA).
- Toast enter: slide up 8px + fade in, 200ms ease-out.
- Sidebar nav item active: instant, no animation.
- Theme toggle: `transition: background-color 200ms, color 200ms` on body.

### Empty States

No illustrations. Just centered text:
```
[icon: inbox, 32px, --muted-foreground]
"No reports yet"
caption: "Reports will appear here once researchers submit them."
```

### Loading States

Skeleton screens: `bg --muted` rectangles with `animate-pulse` (opacity 0.5 → 1.0, 1.5s loop). Same shape as content they replace.

---

## Iconography

Use [Lucide icons](https://lucide.dev) (same as shadcn/ui). Size 18px default, stroke-width 1.5.

Key icons:
- Dashboard: `layout-dashboard`
- Programs: `shield`
- Reports: `file-text`
- Comments: `message-square`
- Users: `users`
- Activity: `activity`
- Bookmark: `bookmark`
- Settings: `settings`
- Severity Critical: `alert-triangle`
- Severity High: `alert-circle`
- Severity Medium: `info`
- Severity Low: `minus-circle`

---

## Anti-patterns (What NOT to do)

1. ❌ No gradient backgrounds anywhere
2. ❌ No hero sections or decorative banners
3. ❌ No card grids with large padding and illustrations
4. ❌ No rounded-2xl on containers (max rounded-lg = 8px)
5. ❌ No colored section backgrounds (stick to `--background` and `--card`)
6. ❌ No hover effects that move/lift elements (no translateY on hover)
7. ❌ No custom scrollbars
8. ❌ No avatar images (use initials in a circle, mono weight, `--muted` bg)
9. ❌ No "AI-generated" hallmarks: no blue-purple combos, no glowing effects, no glassmorphism
10. ❌ No excessive white space between elements — keep it dense like a productivity tool

---

## CSS Implementation Notes

Since this is a PHP project (not React/Next.js), the design system maps to plain CSS custom properties + utility classes:

```css
/* Root variables — Light mode */
:root {
  --background: #fafafa;
  --foreground: #0a0a0a;
  --card: #ffffff;
  --muted: #f4f4f5;
  --muted-foreground: #71717a;
  --border: #e4e4e7;
  --input: #e4e4e7;
  --ring: #f59e0b;
  --accent: #f59e0b;
  --accent-foreground: #0a0a0a;
  --destructive: #ef4444;
  --success: #22c55e;
  --radius-sm: 4px;
  --radius-md: 6px;
  --radius-lg: 8px;
}

/* Dark mode */
[data-theme="dark"] {
  --background: #09090b;
  --foreground: #fafafa;
  --card: #18181b;
  --muted: #27272a;
  --muted-foreground: #a1a1aa;
  --border: #27272a;
  --input: #27272a;
  --destructive: #dc2626;
  --success: #16a34a;
}
```

Theme toggle stores preference in `localStorage` and sets `data-theme` attribute on `<html>`. Falls back to `prefers-color-scheme` media query on first visit.
