@props([
    'name',
    'id' => null,
    'items' => [],
    'selected' => null,
    'iconSize' => null,
    'label' => null,
    'labelledBy' => null,
    'required' => false,
    'disabled' => false,
])
@php
    $groupId = $id ?? $name;
    $iconSize = $iconSize ?? '1rem';

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
<div x-data="loggedCloudInlineButtons({{ \Illuminate\Support\Js::from($config) }})"
     class="lc-inline-buttons"
     style="--lc-icon-size: {{ $iconSize }};"
     role="radiogroup"
     @if ($label) aria-label="{{ $label }}" @endif
     @if ($labelledBy) aria-labelledby="{{ $labelledBy }}" @endif
     @if ($required) aria-required="true" @endif>

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
                class="lc-inline-buttons__item"
                @click="pick(opt.key)"
                @keydown.arrow-right.prevent="moveBy(1, $el)"
                @keydown.arrow-left.prevent="moveBy(-1, $el)"
                @keydown.home.prevent="focusIndex(0)"
                @keydown.end.prevent="focusIndex(items.length - 1)"
                @keydown.space.prevent="pick(opt.key)"
                @keydown.enter.prevent="pick(opt.key)">
            <span class="lc-inline-buttons__icon" aria-hidden="true" x-show="opt.svg">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path :d="opt.svg"></path>
                </svg>
            </span>
            <span x-text="opt.title"></span>
        </button>
    </template>

    @once
        @include('select::styles')
    @endonce
    @once
        <script data-lc-inline-buttons-alpine>
            (function () {
                if (window.__loggedCloudInlineButtonsLoaded) return;
                window.__loggedCloudInlineButtonsLoaded = true;

                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('loggedCloudInlineButtons', (config) => ({
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
