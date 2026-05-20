{{-- CSS for <x-select::box>. Reference via @include('select::styles') in the
     host app's layout (once) so all instances share one stylesheet. The
     component itself emits its own @once-guarded <style> tag so this file
     is optional — only include it if you want one central place to override. --}}
@php
    $theme = config('select.theme');
@endphp
<style>
/* CSS custom properties belong on every variant wrapper · without this
   the cards / lists / inline-buttons variants would fall through to
   undefined --lc-accent and lose their selected-state colour. */
.lc-select,
.lc-radio-list,
.lc-radio-grid,
.lc-multi-grid,
.lc-multi-list,
.lc-inline-buttons,
.lc-cards {
    --lc-bg: {!! $theme['bg'] !!};
    --lc-menu-bg: {!! $theme['menu_bg'] !!};
    --lc-border: {!! $theme['border'] !!};
    --lc-ink: {!! $theme['ink'] !!};
    --lc-ink-dim: {!! $theme['ink_dim'] !!};
    --lc-accent: {!! $theme['accent'] !!};
    --lc-icon-bg: {!! $theme['icon_bg'] !!};
    --lc-hover-bg: {!! $theme['hover_bg'] !!};
    --lc-radius: {!! $theme['radius'] !!};
    position: relative;
}
.lc-select [x-cloak] { display: none !important; }
.lc-select__trigger {
    width: 100%;
    background: var(--lc-bg);
    color: var(--lc-ink);
    border: 1px solid var(--lc-border);
    border-radius: var(--lc-radius);
    padding: .55rem .75rem;
    font: inherit;
    font-size: 0.95rem;
    text-align: left;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    box-sizing: border-box;
    -webkit-tap-highlight-color: transparent;
}
.lc-select__trigger:focus { outline: none; }
.lc-select__trigger.is-open,
.lc-select__trigger:focus-visible {
    /* A 2px outline ring (not a 0-outline) so keyboard users get a clear
       focus indicator that meets WCAG 2.4.7 non-text contrast. */
    outline: 2px solid var(--lc-accent);
    outline-offset: 2px;
    border-color: var(--lc-accent);
}
.lc-select__trigger[aria-disabled="true"] {
    opacity: .6;
    cursor: not-allowed;
}
.lc-select__chosen { display: flex; align-items: center; gap: .65rem; min-width: 0; }
.lc-select__placeholder { color: var(--lc-ink-dim); }
.lc-select__placeholder--filled { color: var(--lc-ink); }
.lc-select__chevron { opacity: .6; }
.lc-select__trigger-tail {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    flex: none;
}
.lc-select__clear {
    background: transparent;
    border: 0;
    padding: .25rem;
    border-radius: 999px;
    cursor: pointer;
    color: var(--lc-ink-dim);
    display: inline-grid;
    place-items: center;
}
.lc-select__clear:hover {
    background: color-mix(in srgb, var(--lc-ink) 12%, transparent);
    color: var(--lc-ink);
}
.lc-select__clear:focus-visible {
    outline: 2px solid var(--lc-accent);
    outline-offset: 1px;
}

/* Render-cap footer · shown when filtered.length exceeds renderLimit so
   the user knows further results exist and can refine to access them. */
.lc-select__more-row {
    list-style: none;
    padding: .5rem .85rem;
    margin: 0;
    color: var(--lc-ink-dim);
    background: color-mix(in srgb, var(--lc-ink) 4%, transparent);
    font-size: .8rem;
    font-style: italic;
    text-align: center;
}
@media (forced-colors: active) {
    .lc-select__more-row { color: CanvasText; background: Canvas; border-top: 1px solid CanvasText; }
}

/* In-menu error row · surfaces when the remote search hook reports an
   HTTP / network failure. Sits alongside the no-results row in the
   listbox so the user notices it where they're already looking. */
