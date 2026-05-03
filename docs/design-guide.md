# Design Guide

**Version**: 1.1.0 | **Theme**: Single Dark Theme | **Primary**: Amber `#d97706`

---

## 1. Color Palette

All colors defined in `public/css/tokens.css` as CSS custom properties.  
**Never use a raw hex value outside of `tokens.css`.**

### Selected from palette (rationale below)

| Token                   | Value                  | Source Palette        | Role                            |
| ----------------------- | ---------------------- | --------------------- | ------------------------------- |
| `--color-primary`       | `#d97706`              | Amber (root)          | Brand, CTAs, active states      |
| `--color-primary-hover` | `#b45900`              | Shades                | Button hover                    |
| `--color-primary-dim`   | `#903b00`              | Shades                | Pressed / active                |
| `--color-primary-faint` | `rgba(217,119,6,0.12)` | Derived               | Tinted backgrounds              |
| `--color-accent`        | `#38beac`              | Switch Palette        | Secondary actions, accent stats |
| `--color-accent-hover`  | `#007360`              | Matching Gradient     | Accent hover                    |
| `--color-success`       | `#00a954`              | Classy Palette        | Completion, positive states     |
| `--color-danger`        | `#db5250`              | Generic Gradient      | Errors, destructive actions     |
| `--color-danger-hover`  | `#b84a7b`              | Generic Gradient      | Danger hover                    |
| `--color-text`          | `#efbc95`              | Spot Palette          | Primary body text (warm cream)  |
| `--color-text-strong`   | `#ffeace`              | Spot Palette          | Headings, high-emphasis         |
| `--color-text-sub`      | `#baa89b`              | Grey Friends / Classy | Secondary, captions             |
| `--color-text-muted`    | `#534439`              | Cube Palette          | Placeholder, disabled           |
| `--color-surface`       | `#1a1612`              | Derived from bg       | Cards, panels                   |
| `--color-surface-alt`   | `#221d18`              | Derived               | Table rows, alt inputs          |
| `--color-border`        | `#3a3028`              | Derived               | Default borders                 |
| `--color-border-strong` | `#534439`              | Cube Palette          | Strong dividers                 |

