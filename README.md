# logged-cloud/select

A searchable Alpine-driven select dropdown with inline-SVG icons. One Blade
component, themeable via CSS variables, item list passed in as a prop.

Sister to [logged-cloud/navigation](https://github.com/Logged-Cloud/navigation).
Used wherever a logged.cloud family app needs an icon-led dropdown — prey
types on snake.logged.cloud, baits on fish.logged.cloud, anything similar.

## Requirements

| Dependency | Versions |
| --- | --- |
| PHP | 8.2, 8.3, 8.4 |
| Laravel | 11, 12, 13 (`illuminate/support`) |
| Livewire | 3, 4 (provides the Alpine bundle the component relies on) |
| Alpine.js | 3 (bundled with Livewire) |

## Install

```bash
composer require logged-cloud/select
```

`vendor:publish --tag=select-config` is optional; the component runs on
sensible defaults out of the box.

## Use

```blade
<x-select::box
    name="prey_type"
    :items="$preyTypes"
    :selected="old('prey_type', $snake->prey_type)"
    placeholder="Pick prey..."
    empty-label="not set"
/>
```

Each item must carry four fields:

| Field | Type | Notes |
| --- | --- | --- |
| `key` | string | What lands in the hidden input · stable identifier the form posts. |
| `title` | string | Bold first line in the row. |
| `subtitle` | string | Optional muted second line. |
| `svg` | string | Single SVG `path d` string drawn at 24×24, `stroke=currentColor`. |

Items can be arrays or objects with the matching attribute names. Order is
respected — the component renders them in the order you pass them.

## Attributes

| Attribute | Default | Purpose |
| --- | --- | --- |
| `name` | (required) | Form input name posted to the server. |
| `id` | `$name` | Trigger element id. |
| `items` | `[]` | The option list (see shape above). |
| `selected` | `null` | Pre-selected `key`, or `null` for no selection. |
| `allow-empty` | `true` | Render the empty row in the menu. |
| `placeholder` | "Select an option" | Trigger text when nothing is chosen. |
| `empty-label` | "not set" | Label for the empty row in the menu. |
| `search-label` | "Search..." | Placeholder for the search input. |
| `no-results-label` | "No options match that." | Empty-search copy. |
| `searchable` | `true` | Show the search input above the list. |
| `icon-size` | `1.75rem` | Tile size of each row's icon square. |
| `label` | `null` | Accessible name announced by screen readers (sets `aria-label` on the trigger + listbox). Use this **or** `labelled-by`. |
| `labelled-by` | `null` | id of an existing visible `<label>` to associate with the combobox via `aria-labelledby`. |
| `required` | `false` | Marks the hidden input as required and sets `aria-required="true"`. |
| `disabled` | `false` | Disables the trigger and sets `aria-disabled="true"`. |

## Accessibility

The component implements the [WAI-ARIA Authoring Practices combobox pattern](https://www.w3.org/WAI/ARIA/apg/patterns/combobox/) with a list popup.

**Roles & state**

- Trigger button: `role="combobox"`, `aria-haspopup="listbox"`, `aria-controls`, `aria-expanded`, `aria-activedescendant`, `aria-autocomplete="list"`. Carries `aria-required` and `aria-disabled` from props.
- Listbox: `role="listbox"`, `aria-label` or `aria-labelledby` from props (defaults to `"Options"`), `tabindex="-1"`.
- Options: `role="option"`, `aria-selected="true"` on the chosen row + a `✓` glyph so the state survives forced-colours / monochrome.

**Keyboard**

| Key | Closed | Open |
| --- | --- | --- |
| `Enter` / `Space` | Open at current selection | Pick the active option |
| `↓` | Open + first option | Move down |
| `↑` | Open + last option | Move up |
| `Home` | — | Jump to first |
| `End` | — | Jump to last |
| `PageDown` / `PageUp` | — | ±5 options |
| `Esc` | — | Close & return focus to trigger |
| `Tab` | — | Close & let focus flow naturally |
| Typing | — | Filters the list (when `searchable`) |

**Focus management** — opening the menu moves focus to the search input (or keeps the trigger if `searchable=false`); selecting or clearing closes the menu and returns focus to the trigger.

**Live region** — a visually-hidden `aria-live="polite"` region announces the filtered result count as you type and the selected label when you pick a row.

**Visible focus** — the trigger gets a 2px `outline` + offset that meets WCAG 2.4.7 non-text contrast against the host theme's accent colour. Each active row gets an inset accent ring.

**`prefers-reduced-motion`** — the menu fade and row transitions are disabled.

**`forced-colors` mode** — Windows High Contrast picks up `CanvasText` / `Highlight` / `HighlightText` so the chrome stays readable.

**Touch & screen readers** — no `pointer-events` traps. Tested against the structural ARIA contract; SR coverage left to the host app to verify with its own tooling.

## Theming

Every colour is a CSS custom property. Override any of these in your app's
CSS to retheme the dropdown:

```css
.lc-select {
    --lc-bg:        #1f2937;
    --lc-menu-bg:   #1f2937;
    --lc-border:    #374151;
    --lc-ink:       #f9fafb;
    --lc-ink-dim:   #9ca3af;
    --lc-accent:    #0e9f6e;
    --lc-icon-bg:   #111827;
    --lc-hover-bg:  #374151;
    --lc-radius:    .5rem;
}
```

By default each variable falls back to your host app's `--surface`,
`--accent` etc — match those and the dropdown picks up your theme without
extra config.

## Tailwind

If your app uses Tailwind v4, add the package to your `@source` directives
so its classes are kept during purging:

```css
@source "../../vendor/logged-cloud/select/resources/views";
```

## Livewire

The component lives inside a Livewire-rendered page (or any page that loads
`@livewireScripts`) so Alpine is available. The Alpine data factory
`loggedCloudSelect` is registered once globally via an `@once`-guarded
`<script>` in the component template.