.lc-select__error-row {
    list-style: none;
    padding: .65rem .85rem;
    margin: 0;
    color: #ef4444;
    background: color-mix(in srgb, #ef4444 12%, transparent);
    font-size: .85rem;
    font-weight: 500;
}
@media (forced-colors: active) {
    .lc-select__error-row { color: Mark; background: Canvas; }
}

/* Validation error state · red ring on trigger + message below. */
.lc-select--error .lc-select__trigger {
    border-color: #ef4444;
    box-shadow: 0 0 0 1px #ef4444;
}
.lc-select__error {
    margin: .35rem 0 0;
    padding: 0;
    color: #ef4444;
    font-size: 0.8rem;
    font-weight: 500;
}
@media (forced-colors: active) {
    .lc-select--error .lc-select__trigger { border-color: Mark; box-shadow: 0 0 0 1px Mark; }
}

/* tags-alpine · combobox where the trigger holds chips + an inline input. */
.lc-select__trigger--tags {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .35rem .4rem;
    cursor: text;
}
.lc-select__trigger--tags.is-open {
    border-color: var(--lc-accent);
    box-shadow: 0 0 0 1px var(--lc-accent);
}
.lc-select__tag-input {
    flex: 1 1 6rem;
    min-width: 6rem;
    background: transparent;
    border: 0;
    color: var(--lc-ink);
    font: inherit;
    padding: .15rem 0;
    outline: none;
}
.lc-select__tag-input::placeholder { color: var(--lc-ink-dim); }
.lc-select__menu--tags { max-height: 18rem; }

/* Search row · wraps the search input + spinner so the spinner can
   live inside the visual frame of the input without affecting its
   focus/border styling. */
.lc-select__search-row {
    position: relative;
    display: flex;
    align-items: center;
}
.lc-select__search-row .lc-select__search { width: 100%; }
.lc-select__spinner {
    position: absolute;
    right: .75rem;
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    border: 2px solid color-mix(in srgb, var(--lc-ink) 25%, transparent);
    border-top-color: var(--lc-accent);
    animation: lc-select-spin 0.7s linear infinite;
}
@keyframes lc-select-spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) {
    .lc-select__spinner { animation-duration: 2.4s; }
}
@media (forced-colors: active) {
    .lc-select__spinner {
        border-color: CanvasText;
        border-top-color: Highlight;
    }
}

/* Search-match highlight · the rank/filter helper wraps matched token
   ranges in <mark class="lc-select__match"> so the eye lands on the
   match. Uses color-mix off --lc-accent so it picks up the host theme. */
.lc-select__match {
    background: color-mix(in srgb, var(--lc-accent) 32%, transparent);
    color: inherit;
    border-radius: .2em;
    padding: 0 .12em;
    font-weight: 600;
}
.is-selected .lc-select__match,
.is-active .lc-select__match {
    background: color-mix(in srgb, var(--lc-accent) 48%, transparent);
}
@media (forced-colors: active) {
    .lc-select__match {
        background: Mark;
        color: MarkText;
        forced-color-adjust: none;
    }
}

.lc-select__menu {
    position: absolute;
    top: calc(100% + 4px);
    left: 0; right: 0;
    z-index: 30;
    background: var(--lc-menu-bg);
    border: 1px solid var(--lc-border);
    border-radius: calc(var(--lc-radius) + .1rem);
    box-shadow: 0 12px 24px rgba(0,0,0,.45);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    max-height: 22rem;
}

/* Mobile · convert the dropdown into a bottom sheet on phones. The
   absolute-positioned 22rem menu collides with the iOS keyboard and
   loses context off-screen; a fixed slide-up sheet feels native. */
