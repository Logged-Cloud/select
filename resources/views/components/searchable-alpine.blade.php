@props([
    'name',
    'id' => null,
    'items' => [],
    'selected' => null,
    'allowEmpty' => null,
    'placeholder' => null,
    'emptyLabel' => null,
    'searchLabel' => null,
    'noResultsLabel' => null,
    'searchable' => null,
    'iconSize' => null,
    'label' => null,
    'labelledBy' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
    'searchUrl' => null,
    'debounceMs' => null,
    'renderLimit' => 50,
])
@php
    // ID derives from label when present (camelCased) so the markup gets
    // human-readable ids: label="Prey type" → id="preyType". Falls back
    // to the field name. An explicit `id` prop always wins.
    $triggerId = $id ?? ($label ? \Illuminate\Support\Str::camel(\Illuminate\Support\Str::slug($label, '_')) : $name);
    $listboxId = $triggerId.'-listbox';
    $searchId = $triggerId.'-search';
    $liveId = $triggerId.'-live';
    $fallbackId = $triggerId.'-fallback';
    $errorId = $triggerId.'-error';
    $allowEmpty = $allowEmpty ?? config('select.behavior.allow_empty', true);
    $searchable = $searchable ?? config('select.behavior.searchable', true);
    $iconSize = $iconSize ?? config('select.behavior.icon_size', '1.75rem');
    $placeholder = $placeholder ?? config('select.copy.placeholder', 'Select an option');
    $emptyLabel = $emptyLabel ?? config('select.copy.empty_label', 'not set');
    $searchLabel = $searchLabel ?? config('select.copy.search_label', 'Search...');
    $noResultsLabel = $noResultsLabel ?? config('select.copy.no_results_label', 'No options match that.');
    $noJsLabel = config('select.copy.no_js_indicator', 'JS off');
    $noJsCopy = config('select.copy.no_js_warning', 'JavaScript is needed for the rich picker.');

    // Caller may pass arrays or objects with key/title/subtitle/svg;
    // we coerce to a plain shape so the Alpine data is predictable.
    $normalised = collect($items)->map(function ($item) {
        if (is_array($item)) {
            $get = fn ($k) => $item[$k] ?? null;
        } else {
            $get = fn ($k) => $item->{$k} ?? null;
        }

        return [
            'key' => (string) $get('key'),
            'title' => (string) $get('title'),
            'subtitle' => (string) ($get('subtitle') ?? ''),
            'svg' => (string) ($get('svg') ?? ''),
        ];
    })->values()->all();

    $config = [
        'items' => $normalised,
        'selected' => $selected,
        'allowEmpty' => (bool) $allowEmpty,
        'searchable' => (bool) $searchable,
        'emptyLabel' => $emptyLabel,
        'listboxId' => $listboxId,
        'searchId' => $searchId,
        'liveId' => $liveId,
        'triggerId' => $triggerId,
        'searchUrl' => $searchUrl,
        'debounceMs' => is_numeric($debounceMs) ? (int) $debounceMs : null,
        'renderLimit' => is_numeric($renderLimit) ? (int) $renderLimit : 50,
        'a11y' => [
            'options_available' => 'options available',
            'no_options' => 'No options.',
            'selected' => 'Selected',
            'cleared' => 'Selection cleared',
            'loading' => 'Searching…',
            'search_failed' => 'Search failed. Try again.',
        ],
    ];
