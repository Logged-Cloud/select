@props([
    'name',
    'id' => null,
    'levels' => [],
    'label' => null,
    'labelledBy' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
])
@php
    // Each level resolves a dataset (string shortcut OR explicit shape) into
    // {name, title, viewBox, items, requires}. The whole drilldown is one
    // trigger; the menu body swaps between pre-rendered SVGs as the user
    // drills in.
    $resolvedLevels = collect($levels)->map(function ($lvl) {
        $dataset = $lvl['dataset'] ?? null;
        $loaded = null;
        if ($dataset === 'world') {
            $loaded = \LoggedCloud\Select\MapData::world();
        } elseif ($dataset === 'uk') {
            $loaded = \LoggedCloud\Select\MapData::uk();
        } elseif (is_string($dataset) && str_starts_with($dataset, 'uk:')) {
            $loaded = \LoggedCloud\Select\MapData::ukRegion(substr($dataset, 3));
        } elseif (isset($lvl['items']) && isset($lvl['viewBox'])) {
            // Explicit inline shape · the host supplied raw items + viewBox.
            $loaded = ['items' => $lvl['items'], 'viewBox' => $lvl['viewBox']];
        } else {
            $loaded = ['items' => [], 'viewBox' => '0 0 1000 500'];
        }
        return [
            'name' => $lvl['name'],
            'title' => $lvl['title'] ?? ucfirst($lvl['name']),
            // `requires` example: ['country' => 'gb'] · level only opens if
            // the previously-set field matches the value. Use '*' to mean
            // "any non-empty value of that field".
            'requires' => $lvl['requires'] ?? null,
            'viewBox' => $loaded['viewBox'] ?? '0 0 1000 500',
            'items' => collect($loaded['items'] ?? [])->map(function ($item) {
                $get = is_array($item) ? fn ($k) => $item[$k] ?? null : fn ($k) => $item->{$k} ?? null;
                $row = [
                    'key' => (string) $get('key'),
                    'title' => (string) $get('title'),
                ];
                if ($get('path') !== null) {
                    $row['path'] = (string) $get('path');
                }
                if ($get('cx') !== null) {
                    $row['cx'] = (float) $get('cx');
                    $row['cy'] = (float) $get('cy');
                }
                return $row;
            })->values()->all(),
        ];
    })->values()->all();

    $triggerId = $id ?? ($label ? \Illuminate\Support\Str::camel(\Illuminate\Support\Str::slug($label, '_')) : $name);
    $liveId = $triggerId.'-live';
    $errorId = $triggerId.'-error';
    $placeholder = $placeholder ?? config('select.copy.placeholder', 'Pick on the map');

    $config = [
        'levels' => $resolvedLevels,
        'triggerId' => $triggerId,
        'placeholder' => $placeholder,
        'a11y' => [
            'back' => 'Back',
            'selected' => 'Selected',
            'final' => 'Selection complete.',
        ],
    ];