.lc-select__backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .55);
    z-index: 40;
}
.lc-select__sheet-handle {
    display: none;
    width: 2.5rem;
    height: .25rem;
    background: var(--lc-border);
    border-radius: 999px;
    margin: .65rem auto .15rem;
    flex: none;
}
@media (max-width: 640px) {
    .lc-select__menu {
        position: fixed;
        top: auto;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 50;
        max-height: 75vh;
        border-radius: calc(var(--lc-radius) + .4rem) calc(var(--lc-radius) + .4rem) 0 0;
        border-bottom: 0;
        padding-bottom: env(safe-area-inset-bottom, 0);
        box-shadow: 0 -8px 24px rgba(0,0,0,.55);
    }
    .lc-select__sheet-handle { display: block; }
    .lc-select__backdrop { display: block; }
    /* Search input feels nicer with a bit more breathing room when it
       sits at the top of a sheet. */
    .lc-select__search {
        padding: .85rem 1rem;
        font-size: 1rem;
    }
    .lc-select__item {
        padding: .75rem .85rem;
        font-size: 1rem;
    }
}
.lc-select__search {
    width: 100%;
    background: var(--lc-bg);
    color: var(--lc-ink);
    border: 0;
    border-bottom: 1px solid var(--lc-border);
    border-radius: 0;
    padding: .65rem .85rem;
    font: inherit;
    font-size: 0.95rem;
    box-sizing: border-box;
}
.lc-select__search:focus { outline: 0; }
.lc-select__search:focus-visible {
    /* Keep the bottom border highlight as the visible focus indicator;
       the menu container itself shows clear chrome already, and a ring
       around the search input would clash with the menu border. */
    border-bottom-color: var(--lc-accent);
    box-shadow: inset 0 -2px 0 0 var(--lc-accent);
}
.lc-select__list {
    list-style: none;
    margin: 0;
    padding: .25rem;
    overflow-y: auto;
    /* Themed scrollbar · Firefox first, then WebKit. The track stays
       transparent so the menu's --lc-menu-bg shows through; the thumb
       picks up the host theme's --lc-border with a hover state on
       --lc-ink-dim. */
    scrollbar-width: thin;
    scrollbar-color: var(--lc-border) transparent;
}
.lc-select__list::-webkit-scrollbar { width: 10px; }
.lc-select__list::-webkit-scrollbar-track { background: transparent; }
.lc-select__list::-webkit-scrollbar-thumb {
    background: var(--lc-border);
    border-radius: 999px;
    /* 2px transparent border around the thumb gives the appearance of
       a rounded bar floating inside the track rather than filling it. */
    border: 2px solid transparent;
    background-clip: padding-box;
}
.lc-select__list::-webkit-scrollbar-thumb:hover {
    background: var(--lc-ink-dim);
    background-clip: padding-box;
}
.lc-select__item {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .55rem .65rem;
    border-radius: calc(var(--lc-radius) - .05rem);
    cursor: pointer;
    color: var(--lc-ink);
}
.lc-select__item.is-active {
    background: var(--lc-hover-bg);
    /* Inset ring instead of an outline so the highlight reads as
       cursor-position to assistive tech and meets non-text contrast. */
    box-shadow: inset 0 0 0 2px var(--lc-accent);
}
.lc-select__item.is-selected { color: var(--lc-accent); }
.lc-select__item[aria-selected="true"] .lc-select__title::after {
    /* A subtle checkmark next to the chosen row, in addition to the colour
       cue, so the selected state survives forced-colours / monochrome users. */
    content: ' ✓';
    color: var(--lc-accent);
    font-weight: 700;
}
.lc-select__body { display: flex; flex-direction: column; min-width: 0; }
.lc-select__title { font-weight: 500; }
.lc-select__subtitle { font-size: 0.78rem; color: var(--lc-ink-dim); }
.lc-select__no-results {
    padding: .8rem;
    text-align: center;
    color: var(--lc-ink-dim);
    font-size: 0.85rem;
}
.lc-select__icon {
    width: var(--lc-icon-size, 1.75rem);
    height: var(--lc-icon-size, 1.75rem);
    border-radius: calc(var(--lc-radius) - .1rem);
    background: var(--lc-icon-bg);
    flex: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--lc-accent);
}
.lc-select__icon svg { width: 70%; height: 70%; display: block; }
.lc-select__icon--empty { opacity: .5; }

/* ── multi-alpine specifics ─────────────────────────────────────────── */

.lc-select__trigger--multi { min-height: 2.5rem; align-items: flex-start; padding: .35rem .55rem; }
.lc-select__chips { display: flex; flex-wrap: wrap; gap: .35rem; min-width: 0; }
.lc-select__chip {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .15rem .25rem .15rem .4rem;
    background: var(--lc-hover-bg);
    border-radius: calc(var(--lc-radius) - .15rem);
    font-size: 0.85rem;
}
.lc-select__chip-icon {
    display: inline-grid;
    place-items: center;
    color: var(--lc-accent);
}
.lc-select__chip-remove {
    background: transparent;
    border: 0;
    padding: .1rem;
    border-radius: 999px;
    cursor: pointer;
    color: var(--lc-ink-dim);
    display: inline-flex;
}
.lc-select__chip-remove:hover { color: var(--lc-ink); background: color-mix(in srgb, var(--lc-ink) 12%, transparent); }
.lc-select__chip-remove:focus-visible { outline: 2px solid var(--lc-accent); outline-offset: 1px; }
.lc-select__chosen-summary { display: inline-flex; gap: .3rem; align-items: baseline; font-weight: 500; }

