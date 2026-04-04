# Design Tokens

This project uses a three-layer token model:

1. Primitive tokens: spacing, radius, type, shadows.
2. Semantic tokens: background, foreground, card, border, accent.
3. Component tokens: sidebar width, topbar height, focus ring.

## Source of Truth

All tokens are defined in [resources/css/app.css](/Users/unmar/Desktop/Projects/vita/resources/css/app.css).

Primary groups:
- `--background`, `--foreground`, `--card`, `--card-foreground`, `--muted-foreground`, `--border`, `--accent`
- `--space-*` (spacing scale)
- `--radius-*` (corner scale)
- `--text-*` (type scale)
- `--shadow-sm`, `--focus-ring`
- `--sidebar-width`, `--topbar-height`

## Mode Strategy

- `:root` defines light defaults.
- `:root.dark` and `:root.light` are explicit mode overrides.
- `@media (prefers-color-scheme: dark)` is used as system fallback.

## Usage Rules

- Use semantic/component tokens in component styles.
- Avoid raw hex values and one-off spacing/radius where a token exists.
- Prefer shared UI classes from `@layer components`:
  - `.ui-page`
  - `.ui-surface`
  - `.ui-surface-muted`
  - `.ui-text-muted`
  - `.ui-title`
  - `.ui-subtitle`
  - `.ui-btn`, `.ui-btn-ghost`, `.ui-icon-btn`
  - `.ui-input-shell`
