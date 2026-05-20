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
    .lc-select__trigger {
        border: 1px solid CanvasText;
    }
    .lc-select__trigger:focus-visible {
        outline: 2px solid Highlight;
    }
    .lc-select__item.is-active {
        background: Highlight;
        color: HighlightText;
    }
}
</style>