.lc-select__item--multi { padding-left: .4rem; }
.lc-select__check {
    width: 1.1rem; height: 1.1rem;
    border-radius: .25rem;
    border: 1.5px solid var(--lc-border);
    display: inline-grid;
    place-items: center;
    color: var(--lc-accent);
    flex: none;
}
.lc-select__item--multi.is-selected .lc-select__check {
    border-color: var(--lc-accent);
    background: color-mix(in srgb, var(--lc-accent) 18%, transparent);
}
.lc-select__item--multi[aria-selected="true"] .lc-select__title::after { content: ''; }

/* ── radio-grid-alpine specifics ────────────────────────────────────── */

.lc-radio-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(var(--lc-cell-min, 6.5rem), 1fr));
    gap: .5rem;
}
.lc-radio-grid__item {
    background: var(--lc-bg);
    border: 2px solid var(--lc-border);
    border-radius: var(--lc-radius);
    padding: .65rem .5rem;
    color: var(--lc-ink);
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    font: inherit;
    transition: border-color .12s, background .12s;
}
.lc-radio-grid__item:hover { border-color: var(--lc-ink-dim); }
.lc-radio-grid__item:focus-visible {
    outline: 2px solid var(--lc-accent);
    outline-offset: 2px;
    border-color: var(--lc-accent);
}
.lc-radio-grid__item.is-selected {
    border-color: var(--lc-accent);
    background: color-mix(in srgb, var(--lc-accent) 12%, var(--lc-bg));
    color: var(--lc-accent);
}
.lc-radio-grid__item[aria-disabled="true"] { opacity: .55; cursor: not-allowed; }
.lc-radio-grid__icon {
    width: var(--lc-icon-size, 1.5rem);
    height: var(--lc-icon-size, 1.5rem);
    display: grid;
    place-items: center;
    margin-bottom: .35rem;
}
.lc-radio-grid__icon svg { width: 100%; height: 100%; }
.lc-radio-grid__title { font-size: 0.85rem; font-weight: 500; }
.lc-radio-grid__subtitle { font-size: 0.72rem; color: var(--lc-ink-dim); margin-top: .15rem; }

/* ── radio-list-alpine specifics ────────────────────────────────────── */

.lc-radio-list { display: flex; flex-direction: column; gap: .35rem; }
.lc-radio-list__item {
    background: var(--lc-bg);
    border: 2px solid var(--lc-border);
    border-radius: var(--lc-radius);
    padding: .55rem .75rem;
    color: var(--lc-ink);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: .75rem;
    font: inherit;
    text-align: left;
    width: 100%;
}
.lc-radio-list__item:hover { border-color: var(--lc-ink-dim); }
.lc-radio-list__item:focus-visible {
    outline: 2px solid var(--lc-accent);
    outline-offset: 2px;
    border-color: var(--lc-accent);
}
.lc-radio-list__item.is-selected {
    border-color: var(--lc-accent);
    background: color-mix(in srgb, var(--lc-accent) 8%, var(--lc-bg));
}
.lc-radio-list__dot {
    width: 1.05rem; height: 1.05rem;
    border-radius: 999px;
    border: 1.5px solid var(--lc-border);
    flex: none;
    display: grid;
    place-items: center;
}
.lc-radio-list__item.is-selected .lc-radio-list__dot { border-color: var(--lc-accent); }
.lc-radio-list__dot-inner {
    width: 0.55rem; height: 0.55rem;
    border-radius: 999px;
    background: var(--lc-accent);
}
.lc-radio-list__icon {
    width: var(--lc-icon-size, 1.5rem);
    height: var(--lc-icon-size, 1.5rem);
    border-radius: calc(var(--lc-radius) - .15rem);
    background: var(--lc-icon-bg);
    display: grid;
    place-items: center;
    color: var(--lc-accent);
    flex: none;
}
.lc-radio-list__icon svg { width: 70%; height: 70%; }
.lc-radio-list__body { display: flex; flex-direction: column; min-width: 0; }
.lc-radio-list__title { font-weight: 500; }
.lc-radio-list__subtitle { font-size: 0.78rem; color: var(--lc-ink-dim); }

