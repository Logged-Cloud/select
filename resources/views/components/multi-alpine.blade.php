@props([
    'name',
    'id' => null,
    'items' => [],
    'selected' => [],
    'placeholder' => null,
    'searchLabel' => null,
    'noResultsLabel' => null,
    'searchable' => null,
    'iconSize' => null,
    'label' => null,
    'labelledBy' => null,
    'required' => false,
    'disabled' => false,
    'max' => null,
    'chipsLimit' => 3,
    'error' => null,
    'searchUrl' => null,
    'debounceMs' => null,
    'renderLimit' => 50,
    'dependsOn' => null,
    'dependsMessage' => null,
])
@php
    $triggerId = $id ?? ($label ? \Illuminate\Support\Str::camel(\Illuminate\Support\Str::slug($label, '_')) : $name);
    $listboxId = $triggerId.'-listbox';
    $searchId = $triggerId.'-search';
    $liveId = $triggerId.'-live';
    $fallbackId = $triggerId.'-fallback';
    $errorId = $triggerId.'-error';
    $searchable = $searchable ?? config('select.behavior.searchable', true);
    $iconSize = $iconSize ?? config('select.behavior.icon_size', '1.75rem');
    $placeholder = $placeholder ?? config('select.copy.placeholder', 'Select options');
    $searchLabel = $searchLabel ?? config('select.copy.search_label', 'Search...');
    $noResultsLabel = $noResultsLabel ?? config('select.copy.no_results_label', 'No options match that.');
    $noJsLabel = config('select.copy.no_js_indicator', 'JS off');
    $noJsCopy = config('select.copy.no_js_warning', 'JavaScript is needed for the rich picker.');

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
            'parent' => $get('parent') !== null ? (string) $get('parent') : null,
        ];
    })->values()->all();

    $selectedKeys = collect($selected)->map(fn ($v) => (string) $v)->values()->all();
    $dependsMessage = $dependsMessage ?? ($dependsOn ? "Select {$dependsOn} first" : null);

    $config = [
        'items' => $normalised,
        'selected' => $selectedKeys,
        'searchable' => (bool) $searchable,
        'listboxId' => $listboxId,
        'triggerId' => $triggerId,
        'max' => is_numeric($max) ? (int) $max : null,
        'chipsLimit' => (int) $chipsLimit,
        'searchUrl' => $searchUrl,
        'debounceMs' => is_numeric($debounceMs) ? (int) $debounceMs : null,
        'renderLimit' => is_numeric($renderLimit) ? (int) $renderLimit : 50,
        'dependsOn' => $dependsOn,
        'dependsMessage' => $dependsMessage,
        'placeholder' => $placeholder,
        'a11y' => [
            'options_available' => 'options available',
            'no_options' => 'No options.',
            'added' => 'Added',
            'removed' => 'Removed',
            'limit_reached' => 'Maximum number of selections reached.',
            'selected_summary' => 'selected',
            'loading' => 'Searching…',
            'search_failed' => 'Search failed. Try again.',
            'parent_changed' => 'Selections cleared because the parent changed.',
            'parent_unset' => 'Selections cleared. Parent is no longer set.',
        ],
    ];
