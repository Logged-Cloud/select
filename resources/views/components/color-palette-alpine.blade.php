@props([
    'name',
    'id' => null,
    'items' => [],
    'selected' => null,
    'placeholder' => null,
    'label' => null,
    'labelledBy' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
    'columns' => 6,
])
@php
    $triggerId = $id ?? ($label ? \Illuminate\Support\Str::camel(\Illuminate\Support\Str::slug($label, '_')) : $name);
    $listboxId = $triggerId.'-listbox';
    $fallbackId = $triggerId.'-fallback';
    $errorId = $triggerId.'-error';
    $placeholder = $placeholder ?? config('select.copy.placeholder', 'Pick a colour');
    $noJsLabel = config('select.copy.no_js_indicator', 'JS off');
    $noJsCopy = config('select.copy.no_js_warning', 'JavaScript is needed for the rich picker.');

    $normalised = collect($items)->map(function ($item) {
        $get = is_array($item) ? fn ($k) => $item[$k] ?? null : fn ($k) => $item->{$k} ?? null;
        return [
            'key' => (string) $get('key'),
            'title' => (string) $get('title'),
            // `color` is what shows on the swatch · falls back to the key so
            // host can pass items as ['key' => '#ff0033', 'title' => 'Red']
            // and the swatch still paints.
            'color' => (string) ($get('color') ?? $get('key')),
        ];
    })->values()->all();

    $config = [
        'items' => $normalised,
        'selected' => $selected,
        'listboxId' => $listboxId,
        'triggerId' => $triggerId,
        'placeholder' => $placeholder,
        'columns' => max(1, (int) $columns),
        'a11y' => [
            'selected' => 'Selected',
            'cleared' => 'Selection cleared',
            'options_available' => 'colours available',
        ],
    ];
@endphp
<div x-data="loggedCloudColorPalette({{ \Illuminate\Support\Js::from($config) }})"
     x-init="$nextTick(() => { syncSelectedFromValue(); if ($refs.fallback) { $refs.fallback.name = ''; } })"
     class="lc-select lc-select--color {{ $error ? 'lc-select--error' : '' }}"
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
         aria-controls="{{ $listboxId }}"
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
         @keydown.space.prevent="toggle()"
         @keydown.arrow-down.prevent="if (!open) toggle()">
        <span class="lc-select__chosen">
            {{-- Selected swatch in the trigger so the user sees the colour
                 they picked at a glance, not just the title. --}}
            <template x-if="selected">
                <span class="lc-color__swatch lc-color__swatch--trigger"
                      :style="'background:' + selected.color"
                      aria-hidden="true"></span>
            </template>
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

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms class="lc-select__menu lc-select__menu--color">
        <div class="lc-select__sheet-handle" aria-hidden="true"></div>
        <ul id="{{ $listboxId }}"
            role="listbox"
            class="lc-color__grid"
            x-ref="grid"
            :style="'--lc-color-cols:' + columns"
            @if ($label) aria-label="{{ $label }}" @else aria-label="{{ $placeholder }}" @endif
            @keydown.arrow-right.prevent="moveBy(1)"
            @keydown.arrow-left.prevent="moveBy(-1)"
            @keydown.arrow-down.prevent="moveBy(columns)"
            @keydown.arrow-up.prevent="moveBy(-columns)"
            @keydown.home.prevent="cursor = 0"
            @keydown.end.prevent="cursor = items.length - 1"
            @keydown.enter.prevent="pickAt(cursor)"
            @keydown.space.prevent="pickAt(cursor)">
            @foreach ($normalised as $i => $opt)
                @php
                    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $opt['key']);
                    $optId = $listboxId.'__opt-'.$safe;
                @endphp
                <li id="{{ $optId }}"
                    role="option"
                    aria-label="{{ $opt['title'] }}"
                    :aria-selected="selected?.key === @js($opt['key']) ? 'true' : 'false'"
                    :class="{ 'is-active': cursor === {{ $i }}, 'is-selected': selected?.key === @js($opt['key']) }"
                    class="lc-color__cell"
                    @click="pickAt({{ $i }})"
                    @mouseenter="cursor = {{ $i }}">
                    <span class="lc-color__swatch" style="background: {{ $opt['color'] }}" aria-hidden="true"></span>
                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none"
                         stroke="currentColor" stroke-width="3"
                         stroke-linecap="round" stroke-linejoin="round"
                         class="lc-color__check"
                         :class="isLight(@js($opt['color'])) ? 'lc-color__check--dark' : ''"
                         x-show="selected?.key === @js($opt['key'])">
                        <path d="M5 12l5 5L20 7" />
                    </svg>
                </li>
            @endforeach
        </ul>
        <div class="lc-color__caption" x-text="items[cursor]?.title || (selected?.title || '')"></div>
    </div>

    @if ($error)
        <p id="{{ $errorId }}" class="lc-select__error" role="alert">{{ $error }}</p>
    @endif

    @once
        @include('select::styles')
    @endonce
    @once
        <script data-lc-color-palette-alpine>
            (function () {
                if (window.__loggedCloudColorPaletteLoaded) return;
                window.__loggedCloudColorPaletteLoaded = true;

                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('loggedCloudColorPalette', (config) => ({
                        items: config.items || [],
                        listboxId: config.listboxId,
                        triggerId: config.triggerId,
                        placeholder: config.placeholder || '',
                        columns: config.columns || 6,
                        a11y: config.a11y || {},
                        value: config.selected || '',
                        selected: null,
                        open: false,
                        cursor: 0,

                        syncSelectedFromValue() {
                            this.selected = this.items.find((o) => o.key === this.value) || null;
                        },

                        // Relative-luminance test · returns true when the
                        // swatch is light enough that a white checkmark
                        // would disappear into it. Falls back to false
                        // (= white check) for non-hex inputs.
                        isLight(color) {
                            if (typeof color !== 'string') return false;
                            let r, g, b;
                            let m = /^#([0-9a-f]{3})$/i.exec(color);
                            if (m) {
                                r = parseInt(m[1][0] + m[1][0], 16);
                                g = parseInt(m[1][1] + m[1][1], 16);
                                b = parseInt(m[1][2] + m[1][2], 16);
                            } else {
                                m = /^#([0-9a-f]{6})$/i.exec(color);
                                if (!m) return false;
                                r = parseInt(m[1].slice(0, 2), 16);
                                g = parseInt(m[1].slice(2, 4), 16);
                                b = parseInt(m[1].slice(4, 6), 16);
                            }
                            // ITU-R BT.709 luminance · threshold tuned so
                            // pastels (light grey, bone, cream) trigger
                            // the dark check but mid-tones don't.
                            return (0.2126 * r + 0.7152 * g + 0.0722 * b) > 150;
                        },

                        optionId(key) {
                            if (!key) return null;
                            return this.listboxId + '__opt-' + window.lcSafeId(key);
                        },

                        toggle() {
                            this.open ? this.close() : this.openMenu();
                        },

                        openMenu() {
                            this.open = true;
                            const i = this.items.findIndex((o) => o.key === this.value);
                            this.cursor = i >= 0 ? i : 0;
                            if (window.matchMedia && window.matchMedia('(max-width: 640px)').matches) {
                                window.lcLockBodyScroll();
                                this._lockedScroll = true;
                            }
                            this.$nextTick(() => this.$refs.grid?.focus());
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