/* ── multi-grid-alpine + multi-list-alpine ──────────────────────────── */

.lc-multi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(var(--lc-cell-min, 6.5rem), 1fr));
    gap: .5rem;
}
.lc-multi-grid__item,
.lc-multi-list__item {
    background: var(--lc-bg);
    border: 2px solid var(--lc-border);
    border-radius: var(--lc-radius);
    color: var(--lc-ink);
    cursor: pointer;
    font: inherit;
    position: relative;
}
.lc-multi-grid__item {
    padding: .65rem .5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}
.lc-multi-list { display: flex; flex-direction: column; gap: .35rem; }
.lc-multi-list__item {
    padding: .55rem .75rem;
    display: flex;
    align-items: center;
    gap: .75rem;
    text-align: left;
    width: 100%;
}
.lc-multi-grid__item:hover,
.lc-multi-list__item:hover { border-color: var(--lc-ink-dim); }
.lc-multi-grid__item:focus-visible,
.lc-multi-list__item:focus-visible {
    outline: 2px solid var(--lc-accent);
    outline-offset: 2px;
    border-color: var(--lc-accent);
}
.lc-multi-grid__item.is-selected,
.lc-multi-list__item.is-selected {
    border-color: var(--lc-accent);
    background: color-mix(in srgb, var(--lc-accent) 12%, var(--lc-bg));
    color: var(--lc-accent);
}
.lc-multi-grid__check,
.lc-multi-list__check {
    width: 1.1rem; height: 1.1rem;
    border-radius: .25rem;
    border: 1.5px solid var(--lc-border);
    display: grid;
    place-items: center;
    color: var(--lc-accent);
    flex: none;
}
.lc-multi-grid__check {
    position: absolute;
    top: .4rem;
    right: .4rem;
}
.lc-multi-grid__item.is-selected .lc-multi-grid__check,
.lc-multi-list__item.is-selected .lc-multi-list__check {
    background: color-mix(in srgb, var(--lc-accent) 18%, transparent);
    border-color: var(--lc-accent);
}
.lc-multi-grid__icon,
.lc-multi-list__icon {
    width: var(--lc-icon-size, 1.5rem);
    height: var(--lc-icon-size, 1.5rem);
    display: grid;
    place-items: center;
    color: var(--lc-accent);
    flex: none;
}
.lc-multi-grid__icon { margin-bottom: .35rem; }
.lc-multi-grid__icon svg, .lc-multi-list__icon svg { width: 100%; height: 100%; }
.lc-multi-grid__title { font-size: 0.85rem; font-weight: 500; }
.lc-multi-grid__subtitle { font-size: 0.72rem; color: var(--lc-ink-dim); margin-top: .15rem; }
.lc-multi-list__body { display: flex; flex-direction: column; min-width: 0; }
.lc-multi-list__title { font-weight: 500; }
.lc-multi-list__subtitle { font-size: 0.78rem; color: var(--lc-ink-dim); }

/* ── inline-buttons-alpine (segmented control) ──────────────────────── */

.lc-inline-buttons {
    display: inline-flex;
    flex-wrap: wrap;
    gap: .25rem;
    padding: .2rem;
    background: var(--lc-icon-bg);
    border: 1px solid var(--lc-border);
    border-radius: 999px;
}
.lc-inline-buttons__item {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .35rem .75rem;
    border-radius: 999px;
    border: 0;
    background: transparent;
    color: var(--lc-ink-dim);
    cursor: pointer;
    font: inherit;
    font-size: 0.85rem;
}
.lc-inline-buttons__item:hover { color: var(--lc-ink); }
.lc-inline-buttons__item:focus-visible {
    outline: 2px solid var(--lc-accent);
    outline-offset: 2px;
}
.lc-inline-buttons__item.is-selected {
    background: var(--lc-bg);
    color: var(--lc-accent);
    box-shadow: 0 1px 2px rgba(0,0,0,.2);
}
.lc-inline-buttons__icon { display: inline-grid; place-items: center; color: var(--lc-accent); }

/* ── card-single + card-multi (big visual cards) ────────────────────── */

