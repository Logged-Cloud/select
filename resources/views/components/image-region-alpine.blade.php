@props([
    'name',
    'id' => null,
    'src' => null,                // background image URL
    'imageAlt' => '',
    'imageWidth' => null,
    'imageHeight' => null,
    'viewBox' => null,            // defaults to '0 0 imageWidth imageHeight' if dims given
    'items' => [],                // [{key, title, path}] · SVG path describing the hit region
    'selected' => null,
    'placeholder' => null,
    'label' => null,
    'labelledBy' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
    'showOutlines' => true,       // when true, region paths render as faint outlines
])
@php
    $triggerId = $id ?? ($label ? \Illuminate\Support\Str::camel(\Illuminate\Support\Str::slug($label, '_')) : $name);
    $svgId = $triggerId.'-svg';
    $fallbackId = $triggerId.'-fallback';
    $errorId = $triggerId.'-error';
    $placeholder = $placeholder ?? 'Pick a region';
    $noJsLabel = config('select.copy.no_js_indicator', 'JS off');
    $noJsCopy = config('select.copy.no_js_warning', 'JavaScript is needed for the rich picker.');

    if (! $viewBox && $imageWidth && $imageHeight) {
        $viewBox = "0 0 {$imageWidth} {$imageHeight}";
    }
    $viewBox = $viewBox ?? '0 0 1000 500';

    $normalised = collect($items)->map(function ($item) {
        $get = is_array($item) ? fn ($k) => $item[$k] ?? null : fn ($k) => $item->{$k} ?? null;
        return [
            'key' => (string) $get('key'),
            'title' => (string) $get('title'),
            'path' => (string) ($get('path') ?? ''),
        ];
    })->values()->all();

    $config = [
        'items' => $normalised,
        'selected' => $selected,
        'svgId' => $svgId,
        'triggerId' => $triggerId,
        'placeholder' => $placeholder,
        'a11y' => [
            'selected' => 'Selected',
        ],
    ];