@endphp
<div x-data="loggedCloudMultiSelect({{ \Illuminate\Support\Js::from($config) }})"
     x-init="$nextTick(() => { syncFromSelected(); if ($refs.fallback) { $refs.fallback.name = ''; } })"
     class="lc-select lc-select--multi {{ $error ? 'lc-select--error' : '' }}"
     style="--lc-icon-size: {{ $iconSize }};"
     @click.outside="close()"
     @keydown.escape.window="if (open) { close(); }">

    @include('select::partials.fallback', [
        'name' => $name, 'items' => $normalised, 'selected' => $selectedKeys,
        'multi' => true, 'fallbackId' => $fallbackId, 'required' => $required,
        'noJsLabel' => $noJsLabel, 'noJsCopy' => $noJsCopy,
    ])

    {{-- Hidden inputs · one per chosen key so Laravel's request->validate(['name' => 'array']) just works. --}}
    <template x-for="key in values" :key="'h-'+key">
        <input type="hidden" :name="@js($name).concat('[]')" :value="key" x-ref="hidden">
    </template>

    {{-- The trigger is a div, not a button, because chip-remove + clear-all
         live inside it · nesting a <button> inside a <button> is invalid
         HTML and the browser auto-closes the outer button. role=combobox +
         tabindex preserves the keyboard semantics. --}}
    <div id="{{ $triggerId }}"
            x-ref="trigger"
            tabindex="{{ $disabled ? '-1' : '0' }}"
            class="lc-select__trigger lc-select__trigger--multi"
            :class="{ 'is-open': open }"
            role="combobox"
            aria-haspopup="listbox"
            aria-autocomplete="{{ $searchable ? 'list' : 'none' }}"
            aria-multiselectable="true"
            aria-controls="{{ $listboxId }}"
            :aria-expanded="open"
            :aria-activedescendant="open ? activeOptionId() : null"
            @if ($label) aria-label="{{ $label }}"
            @elseif ($labelledBy) aria-labelledby="{{ $labelledBy }}"
            @else aria-label="{{ $placeholder }}" @endif
            @if ($required) aria-required="true" @endif
            @if ($disabled) aria-disabled="true" @endif
            @if ($error) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
            :aria-disabled="isLocked ? 'true' : null"
            @click="if (isLocked) return; toggle()"
            @keydown.arrow-down.prevent="open ? move(1) : openMenu(0)"
            @keydown.arrow-up.prevent="open ? move(-1) : openMenu(visible.length - 1)"
            @keydown.home.prevent="if (open) { cursor = 0; }"
            @keydown.end.prevent="if (open) { cursor = visible.length - 1; }"
            @keydown.enter.prevent="open ? toggleAt(cursor) : openMenu(currentIndex())"
            @keydown.space.prevent="open ? toggleAt(cursor) : openMenu(currentIndex())"
            @keydown.tab="if (open) { close(); }">
        <span class="lc-select__chosen">
            <template x-if="values.length === 0">
                <span class="lc-select__placeholder" x-text="effectivePlaceholder"></span>
            </template>
            <template x-if="values.length > 0 && values.length <= chipsLimit">
                <span class="lc-select__chips">
                    <template x-for="opt in selectedItems" :key="'chip-'+opt.key">
                        <span class="lc-select__chip">
                            <span class="lc-select__chip-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none"
                                     stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round">
                                    <path :d="opt.svg"></path>
                                </svg>
                            </span>
                            <span x-text="opt.title"></span>
                            <button type="button" class="lc-select__chip-remove"
                                    :aria-label="'Remove ' + opt.title"
                                    @click.stop="removeKey(opt.key); focusTrigger();">
                                <svg viewBox="0 0 24 24" width="12" height="12" fill="none"
                                     stroke="currentColor" stroke-width="2.5"
                                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M6 6l12 12M6 18L18 6"></path>
                                </svg>
                            </button>
                        </span>
                    </template>
                </span>
            </template>
            <template x-if="values.length > chipsLimit">
                <span class="lc-select__chosen-summary">
                    <span x-text="values.length"></span>
                    <span class="lc-select__placeholder">{{ $config['a11y']['selected_summary'] }}</span>
                </span>
            </template>
        </span>
        <span class="lc-select__trigger-tail">
            {{-- Clear-all button surfaces only when at least one chip is
                 selected · empties the values array via clearAll(). --}}
            <button type="button"
                    x-show="values.length > 0"
                    x-cloak
                    class="lc-select__clear"
                    aria-label="Clear all selections"
                    @click.stop="clearAll()">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none"
                     stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M6 6l12 12M6 18L18 6"></path>
                </svg>
            </button>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round"
                 class="lc-select__chevron" aria-hidden="true">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </span>
    </div>

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
                       @keydown.enter.prevent="toggleAt(cursor)"
                       @keydown.tab="close()">
                <span class="lc-select__spinner" x-show="loading" x-cloak aria-hidden="true"></span>
            </div>
        @endif
        <ul class="lc-select__list"
            id="{{ $listboxId }}"
            x-ref="listbox"
            role="listbox"
            aria-multiselectable="true"
            tabindex="-1"
            @if ($label) aria-label="{{ $label }}"
            @elseif ($labelledBy) aria-labelledby="{{ $labelledBy }}"
            @else aria-label="Options" @endif>
            <template x-for="(opt, i) in visible" :key="opt.key">
                <li role="option"
                    :id="optionId(opt.key)"
                    :aria-selected="isSelected(opt.key) ? 'true' : 'false'"
                    :class="{ 'is-active': i === cursor, 'is-selected': isSelected(opt.key) }"
                    @click="toggleAt(i)"
                    @mouseenter="cursor = i"
                    class="lc-select__item lc-select__item--multi">
                    <span class="lc-select__check" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none"
                             stroke="currentColor" stroke-width="3"
                             stroke-linecap="round" stroke-linejoin="round" x-show="isSelected(opt.key)">
                            <path d="M5 12l5 5L20 7"/>
                        </svg>
                    </span>
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

    <div id="{{ $liveId }}" class="lc-select__live" aria-live="polite" aria-atomic="true" x-text="liveMessage"></div>

    @once
        @include('select::styles')
    @endonce
    @once
        @include('select::partials.search-helpers')
    @endonce
    @once
        <script data-lc-multi-select-alpine>
            (function () {
                if (window.__loggedCloudMultiSelectLoaded) return;
                window.__loggedCloudMultiSelectLoaded = true;

                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('loggedCloudMultiSelect', (config) => ({
                        items: config.items || [],
                        searchable: !!config.searchable,
                        listboxId: config.listboxId,
                        triggerId: config.triggerId,
                        max: config.max,
                        chipsLimit: config.chipsLimit ?? 3,
                        renderLimit: config.renderLimit ?? 50,
                        dependsOn: config.dependsOn || null,
                        dependsMessage: config.dependsMessage || '',
                        placeholder: config.placeholder || '',
                        parentValue: '',
                        a11y: config.a11y || {},
                        values: Array.isArray(config.selected) ? [...config.selected] : [],
                        query: '',
                        open: false,
                        cursor: 0,
                        liveMessage: '',
                        searchUrl: config.searchUrl || null,
                        debounceMs: config.debounceMs,
                        loading: false,
                        searchError: '',
                        _remote: null,

                        get isLocked() {
                            return this.dependsOn && !this.parentValue;
                        },

                        get effectivePlaceholder() {
                            return this.isLocked ? (this.dependsMessage || this.placeholder) : this.placeholder;
                        },

                        get filtered() {
                            if (this.isLocked) return [];
                            this._filter ??= window.lcMakeFilter();
                            const pool = this._parentScoped();
                            return this._filter(pool, this.query);
                        },

                        _parentScoped() {
                            if (!this.dependsOn || !this.parentValue) return this.items;
                            if (this._lastScopedParent === this.parentValue
                                && this._lastScopedItems === this.items
                                && this._lastScoped) {
                                return this._lastScoped;
                            }
                            this._lastScopedParent = this.parentValue;
                            this._lastScopedItems = this.items;
                            this._lastScoped = this.items.filter(
                                (o) => o.parent == null || o.parent === this.parentValue
                            );
                            return this._lastScoped;
                        },

                        _readParent() {
                            const el = document.querySelector('[name="' + this.dependsOn + '"]');
                            const next = el ? String(el.value || '') : '';
                            if (next === this.parentValue) return;
                            const wasSet = !!this.parentValue;
                            const hadValues = this.values.length > 0;
                            this.parentValue = next;
                            if (hadValues) {
                                this.values = [];
                                this.$nextTick(() => {
                                    this.$el.querySelectorAll('input[type=hidden]').forEach((el) =>
                                        el.dispatchEvent(new Event('change', { bubbles: true })));
                                });
                                if (wasSet && next) {
                                    this.liveMessage = this.a11y.parent_changed || 'Selections cleared.';
                                } else if (wasSet && !next) {
                                    this.liveMessage = this.a11y.parent_unset || this.dependsMessage || 'Selections cleared.';
                                }
                            }
                        },

                        destroy() {
                            if (this._parentListener) {
                                document.removeEventListener('change', this._parentListener);
                                this._parentListener = null;
                            }
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

                        get selectedItems() {
                            // Preserve the user's selection order, not the original list order.
                            return this.values
                                .map((k) => this.items.find((o) => o.key === k))
                                .filter(Boolean);
                        },

                        isSelected(key) {
                            return this.values.includes(key);
                        },

                        syncFromSelected() {
                            // Hook for external code that mutates `values` directly.
                            // Currently a no-op; kept symmetric with searchable-alpine.
                        },

                        optionId(key) {
                            return this.listboxId + '__opt-' + window.lcSafeId(key);
                        },

                        currentIndex() {
                            // Open at the first selected (or 0 if none).
                            if (!this.values.length) return 0;
                            const i = this.filtered.findIndex((o) => o.key === this.values[0]);
                            return i >= 0 ? i : 0;
                        },

                        activeOptionId() {
                            if (!this.open) return null;
                            const opt = this.visible[this.cursor];
                            return opt ? this.optionId(opt.key) : null;
                        },

                        toggle() {
                            this.open ? this.close() : this.openMenu(this.currentIndex());
                        },

                        openMenu(cursor) {
                            this.open = true;
                            this.cursor = Math.max(0, Math.min(cursor, this.visible.length - 1));
                            this.announceResults();
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
                            const max = this.visible.length - 1;
                            this.cursor = Math.max(0, Math.min(this.cursor + delta, max));
                            this.scrollActiveIntoView();
                        },

                        scrollActiveIntoView() {
                            const id = this.activeOptionId();
                            if (!id) return;
                            const el = document.getElementById(id);
                            el?.scrollIntoView?.({ block: 'nearest' });
                        },

                        announceResults() {
                            const n = this.filtered.length;
                            const msg = n === 0
                                ? (this.a11y.no_options || 'No options.')
                                : n + ' ' + (this.a11y.options_available || 'options available');
                            this._announce ??= window.lcMakeAnnouncer((m) => { this.liveMessage = m; });
                            this._announce(msg);
                        },

                        toggleAt(i) {
                            const opt = this.visible[i];
                            if (!opt) return;
                            const idx = this.values.indexOf(opt.key);
                            if (idx >= 0) {
                                this.values.splice(idx, 1);
                                this.liveMessage = (this.a11y.removed || 'Removed') + ' ' + opt.title;
                            } else {
                                if (this.max != null && this.values.length >= this.max) {
                                    this.liveMessage = this.a11y.limit_reached || 'Maximum number of selections reached.';
                                    return;
                                }
                                this.values.push(opt.key);
                                this.liveMessage = (this.a11y.added || 'Added') + ' ' + opt.title;
                            }
                            this.notifyChange();
                            // Stay open · multi-select is iterative.
                        },

                        removeKey(key) {
                            const idx = this.values.indexOf(key);
                            if (idx < 0) return;
                            const opt = this.items.find((o) => o.key === key);
                            this.values.splice(idx, 1);
                            this.liveMessage = (this.a11y.removed || 'Removed') + ' ' + (opt?.title || '');
                            this.notifyChange();
                        },

                        clearAll() {
                            const n = this.values.length;
                            if (n === 0) return;
                            this.values = [];
                            this.liveMessage = (this.a11y.removed || 'Removed') + ' ' + n + ' ' + (this.a11y.selected_summary || 'selected');
                            this.notifyChange();
                        },

                        notifyChange() {
                            // Coalesce one bubbling `change` so listeners on the form react
                            // the same way they would with native <select multiple>.
                            this.$nextTick(() => {
                                this.$el.querySelectorAll('input[type=hidden]').forEach((el) => {
                                    el.dispatchEvent(new Event('change', { bubbles: true }));
                                });
                            });
                        },

                        init() {
                            this.$watch('filtered', () => {
                                if (this.open) this.announceResults();
                                const max = this.visible.length - 1;
                                if (this.cursor > max) this.cursor = Math.max(0, max);
                            });

                            if (this.dependsOn) {
                                this._readParent();
                                this._parentListener = (e) => {
                                    if (!e.target || e.target.name !== this.dependsOn) return;
                                    queueMicrotask(() => this._readParent());
                                };
                                document.addEventListener('change', this._parentListener);
                            }

                            if (this.searchUrl) {
                                this._remote = window.lcMakeRemoteSearch({
                                    url: () => {
                                        if (this.isLocked) return '';
                                        let u = this.searchUrl;
                                        if (this.dependsOn && this.parentValue) {
                                            u += (u.indexOf('?') >= 0 ? '&' : '?') + 'parent=' + encodeURIComponent(this.parentValue);
                                        }
                                        return u;
                                    },
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
                                        this.cursor = 0;
                                    },
                                    onError: (err) => {
                                        this.searchError = this.a11y.search_failed || 'Search failed.';
                                        this.liveMessage = this.searchError;
                                        console.error('[lc-select]', err);
                                    },
                                });
                                this.$watch('query', (q) => this._remote.queue(q));
                            }

                            this.$watch('parentValue', () => {
                                if (this._remote && this.parentValue) {
                                    this._remote.queue(this.query);
                                }
                            });
                        },
                    }));
                });
            })();
        </script>
    @endonce
</div>
