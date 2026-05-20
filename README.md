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
