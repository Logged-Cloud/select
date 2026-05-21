# logged-cloud/select

A family of accessible select widgets for Laravel apps. Each component name spells out behaviour + driver so you can pick the right one without reading the docs.

## Variants

| Component | Preview | Search | Multi | Pattern |
| --- | --- | --- | --- | --- |
| [`searchable-alpine`](#x-selectsearchable-alpine) | <img src="docs/images/searchable-alpine.png" width="280" alt="searchable-alpine"> | ✓ client | — | dropdown · combobox + listbox |
| [`multi-alpine`](#x-selectmulti-alpine) | <img src="docs/images/multi-alpine.png" width="280" alt="multi-alpine"> | ✓ client | ✓ chips | dropdown · combobox + multiselectable listbox |
| [`radio-grid-alpine`](#x-selectradio-grid-alpine) | <img src="docs/images/radio-grid-alpine.png" width="280" alt="radio-grid-alpine"> | — | — | compact card grid · radiogroup |
| [`radio-list-alpine`](#x-selectradio-list-alpine) | <img src="docs/images/radio-list-alpine.png" width="280" alt="radio-list-alpine"> | — | — | vertical list · radiogroup |
| [`multi-grid-alpine`](#x-selectmulti-grid-alpine) | <img src="docs/images/multi-grid-alpine.png" width="280" alt="multi-grid-alpine"> | — | ✓ checks | compact card grid · toggle-button group |
| [`multi-list-alpine`](#x-selectmulti-list-alpine) | <img src="docs/images/multi-list-alpine.png" width="280" alt="multi-list-alpine"> | — | ✓ checks | vertical list · toggle-button group |
| [`inline-buttons-alpine`](#x-selectinline-buttons-alpine) | <img src="docs/images/inline-buttons-alpine.png" width="280" alt="inline-buttons-alpine"> | — | — | segmented pill row · radiogroup |
| [`card-single-alpine`](#x-selectcard-single-alpine) | <img src="docs/images/card-single-alpine.png" width="280" alt="card-single-alpine"> | — | — | big visual cards · radiogroup |
| [`card-multi-alpine`](#x-selectcard-multi-alpine) | <img src="docs/images/card-multi-alpine.png" width="280" alt="card-multi-alpine"> | — | ✓ | big visual cards · toggle-button group |
| [`tags-alpine`](#x-selecttags-alpine) | <img src="docs/images/tags-alpine.png" width="280" alt="tags-alpine"> | ✓ client | ✓ chips | free-form tag entry · combobox + listbox |
| `searchable-alpine` + `:search-url` | <img src="docs/images/searchable-remote.png" width="280" alt="searchable-remote"> | ✓ remote | — | same component, debounced server-side search |
| `card-single-alpine` + `:page-size` | <img src="docs/images/card-paginated.png" width="280" alt="card-paginated"> | — | — | same component, prev/next pagination |
| `searchable-alpine` + `:depends-on` | <img src="docs/images/depends-on.png" width="280" alt="depends-on"> | ✓ scoped | — | child select gated + scoped by a parent field |
| [`map-svg-alpine`](#x-selectmap-svg-alpine) (world) | <img src="docs/images/map-world.png" width="280" alt="map-world"> | — | — | SVG map menu · click a country |
| `map-svg-alpine` (country detail) | <img src="docs/images/map-uk.png" width="280" alt="map-uk"> | — | — | UK outline + city points |
| `map-svg-alpine` drilldown | <img src="docs/images/map-drilldown.png" width="280" alt="map-drilldown"> | — | — | world → country → town via `depends-on` |
| [`map-drilldown-alpine`](#x-selectmap-drilldown-alpine) | <img src="docs/images/map-drilldown-single.png" width="280" alt="map-drilldown-single"> | — | — | single trigger, menu swaps as user drills in |
| [`tree-alpine`](#x-selecttree-alpine) | <img src="docs/images/tree-alpine.png" width="280" alt="tree-alpine"> | — | — | hierarchical list · expand/collapse, roving tabindex |
| [`rating-alpine`](#x-selectrating-alpine) | <img src="docs/images/rating-alpine.png" width="280" alt="rating-alpine"> | — | — | star rating · role=slider, half-step + clear |
| [`color-palette-alpine`](#x-selectcolor-palette-alpine) | <img src="docs/images/color-palette-alpine.png" width="280" alt="color-palette-alpine"> | — | — | swatch grid · arrow keys wrap by columns |
| [`map-pin-alpine`](#x-selectmap-pin-alpine) | <img src="docs/images/map-pin-alpine.png" width="280" alt="map-pin-alpine"> | — | — | click anywhere on a map to drop a pin |
| [`date-alpine`](#x-selectdate-alpine) | <img src="docs/images/date-alpine.png" width="280" alt="date-alpine"> | — | — | month-grid calendar · WAI grid pattern + min/max |

Naming convention is **`<behaviour>-<driver>`**: behaviour first (`searchable`, `multi`, `radio-grid`, `card-multi`, `tags`, …), driver second (`alpine`, `livewire`, ...). Future entries (`remote-livewire` for server-side search, `native` for a no-JS fallback, …) slot in alongside without forcing a new `composer require`.

### v3.6 highlights · R.A.P pass on the v3.0-v3.5 family

- **Robust** · color-palette uses ITU-R BT.709 luminance to flip the checkmark to dark on light swatches (white-check-on-white was invisible); date picker's arrow nav now skips disabled cells in the same direction so the focus never lands on something the user can't pick.
- **Accessible** · map-pin gains full keyboard placement via a ghost cursor (arrow keys move, Shift jumps 10×, Enter commits, Delete clears) — the SVG is now `tabindex=0` with a focus ring; rating's `aria-valuetext` uses singular `1 star` vs plural; date picker adds `«` / `»` year-jump buttons in the header and Shift-Page Up/Down for keyboard year-jump.
- **Performance** · tree picker pre-computes a `_parentOf[]` map at init so `isVisible()` is O(depth) hash lookups instead of an O(N) backwards scan per node per render.

### v3.5 highlights · `date-alpine` (month-grid calendar)

Calendar-grid date picker following the WAI-ARIA grid pattern.

- **`role="dialog"`** menu containing a `role="grid"` table of `role="gridcell"` days · roving tabindex tracks the focused cell.
- **Keyboard**: ↑/↓/←/→ move by 1 day, Page Up/Down change month, Home/End jump to week start/end, Enter / Space pick. Month nav arrows are buttons in the header.
- **`:min` / `:max`** prop (ISO `YYYY-MM-DD`) · cells outside the window get `aria-disabled` and are not pickable.
- **`:first-day-of-week`** (default 1 = Monday) shifts the day-of-week header + column ordering.
- **No-JS fallback uses native `<input type="date">`** rather than a `<select>` of 365 options · way better screen-reader UX. The Alpine wrapper clears the native input's `name` on boot so the hidden input is the sole poster.
- **Today + Clear** action buttons in the footer.

```blade
<x-select::date-alpine
    name="due_date"
    selected="2026-05-15"
    min="2026-01-01"
    max="2026-12-31"
    label="Pick a date" />
```

### v3.4 highlights · rating + colour palette + map-pin

Three small siblings that round out the form-input family.

- **`rating-alpine`** · star rating with `role="slider"`, optional `:step="0.5"` half-stars, `:allow-zero` clear-to-empty, keyboard ↑/→/↓/←/Home/End. Hover preview without committing.
- **`color-palette-alpine`** · swatch grid where each item carries `color` (any CSS colour). `:columns` controls the grid, arrow ↑/↓ jump by a whole row, Enter/Space commits. Selected swatch renders inline on the trigger.
- **`map-pin-alpine`** · same map plumbing as `map-svg-alpine` (`dataset="world"` / `uk` / `uk:<region>` or inline items + viewBox), but click drops a pin at the clicked viewBox coordinate. Uses `SVG.getScreenCTM().inverse()` so the click→pin math survives any CSS scaling. Hidden input emits `"x,y"`.

### v3.3 highlights · `tree-alpine` (hierarchical select)

- **New variant `tree-alpine`** · items can carry `children` recursively to build any depth of hierarchy. Each branch row gets a twisty button; clicking expands/collapses, clicking a leaf picks it.
- **WAI-ARIA `tree` / `treeitem`** with `aria-level`, `aria-expanded`, `aria-selected`. Arrow Down/Up moves through visible rows, Arrow Right expands a collapsed branch (or moves to first child), Arrow Left collapses an expanded branch (or moves to parent), Home/End jump to ends, Enter / Space picks (or toggles a branch when `leaves-only`).
- **Re-opening restores ancestors** of the previously-picked leaf so the user lands on what they chose.
- **`:expanded-depth`** prop auto-expands the first N depths so the tree opens already showing structure rather than as one collapsed root.
- **`:leaves-only`** (default `true`) restricts picks to leaf nodes. Set `false` to allow picking a branch too.

```blade
<x-select::tree-alpine
    name="taxonomy"
    :items="$tree"
    label="Pick an item"
    :expanded-depth="1" />

{{-- items shape: --}}
[
    ['key' => 'animals', 'title' => 'Animals', 'children' => [
        ['key' => 'reptiles', 'title' => 'Reptiles', 'children' => [
            ['key' => 'ball-python', 'title' => 'Ball python'],
        ]],
    ]],
]
```

### v3.2 highlights · single-trigger map drilldown

- **New variant `map-drilldown-alpine`** · one trigger, one dropdown. Click UK on the world map → menu stays open, swaps to UK regions. Click Greater London → menu swaps to London boroughs. Click a borough → menu closes with all three values set.
- **Breadcrumb + back button** in the menu header so the user always knows where they are and can step back up the hierarchy.
- **`:levels` config** · an array of `[{name, title, dataset, requires?}]` entries. `requires` gates whether the level is reachable (e.g. only drill into UK regions when `country=gb`). Stops drilldown gracefully when the chosen branch has no further data.
- **One hidden input per level** so standard form posts get `country=gb&region=greater-london&borough=camden`. Re-opening the menu resumes at the deepest enabled level rather than rewinding.

```blade
<x-select::map-drilldown-alpine
    name="location"
    label="Pick a location"
    :levels="[
        ['name' => 'country', 'title' => 'Country', 'dataset' => 'world'],
        ['name' => 'region',  'title' => 'UK region', 'dataset' => 'uk',
            'requires' => ['country' => 'gb']],
        ['name' => 'borough', 'title' => 'Borough',  'dataset' => 'uk:greater-london',
            'requires' => ['region' => 'greater-london']],
    ]" />
```

### v3.1 highlights · clickable region polygons all the way down

- **UK is now polygons, not dots** · `uk.json` ships 16 Natural Earth admin-1 regions (Greater London, South East, Scotland, etc) grouped from the raw 232 sub-features. Each region is one SVG path that visually shows internal sub-borders while clicking anywhere selects the region.
- **Per-region drilldown datasets** · `resources/data/uk-greater-london.json`, `uk-south-east.json`, `uk-scotland.json`, … one file per UK region with its sub-region polygons re-projected into a tight per-region viewBox so the zoom is useful.
- **`dataset="uk:<region>"`** shortcut · the component resolves `dataset="uk:greater-london"` to the matching file via the new `MapData::ukRegion('greater-london')` helper.
- The hand-curated dot-marker `uk-towns.json` is kept as an alternative — apps that prefer point markers over polygons can still use it.

### v3.0 highlights · `map-svg-alpine` + bundled world / UK data

- **New variant `map-svg-alpine`** · same trigger / menu / a11y pattern as the dropdown family, but the menu content is an `<svg>` with each item as a clickable `<path>` (polygon) or `<circle>` (point). Keyboard arrow / Home / End cycle through items; the bottom hover-strip shows the active item's title.
- **Bundled data**:
  - `world.json` — Natural Earth admin-0 110m, ~180 countries as SVG paths (~112KB).
  - `uk.json` — UK outline + ~40 major city points (~2.8KB).
  - `uk-towns.json` — hand-curated boroughs / suburbs for London, Manchester, Birmingham, Glasgow, Edinburgh (~4KB).
- **Helper** `LoggedCloud\Select\MapData::world()` / `::uk()` / `::ukTowns($cityKey)` returns the dataset arrays so apps can pass them inline.
- **Drilldown** is just the existing `:depends-on` chain · three `map-svg-alpine` stacked, each gated by the previous selection. No new composer needed.
- **Data shape is open** · supply your own `{viewBox, items: [{key, title, path?, cx?, cy?}], outline?}` arrays for any country / region. The bundled UK data is a worked example; the Python builder under `bin/build-map-data.py` is the reference pipeline.

```blade
<x-select::map-svg-alpine name="country" dataset="world" label="Country" />

{{-- Drilldown using depends-on · child unlocks when parent is set --}}
<x-select::map-svg-alpine name="city" dataset="uk"
    depends-on="country" depends-message="Pick a country first" />
<x-select::map-svg-alpine name="town" dataset="uk-towns:london"
    depends-on="city" />
```

### v2.11 highlights (R.A.P pass on v2.10 additions)

- **Cards — robust:** the pager `<nav>` is now a sibling of the `role="radiogroup"` / `role="group"` div, not a child, so the WAI-ARIA "radiogroup must contain only radio children" contract holds. The card variants now render as `.lc-cards-host > .lc-cards + nav.lc-cards__pager`. `page` is clamped via a `$watch('items')` so a remote refresh that shrinks the list can't leave us on an empty page.
- **Cards — accessible:** clicking Prev/Next moves focus to the first card of the new page so keyboard arrow navigation continues naturally. Components with an initial selection open on the page containing it.
- **Depends-on — robust:** every variant exposes an Alpine `destroy()` hook that removes the document-level `change` listener · no zombie callbacks under Livewire navigation.
- **Depends-on — accessible:** the polite live region now announces "Selection cleared because the parent changed." (and the parent-unset variant) when the parent change auto-clears the child.

### v2.10 highlights

- **Card pagination.** `card-single-alpine` + `card-multi-alpine` accept `:page-size="6"` to slice items into pages with a prev/next pager + `Page N of M` status. Keyboard arrow at the last card of a page advances to the next page and focuses its first card; arrow-up from the first card goes back. Works with any number of items.
- **Depends-on.** `searchable-alpine`, `multi-alpine`, `tags-alpine` accept `:depends-on="country"` to gate the picker on another form field. Until the parent is set, the trigger renders disabled with a `dependsMessage` placeholder ("Pick a country first" by default). Once the parent has a value, items get scoped client-side via an optional `parent` key on each item, and any `:search-url` gets `&parent=…` appended so the server can filter too. Changing the parent auto-resets the child's selection.

```blade
<x-select::searchable-alpine name="country" :items="$countries" label="Country" />
<x-select::searchable-alpine
    name="city" :items="$cities" label="City"
    depends-on="country"
    depends-message="Pick a country first" />
{{-- Each city has parent => 'uk', 'fr', etc. --}}
```

### v2.9 highlights (final R.A.P pass)

- **Robust:** transient `5xx` / network failures get one automatic retry with a 200ms gap before surfacing as an error. `optionId` routes through `window.lcSafeId` (escapes input underscores first so `"héllo"` and `"h_e9llo"` keys can no longer collide on the DOM id). On viewports ≤ 640px, opening the menu also locks `document.documentElement` scroll (reference-counted) so the page doesn't slide under the user's thumb on iOS.
- **Accessible:** live-region announcements are throttled via `window.lcMakeAnnouncer` (~280ms coalesce) so fast typing no longer chatters in JAWS / NVDA. `.lc-select__more-row` gained a `forced-colors` block that pins it to `CanvasText` / `Canvas` with a top divider.
- **Perf:** ranking now reads from a per-items `WeakMap` of pre-lowercased title / subtitle / key strings · we lowercase once when an items array first lands and reuse those strings for every subsequent query against it. Memo from v2.8 still short-circuits the no-change case on top.

### v2.8 highlights

- **Memoized filter pipeline.** `window.lcMakeFilter` caches the last `(items, query)` pair and short-circuits the ranking + highlight work when Alpine re-invokes the `filtered` getter without inputs having changed. Meaningful on large lists where the O(items × tokens) work was running per render tick.
- **Listbox render cap.** All three search-bearing variants take a `:render-limit="50"` prop (default 50). Beyond the cap the menu shows "Showing 50 of N · refine your search to narrow further." instead of dumping hundreds of DOM nodes. Keyboard navigation + cursor stay scoped to the visible window so arrow-down doesn't leap into hidden rows.

### v2.7 highlights

- **Inline error row** when a remote fetch fails (HTTP / network) · renders in the menu as `role="alert"` and the polite live region announces "Search failed. Try again." so the failure is impossible to miss whether you're sighted or using a screen reader.
- **Cancel-on-close** for any in-flight remote search · `_remote.cancel()` runs from each variant's `close()` so a stale response can't replace items after the user has moved on.
- **Trim + case-insensitive dedupe** for `tags-alpine` custom values · " Feeding " and "feeding" don't both land. Existing keys win over new strings when the title matches a suggestion case-insensitively.
- **Accessible-name fallback** · every trigger now resolves to `label` → `labelledBy` → `placeholder` so a developer who forgets all three still ships a usable picker for screen-reader users.
- **`<span>`-not-`<mark>`** for the match highlight · prevents JAWS / NVDA in verbose modes from announcing "marked" / "marked end" around every matched substring.
- **`tags-alpine` announces filtered counts** on the polite live region the same way `searchable` / `multi` do.

### v2.6 highlights

- **Debounced remote search.** Pass `:search-url="route('prey.search')"` (and optional `:debounce-ms="200"`) to `searchable-alpine`, `multi-alpine`, or `tags-alpine`. Typing fires a debounced `GET ${url}?q=…` that returns a JSON array of items; `AbortController` discards in-flight requests when the next keystroke lands so stale responses never overwrite fresh ones. The trigger's search input flips `aria-busy="true"` and a small accent-coloured spinner renders inside the input while a request is open.
- The token-ranked filter from v2.5 still runs over whatever items the server returns, so server-side relevance can stay simple while the client still gets prefix-priority sort + highlighted matches.

### v2.5 highlights

- **Token-aware ranking.** Multiple search tokens are AND-ed together and ranked: title-prefix > mid-title > key-prefix > key-substring > subtitle. So typing `ra` lists "Rat" before "African soft-furred rat", and `mou rat` only matches items that contain both.
- **Match highlight.** The filter pipeline reports match ranges, the template wraps them in `<mark class="lc-select__match">`, and the CSS tints them with the host's accent via `color-mix`. Inherited everywhere the same item shape is used (`searchable-alpine`, `multi-alpine`, `tags-alpine`).

### v2.4 highlights

- **Mobile bottom-sheet.** On viewports ≤ 640px the `searchable-alpine` + `multi-alpine` dropdowns render as a slide-up sheet with a tap-out backdrop instead of an absolute-positioned 22rem menu above the keyboard.
- **× clear button.** The `searchable-alpine` trigger gains an inline × when something is selected; `multi-alpine` gains a clear-all × beside the chevron.
- **`error="..."` prop.** Pass an error string on any dropdown variant to get a red ring, `aria-invalid="true"`, an `aria-describedby`-linked message with `role="alert"`, and `forced-colors` support.
- **`tags-alpine` variant.** Free-form tag entry with chip removal, Backspace-to-delete-last, suggestion filtering, and optional `allowCustom="false"` to lock typing to the suggestion set.

All variants share the same `{key, title, subtitle, svg}` item shape, the same CSS custom-property theming, the same reduced-motion / forced-colours handling, and a built-in `<noscript>` fallback that swaps in a native `<select>` when JavaScript is disabled.

Sister to [logged-cloud/navigation](https://github.com/Logged-Cloud/navigation).

## Requirements

| Dependency | Versions |
| --- | --- |
| PHP | 8.2, 8.3, 8.4 |
| Laravel | 11, 12, 13 (`illuminate/support`) |
| Livewire | 3, 4 (provides the Alpine bundle) |
| Alpine.js | 3 (bundled with Livewire, or load directly) |

## Install

```bash
composer require logged-cloud/select
```

`vendor:publish --tag=select-config` is optional; the components run on sensible defaults out of the box.

If you use Tailwind v4, add the package to your `@source` directives so its classes survive purging:

```css
@source "../../vendor/logged-cloud/select/resources/views";
```

## Item shape (every variant)

| Field | Type | Notes |
| --- | --- | --- |
| `key` | string | Stable identifier the form posts. |
| `title` | string | Bold first line in the row / card. |
| `subtitle` | string | Optional muted second line. |
| `svg` | string | Single SVG `path d` string drawn at 24×24, `stroke=currentColor`. |

Items can be arrays or objects with the matching attribute names. **Order is respected** — components render them in the order you pass them.

---

## `<x-select::searchable-alpine>`

![searchable-alpine](docs/images/searchable-alpine.png)

Searchable single-select with a dropdown popup. Items shipped to the page; search runs client-side.

```blade
<x-select::searchable-alpine
    name="prey_type"
    :items="$preyTypes"
    :selected="old('prey_type', $snake->prey_type)"
    placeholder="Pick prey..."
    label="Prey type"
/>
```

| Attribute | Default | Purpose |
| --- | --- | --- |
| `name` | (required) | Form input name. |
| `id` | `Str::camel($label)` or `$name` | Trigger element id. |
| `items` | `[]` | The option list. |
| `selected` | `null` | Pre-selected key. |
| `allow-empty` | `true` | Render the empty row in the menu. |
| `placeholder` | "Select an option" | Trigger text when nothing chosen. |
| `empty-label` | "not set" | Empty row label. |
| `search-label` | "Search..." | Search input placeholder. |
| `no-results-label` | "No options match that." | Empty-search copy. |
| `searchable` | `true` | Show the search input. |
| `icon-size` | `1.75rem` | Row icon tile size. |
| `label` / `labelled-by` | `null` | Accessible name. |
| `required` | `false` | Sets `aria-required` + hidden input required. |
| `disabled` | `false` | Sets `aria-disabled`. |

**Keyboard** — ↑↓ Home/End PageUp/PageDown Esc Tab; Enter / Space picks; typing filters. Opening sends focus to search; picking returns it to the trigger.

---

## `<x-select::multi-alpine>`

![multi-alpine](docs/images/multi-alpine.png)

Searchable multi-select with chips on the trigger, checkmarks in the menu, hidden inputs posted as `name[]`.

```blade
<x-select::multi-alpine
    name="prey_types"
    :items="$preyTypes"
    :selected="['mouse', 'rat']"
    label="Acceptable prey"
    :max="5"
/>
```

| Extra attribute | Default | Purpose |
| --- | --- | --- |
| `max` | `null` | Cap selections; extras refused with SR-announced "Maximum reached". |
| `chips-limit` | `3` | Above this count the trigger collapses to "N selected". |

`aria-multiselectable="true"` on both the trigger and the listbox. Enter / Space toggles without closing the menu. Each chip has a per-chip × button.

---

## `<x-select::radio-grid-alpine>`

![radio-grid-alpine](docs/images/radio-grid-alpine.png)

Compact card grid for single-pick where every option should be visible at once (event types, status switchers).

```blade
<x-select::radio-grid-alpine
    name="event_type"
    :items="$eventTypes"
    selected="feeding"
    label="Event type"
    min-width="6.5rem"
/>
```

WAI radio-group roving tabindex. Arrow keys move the selection; Home/End jump to ends; Space/Enter pick the focused card.

---

## `<x-select::radio-list-alpine>`

![radio-list-alpine](docs/images/radio-list-alpine.png)

Vertical list of radio rows with classic dot indicators. Better than the grid for longer choice lists where reading order matters.

```blade
<x-select::radio-list-alpine
    name="event_type"
    :items="$eventTypes"
    selected="weight"
    label="Event type"
/>
```

Same WAI radiogroup pattern as the grid; ↑↓ move within the list, Home/End jump.

---

## `<x-select::multi-grid-alpine>`

![multi-grid-alpine](docs/images/multi-grid-alpine.png)

Compact card grid for **multi-select**. Toggle-button semantics (`aria-pressed`), checkmark on each chosen card. Posts as `name[]`.

```blade
<x-select::multi-grid-alpine
    name="event_types"
    :items="$eventTypes"
    :selected="['feeding', 'shed']"
    label="Event types"
    :max="3"
/>
```

---

## `<x-select::multi-list-alpine>`

![multi-list-alpine](docs/images/multi-list-alpine.png)

Vertical list version of the above. Each row carries its own checkbox cell + icon + title + subtitle.

```blade
<x-select::multi-list-alpine
    name="event_types"
    :items="$eventTypes"
    :selected="['feeding', 'weight']"
    label="Event types"
/>
```

---

## `<x-select::inline-buttons-alpine>`

![inline-buttons-alpine](docs/images/inline-buttons-alpine.png)

Segmented control / pill row for compact single-pick toolbars. Horizontal layout; the active button gets a raised look.

```blade
<x-select::inline-buttons-alpine
    name="event_type"
    :items="$eventTypes"
    selected="handled"
    label="Event type"
/>
```

WAI radiogroup with ←/→ navigation.

---

## `<x-select::card-single-alpine>`

![card-single-alpine](docs/images/card-single-alpine.png)

Big visual cards for single-pick choices where each option deserves room for a description and a prominent icon. Useful for plan pickers, mode switchers, onboarding choices.

```blade
<x-select::card-single-alpine
    name="event_type"
    :items="$cards"
    selected="feeding"
    label="Event type"
    min-width="14rem"
    icon-size="2.5rem"
/>
```

Roving tabindex with four-direction arrow navigation (←↑→↓); Home / End jump to ends.

---

## `<x-select::card-multi-alpine>`

![card-multi-alpine](docs/images/card-multi-alpine.png)

Multi-select version of the cards. Toggle-button semantics, checkmark badge on each chosen card. Posts as `name[]`.

```blade
<x-select::card-multi-alpine
    name="event_types"
    :items="$cards"
    :selected="['feeding', 'shed']"
    label="Event types"
    :max="3"
/>
```

---

## `<x-select::tags-alpine>`

![tags-alpine](docs/images/tags-alpine.png)

Free-form tag editor. The trigger holds chips + an inline input · type to filter suggestions, **Enter** commits the highlighted suggestion or (with `allow-custom`) the typed string, **Backspace** on an empty input removes the last chip, ↑/↓ navigate suggestions. Posts as `name[]`.

```blade
<x-select::tags-alpine
    name="tags"
    :items="$suggestions"
    :selected="$existing"
    label="Tags"
    placeholder="Add a tag..."
    :max="10"
/>

{{-- Lock entries to the suggestion list (no custom strings): --}}
<x-select::tags-alpine
    name="role"
    :items="$roles"
    :allow-custom="false"
    label="Role"
/>
```

| Prop | Default | Notes |
| --- | --- | --- |
| `allow-custom` | `true` | Set `false` to disallow strings that aren't in `items` (Enter on a non-match becomes a no-op). |
| `max` | `null` | Cap the number of chips. Beyond the cap the screen-reader live region announces "Maximum number of tags reached." |
| `error` | `null` | Red ring + `role="alert"` message + `aria-invalid`. |

---

## Validation · `error="..."`

Every dropdown variant (`searchable-alpine`, `multi-alpine`, `tags-alpine`) takes an optional `error` prop. When non-empty the trigger picks up a `.lc-select--error` class (red ring), the message renders below it as a `role="alert"`, and the trigger's `aria-invalid` + `aria-describedby` point at the message. Plays nice with `@error('field')` in Blade.

```blade
<x-select::searchable-alpine
    name="prey"
    :items="$prey"
    label="Prey"
    :error="$errors->first('prey')"
/>
```

---

## Remote search · `:search-url` + `:debounce-ms`

Any of the three search-bearing variants can swap its in-memory filter for a debounced server-side search by passing `:search-url`. The component fetches `GET ${url}?q=…` and expects a JSON array of `{key, title, subtitle?, svg?}`. Initial `items` still seed the dropdown before the first request lands, so the menu opens with content even on cold mounts.

```blade
<x-select::searchable-alpine
    name="prey"
    :items="$preyTypes"            {{-- seeds the menu before the first fetch --}}
    :search-url="route('prey.search')"
    :debounce-ms="250"             {{-- defaults to 250ms when search-url is set --}}
    label="Prey"
    placeholder="Search prey..."
/>
```

A bare-bones search endpoint:

```php
Route::get('/prey/search', function (Request $r) {
    $q = strtolower((string) $r->query('q', ''));
    return PreyType::query()
        ->when($q !== '', fn ($q2) => $q2->whereRaw('LOWER(name) LIKE ?', ["%{$q}%"]))
        ->limit(25)
        ->get()
        ->map(fn ($p) => ['key' => $p->key, 'title' => $p->name, 'subtitle' => $p->subtitle, 'svg' => $p->icon_svg]);
})->name('prey.search');
```

The client still applies v2.5 token-aware ranking + match highlighting over the server's response, so prefix matches still float to the top and matched substrings still get `<mark>` wrapping. An in-flight `AbortController` cancels stale requests when the user keeps typing, and the search input picks up `aria-busy="true"` while a request is open.

---

## Mobile bottom-sheet

On viewports ≤ 640px the dropdown variants (`searchable-alpine`, `multi-alpine`, `tags-alpine`) flip from an absolute-positioned menu into a fixed slide-up sheet with a dismiss-on-tap backdrop, a drag-handle visual, and `env(safe-area-inset-bottom)` padding so iOS home-bar devices clear the indicator. Nothing to wire up · purely a CSS `@media` block.

---

## Accessibility (shared)

- Every variant uses CSS custom properties + system colours so it survives **`forced-colors`** mode (Windows High Contrast → `CanvasText` / `Highlight` / `HighlightText`).
- All transitions drop under **`prefers-reduced-motion: reduce`**.
- Focus rings are 2px outline + 2px offset on the host theme's accent colour, meeting WCAG 2.4.7 non-text contrast.
- Single-pick variants implement the WAI **roving tabindex** radio-group pattern; multi-pick variants use **toggle-button** (`aria-pressed`) semantics.

## Progressive enhancement · JavaScript-off fallback

Every variant ships a hidden native `<select>` (or `<select multiple>`) inside a `<noscript>`-gated CSS block. With JS disabled the native control is shown and submitted; a small red **JS off** pill appears next to the label so the user understands they're on the basic experience. With JS enabled, `x-init` clears the native's `name` attribute so only the Alpine widget's value posts.

## Label-derived ids

When no explicit `id` is passed, the trigger / group id derives from `label` as camelCase: `label="Prey type"` → `id="preyType"`. Falls back to the field name if no label is set, so existing usages keep working.

## Theming

Every colour is a CSS custom property. Defaults fall back to the **fish.logged.cloud** palette (`#C7593A` accent / `#25272A` bg / `#F0EDE5` ink) so an app that sets nothing still looks like a logged.cloud family app. Override any of these in your app's CSS:

```css
.lc-select, .lc-radio-list, .lc-radio-grid,
.lc-multi-grid, .lc-multi-list,
.lc-inline-buttons, .lc-cards {
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

By default each variable also falls back to a host `--surface`, `--accent` etc., so matching those alone is enough to retheme without touching this stylesheet.

## Regenerating the screenshots

The images above are real renders captured by `bin/screenshots.sh`, which drives snake-logged's Dusk harness from the host:

```bash
cd /var/www/logged-cloud-select
bin/screenshots.sh          # full run · dusk capture + copy
bin/screenshots.sh --skip   # reuse existing dusk output, just copy
```

The script syncs the local package's `resources/` and `config/` into snake-logged's vendor (handy for unpushed changes), clears the Blade view cache, runs `php artisan dusk --filter=SelectVariantScreenshotsTest`, then copies the resulting PNGs into `docs/images/`.

## Tests

`vendor/bin/pest` runs **42 structural tests / 211 assertions** covering ARIA wiring, keyboard handler sets, the hidden-input conventions, focus-return helpers, the `<noscript>` fallback, label-derived ids, the default fish-orange palette, the 2px card border, and CSS hooks for every variant. Behavioural coverage lives in the consumer app's browser-test suite.

## Livewire

The components live inside any page that loads `@livewireScripts`, which is where Alpine comes from. Each variant registers its Alpine data factory once globally via an `@once`-guarded `<script>` in its template — multiple instances on a page share one bundle.
