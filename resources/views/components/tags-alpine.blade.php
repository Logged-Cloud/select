@props([
    'name',
    'id' => null,
    'items' => [],
    'selected' => [],
    'placeholder' => null,
    'iconSize' => null,
    'label' => null,
    'labelledBy' => null,
    'required' => false,
    'disabled' => false,
    'max' => null,
    'allowCustom' => true,
    'error' => null,
])
@php
    $triggerId = $id ?? ($label ? \Illuminate\Support\Str::camel(\Illuminate\Support\Str::slug($label, '_')) : $name);
    $listboxId = $triggerId.'-listbox';
    $inputId = $triggerId.'-input';
    $liveId = $triggerId.'-live';
    $fallbackId = $triggerId.'-fallback';
    $errorId = $triggerId.'-error';
    $iconSize = $iconSize ?? config('select.behavior.icon_size', '1.75rem');
    $placeholder = $placeholder ?? config('select.copy.tags_placeholder', 'Add a tag...');
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

    $selectedTags = collect($selected)->map(fn ($v) => (string) $v)->values()->all();

    $config = [
        'items' => $normalised,
        'selected' => $selectedTags,
        'listboxId' => $listboxId,
        'triggerId' => $triggerId,
        'max' => is_numeric($max) ? (int) $max : null,
        'allowCustom' => (bool) $allowCustom,
        'a11y' => [
            'options_available' => 'suggestions available',
            'added' => 'Added',
            'removed' => 'Removed',
            'limit_reached' => 'Maximum number of tags reached.',
        ],
    ];
