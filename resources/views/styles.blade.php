{{-- CSS for <x-select::box>. Reference via @include('select::styles') in the
     host app's layout (once) so all instances share one stylesheet. The
     component itself emits its own @once-guarded <style> tag so this file
     is optional — only include it if you want one central place to override. --}}
@php
    $theme = config('select.theme');
@endphp
<style>
.lc-select {
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
}
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
.lc-select__list { list-style: none; margin: 0; padding: .25rem; overflow-y: auto; }
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
    border: 1px solid var(--lc-border);
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
    border: 1px solid var(--lc-border);
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
    border: 1px solid var(--lc-border);
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
    border: 1px solid var(--lc-border);
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
