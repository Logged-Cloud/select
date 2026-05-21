@props([
    'name',
    'id' => null,
    'selected' => null,
    'viewBox' => '0 0 1000 500',
    'outline' => null,
    'items' => [],          // background polygons (countries, regions …) for context
    'dataset' => null,      // 'world' / 'uk' / 'uk:greater-london' shortcuts
    'placeholder' => null,
    'label' => null,
    'labelledBy' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
])
@php
    // Reuse the bundled-dataset loaders from map-svg-alpine · the dataset's
    // items become non-clickable background scenery and viewBox sets the
    // projection. The user clicks anywhere inside the SVG to drop a pin.
    if (empty($items) && $dataset !== null) {
        $loaded = match ($dataset) {
            'world' => \LoggedCloud\Select\MapData::world(),
            'uk' => \LoggedCloud\Select\MapData::uk(),
            default => null,
        };
        if ($loaded === null && is_string($dataset) && str_starts_with($dataset, 'uk:')) {
            $loaded = \LoggedCloud\Select\MapData::ukRegion(substr($dataset, 3));
        }
        if ($loaded !== null) {
            $items = $loaded['items'] ?? [];
            $viewBox = $loaded['viewBox'] ?? $viewBox;
            $outline = $outline ?? ($loaded['outline'] ?? null);
        }
    }

    $triggerId = $id ?? ($label ? \Illuminate\Support\Str::camel(\Illuminate\Support\Str::slug($label, '_')) : $name);
    $svgId = $triggerId.'-svg';
    $fallbackId = $triggerId.'-fallback';
    $errorId = $triggerId.'-error';
    $placeholder = $placeholder ?? config('select.copy.placeholder', 'Drop a pin on the map');
    $noJsLabel = config('select.copy.no_js_indicator', 'JS off');
    $noJsCopy = config('select.copy.no_js_warning', 'JavaScript is needed for the map picker.');

    $normalised = collect($items)->map(function ($item) {
        $get = is_array($item) ? fn ($k) => $item[$k] ?? null : fn ($k) => $item->{$k} ?? null;
        return [
            'key' => (string) $get('key'),
            'title' => (string) $get('title'),
            'path' => (string) ($get('path') ?? ''),
        ];
    })->values()->all();

    // `selected` is expected as "x,y" in the viewBox's coord space. The
    // component emits the same shape back through the hidden input.
    $initial = null;
    if (is_string($selected) && str_contains($selected, ',')) {
        [$sx, $sy] = array_map('trim', explode(',', $selected, 2));
        if (is_numeric($sx) && is_numeric($sy)) {
            $initial = ['x' => (float) $sx, 'y' => (float) $sy];
        }
    }

    $config = [
        'items' => $normalised,
        'outline' => $outline,
        'viewBox' => $viewBox,
        'svgId' => $svgId,
        'triggerId' => $triggerId,
        'placeholder' => $placeholder,
        'pin' => $initial,
        'a11y' => [
            'pin_placed' => 'Pin placed at',
            'no_pin' => 'No pin set',
        ],
    ];