@endphp
<div x-data="loggedCloudTags({{ \Illuminate\Support\Js::from($config) }})"
     x-init="$nextTick(() => { if ($refs.fallback) { $refs.fallback.name = ''; } })"
     class="lc-select lc-select--tags {{ $error ? 'lc-select--error' : '' }}"
     style="--lc-icon-size: {{ $iconSize }};"
     @click.outside="close()"
     @keydown.escape.window="if (open) { close(); }">

    @include('select::partials.fallback', [
        'name' => $name, 'items' => $normalised, 'selected' => $selectedTags,
        'multi' => true, 'fallbackId' => $fallbackId, 'required' => $required,
        'noJsLabel' => $noJsLabel, 'noJsCopy' => $noJsCopy,
    ])

    <template x-for="tag in values" :key="'h-'+tag">
        <input type="hidden" :name="@js($name).concat('[]')" :value="tag" x-ref="hidden">
    </template>

    {{-- The trigger doubles as the editor · clicking any blank area focuses
         the inline input. Chips render as buttons that x removes. --}}
    <div class="lc-select__trigger lc-select__trigger--tags"
         :class="{ 'is-open': open }"
         role="group"
         @if ($label) aria-label="{{ $label }}" @endif
         @if ($labelledBy) aria-labelledby="{{ $labelledBy }}" @endif
         @click="focusInput($event)">

        <template x-for="tag in values" :key="'chip-'+tag">
            <span class="lc-select__chip">
                <span x-text="titleFor(tag)"></span>
                <button type="button"
                        class="lc-select__chip-remove"
                        :aria-label="(a11y.removed || 'Removed') + ' ' + titleFor(tag)"
                        @click.stop="removeTag(tag)">
                    <svg viewBox="0 0 24 24" width="10" height="10" fill="none"
                         stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M6 6l12 12M6 18L18 6"></path>
                    </svg>
                </button>
            </span>
        </template>

        <input type="text"
               id="{{ $triggerId }}"
               x-ref="input"
               x-model="query"
               class="lc-select__tag-input"
               role="combobox"
               autocomplete="off"
               autocapitalize="off"
               spellcheck="false"
               aria-haspopup="listbox"
               aria-autocomplete="list"
               aria-controls="{{ $listboxId }}"
               :aria-expanded="open"
               :aria-activedescendant="open && activeKey ? optionId(activeKey) : null"
               placeholder="{{ $placeholder }}"
               @if ($required) aria-required="true" @endif
               @if ($disabled) aria-disabled="true" disabled @endif
               @if ($error) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
               @focus="open = true"
               @input="onInput()"
               @keydown.enter.prevent="commit()"
               @keydown.backspace="onBackspace()"
               @keydown.arrow-down.prevent="moveActive(1)"
               @keydown.arrow-up.prevent="moveActive(-1)"
               @keydown.tab="if (open && activeKey) { commit(); } else { close(); }">
    </div>

    {{-- Suggestion menu · only renders when filtered results exist, so an
         empty query with no matches shows nothing rather than a "no results"
         row (free-form entry is the primary path). --}}
    <div x-show="open && filtered.length > 0"
         x-cloak
         x-transition.opacity.duration.100ms
         class="lc-select__menu lc-select__menu--tags">
        <div class="lc-select__sheet-handle" aria-hidden="true"></div>
        <ul id="{{ $listboxId }}" role="listbox" class="lc-select__list">
            <template x-for="(opt, i) in filtered" :key="opt.key">
                <li :id="optionId(opt.key)"
                    role="option"
                    :aria-selected="values.includes(opt.key) ? 'true' : 'false'"
                    :class="{ 'is-active': activeKey === opt.key, 'is-selected': values.includes(opt.key) }"
                    class="lc-select__item"
                    @mousedown.prevent="addKey(opt.key)"
                    @mouseenter="activeKey = opt.key">
                    <span class="lc-select__item-icon" aria-hidden="true" x-show="opt.svg">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none"
                             stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path :d="opt.svg"></path>
                        </svg>
                    </span>
                    <span class="lc-select__item-body">
                        <span class="lc-select__item-title" x-text="opt.title"></span>
                        <span class="lc-select__item-subtitle" x-show="opt.subtitle" x-text="opt.subtitle"></span>
                    </span>
                </li>
            </template>
        </ul>
    </div>

    <div x-show="open && filtered.length > 0" x-cloak class="lc-select__backdrop" @click="close()"></div>

    @if ($error)
        <p id="{{ $errorId }}" class="lc-select__error" role="alert">{{ $error }}</p>
    @endif

    <div id="{{ $liveId }}" class="lc-select__live" aria-live="polite" aria-atomic="true" x-text="liveMessage"></div>

    @once
        @include('select::styles')
    @endonce
    @once
        <script data-lc-tags-alpine>
            (function () {
                if (window.__loggedCloudTagsLoaded) return;
                window.__loggedCloudTagsLoaded = true;

                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('loggedCloudTags', (config) => ({
                        items: config.items || [],
                        values: (config.selected || []).slice(),
                        max: config.max,
                        allowCustom: config.allowCustom !== false,
                        listboxId: config.listboxId,
                        triggerId: config.triggerId,
                        a11y: config.a11y || {},

                        open: false,
                        query: '',
                        activeKey: null,
                        liveMessage: '',

                        get filtered() {
                            const q = this.query.trim().toLowerCase();
                            const out = this.items.filter((o) => {
                                if (this.values.includes(o.key)) return false;
                                if (!q) return true;
                                return o.title.toLowerCase().includes(q)
                                    || (o.subtitle || '').toLowerCase().includes(q)
                                    || o.key.toLowerCase().includes(q);
                            });
                            if (out.length > 0 && !out.find((o) => o.key === this.activeKey)) {
                                this.activeKey = out[0].key;
                            }
                            return out;
                        },

                        titleFor(key) {
                            const it = this.items.find((o) => o.key === key);
                            return it ? it.title : key;
                        },

                        optionId(key) {
                            const safe = String(key).replace(/[^a-zA-Z0-9_-]/g, (c) => '_' + c.charCodeAt(0).toString(16));
                            return this.triggerId + '__opt-' + safe;
                        },

                        focusInput(e) {
                            if (e.target.closest('.lc-select__chip')) return;
                            this.$refs.input?.focus();
                        },

                        close() {
                            this.open = false;
                        },

                        onInput() {
                            this.open = true;
                        },

                        onBackspace() {
                            if (this.query === '' && this.values.length > 0) {
                                this.removeTag(this.values[this.values.length - 1]);
                            }
                        },

                        moveActive(delta) {
                            this.open = true;
                            const list = this.filtered;
                            if (list.length === 0) return;
                            const i = list.findIndex((o) => o.key === this.activeKey);
                            const next = (i + delta + list.length) % list.length;
                            this.activeKey = list[next].key;
                        },

                        commit() {
                            // Prefer the highlighted suggestion when one is active,
                            // otherwise fall back to free-form custom value entry.
                            if (this.activeKey && this.filtered.find((o) => o.key === this.activeKey)) {
                                this.addKey(this.activeKey);
                                return;
                            }
                            const raw = this.query.trim();
                            if (!raw) return;
                            if (!this.allowCustom) return;
                            this.addKey(raw);
                        },

                        addKey(key) {
                            if (!key) return;
                            if (this.values.includes(key)) return;
                            if (this.max && this.values.length >= this.max) {
                                this.liveMessage = this.a11y.limit_reached || 'Limit reached.';
                                return;
                            }
                            this.values.push(key);
                            this.query = '';
                            this.activeKey = null;
                            this.liveMessage = (this.a11y.added || 'Added') + ' ' + this.titleFor(key);
                            this.notifyChange();
                        },

                        removeTag(key) {
                            const idx = this.values.indexOf(key);
                            if (idx < 0) return;
                            this.values.splice(idx, 1);
                            this.liveMessage = (this.a11y.removed || 'Removed') + ' ' + this.titleFor(key);
                            this.notifyChange();
                            this.$refs.input?.focus();
                        },

                        notifyChange() {
                            this.$nextTick(() => {
                                // Bubble a change event from the wrapper so Livewire
                                // entangle / external listeners pick it up.
                                this.$root?.dispatchEvent(new CustomEvent('change', {
                                    bubbles: true,
                                    detail: { values: this.values.slice() },
                                }));
                            });
                        },
                    }));
                });
            })();
        </script>
    @endonce
</div>