@endphp
<div x-data="loggedCloudMapDrilldown({{ \Illuminate\Support\Js::from($config) }})"
     x-init="$nextTick(() => { if ($refs.fallback) { $refs.fallback.name = ''; } })"
     class="lc-select lc-select--drilldown {{ $error ? 'lc-select--error' : '' }}"
     @click.outside="close()"
     @keydown.escape.window="if (open) { close(); }">

    {{-- No-JS fallback · the drilldown only makes sense with JS, but we
         still expose a native <select> for the FIRST level so a JS-off
         user can at least submit the top-level value. --}}
    @php
        $firstLevelItems = $resolvedLevels[0]['items'] ?? [];
        $firstLevelName = $resolvedLevels[0]['name'] ?? $name;
        $fallbackId = $triggerId.'-fallback';
        $noJsLabel = config('select.copy.no_js_indicator', 'JS off');
        $noJsCopy = config('select.copy.no_js_warning', 'JavaScript is needed for the rich picker.');
    @endphp
    @include('select::partials.fallback', [
        'name' => $firstLevelName, 'items' => $firstLevelItems, 'selected' => null,
        'multi' => false, 'fallbackId' => $fallbackId, 'required' => $required,
        'noJsLabel' => $noJsLabel, 'noJsCopy' => $noJsCopy,
    ])

    {{-- One hidden input per level · standard form posts. --}}
    @foreach ($resolvedLevels as $level)
        <input type="hidden" name="{{ $level['name'] }}" :value="values[@js($level['name'])] || ''">
    @endforeach

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
         @keydown.space.prevent="toggle()"
         @keydown.arrow-down.prevent="if (!open) toggle()">
        <span class="lc-select__chosen">
            <span x-text="summary() || @js($placeholder)"
                  :class="summary() ? 'lc-select__placeholder--filled' : 'lc-select__placeholder'"></span>
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

        {{-- Breadcrumb + back · always visible when level > 0 so the user
             can see where they are and step back up the hierarchy. --}}
        <div class="lc-map__crumbs" role="group" aria-label="Drilldown path">
            <button type="button" class="lc-map__back" x-show="currentLevel > 0" @click="back()" :aria-label="a11y.back">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </button>
            <template x-for="(lvl, i) in levels" :key="lvl.name">
                <span class="lc-map__crumb" x-show="i <= currentLevel">
                    <span class="lc-map__crumb-sep" x-show="i > 0" aria-hidden="true">›</span>
                    <span x-text="values[lvl.name] ? titleOf(i, values[lvl.name]) : lvl.title"
                          :class="i === currentLevel ? 'lc-map__crumb-active' : ''"></span>
                </span>
            </template>
        </div>

        {{-- Pre-render every level's SVG · x-show toggles between them as
             the user drills. Pre-render means Blade @foreach owns the SVG
             children (Alpine's x-for inside <svg> silently fails). --}}
        @foreach ($resolvedLevels as $i => $level)
            @php
                $svgId = $triggerId.'-svg-'.$i;
            @endphp
            <svg x-show="currentLevel === {{ $i }}"
                 id="{{ $svgId }}"
                 viewBox="{{ $level['viewBox'] }}"
                 role="listbox"
                 class="lc-map"
                 preserveAspectRatio="xMidYMid meet"
                 :aria-label="'{{ $level['title'] }}'">
                @foreach ($level['items'] as $j => $opt)
                    @php
                        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $opt['key']);
                        $optId = $svgId.'__opt-'.$safe;
                    @endphp
                    @if (isset($opt['path']))
                        <path id="{{ $optId }}"
                              role="option"
                              d="{{ $opt['path'] }}"
                              aria-label="{{ $opt['title'] }}"
                              :aria-selected="values[@js($level['name'])] === @js($opt['key']) ? 'true' : 'false'"
                              :class="{ 'is-active': currentLevel === {{ $i }} && cursor === {{ $j }}, 'is-selected': values[@js($level['name'])] === @js($opt['key']) }"
                              class="lc-map__item"
                              @click="pick({{ $i }}, {{ $j }})"
                              @mouseenter="cursor = {{ $j }}" />
                    @elseif (isset($opt['cx']))
                        <circle id="{{ $optId }}"
                                role="option"
                                cx="{{ $opt['cx'] }}" cy="{{ $opt['cy'] }}" r="6"
                                aria-label="{{ $opt['title'] }}"
                                :aria-selected="values[@js($level['name'])] === @js($opt['key']) ? 'true' : 'false'"
                                :class="{ 'is-active': currentLevel === {{ $i }} && cursor === {{ $j }}, 'is-selected': values[@js($level['name'])] === @js($opt['key']) }"
                                class="lc-map__point"
                                @click="pick({{ $i }}, {{ $j }})"
                                @mouseenter="cursor = {{ $j }}" />
                    @endif
                @endforeach
            </svg>
        @endforeach

        <div class="lc-map__hover" x-text="hoverTitle()"></div>
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
        <script data-lc-map-drilldown-alpine>
            (function () {
                if (window.__loggedCloudMapDrilldownLoaded) return;
                window.__loggedCloudMapDrilldownLoaded = true;

                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('loggedCloudMapDrilldown', (config) => ({
                        levels: config.levels || [],
                        triggerId: config.triggerId,
                        placeholder: config.placeholder || '',
                        a11y: config.a11y || {},
                        open: false,
                        currentLevel: 0,
                        cursor: 0,
                        values: {},
                        liveMessage: '',

                        toggle() {
                            this.open ? this.close() : this.openMenu();
                        },

                        openMenu() {
                            this.open = true;
                            // Restart from the deepest unfinished level so a
                            // re-open after a partial drilldown picks up where
                            // the user left off rather than rewinding to 0.
                            this.currentLevel = this._deepestEnabled();
                            this.cursor = 0;
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

                        // Find the deepest level whose `requires` is satisfied.
                        _deepestEnabled() {
                            for (let i = this.levels.length - 1; i >= 0; i--) {
                                if (this._levelEnabled(i)) return i;
                            }
                            return 0;
                        },

                        _levelEnabled(i) {
                            if (i === 0) return true;
                            const req = this.levels[i].requires;
                            if (!req) return true;
                            for (const [field, expected] of Object.entries(req)) {
                                const v = this.values[field];
                                if (!v) return false;
                                if (expected !== '*' && expected !== v) return false;
                            }
                            return true;
                        },

                        pick(levelIdx, optIdx) {
                            const lvl = this.levels[levelIdx];
                            const opt = lvl.items[optIdx];
                            if (!opt) return;
                            this.values[lvl.name] = opt.key;
                            // Clear deeper level selections · they no longer
                            // make sense once the parent changes.
                            for (let j = levelIdx + 1; j < this.levels.length; j++) {
                                delete this.values[this.levels[j].name];
                            }
                            const nextIdx = levelIdx + 1;
                            if (nextIdx < this.levels.length && this._levelEnabled(nextIdx)) {
                                // Drill in · keep menu open, swap level.
                                this.currentLevel = nextIdx;
                                this.cursor = 0;
                                this.liveMessage = (this.a11y.selected || 'Selected') + ' ' + opt.title;
                                this.$nextTick(() => this._dispatchHiddenChange());
                            } else {
                                // No further level · final pick, close.
                                this.liveMessage = (this.a11y.final || 'Selection complete.');
                                this.$nextTick(() => this._dispatchHiddenChange());
                                this.close();
                            }
                        },

                        back() {
                            if (this.currentLevel <= 0) return;
                            // Clear the current level's stored value so the
                            // hidden input doesn't keep a stale pick.
                            const curName = this.levels[this.currentLevel].name;
                            delete this.values[curName];
                            this.currentLevel--;
                            this.cursor = 0;
                            this.$nextTick(() => this._dispatchHiddenChange());
                        },

                        titleOf(levelIdx, key) {
                            const lvl = this.levels[levelIdx];
                            const it = lvl.items.find((o) => o.key === key);
                            return it ? it.title : key;
                        },

                        summary() {
                            // Build a "Country › Region › Borough" string from
                            // whatever values have been set; trigger shows it.
                            const parts = [];
                            for (const lvl of this.levels) {
                                const v = this.values[lvl.name];
                                if (!v) break;
                                parts.push(this.titleOf(this.levels.indexOf(lvl), v));
                            }
                            return parts.join(' › ');
                        },

                        hoverTitle() {
                            const lvl = this.levels[this.currentLevel];
                            if (!lvl) return '';
                            const opt = lvl.items[this.cursor];
                            return opt ? opt.title : '';
                        },

                        _dispatchHiddenChange() {
                            this.$el.querySelectorAll('input[type=hidden]').forEach((el) =>
                                el.dispatchEvent(new Event('change', { bubbles: true })));
                        },
                    }));
                });
            })();
        </script>
    @endonce
</div>
