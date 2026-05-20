# logged-cloud/select

A family of select widgets for Laravel apps. Each component name spells out which JS layer it uses and what it does, so you can pick the right one without reading the docs.

## Variants

| Component | Search | Multi | Driver | A11y pattern |
| --- | --- | --- | --- | --- |
| `<x-select::searchable-alpine>` | ✓ client-side | — | Alpine | WAI combobox + listbox popup |
| `<x-select::multi-alpine>` | ✓ client-side | ✓ chips | Alpine | WAI combobox + multiselectable listbox |
| `<x-select::radio-grid-alpine>` | — | — | Alpine | WAI radiogroup, roving tabindex |

The naming convention is **`<behaviour>-<driver>`**. Behaviour first (`searchable`, `multi`, `radio-grid`, …), driver second (`alpine`, `livewire`, ...). Future entries (`remote-livewire` for server-side search of huge lists, `native` for a no-JS fallback, `tags-alpine` for free-form tag entry, etc.) slot in alongside without forcing a new `composer require`.

All three share the same theming, CSS class scope (`.lc-select__*`, `.lc-radio-grid__*`), reduced-motion / forced-colours handling and `{key, title, subtitle, svg}` item shape.

Sister to [logged-cloud/navigation](https://github.com/Logged-Cloud/navigation).

## Requirements

| Dependency | Versions |
| --- | --- |
| PHP | 8.2, 8.3, 8.4 |
| Laravel | 11, 12, 13 (`illuminate/support`) |
| Livewire | 3, 4 (provides the Alpine bundle the components rely on) |
| Alpine.js | 3 (bundled with Livewire) |

## Install

```bash
composer require logged-cloud/select
```

`vendor:publish --tag=select-config` is optional; the components run on sensible defaults out of the box. `@source "../../vendor/logged-cloud/select/resources/views"` in your Tailwind config keeps the package classes during purging.

## Item shape (every variant)

Each item carries four fields:

| Field | Type | Notes |
| --- | --- | --- |
| `key` | string | What lands in the hidden input · stable identifier the form posts. |
| `title` | string | Bold first line in the row / card. |
| `subtitle` | string | Optional muted second line. |
| `svg` | string | Single SVG `path d` string drawn at 24×24, `stroke=currentColor`. |

Items can be arrays or objects with the matching attribute names. **Order is respected** — components render them in the order you pass them.

---

## `<x-select::searchable-alpine>`

Searchable single-select. Items shipped to the page; search runs client-side.

```blade
<x-select::searchable-alpine
    name="prey_type"
    :items="$preyTypes"
    :selected="old('prey_type', $snake->prey_type)"
    placeholder="Pick prey..."
    label="Prey type"
/>
```

| Attribute | Type | Default | Purpose |
| --- | --- | --- | --- |
| `name` | string | (required) | Form input name posted to the server. |
| `id` | string | `$name` | Trigger element id. |
| `items` | array\|Collection | `[]` | The option list (see shape above). |
| `selected` | string\|null | `null` | Pre-selected `key`, or `null`. |
| `allow-empty` | bool | `true` | Render the empty row in the menu. |
| `placeholder` | string | "Select an option" | Trigger text when nothing is chosen. |
| `empty-label` | string | "not set" | Label for the empty row in the menu. |
| `search-label` | string | "Search..." | Placeholder for the search input. |
| `no-results-label` | string | "No options match that." | Empty-search copy. |
| `searchable` | bool | `true` | Show the search input above the list. |
| `icon-size` | string | `1.75rem` | Tile size of each row's icon square. |
| `label` | string\|null | `null` | Accessible name (`aria-label`). |
| `labelled-by` | string\|null | `null` | Existing `<label>` id (`aria-labelledby`). |
| `required` | bool | `false` | Sets `aria-required` + hidden input `required`. |
| `disabled` | bool | `false` | Sets `aria-disabled` + disables the trigger. |

**Keyboard**

| Key | Closed | Open |
| --- | --- | --- |
| `Enter` / `Space` | Open at current selection | Pick the active option |
| `↓` | Open + first option | Move down |
| `↑` | Open + last option | Move up |
| `Home` / `End` | — | Jump to first / last |
| `PageDown` / `PageUp` | — | ±5 options |
| `Esc` | — | Close & return focus to trigger |
| `Tab` | — | Close & let focus flow |
| Typing | — | Filters the list (when `searchable`) |

---

## `<x-select::multi-alpine>`

Searchable multi-select. Chips on the trigger, checkmarks in the menu, hidden inputs posted as `name[]` so `$request->validate(['prey_types' => 'array'])` works directly.

```blade
<x-select::multi-alpine
    name="prey_types"
    :items="$preyTypes"
    :selected="old('prey_types', $snake->prey_types ?? [])"
    label="Acceptable prey"
    :max="5"
/>
```

| Attribute | Type | Default | Purpose |
| --- | --- | --- | --- |
| `name` | string | (required) | Posted as `name[]` to the server. |
| `id` | string | `$name` | Trigger element id. |
| `items` | array\|Collection | `[]` | The option list. |
| `selected` | array\|Collection | `[]` | Pre-selected keys (preserves order). |
| `placeholder` | string | "Select options" | Trigger text when nothing is chosen. |
| `search-label` | string | "Search..." | Placeholder for the search input. |
| `no-results-label` | string | "No options match that." | Empty-search copy. |
| `searchable` | bool | `true` | Show the search input. |
| `icon-size` | string | `1.75rem` | Tile size of each row's icon square. |
| `label` | string\|null | `null` | Accessible name. |
| `labelled-by` | string\|null | `null` | Existing `<label>` id. |
| `required` | bool | `false` | Sets `aria-required`. |
| `disabled` | bool | `false` | Sets `aria-disabled` + disables the trigger. |
| `max` | int\|null | `null` | Cap selections; extras are refused with an SR-announced "Maximum reached" message. |
| `chips-limit` | int | `3` | Above this count the trigger collapses to "N selected" instead of stacking chips. |

**Behaviour differences from `searchable-alpine`**

- `aria-multiselectable="true"` on both the trigger combobox and the listbox.
- `Enter` / `Space` *toggles* the active option and **keeps the menu open** — multi-select is iterative.
- A checkmark cell + chips replace the single-row selected-icon UI.
- Each chip carries its own remove (×) button with `aria-label="Remove <title>"`.
- The live region announces `Added <title>` / `Removed <title>` per toggle.

---

## `<x-select::radio-grid-alpine>`

A card-grid radiogroup for single-pick choices where you want every option visible at once (event-type pickers, status switchers, etc.). No dropdown.

```blade
<x-select::radio-grid-alpine
    name="event_type"
    :items="$eventTypes"
    :selected="old('event_type', 'feeding')"
    label="Event type"
    min-width="7rem"
/>
```

| Attribute | Type | Default | Purpose |
| --- | --- | --- | --- |
| `name` | string | (required) | Form input name posted to the server. |
| `id` | string | `$name` | Container id. |
| `items` | array\|Collection | `[]` | Cards rendered in order. |
| `selected` | string\|null | `null` | Pre-selected `key`. |
| `icon-size` | string | `1.5rem` | Icon size inside each card. |
| `min-width` | string | `6.5rem` | Min cell width passed to the CSS grid (`auto-fill, minmax(...)`). |
| `label` | string\|null | `null` | Accessible name (`aria-label` on the radiogroup). |
| `labelled-by` | string\|null | `null` | Existing `<label>` id. |
| `required` | bool | `false` | Sets `aria-required` + hidden input `required`. |
| `disabled` | bool | `false` | Each card carries `aria-disabled`. |

**Keyboard** — implements the WAI radiogroup *roving tabindex* pattern.

| Key | Effect |
| --- | --- |
| `Tab` | Enter / exit the group (focus lands on the chosen card, or the first one if none chosen). |
| `←` / `→` / `↑` / `↓` | Move to the previous / next card (and select it). |
| `Home` / `End` | Jump to first / last card. |
| `Space` / `Enter` | Pick the focused card. |

The currently-chosen card has `tabindex="0"`; every other card has `tabindex="-1"`. `aria-checked` flips with the selection.

---

## Accessibility (shared across variants)

- Every variant uses CSS custom properties + system colours so it survives **`forced-colors`** mode (Windows High Contrast → `CanvasText` / `Highlight` / `HighlightText`).
- All transitions are dropped under **`prefers-reduced-motion: reduce`**.
- Focus rings are 2px outline + 2px offset on the host theme's accent, meeting WCAG 2.4.7 non-text contrast.
- The `searchable-alpine` and `multi-alpine` variants both ship a polite `aria-live` region announcing the filtered result count and selection events.

Behavioural testing is left to the consumer app's browser test suite (snake.logged.cloud uses Laravel Dusk via the navigation package's sandbox pattern). The package itself ships 25 structural Pest tests / 117 assertions covering the ARIA wiring, keyboard handler set, focus-return helpers and CSS hooks for every variant.

## Theming

Every colour is a CSS custom property. Override any of these in your app's CSS to retheme:

```css
.lc-select, .lc-radio-grid {
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

By default each variable falls back to your host app's `--surface`, `--accent` etc — match those and the components pick up your theme without extra config.

## Tailwind

If your app uses Tailwind v4, add the package to your `@source` directives so its classes survive purging:

```css
@source "../../vendor/logged-cloud/select/resources/views";
```

## Livewire

The components live inside any page that loads `@livewireScripts`, which is where Alpine comes from. Each variant registers its Alpine data factory once globally via an `@once`-guarded `<script>` in the component template — multiple instances on a page share one bundle.