> Palettes NOT used: Random Shades (too many similar ambers), Squash (magenta doesn't fit), Threedom (too colorful for minimal).

---

## 2. Typography

**Font:** Inter (Google Fonts) — loaded via `style.css` `@import`.  
Minimum body weight: **500 (Medium)**. Headings start at **600 (Semibold)**.

| Class         | Size | Weight | Use                         |
| ------------- | ---- | ------ | --------------------------- |
| `.text-h1`    | 28px | 700    | Page titles (one per page)  |
| `.text-h2`    | 22px | 600    | Section headers             |
| `.text-h3`    | 18px | 600    | Card headers                |
| `.text-body`  | 15px | 500    | Body copy                   |
| `.text-sm`    | 13px | 500    | Labels, captions            |
| `.text-xs`    | 11px | 500    | Meta info, uppercase labels |
| `.text-muted` | 13px | 400    | Helper / secondary text     |
| `.text-label` | 11px | 700    | Form labels (uppercase)     |

---

## 3. Spacing (4px grid)

Only use these tokens for `margin` and `padding`. No arbitrary values.

| Token       | Value | Common Use                           |
| ----------- | ----- | ------------------------------------ |
| `--space-1` | 4px   | Micro gaps, badge padding            |
| `--space-2` | 8px   | Button padding (sm), icon gaps       |
| `--space-3` | 12px  | Input padding, table cell padding    |
| `--space-4` | 16px  | Card padding (sm), form gaps         |
| `--space-5` | 24px  | Card padding (default), section gaps |
| `--space-6` | 32px  | Between sections                     |
| `--space-7` | 48px  | Page section breaks                  |
| `--space-8` | 64px  | Page bottom padding                  |

---

## 4. Border Radius

| Token         | Value | Use                          |
| ------------- | ----- | ---------------------------- |
| `--radius-sm` | 4px   | Badges, tags                 |
| `--radius-md` | 8px   | Buttons, inputs, small cards |
| `--radius-lg` | 12px  | Cards, panels                |
| `--radius-xl` | 16px  | Large modals (if needed)     |

---

## 5. Shadows

| Token                   | Use                                   |
| ----------------------- | ------------------------------------- |
| `--shadow-sm`           | Subtle lift                           |
| `--shadow-card`         | All `.card` and `.stat-card` elements |
| `--shadow-focus`        | Focus ring on inputs/buttons (amber)  |
| `--shadow-focus-accent` | Focus ring on accent-colored elements |

---

## 6. Component Classes (quick ref)

### Buttons

```html
<button class="btn btn-primary">Primary</button>
<button class="btn btn-secondary">Secondary</button>
<button class="btn btn-ghost">Ghost</button>
<button class="btn btn-accent">Accent</button>
<button class="btn btn-danger">Danger</button>

<!-- Sizes: add btn-sm or btn-lg -->
<button class="btn btn-primary btn-sm">Small</button>
<button class="btn btn-primary btn-lg">Large</button>
```

### Forms

```html
<div class="form-group">
  <label class="form-label" for="name">Activity Name</label>
  <input id="name" type="text" class="input" placeholder="e.g. Development" />
  <span class="form-hint">This appears in your time logs.</span>
</div>
```

### Cards

```html
<div class="card">
  <div class="card-header">
    <h2 class="text-h3">Card Title</h2>
    <button class="btn btn-ghost btn-sm">Action</button>
  </div>
  <!-- content -->
</div>
```

### Stat Cards

```html
<div class="stat-card stat-card--primary">
  <div class="stat-card__label">Total Hours</div>
  <div class="stat-card__value">4h 20m</div>
  <div class="stat-card__sub">Logged today</div>
</div>
```

Modifiers: `stat-card--primary` (amber), `stat-card--accent` (teal), plain (neutral).

### Tables

```html
<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th>Activity</th>
        <th>Duration</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Development</td>
        <td>1h 30m</td>
        <td><span class="badge badge-success">Done</span></td>
      </tr>
    </tbody>
  </table>
</div>
```

### Badges

```html
<span class="badge badge-success">Complete</span>
<span class="badge badge-warning">Running</span>
<span class="badge badge-danger">Missed</span>
<span class="badge badge-accent">Active</span>
<span class="badge badge-neutral">Paused</span>
```

### Alerts (flash messages from PHP)

```html
<div class="alert alert-success">Routine saved successfully.</div>
<div class="alert alert-danger">Could not delete — entries exist.</div>
```

### Progress Bar

```html
<div class="progress">
  <div class="progress-bar" style="width: 65%"></div>
</div>
```

Modifiers: `progress-bar--accent`, `progress-bar--success`, `progress-bar--danger`.

---

## 7. CSS File Structure

```
public/css/
  style.css        ← entry point, @import only — no rules here
  tokens.css       ← all :root CSS custom properties
  base.css         ← reset, body, typography classes, layout helpers
  components.css   ← .btn, .card, .table, .badge, .nav, etc.
  dashboard.css    ← page-specific overrides (only if needed)
  reports.css      ← page-specific overrides (only if needed)
```

**Load order in `style.css`:**

```css
@import url('Google Fonts');
@import 'tokens.css';
@import 'base.css';
@import 'components.css';
```

---

## 8. PHP Page Template

```php
<?php
$pageTitle = 'Page Name'; // shows in <title>
$pageCSS = 'dashboard.css'; // optional page-specific CSS
require_once 'header.php';
?>

<!-- page content using design system classes -->

<?php require_once 'footer.php'; ?>
```

---

## 9. Rules

| ✅ Do                                                | ❌ Don't                                    |
| ---------------------------------------------------- | ------------------------------------------- |
| Use `var(--token)` for all colors, spacing, radius   | Write raw hex or px values in component CSS |
| Font weight ≥ 500 for all body text                  | Use `font-weight: 400` for readable text    |
| Use spacing tokens (`--space-*`)                     | Use arbitrary values like `margin: 7px`     |
| Set `$pageTitle` in each PHP file                    | Leave the `<title>` as the default          |
| Use `.table-wrap > .table` for all tables            | Put `<table>` directly with no wrapper      |
| Only put page-specific rules in `dashboard.css` etc. | Add rules to `style.css` (entry point only) |
| Dark theme only — hard-coded dark surfaces           | Add `prefers-color-scheme` or light mode    |
