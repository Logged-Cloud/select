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
])
@php
    $id = $id ?? $name;
    $allowEmpty = $allowEmpty ?? config('select.behavior.allow_empty', true);
    $searchable = $searchable ?? config('select.behavior.searchable', true);
    $iconSize = $iconSize ?? config('select.behavior.icon_size', '1.75rem');
    $placeholder = $placeholder ?? config('select.copy.placeholder', 'Select an option');
    $emptyLabel = $emptyLabel ?? config('select.copy.empty_label', 'not set');
    $searchLabel = $searchLabel ?? config('select.copy.search_label', 'Search...');
    $noResultsLabel = $noResultsLabel ?? config('select.copy.no_results_label', 'No options match that.');

    // Normalise items. Caller may pass arrays or objects with key/title/subtitle/svg;
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
    ];
@endphp
<div x-data="loggedCloudSelect({{ \Illuminate\Support\Js::from($config) }})"
     x-init="$nextTick(() => syncSelectedFromValue())"
     class="lc-select"
     style="--lc-icon-size: {{ $iconSize }};"
     @click.outside="open = false"
     @keydown.escape.window="open = false">

    <input type="hidden" name="{{ $name }}" :value="value" x-ref="hidden">

    <button type="button" id="{{ $id }}"
            class="lc-select__trigger"
            :class="{ 'is-open': open }"
            @click="toggle()" :aria-expanded="open">
        <span class="lc-select__chosen">
            <template x-if="selected">
                <span class="lc-select__icon">
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
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round"
             class="lc-select__chevron">
            <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms class="lc-select__menu">
        @if ($searchable)
            <input type="text" x-ref="search" x-model="query"
                   class="lc-select__search" placeholder="{{ $searchLabel }}"
                   @keydown.arrow-down.prevent="cursor = Math.min(cursor + 1, filtered.length - 1)"
                   @keydown.arrow-up.prevent="cursor = Math.max(cursor - 1, 0)"
                   @keydown.enter.prevent="pickAt(cursor)">
        @endif
        <ul class="lc-select__list" role="listbox">
            @if ($allowEmpty)
                <li role="option"
                    @click="clear()"
                    :class="!selected && 'is-active'"
                    class="lc-select__item lc-select__item--empty">
                    <span class="lc-select__icon lc-select__icon--empty"></span>
                    <span class="lc-select__placeholder">{{ $emptyLabel }}</span>
                </li>
            @endif
            <template x-for="(opt, i) in filtered" :key="opt.key">
                <li role="option"
                    :class="{ 'is-active': i === cursor, 'is-selected': selected?.key === opt.key }"
                    @click="pickAt(i)"
                    @mouseenter="cursor = i"
                    class="lc-select__item">
                    <span class="lc-select__icon">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none"
                             stroke="currentColor" stroke-width="1.8"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path :d="opt.svg"></path>
                        </svg>
                    </span>
                    <span class="lc-select__body">
                        <span class="lc-select__title" x-text="opt.title"></span>
                        <span class="lc-select__subtitle" x-show="opt.subtitle" x-text="opt.subtitle"></span>
                    </span>
                </li>
            </template>
            <li x-show="filtered.length === 0" class="lc-select__no-results">{{ $noResultsLabel }}</li>
        </ul>
    </div>

    @once
        @include('select::styles')
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
                        value: config.selected || '',
                        selected: null,
                        query: '',
                        open: false,
                        cursor: 0,

                        get filtered() {
                            const q = this.query.trim().toLowerCase();
                            if (!q) return this.items;
                            return this.items.filter((o) =>
                                (o.title || '').toLowerCase().includes(q)
                                || (o.subtitle || '').toLowerCase().includes(q)
                                || (o.key || '').toLowerCase().includes(q)
                            );
                        },

                        syncSelectedFromValue() {
                            this.selected = this.items.find((o) => o.key === this.value) || null;
                        },

                        toggle() {
                            this.open = !this.open;
                            if (this.open) {
                                this.cursor = Math.max(0, this.filtered.findIndex((o) => o.key === this.value));
                                if (this.searchable) {
                                    this.$nextTick(() => this.$refs.search?.focus());
                                }
                            }
                        },

                        pickAt(i) {
                            const opt = this.filtered[i];
                            if (!opt) return;
                            this.value = opt.key;
                            this.selected = opt;
                            this.open = false;
                            this.query = '';
                            this.$refs.hidden?.dispatchEvent(new Event('change', { bubbles: true }));
                        },

                        clear() {
                            this.value = '';
                            this.selected = null;
                            this.open = false;
                            this.query = '';
                            this.$refs.hidden?.dispatchEvent(new Event('change', { bubbles: true }));
                        },
                    }));
                });
            })();
        </script>
    @endonce
</div>
