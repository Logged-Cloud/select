@props([
    'name',
    'id' => null,
    'items' => [],
    'selected' => [],
    'iconSize' => null,
    'label' => null,
    'labelledBy' => null,
    'required' => false,
    'disabled' => false,
    'max' => null,
])
@php
    $groupId = $id ?? ($label ? \Illuminate\Support\Str::camel(\Illuminate\Support\Str::slug($label, '_')) : $name);
    $fallbackId = $groupId.'-fallback';
    $iconSize = $iconSize ?? '1.5rem';
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

    $selectedKeys = collect($selected)->map(fn ($v) => (string) $v)->values()->all();

    $config = [
        'items' => $normalised,
        'selected' => $selectedKeys,
        'groupId' => $groupId,
        'max' => is_numeric($max) ? (int) $max : null,
        'a11y' => [
            'added' => 'Added',
            'removed' => 'Removed',
            'limit_reached' => 'Maximum number of selections reached.',
        ],
    ];
@endphp
<div x-data="loggedCloudMultiList({{ \Illuminate\Support\Js::from($config) }})"
     x-init="$nextTick(() => { if ($refs.fallback) { $refs.fallback.name = ''; } })"
     class="lc-multi-list"
     id="{{ $groupId }}"
     style="--lc-icon-size: {{ $iconSize }};"
     role="group"
     @if ($label) aria-label="{{ $label }}" @endif
     @if ($labelledBy) aria-labelledby="{{ $labelledBy }}" @endif
     @if ($required) aria-required="true" @endif>

    @include('select::partials.fallback', [
        'name' => $name, 'items' => $normalised, 'selected' => $selectedKeys,
        'multi' => true, 'fallbackId' => $fallbackId, 'required' => $required,
        'noJsLabel' => $noJsLabel, 'noJsCopy' => $noJsCopy,
    ])

    <template x-for="key in values" :key="'h-'+key">
        <input type="hidden" :name="@js($name).concat('[]')" :value="key">
    </template>

    <template x-for="(opt, i) in items" :key="opt.key">
        <button type="button"
                :id="optionId(opt.key)"
                :aria-pressed="isSelected(opt.key) ? 'true' : 'false'"
                @if ($disabled) aria-disabled="true" disabled @endif
                :class="{ 'is-selected': isSelected(opt.key) }"
                class="lc-multi-list__item"
                @click="toggle(opt.key)"
                @keydown.space.prevent="toggle(opt.key)"
                @keydown.enter.prevent="toggle(opt.key)">
            <span class="lc-multi-list__check" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none"
                     stroke="currentColor" stroke-width="3"
                     stroke-linecap="round" stroke-linejoin="round" x-show="isSelected(opt.key)">
                    <path d="M5 12l5 5L20 7"/>
                </svg>
            </span>
            <span class="lc-multi-list__icon" aria-hidden="true" x-show="opt.svg">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none"
                     stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path :d="opt.svg"></path>
                </svg>
            </span>
            <span class="lc-multi-list__body">
                <span class="lc-multi-list__title" x-text="opt.title"></span>
                <span class="lc-multi-list__subtitle" x-show="opt.subtitle" x-text="opt.subtitle"></span>
            </span>
        </button>
    </template>

    <div :id="groupId+'-live'" class="lc-select__live" aria-live="polite" aria-atomic="true" x-text="liveMessage"></div>

    @once
        @include('select::styles')
    @endonce
    @once
        <script data-lc-multi-list-alpine>
            (function () {
                if (window.__loggedCloudMultiListLoaded) return;
                window.__loggedCloudMultiListLoaded = true;

                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('loggedCloudMultiList', (config) => ({
                        items: config.items || [],
                        groupId: config.groupId,
                        max: config.max,
                        a11y: config.a11y || {},
                        values: Array.isArray(config.selected) ? [...config.selected] : [],
                        liveMessage: '',

                        optionId(key) {
                            const safe = String(key).replace(/[^a-zA-Z0-9_-]/g, (c) => '_' + c.charCodeAt(0).toString(16));
                            return this.groupId + '__opt-' + safe;
                        },

                        isSelected(key) {
                            return this.values.includes(key);
                        },

                        toggle(key) {
                            const opt = this.items.find((o) => o.key === key);
                            if (!opt) return;
                            const idx = this.values.indexOf(key);
                            if (idx >= 0) {
                                this.values.splice(idx, 1);
                                this.liveMessage = (this.a11y.removed || 'Removed') + ' ' + opt.title;
                            } else {
                                if (this.max != null && this.values.length >= this.max) {
                                    this.liveMessage = this.a11y.limit_reached || 'Maximum reached.';
                                    return;
                                }
                                this.values.push(key);
                                this.liveMessage = (this.a11y.added || 'Added') + ' ' + opt.title;
                            }
                            this.$nextTick(() => {
                                this.$el.querySelectorAll('input[type=hidden]').forEach((el) => {
                                    el.dispatchEvent(new Event('change', { bubbles: true }));
                                });
                            });
                        },
                    }));
                });
            })();
        </script>
    @endonce
</div>