@endphp
<div x-data="loggedCloudMapPin({{ \Illuminate\Support\Js::from($config) }})"
     x-init="$nextTick(() => { if ($refs.fallback) { $refs.fallback.name = ''; } })"
     class="lc-select lc-select--map {{ $error ? 'lc-select--error' : '' }}"
     @click.outside="close()"
     @keydown.escape.window="if (open) { close(); }">

    {{-- No-JS fallback · no items to swap to a native <select>, but we
         include the partial with an empty options list so the noscript-
         gated indicator + name-clearing-on-init pattern stays consistent
         across the variant family. A JS-off user just sees the "JS off"
         pill and the hidden input stays empty. --}}
    @include('select::partials.fallback', [
        'name' => $name, 'items' => [], 'selected' => $selected,
        'multi' => false, 'fallbackId' => $fallbackId, 'required' => $required,
        'noJsLabel' => $noJsLabel, 'noJsCopy' => $noJsCopy,
    ])
    <input type="hidden" name="{{ $name }}" :value="serialised()" x-ref="hidden"
           @if ($required) required @endif>

    <div id="{{ $triggerId }}"
         x-ref="trigger"
         tabindex="{{ $disabled ? '-1' : '0' }}"
         class="lc-select__trigger"
         :class="{ 'is-open': open }"
         role="combobox"
         aria-haspopup="dialog"
         :aria-expanded="open"
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
            <span x-text="pin ? (pin.x + ', ' + pin.y) : @js($placeholder)"
                  :class="pin ? 'lc-select__placeholder--filled' : 'lc-select__placeholder'"></span>
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
        <svg id="{{ $svgId }}"
             x-ref="svg"
             viewBox="{{ $viewBox }}"
             class="lc-map lc-map--pinnable"
             role="application"
             aria-label="Click anywhere to drop a pin"
             preserveAspectRatio="xMidYMid meet"
             @click="placeFromEvent($event)">
            @if ($outline)
                <path d="{{ $outline }}" class="lc-map__outline" aria-hidden="true" />
            @endif
            @foreach ($normalised as $opt)
                @if (! empty($opt['path']))
                    <path d="{{ $opt['path'] }}" class="lc-map__item lc-map__item--bg" aria-hidden="true" />
                @endif
            @endforeach
            {{-- Always render the <g> · Alpine's <template x-if> inside an
                 <svg> creates HTML-namespace nodes that the browser drops,
                 so we use x-show to toggle a real SVG element instead. --}}
            <g class="lc-map__pin"
               x-show="pin"
               :style="pin ? ('transform: translate(' + pin.x + 'px,' + pin.y + 'px)') : ''">
                <circle r="10" class="lc-map__pin-halo" />
                <circle r="4"  class="lc-map__pin-dot" />
            </g>
        </svg>
        <div class="lc-map__hover">
            <span x-text="pin ? (a11y.pin_placed + ' ' + pin.x + ', ' + pin.y) : a11y.no_pin"></span>
            <button type="button" x-show="pin" x-cloak class="lc-map__pin-clear" @click="clearPin()" aria-label="Clear pin">×</button>
        </div>
    </div>

    @if ($error)
        <p id="{{ $errorId }}" class="lc-select__error" role="alert">{{ $error }}</p>
    @endif

    @once
        @include('select::styles')
    @endonce
    @once
        <script data-lc-map-pin-alpine>
            (function () {
                if (window.__loggedCloudMapPinLoaded) return;
                window.__loggedCloudMapPinLoaded = true;

                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('loggedCloudMapPin', (config) => ({
                        viewBox: config.viewBox || '0 0 1000 500',
                        triggerId: config.triggerId,
                        svgId: config.svgId,
                        placeholder: config.placeholder || '',
                        a11y: config.a11y || {},
                        pin: config.pin || null,
                        open: false,

                        toggle() {
                            this.open ? this.close() : this.openMenu();
                        },

                        openMenu() {
                            this.open = true;
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
                            this.$nextTick(() => document.getElementById(this.triggerId)?.focus());
                        },

                        // Convert a click's screen coordinates into the SVG's
                        // own viewBox coordinate space · works regardless of
                        // CSS scaling because we use the SVG's CTM inverse.
                        placeFromEvent(e) {
                            const svg = this.$refs.svg;
                            if (!svg || typeof svg.getScreenCTM !== 'function') return;
                            const pt = svg.createSVGPoint();
                            pt.x = e.clientX;
                            pt.y = e.clientY;
                            const ctm = svg.getScreenCTM();
                            if (!ctm) return;
                            const inv = ctm.inverse();
                            const local = pt.matrixTransform(inv);
                            this.pin = { x: Math.round(local.x), y: Math.round(local.y) };
                            if (this.$refs.hidden) {
                                this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        },

                        clearPin() {
                            this.pin = null;
                            if (this.$refs.hidden) {
                                this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        },

                        // What the hidden input emits · "x,y" or empty.
                        serialised() {
                            return this.pin ? (this.pin.x + ',' + this.pin.y) : '';
                        },
                    }));
                });
            })();
        </script>
    @endonce
</div>