@endphp
<div x-data="loggedCloudImageRegion({{ \Illuminate\Support\Js::from($config) }})"
     x-init="$nextTick(() => { syncSelectedFromValue(); if ($refs.fallback) { $refs.fallback.name = ''; } })"
     class="lc-select lc-select--image-region {{ $error ? 'lc-select--error' : '' }}"
     @click.outside="close()"
     @keydown.escape.window="if (open) { close(); }">

    @include('select::partials.fallback', [
        'name' => $name, 'items' => $normalised, 'selected' => $selected,
        'multi' => false, 'fallbackId' => $fallbackId, 'required' => $required,
        'noJsLabel' => $noJsLabel, 'noJsCopy' => $noJsCopy,
    ])

    <input type="hidden" name="{{ $name }}" :value="value" x-ref="hidden"
           @if ($required) required @endif>

    <div id="{{ $triggerId }}"
         x-ref="trigger"
         tabindex="{{ $disabled ? '-1' : '0' }}"
         class="lc-select__trigger"
         :class="{ 'is-open': open }"
         role="combobox"
         aria-haspopup="listbox"
         aria-controls="{{ $svgId }}"
         :aria-expanded="open"
         :aria-activedescendant="open ? optionId(items[cursor]?.key) : null"
         @if ($label) aria-label="{{ $label }}"
         @elseif ($labelledBy) aria-labelledby="{{ $labelledBy }}"
         @else aria-label="{{ $placeholder }}" @endif
         @if ($required) aria-required="true" @endif
         @if ($disabled) aria-disabled="true" @endif
         @if ($error) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
         @click="toggle()"
         @keydown.enter.prevent="toggle()"
         @keydown.space.prevent="toggle()">
        <span class="lc-select__chosen">
            <span x-text="selected ? selected.title : @js($placeholder)"
                  :class="selected ? 'lc-select__placeholder--filled' : 'lc-select__placeholder'"></span>
        </span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round"
             class="lc-select__chevron" aria-hidden="true">
            <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
    </div>

    <div x-show="open" x-cloak class="lc-select__backdrop" @click="close()"></div>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms class="lc-select__menu lc-select__menu--map">
        <div class="lc-select__sheet-handle" aria-hidden="true"></div>
        {{-- The SVG is tabindex=0 so keyboard nav works without focusing a
             specific path · arrow keys cycle through regions and Enter
             commits. Without this, image-region was mouse-only. --}}
        <svg id="{{ $svgId }}" role="listbox" viewBox="{{ $viewBox }}"
             class="lc-image-region {{ $showOutlines ? '' : 'lc-image-region--no-outline' }}"
             preserveAspectRatio="xMidYMid meet"
             tabindex="{{ $disabled ? '-1' : '0' }}"
             @if ($label) aria-label="{{ $label }}" @else aria-label="{{ $placeholder }}" @endif
             @keydown.arrow-right.prevent="moveBy(1)"
             @keydown.arrow-down.prevent="moveBy(1)"
             @keydown.arrow-left.prevent="moveBy(-1)"
             @keydown.arrow-up.prevent="moveBy(-1)"
             @keydown.home.prevent="cursor = 0"
             @keydown.end.prevent="cursor = items.length - 1"
             @keydown.enter.prevent="pickAt(cursor)"
             @keydown.space.prevent="pickAt(cursor)">
            @if ($src)
                <image href="{{ $src }}"
                       x="0" y="0"
                       width="{{ $imageWidth ?? '100%' }}" height="{{ $imageHeight ?? '100%' }}"
                       preserveAspectRatio="xMidYMid meet"
                       aria-hidden="true" />
            @endif
            @foreach ($normalised as $i => $opt)
                @if (! empty($opt['path']))
                    @php
                        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $opt['key']);
                        $optId = $svgId.'__opt-'.$safe;
                    @endphp
                    <path id="{{ $optId }}"
                          role="option"
                          d="{{ $opt['path'] }}"
                          aria-label="{{ $opt['title'] }}"
                          :aria-selected="selected?.key === @js($opt['key']) ? 'true' : 'false'"
                          :class="{ 'is-active': cursor === {{ $i }}, 'is-selected': selected?.key === @js($opt['key']) }"
                          class="lc-image-region__item"
                          @click="pickAt({{ $i }})"
                          @mouseenter="cursor = {{ $i }}" />
                @endif
            @endforeach
        </svg>
        <div class="lc-map__hover" x-text="items[cursor]?.title || (selected?.title || '')"></div>
    </div>

    @if ($error)
        <p id="{{ $errorId }}" class="lc-select__error" role="alert">{{ $error }}</p>
    @endif

    @once
        @include('select::styles')
    @endonce
    @once
        @include('select::partials.search-helpers')
    @endonce
    @once
        <script data-lc-image-region-alpine>
            (function () {
                if (window.__loggedCloudImageRegionLoaded) return;
                window.__loggedCloudImageRegionLoaded = true;

                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('loggedCloudImageRegion', (config) => ({
                        items: config.items || [],
                        svgId: config.svgId,
                        triggerId: config.triggerId,
                        placeholder: config.placeholder || '',
                        a11y: config.a11y || {},
                        value: config.selected || '',
                        selected: null,
                        open: false,
                        cursor: 0,
                        liveMessage: '',

                        optionId(key) {
                            if (!key) return null;
                            return this.svgId + '__opt-' + window.lcSafeId(key);
                        },

                        syncSelectedFromValue() {
                            this.selected = this.items.find((o) => o.key === this.value) || null;
                        },

                        toggle() { this.open ? this.close() : this.openMenu(); },

                        openMenu() {
                            this.open = true;
                            const i = this.items.findIndex((o) => o.key === this.value);
                            this.cursor = i >= 0 ? i : 0;
                            if (window.matchMedia && window.matchMedia('(max-width: 640px)').matches) {
                                window.lcLockBodyScroll();
                                this._lockedScroll = true;
                            }
                        },

                        close() {
                            if (!this.open) return;
                            this.open = false;
                            if (this._lockedScroll) {
                                window.lcUnlockBodyScroll();
                                this._lockedScroll = false;
                            }
                            this.$nextTick(() => this.$refs.trigger?.focus());
                        },

                        moveBy(delta) {
                            const max = this.items.length - 1;
                            this.cursor = Math.max(0, Math.min(this.cursor + delta, max));
                        },

                        pickAt(i) {
                            const opt = this.items[i];
                            if (!opt) return;
                            this.value = opt.key;
                            this.selected = opt;
                            this.liveMessage = (this.a11y.selected || 'Selected') + ' ' + opt.title;
                            if (this.$refs.hidden) {
                                this.$refs.hidden.value = opt.key;
                                this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                            this.close();
                        },
                    }));
                });
            })();
        </script>
    @endonce
</div>
