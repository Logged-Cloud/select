@props([
    'name',
    'id' => null,
    'items' => [],
    'selected' => null,
    'viewBox' => '0 0 1000 500',
    'outline' => null,
    'placeholder' => null,
    'label' => null,
    'labelledBy' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
    'dataset' => null,
    'dependsOn' => null,
    'dependsMessage' => null,
])
@php
    // The component supports two ways to bring data: pass items + viewBox
    // (+ optional outline) directly, OR set dataset="world" / "uk" to load
    // a bundled JSON file. Inline data wins when both are provided.
    if (empty($items) && $dataset !== null) {
        $loaded = match ($dataset) {
            'world' => \LoggedCloud\Select\MapData::world(),
            'uk' => \LoggedCloud\Select\MapData::uk(),
            default => null,
        };
        if ($loaded === null) {
            // Town buckets are addressed as uk-towns:london, uk-towns:manchester …
            if (str_starts_with($dataset, 'uk-towns:')) {
                $loaded = \LoggedCloud\Select\MapData::ukTowns(substr($dataset, 9));
            }
        }
        if ($loaded !== null) {
            $items = $loaded['items'] ?? [];
            $viewBox = $loaded['viewBox'] ?? $viewBox;
            $outline = $outline ?? ($loaded['outline'] ?? null);
        }
    }

    $triggerId = $id ?? ($label ? \Illuminate\Support\Str::camel(\Illuminate\Support\Str::slug($label, '_')) : $name);
    $svgId = $triggerId.'-svg';
    $liveId = $triggerId.'-live';
    $fallbackId = $triggerId.'-fallback';
    $errorId = $triggerId.'-error';
    $placeholder = $placeholder ?? config('select.copy.placeholder', 'Select an option');
    $noJsLabel = config('select.copy.no_js_indicator', 'JS off');
    $noJsCopy = config('select.copy.no_js_warning', 'JavaScript is needed for the rich picker.');

    $normalised = collect($items)->map(function ($item) {
        $get = is_array($item) ? fn ($k) => $item[$k] ?? null : fn ($k) => $item->{$k} ?? null;
        $row = [
            'key' => (string) $get('key'),
            'title' => (string) $get('title'),
        ];
        // Polygons carry a `path`; city/town points carry `cx`/`cy` instead.
        if ($get('path') !== null) {
            $row['path'] = (string) $get('path');
        }
        if ($get('cx') !== null) {
            $row['cx'] = (float) $get('cx');
            $row['cy'] = (float) $get('cy');
        }
        if ($get('parent') !== null) {
            $row['parent'] = (string) $get('parent');
        }
        return $row;
    })->values()->all();

    $dependsMessage = $dependsMessage ?? ($dependsOn ? "Select {$dependsOn} first" : null);

    $config = [
        'items' => $normalised,
        'selected' => $selected,
        'svgId' => $svgId,
        'triggerId' => $triggerId,
        'outline' => $outline,
        'viewBox' => $viewBox,
        'placeholder' => $placeholder,
        'dependsOn' => $dependsOn,
        'dependsMessage' => $dependsMessage,
        'a11y' => [
            'options_available' => 'regions available on the map',
            'no_options' => 'No regions on the map.',
            'selected' => 'Selected',
            'parent_changed' => 'Selection cleared because the parent changed.',
        ],
    ];