.lc-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(var(--lc-cell-min, 12rem), 1fr));
    gap: .75rem;
}
.lc-cards__item {
    background: var(--lc-bg);
    border: 2px solid var(--lc-border);
    border-radius: var(--lc-radius);
    padding: 1.25rem 1rem;
    color: var(--lc-ink);
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: .5rem;
    font: inherit;
    text-align: left;
    position: relative;
    transition: border-color .12s, background .12s, transform .12s;
}
.lc-cards__item:hover {
    border-color: var(--lc-ink-dim);
    transform: translateY(-1px);
}
.lc-cards__item:focus-visible {
    outline: 2px solid var(--lc-accent);
    outline-offset: 2px;
    border-color: var(--lc-accent);
}
.lc-cards__item.is-selected {
    border-color: var(--lc-accent);
    background: color-mix(in srgb, var(--lc-accent) 8%, var(--lc-bg));
    box-shadow: 0 0 0 1px var(--lc-accent);
}
.lc-cards__check {
    position: absolute;
    top: .65rem;
    right: .65rem;
    width: 1.4rem; height: 1.4rem;
    border-radius: 999px;
    background: var(--lc-accent);
    color: var(--lc-on-accent, white);
    display: grid;
    place-items: center;
}
.lc-cards__icon {
    width: var(--lc-icon-size, 2.5rem);
    height: var(--lc-icon-size, 2.5rem);
    border-radius: calc(var(--lc-radius) + .1rem);
    background: var(--lc-icon-bg);
    display: grid;
    place-items: center;
    color: var(--lc-accent);
    flex: none;
}
.lc-cards__icon svg { width: 65%; height: 65%; }
.lc-cards__title { font-size: 1rem; font-weight: 600; }
.lc-cards__subtitle {
    font-size: 0.85rem;
    color: var(--lc-ink-dim);
    line-height: 1.35;
}

/* ── progressive-enhancement fallback (no-JS) ────────────────────── */

/* Native <select> fallback · invisible until <noscript> CSS flips it on. */
.lc-select__fallback {
    background: var(--lc-bg);
    color: var(--lc-ink);
    border: 1px solid var(--lc-border);
    border-radius: var(--lc-radius);
    padding: .55rem .75rem;
    font: inherit;
    font-size: 0.95rem;
    width: 100%;
    box-sizing: border-box;
    margin-bottom: .5rem;
}

/* JS-off indicator pill · only appears when scripting is disabled. */
.lc-no-js {
    display: none;
    align-items: center;
    gap: .25rem;
    margin-left: .5rem;
    padding: 0 .35rem;
    border-radius: 999px;
    background: color-mix(in srgb, #dc2626 30%, transparent);
    color: #fca5a5;
    font-size: 0.72rem;
    font-weight: 600;
    vertical-align: middle;
}
.lc-no-js > [aria-hidden] { color: #f87171; }
@media (forced-colors: active) {
    .lc-no-js { background: Highlight; color: HighlightText; }
}

/* Visually-hidden region for aria-live announcements. Same rules as
   Tailwind's sr-only · invisible to sighted users, picked up by screen
   readers. */
.lc-select__live {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* Respect users who ask for reduced motion · drop the menu transition. */
@media (prefers-reduced-motion: reduce) {
    .lc-select__menu, .lc-select__item, .lc-select__trigger {
        transition: none !important;
    }
}

/* High-contrast / forced-colours mode (Windows High Contrast) · let the
   browser repaint borders and focus rings, but keep the structure. */
@media (forced-colors: active) {
    .lc-select__trigger,
    .lc-radio-list__item,
    .lc-radio-grid__item,
    .lc-multi-grid__item,
    .lc-multi-list__item,
    .lc-inline-buttons__item,
    .lc-cards__item {
        border: 1px solid CanvasText;
    }
    .lc-select__trigger:focus-visible,
    .lc-radio-list__item:focus-visible,
    .lc-radio-grid__item:focus-visible,
    .lc-multi-grid__item:focus-visible,
    .lc-multi-list__item:focus-visible,
    .lc-inline-buttons__item:focus-visible,
    .lc-cards__item:focus-visible {
        outline: 2px solid Highlight;
    }
    .lc-select__item.is-active,
    .lc-radio-list__item.is-selected,
    .lc-radio-grid__item.is-selected,
    .lc-multi-grid__item.is-selected,
    .lc-multi-list__item.is-selected,
    .lc-inline-buttons__item.is-selected,
    .lc-cards__item.is-selected {
        background: Highlight;
        color: HighlightText;
    }
}
</style>
