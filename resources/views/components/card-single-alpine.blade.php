@props([
    'name',
    'id' => null,
    'items' => [],
    'selected' => null,
    'label' => null,
    'labelledBy' => null,
    'required' => false,
    'disabled' => false,
    'minWidth' => '12rem',
    'iconSize' => '2.5rem',
    'pageSize' => 0,
])
@php
    $groupId = $id ?? ($label ? \Illuminate\Support\Str::camel(\Illuminate\Support\Str::slug($label, '_')) : $name);
    $fallbackId = $groupId.'-fallback';
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
        ];
    })->values()->all();

    $config = [
        'items' => $normalised,
        'selected' => $selected,
        'groupId' => $groupId,
        'pageSize' => is_numeric($pageSize) ? (int) $pageSize : 0,
    ];
@endphp
<div x-data="loggedCloudCardSingle({{ \Illuminate\Support\Js::from($config) }})"
     x-init="$nextTick(() => { if ($refs.fallback) { $refs.fallback.name = ''; } })"
     class="lc-cards"
     id="{{ $groupId }}"
     style="--lc-icon-size: {{ $iconSize }}; --lc-cell-min: {{ $minWidth }};"
     role="radiogroup"
     @if ($label) aria-label="{{ $label }}" @endif
     @if ($labelledBy) aria-labelledby="{{ $labelledBy }}" @endif
     @if ($required) aria-required="true" @endif>

    @include('select::partials.fallback', [
        'name' => $name, 'items' => $normalised, 'selected' => $selected,
        'multi' => false, 'fallbackId' => $fallbackId, 'required' => $required,
        'noJsLabel' => $noJsLabel, 'noJsCopy' => $noJsCopy,
    ])

    <input type="hidden" name="{{ $name }}" :value="value" x-ref="hidden"
           @if ($required) required @endif>

    <template x-for="(opt, i) in visible" :key="opt.key">
        <button type="button"
                :id="optionId(opt.key)"
                role="radio"
                :aria-checked="value === opt.key ? 'true' : 'false'"
                :tabindex="(value === opt.key || (!value && i === 0)) ? 0 : -1"
                @if ($disabled) aria-disabled="true" disabled @endif
                :class="{ 'is-selected': value === opt.key }"
                class="lc-cards__item"
                @click="pick(opt.key)"
                @keydown.arrow-right.prevent="moveBy(1, $el)"
                @keydown.arrow-down.prevent="moveBy(1, $el)"
                @keydown.arrow-left.prevent="moveBy(-1, $el)"
                @keydown.arrow-up.prevent="moveBy(-1, $el)"
                @keydown.home.prevent="focusIndex(0)"
                @keydown.end.prevent="focusIndex(visible.length - 1)"
                @keydown.space.prevent="pick(opt.key)"
                @keydown.enter.prevent="pick(opt.key)">
            <span class="lc-cards__check" aria-hidden="true" x-show="value === opt.key">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none"
                     stroke="currentColor" stroke-width="3"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12l5 5L20 7"/>
                </svg>
            </span>
            <span class="lc-cards__icon" aria-hidden="true" x-show="opt.svg">
                <svg viewBox="0 0 24 24" width="40" height="40" fill="none"
                     stroke="currentColor" stroke-width="1.6"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path :d="opt.svg"></path>
                </svg>
            </span>
            <span class="lc-cards__title" x-text="opt.title"></span>
            <span class="lc-cards__subtitle" x-show="opt.subtitle" x-text="opt.subtitle"></span>
        </button>
    </template>

    {{-- Pagination controls · only render when page-size is set and there is
         more than one page. Lives outside the radiogroup so it doesn't show
         up to assistive tech as a fake card. --}}
    <nav x-show="pageSize > 0 && pageCount > 1" x-cloak class="lc-cards__pager" aria-label="Pagination">
        <button type="button" class="lc-cards__page-btn" :disabled="page === 0" @click="prevPage()" aria-label="Previous page">‹ Prev</button>
        <span class="lc-cards__page-status" aria-live="polite">
            Page <span x-text="page + 1"></span> of <span x-text="pageCount"></span>
        </span>
        <button type="button" class="lc-cards__page-btn" :disabled="page >= pageCount - 1" @click="nextPage()" aria-label="Next page">Next ›</button>
    </nav>

    @once
        @include('select::styles')
    @endonce
    @once
        @include('select::partials.search-helpers')
    @endonce
    @once
        <script data-lc-card-single-alpine>
            (function () {
                if (window.__loggedCloudCardSingleLoaded) return;
                window.__loggedCloudCardSingleLoaded = true;

                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('loggedCloudCardSingle', (config) => ({
                        items: config.items || [],
                        groupId: config.groupId,
                        value: config.selected || '',
                        pageSize: config.pageSize || 0,
                        page: 0,

                        get pageCount() {
                            if (this.pageSize <= 0) return 1;
                            return Math.max(1, Math.ceil(this.items.length / this.pageSize));
                        },

                        get visible() {
                            if (this.pageSize <= 0) return this.items;
                            const start = this.page * this.pageSize;
                            return this.items.slice(start, start + this.pageSize);
                        },

                        optionId(key) {
                            return this.groupId + '__opt-' + window.lcSafeId(key);
                        },

                        pick(key) {
                            this.value = key;
                            if (this.$refs.hidden) {
                                this.$refs.hidden.value = key;
                                this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                            this.$nextTick(() => {
                                document.getElementById(this.optionId(key))?.focus();
                            });
                        },

                        focusIndex(i) {
                            // Index is into the VISIBLE slice · arrow-right past
                            // the end of the page advances to the next page and
                            // focuses its first card; same in reverse.
                            if (i < 0) {
                                if (this.page > 0) {
                                    this.page--;
                                    this.$nextTick(() => this.pick(this.visible[this.visible.length - 1].key));
                                }
                                return;
                            }
                            if (i >= this.visible.length) {
                                if (this.page < this.pageCount - 1) {
                                    this.page++;
                                    this.$nextTick(() => this.pick(this.visible[0].key));
                                }
                                return;
                            }
                            const opt = this.visible[i];
                            if (opt) this.pick(opt.key);
                        },

                        moveBy(delta, fromEl) {
                            const i = this.visible.findIndex((o) => this.optionId(o.key) === fromEl?.id);
                            if (i < 0) return this.focusIndex(0);
                            this.focusIndex(i + delta);
                        },

                        prevPage() { if (this.page > 0) this.page--; },
                        nextPage() { if (this.page < this.pageCount - 1) this.page++; },
                    }));
                });
            })();
        </script>
    @endonce
</div>