@endphp
<div x-data="loggedCloudSelect({{ \Illuminate\Support\Js::from($config) }})"
     x-init="$nextTick(() => { syncSelectedFromValue(); if ($refs.fallback) { $refs.fallback.name = ''; } })"
     class="lc-select {{ $error ? 'lc-select--error' : '' }}"
     style="--lc-icon-size: {{ $iconSize }};"
     @click.outside="close()"
     @keydown.escape.window="if (open) { close(); }">

    @include('select::partials.fallback', [
        'name' => $name,
        'items' => $normalised,
        'selected' => $selected,
        'multi' => false,
        'fallbackId' => $fallbackId,
        'required' => $required,
        'noJsLabel' => $noJsLabel,
        'noJsCopy' => $noJsCopy,
    ])

    <input type="hidden" name="{{ $name }}" :value="value" x-ref="hidden"
           @if ($required) required @endif>

    {{-- div, not button, because the × clear lives inside · nesting buttons
         is invalid HTML and gets auto-closed. role=combobox + tabindex
         preserves the keyboard semantics. --}}
    <div id="{{ $triggerId }}"
            x-ref="trigger"
            tabindex="{{ $disabled ? '-1' : '0' }}"
            class="lc-select__trigger"
            :class="{ 'is-open': open }"
            role="combobox"
            aria-haspopup="listbox"
            aria-autocomplete="{{ $searchable ? 'list' : 'none' }}"
            aria-controls="{{ $listboxId }}"
            :aria-expanded="open"
            :aria-activedescendant="open ? activeOptionId() : null"
            @if ($label) aria-label="{{ $label }}"
            @elseif ($labelledBy) aria-labelledby="{{ $labelledBy }}"
            @else aria-label="{{ $placeholder }}" @endif
            @if ($required) aria-required="true" @endif
            @if ($disabled) aria-disabled="true" @endif
            @if ($error) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
            @click="toggle()"
            @keydown.arrow-down.prevent="open ? move(1) : openMenu(0)"
            @keydown.arrow-up.prevent="open ? move(-1) : openMenu(visible.length - 1)"
            @keydown.home.prevent="if (open) { cursor = 0; }"
            @keydown.end.prevent="if (open) { cursor = visible.length - 1; }"
            @keydown.enter.prevent="open ? pickAt(cursor) : openMenu(currentIndex())"
            @keydown.space.prevent="open ? pickAt(cursor) : openMenu(currentIndex())"
            @keydown.tab="if (open) { close(); }">
        <span class="lc-select__chosen">
            <template x-if="selected">
                <span class="lc-select__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none"
                         stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path :d="selected.svg"></path>
                    </svg>
                </span>
            </template>
            <span x-text="selected ? selected.title : @js($placeholder)"
                  :class="selected ? 'lc-select__placeholder--filled' : 'lc-select__placeholder'"></span>
        </span>
        <span class="lc-select__trigger-tail">
            {{-- Clear button surfaces only when something is selected and
                 allow-empty is honoured · sits before the chevron. Stops
                 propagation so the trigger does not toggle the menu. --}}
            @if ($allowEmpty)
                <button type="button"
                        x-show="selected"
                        x-cloak
                        class="lc-select__clear"
                        aria-label="Clear selection"
                        @click.stop="clear()">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none"
                         stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M6 6l12 12M6 18L18 6"></path>
                    </svg>
                </button>
            @endif
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round"
                 class="lc-select__chevron" aria-hidden="true">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </span>
    </div>

    {{-- Mobile-only backdrop · only the @media (max-width:640px) block makes
         this visible. Click-through to close uses the same @click.outside
         on the wrapper since the backdrop sits outside the .lc-select element. --}}
    <div x-show="open" x-cloak class="lc-select__backdrop" @click="close()"></div>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms class="lc-select__menu">
        <div class="lc-select__sheet-handle" aria-hidden="true"></div>
        @if ($searchable)
            <div class="lc-select__search-row">
                <input type="text"
                       id="{{ $searchId }}"
                       x-ref="search" x-model="query"
                       class="lc-select__search"
                       placeholder="{{ $searchLabel }}"
                       role="searchbox"
                       aria-controls="{{ $listboxId }}"
                       aria-label="{{ $searchLabel }}"
                       :aria-activedescendant="activeOptionId()"
                       :aria-busy="loading"
                       autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                       @keydown.arrow-down.prevent="move(1)"
                       @keydown.arrow-up.prevent="move(-1)"
                       @keydown.home.prevent="cursor = 0"
                       @keydown.end.prevent="cursor = visible.length - 1"
                       @keydown.page-down.prevent="cursor = Math.min(cursor + 5, visible.length - 1)"
                       @keydown.page-up.prevent="cursor = Math.max(cursor - 5, 0)"
                       @keydown.enter.prevent="pickAt(cursor)"
                       @keydown.tab="close()">
                <span class="lc-select__spinner" x-show="loading" x-cloak aria-hidden="true"></span>
            </div>
        @endif
        <ul class="lc-select__list"
            id="{{ $listboxId }}"
            x-ref="listbox"
            role="listbox"
            tabindex="-1"
            @if ($label) aria-label="{{ $label }}"
            @elseif ($labelledBy) aria-labelledby="{{ $labelledBy }}"
            @else aria-label="Options" @endif>
            @if ($allowEmpty)
                <li role="option"
                    :id="optionId('__empty')"
                    :aria-selected="!selected ? 'true' : 'false'"
                    @click="clear(); focusTrigger();"
                    :class="cursor === -1 && 'is-active'"
                    @mouseenter="cursor = -1"
                    class="lc-select__item lc-select__item--empty">
                    <span class="lc-select__icon lc-select__icon--empty" aria-hidden="true"></span>
                    <span class="lc-select__placeholder">{{ $emptyLabel }}</span>
                </li>
            @endif
            <template x-for="(opt, i) in visible" :key="opt.key">
                <li role="option"
                    :id="optionId(opt.key)"
                    :aria-selected="selected?.key === opt.key ? 'true' : 'false'"
                    :class="{ 'is-active': i === cursor, 'is-selected': selected?.key === opt.key }"
                    @click="pickAt(i); focusTrigger();"
                    @mouseenter="cursor = i"
                    class="lc-select__item">
                    <span class="lc-select__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none"
                             stroke="currentColor" stroke-width="1.8"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path :d="opt.svg"></path>
                        </svg>
                    </span>
                    <span class="lc-select__body">
                        <span class="lc-select__title" x-html="highlight(opt.title, opt._hl?.title)"></span>
                        <span class="lc-select__subtitle" x-show="opt.subtitle" x-html="highlight(opt.subtitle, opt._hl?.subtitle)"></span>
                    </span>
                </li>
            </template>
            <li x-show="filtered.length === 0 && !searchError" class="lc-select__no-results" role="presentation">{{ $noResultsLabel }}</li>
            <li x-show="filtered.length > visible.length" class="lc-select__more-row" role="presentation">
                Showing <span x-text="visible.length"></span> of <span x-text="filtered.length"></span> · refine your search to narrow further.
            </li>
            <li x-show="searchError" x-cloak class="lc-select__error-row" role="alert" x-text="searchError"></li>
        </ul>
    </div>

    @if ($error)
        <p id="{{ $errorId }}" class="lc-select__error" role="alert">{{ $error }}</p>
    @endif

    {{-- Polite live region: announces filtered-results count + selection
         changes to assistive tech without grabbing focus. --}}
    <div id="{{ $liveId }}" class="lc-select__live" aria-live="polite" aria-atomic="true" x-text="liveMessage"></div>

    @once
        @include('select::styles')
    @endonce
    @once
        @include('select::partials.search-helpers')
    @endonce
    @once
        <script data-lc-select-alpine>
            (function () {
                if (window.__loggedCloudSelectLoaded) return;
                window.__loggedCloudSelectLoaded = true;

                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('loggedCloudSelect', (config) => ({
                        items: config.items || [],
                        allowEmpty: !!config.allowEmpty,
                        searchable: !!config.searchable,
                        emptyLabel: config.emptyLabel || '',
                        listboxId: config.listboxId,
                        searchId: config.searchId,
                        triggerId: config.triggerId,
                        renderLimit: config.renderLimit ?? 50,
                        a11y: config.a11y || {},
                        value: config.selected || '',
                        selected: null,
                        query: '',
                        open: false,
                        cursor: 0,
                        liveMessage: '',
                        searchUrl: config.searchUrl || null,
                        debounceMs: config.debounceMs,
                        loading: false,
                        searchError: '',
                        _remote: null,

                        get filtered() {
                            this._filter ??= window.lcMakeFilter();
                            return this._filter(this.items, this.query);
                        },

                        get visible() {
                            const all = this.filtered;
                            return this.renderLimit > 0 && all.length > this.renderLimit
                                ? all.slice(0, this.renderLimit)
                                : all;
                        },

                        highlight(text, ranges) {
                            return window.lcHighlightHtml(text, ranges);
                        },

                        syncSelectedFromValue() {
                            this.selected = this.items.find((o) => o.key === this.value) || null;
                        },

                        optionId(key) {
                            return this.listboxId + '__opt-' + window.lcSafeId(key);
                        },

                        currentIndex() {
                            const i = this.visible.findIndex((o) => o.key === this.value);
                            return i >= 0 ? i : 0;
                        },

                        activeOptionId() {
                            if (!this.open) return null;
                            if (this.cursor === -1 && this.allowEmpty) return this.optionId('__empty');
                            const opt = this.visible[this.cursor];
                            return opt ? this.optionId(opt.key) : null;
                        },

                        toggle() {
                            this.open ? this.close() : this.openMenu(this.currentIndex());
                        },

                        openMenu(cursor) {
                            this.open = true;
                            this.cursor = Math.max(this.allowEmpty ? -1 : 0, Math.min(cursor, this.visible.length - 1));
                            this.announceResults();
                            // Lock the body scroll on phones · the bottom-
                            // sheet CSS kicks in at the same breakpoint.
                            if (window.matchMedia && window.matchMedia('(max-width: 640px)').matches) {
                                window.lcLockBodyScroll();
                                this._lockedScroll = true;
                            }
                            if (this.searchable) {
                                this.$nextTick(() => this.$refs.search?.focus());
                            }
                            this.$nextTick(() => this.scrollActiveIntoView());
                        },

                        close() {
                            if (!this.open) return;
                            this.open = false;
                            this.query = '';
                            this.cursor = 0;
                            this.searchError = '';
                            if (this._remote) this._remote.cancel();
                            if (this._lockedScroll) {
                                window.lcUnlockBodyScroll();
                                this._lockedScroll = false;
                            }
                            this.focusTrigger();
                        },

                        focusTrigger() {
                            this.$nextTick(() => this.$refs.trigger?.focus());
                        },

                        move(delta) {
                            const min = this.allowEmpty ? -1 : 0;
                            const max = this.visible.length - 1;
                            this.cursor = Math.max(min, Math.min(this.cursor + delta, max));
                            this.scrollActiveIntoView();
                        },

                        pickAt(i) {
                            // pickAt is called by enter / click handlers with
                            // an index into `visible`; the original `filtered`-
                            // indexed callers still resolve correctly because
                            // visible[i] === filtered[i] for i < renderLimit.
                            const opt = this.visible[i];
                            if (!opt) return;
                            this.value = opt.key;
                            this.selected = opt;
                            this.announceSelection(opt);
                            this.open = false;
                            this.query = '';
                            this.cursor = 0;
                            this.$refs.hidden?.dispatchEvent(new Event('change', { bubbles: true }));
                            this.focusTrigger();
                        },

                        scrollActiveIntoView() {
                            const id = this.activeOptionId();
                            if (!id) return;
                            const el = document.getElementById(id);
                            if (el && typeof el.scrollIntoView === 'function') {
                                el.scrollIntoView({ block: 'nearest', behavior: 'instant' in HTMLElement.prototype ? 'auto' : 'auto' });
                            }
                        },

                        announceResults() {
                            const n = this.filtered.length;
                            const msg = n === 0
                                ? (this.a11y.no_options || 'No options.')
                                : n + ' ' + (this.a11y.options_available || 'options available');
                            // Coalesce keystroke-rate updates · screen readers
                            // chatter otherwise. Direct sets (selection, error)
                            // still write straight to liveMessage and win.
                            this._announce ??= window.lcMakeAnnouncer((m) => { this.liveMessage = m; });
                            this._announce(msg);
                        },

                        announceSelection(opt) {
                            const label = (this.a11y.selected || 'Selected') + ' ' + (opt?.title || '');
                            this.liveMessage = label.trim();
                        },

                        clear() {
                            this.value = '';
                            this.selected = null;
                            this.liveMessage = this.a11y.cleared || 'Selection cleared';
                            this.open = false;
                            this.query = '';
                            this.cursor = 0;
                            this.$refs.hidden?.dispatchEvent(new Event('change', { bubbles: true }));
                            this.focusTrigger();
                        },

                        init() {
                            this.$watch('filtered', () => {
                                if (this.open) this.announceResults();
                                // Keep the cursor inside the new bounds when filtering.
                                const max = this.visible.length - 1;
                                if (this.cursor > max) this.cursor = max;
                                const min = this.allowEmpty ? -1 : 0;
                                if (this.cursor < min) this.cursor = min;
                            });

                            if (this.searchUrl) {
                                this._remote = window.lcMakeRemoteSearch({
                                    url: () => this.searchUrl,
                                    debounceMs: () => this.debounceMs ?? 250,
                                    onLoading: (v) => { this.loading = v; if (v) { this.searchError = ''; this.liveMessage = this.a11y.loading || 'Searching…'; } },
                                    onResult: (items) => {
                                        this.searchError = '';
                                        this.items = (items || []).map((o) => ({
                                            key: String(o.key ?? ''),
                                            title: String(o.title ?? ''),
                                            subtitle: String(o.subtitle ?? ''),
                                            svg: String(o.svg ?? ''),
                                        }));
                                        this.cursor = this.allowEmpty ? -1 : 0;
                                    },
                                    onError: (err) => {
                                        this.searchError = this.a11y.search_failed || 'Search failed.';
                                        this.liveMessage = this.searchError;
                                        console.error('[lc-select]', err);
                                    },
                                });
                                this.$watch('query', (q) => this._remote.queue(q));
                            }
                        },
                    }));
                });
            })();
        </script>
    @endonce
</div>