@endphp
<div x-data="loggedCloudMapSvg({{ \Illuminate\Support\Js::from($config) }})"
     x-init="$nextTick(() => { if ($refs.fallback) { $refs.fallback.name = ''; } })"
     class="lc-select lc-select--map {{ $error ? 'lc-select--error' : '' }}"
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
         :aria-activedescendant="open ? activeOptionId() : null"
         @if ($label) aria-label="{{ $label }}"
         @elseif ($labelledBy) aria-labelledby="{{ $labelledBy }}"
         @else aria-label="{{ $placeholder }}" @endif
         @if ($required) aria-required="true" @endif
         @if ($disabled) aria-disabled="true" @endif
         @if ($error) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
         :aria-disabled="isLocked ? 'true' : null"
         @click="if (isLocked) return; toggle()"
         @keydown.arrow-down.prevent="open ? move(1) : openMenu()"
         @keydown.arrow-up.prevent="open ? move(-1) : openMenu()"
         @keydown.home.prevent="if (open) { cursor = 0; }"
         @keydown.end.prevent="if (open) { cursor = items.length - 1; }"
         @keydown.enter.prevent="open ? pickAt(cursor) : openMenu()"
         @keydown.space.prevent="open ? pickAt(cursor) : openMenu()"
         @keydown.tab="if (open) { close(); }">
        <span class="lc-select__chosen">
            <span x-text="selected ? selected.title : effectivePlaceholder"
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
        {{-- SVG children are rendered server-side · Alpine's x-for inside
             an <svg> creates HTML-namespace nodes that the browser ignores.
             Each rendered child still binds its highlight / selected state
             via Alpine class + aria expressions. --}}
        <svg id="{{ $svgId }}" role="listbox" viewBox="{{ $viewBox }}" class="lc-map" preserveAspectRatio="xMidYMid meet"
             @if ($label) aria-label="{{ $label }}" @else aria-label="{{ $placeholder }}" @endif>
            @if ($outline)
                <path d="{{ $outline }}" class="lc-map__outline" aria-hidden="true" />
            @endif
            @foreach ($normalised as $i => $opt)
                @php
                    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $opt['key']);
                    $optId = $svgId.'__opt-'.$safe;
                @endphp
                @if (isset($opt['path']))
                    <path id="{{ $optId }}"
                          role="option"
                          d="{{ $opt['path'] }}"
                          aria-label="{{ $opt['title'] }}"
                          :aria-selected="selected?.key === @js($opt['key']) ? 'true' : 'false'"
                          :class="{ 'is-active': cursor === {{ $i }}, 'is-selected': selected?.key === @js($opt['key']) }"
                          class="lc-map__item"
                          @click="pickAt({{ $i }}); focusTrigger();"
                          @mouseenter="cursor = {{ $i }}" />
                @elseif (isset($opt['cx']))
                    <circle id="{{ $optId }}"
                            role="option"
                            cx="{{ $opt['cx'] }}" cy="{{ $opt['cy'] }}" r="6"
                            aria-label="{{ $opt['title'] }}"
                            :aria-selected="selected?.key === @js($opt['key']) ? 'true' : 'false'"
                            :class="{ 'is-active': cursor === {{ $i }}, 'is-selected': selected?.key === @js($opt['key']) }"
                            class="lc-map__point"
                            @click="pickAt({{ $i }}); focusTrigger();"
                            @mouseenter="cursor = {{ $i }}" />
                @endif
            @endforeach
        </svg>
        <div class="lc-map__hover" x-text="items[cursor]?.title || (selected?.title || '')"></div>
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
        <script data-lc-map-svg-alpine>
            (function () {
                if (window.__loggedCloudMapSvgLoaded) return;
                window.__loggedCloudMapSvgLoaded = true;

                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('loggedCloudMapSvg', (config) => ({
                        items: config.items || [],
                        outline: config.outline || null,
                        viewBox: config.viewBox || '0 0 1000 500',
                        svgId: config.svgId,
                        triggerId: config.triggerId,
                        a11y: config.a11y || {},
                        placeholder: config.placeholder || '',
                        dependsOn: config.dependsOn || null,
                        dependsMessage: config.dependsMessage || '',
                        parentValue: '',
                        value: config.selected || '',
                        selected: null,
                        open: false,
                        cursor: 0,
                        liveMessage: '',

                        get isLocked() {
                            return this.dependsOn && !this.parentValue;
                        },

                        get effectivePlaceholder() {
                            return this.isLocked ? (this.dependsMessage || this.placeholder) : this.placeholder;
                        },

                        init() {
                            this.syncSelectedFromValue();
                            if (this.dependsOn) {
                                this._readParent();
                                this._parentListener = (e) => {
                                    if (!e.target || e.target.name !== this.dependsOn) return;
                                    queueMicrotask(() => this._readParent());
                                };
                                document.addEventListener('change', this._parentListener);
                            }
                        },

                        destroy() {
                            if (this._parentListener) {
                                document.removeEventListener('change', this._parentListener);
                                this._parentListener = null;
                            }
                        },

                        _readParent() {
                            const el = document.querySelector('[name="' + this.dependsOn + '"]');
                            const next = el ? String(el.value || '') : '';
                            if (next === this.parentValue) return;
                            const wasSet = !!this.parentValue;
                            const hadValue = !!this.value;
                            this.parentValue = next;
                            if (hadValue) {
                                this.value = '';
                                this.selected = null;
                                if (this.$refs.hidden) {
                                    this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true }));
                                }
                                if (wasSet) {
                                    this.liveMessage = this.a11y.parent_changed || 'Selection cleared.';
                                }
                            }
                        },

                        optionId(key) {
                            return this.svgId + '__opt-' + window.lcSafeId(key);
                        },

                        activeOptionId() {
                            if (!this.open) return null;
                            const opt = this.items[this.cursor];
                            return opt ? this.optionId(opt.key) : null;
                        },

                        syncSelectedFromValue() {
                            this.selected = this.items.find((o) => o.key === this.value) || null;
                        },

                        toggle() {
                            this.open ? this.close() : this.openMenu();
                        },

                        openMenu() {
                            this.open = true;
                            // Cursor starts on the selected item or the first.
                            const i = this.items.findIndex((o) => o.key === this.value);
                            this.cursor = i >= 0 ? i : 0;
                            this.announceResults();
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
                            this.focusTrigger();
                        },

                        focusTrigger() {
                            this.$nextTick(() => this.$refs.trigger?.focus());
                        },

                        move(delta) {
                            const max = this.items.length - 1;
                            this.cursor = Math.max(0, Math.min(this.cursor + delta, max));
                        },

                        pickAt(i) {
                            const opt = this.items[i];
                            if (!opt) return;
                            this.value = opt.key;
                            this.selected = opt;
                            this.liveMessage = (this.a11y.selected || 'Selected') + ' ' + opt.title;
                            this.open = false;
                            if (this._lockedScroll) {
                                window.lcUnlockBodyScroll();
                                this._lockedScroll = false;
                            }
                            if (this.$refs.hidden) {
                                this.$refs.hidden.value = opt.key;
                                this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        },

                        announceResults() {
                            const n = this.items.length;
                            this.liveMessage = n === 0
                                ? (this.a11y.no_options || 'No regions on the map.')
                                : n + ' ' + (this.a11y.options_available || 'regions available');
                        },
                    }));
                });
            })();
        </script>
    @endonce
</div>
