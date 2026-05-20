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

    <template x-for="(opt, i) in items" :key="opt.key">
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
                @keydown.end.prevent="focusIndex(items.length - 1)"
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

    @once
        @include('select::styles')
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

                        optionId(key) {
                            const safe = String(key).replace(/[^a-zA-Z0-9_-]/g, (c) => '_' + c.charCodeAt(0).toString(16));
                            return this.groupId + '__opt-' + safe;
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
                            const opt = this.items[i];
                            if (!opt) return;
                            this.pick(opt.key);
                        },

                        moveBy(delta, fromEl) {
                            const i = this.items.findIndex((o) => this.optionId(o.key) === fromEl?.id);
                            if (i < 0) return this.focusIndex(0);
                            const next = (i + delta + this.items.length) % this.items.length;
                            this.focusIndex(next);
                        },
                    }));
                });
            })();
        </script>
    @endonce
</div>
