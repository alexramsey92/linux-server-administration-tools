# Tailwind Dark Mode Playbook (Concise)

A short guide for fixing recurring dark mode issues across Tailwind projects.

## Common Problems

- Bright/light cards look fine in light theme but glow or wash out in dark theme.
- Buttons look like plain text (or inverted incorrectly) in dark mode.
- Status colors (red/green/yellow) are applied to full containers, reducing readability.
- Text contrast is inconsistent (`text-*` classes not paired with `dark:text-*`).

## Root Cause

Most issues come from **missing paired dark classes** and **overuse of semantic colors on large surfaces**.

## Ruleset We Used (Works Well)

1. **Neutral containers first**
   - Use: `bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700`
   - Avoid full-card status backgrounds.

2. **Color only for status accents**
   - Use badges/chips/icons for status, not whole cards.

3. **Standard text tiers**
   - Primary: `text-slate-900 dark:text-white`
   - Secondary: `text-slate-600 dark:text-slate-400`
   - Disabled: `text-slate-500 dark:text-slate-300`

4. **Consistent button styles**
   - Primary: `bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 text-white`
   - Secondary: `bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-900 dark:text-white`

5. **No inverted light buttons in dark mode**
   - Avoid patterns like `dark:bg-slate-100 dark:text-slate-900` unless explicitly intended.

## Status Badge Palette

- Ready: `bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200`
- Warning: `bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200`
- Error: `bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200`

## Fast Review Checklist (Before Merge)

- Every `bg-*` has a `dark:bg-*` pair.
- Every `text-*` has a `dark:text-*` pair.
- Every `border-*` has a `dark:border-*` pair.
- Action elements still look like controls in dark mode.
- Long sections remain neutral; color is limited to badges/icons/buttons.

## Practical Pattern (Copy/Paste)

```html
<div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
  <div class="flex items-center justify-between">
    <h3 class="text-slate-900 dark:text-white font-semibold">Card Title</h3>
    <span class="px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
      Ready
    </span>
  </div>

  <p class="mt-2 text-slate-600 dark:text-slate-400">Secondary text content.</p>

  <button class="mt-4 px-3 py-2 rounded bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 text-white">
    Action
  </button>
</div>
```

## One-Line Principle

**Use neutral surfaces for layout; use color only to communicate status and action.**
