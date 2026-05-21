@props([
    'name',
    'id' => null,
    'items' => [],
    'selected' => [],            // ordered array of keys; defaults to items' natural order
    'label' => null,
    'labelledBy' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
])
@php
    $triggerId = $id ?? ($label ? \Illuminate\Support\Str::camel(\Illuminate\Support\Str::slug($label, '_')) : $name);
    $liveId = $triggerId.'-live';
    $fallbackId = $triggerId.'-fallback';
    $errorId = $triggerId.'-error';
    $noJsLabel = config('select.copy.no_js_indicator', 'JS off');
    $noJsCopy = config('select.copy.no_js_warning', 'JavaScript is needed for the rich picker.');

    $normalised = collect($items)->map(function ($item) {
        $get = is_array($item) ? fn ($k) => $item[$k] ?? null : fn ($k) => $item->{$k} ?? null;
        return [
            'key' => (string) $get('key'),
            'title' => (string) $get('title'),
            'subtitle' => (string) ($get('subtitle') ?? ''),
            'svg' => (string) ($get('svg') ?? ''),
        ];
    })->values()->all();

    $selectedKeys = collect($selected)->map(fn ($v) => (string) $v)->values()->all();
    if (empty($selectedKeys)) {
        $selectedKeys = collect($normalised)->pluck('key')->all();
    }

    $config = [
        'items' => $normalised,
        'order' => $selectedKeys,
        'triggerId' => $triggerId,
        'a11y' => [
            'moved_up' => 'Moved up',
            'moved_down' => 'Moved down',
        ],
    ];
@endphp
<div x-data="loggedCloudSortableRank({{ \Illuminate\Support\Js::from($config) }})"
     x-init="$nextTick(() => { if ($refs.fallback) { $refs.fallback.name = ''; } })"
     class="lc-rank {{ $error ? 'lc-rank--error' : '' }}"
     id="{{ $triggerId }}">

    @include('select::partials.fallback', [
        'name' => $name, 'items' => $normalised, 'selected' => $selectedKeys,
        'multi' => true, 'fallbackId' => $fallbackId, 'required' => $required,
        'noJsLabel' => $noJsLabel, 'noJsCopy' => $noJsCopy,
    ])

    {{-- One hidden input per ordered key · standard array post. The DOM
         order is the source of truth so the server gets the rank intact. --}}
    <template x-for="(key, idx) in order" :key="'h-'+key">
        <input type="hidden" :name="@js($name).concat('[]')" :value="key">
    </template>

    <ol class="lc-rank__list"
        role="listbox"
        aria-roledescription="sortable list"
        x-ref="list"
        @if ($label) aria-label="{{ $label }}" @endif
        @if ($labelledBy) aria-labelledby="{{ $labelledBy }}" @endif
        @if ($required) aria-required="true" @endif>
        <template x-for="(key, idx) in order" :key="key">
            <li class="lc-rank__row"
                :class="{ 'is-dragging': dragKey === key, 'is-over': overKey === key }"
                role="option"
                :aria-selected="false"
                draggable="true"
                :id="@js($triggerId).concat('__row-' + key)"
                :tabindex="idx === activeIdx ? 0 : -1"
                @dragstart="onDragStart($event, key)"
                @dragover.prevent="onDragOver(key)"
                @dragend="onDragEnd()"
                @drop.prevent="onDrop(key)"
                @keydown.arrow-down.prevent="moveActive(1)"
                @keydown.arrow-up.prevent="moveActive(-1)"
                @keydown.space.prevent="cycleDirection($event, key)"
                @keydown.alt.arrow-up.prevent="moveBy(idx, -1)"
                @keydown.alt.arrow-down.prevent="moveBy(idx, 1)">
                <span class="lc-rank__handle" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none"
                         stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <line x1="4" y1="9" x2="20" y2="9"/>
                        <line x1="4" y1="15" x2="20" y2="15"/>
                    </svg>
                </span>
                <span class="lc-rank__index" x-text="idx + 1" aria-hidden="true"></span>
                <span class="lc-rank__title" x-text="titleOf(key)"></span>
                <span class="lc-rank__nudge">
                    <button type="button" class="lc-rank__btn"
                            :aria-label="(a11y.moved_up || 'Move up') + ' ' + titleOf(key)"
                            :disabled="idx === 0"
                            @click="moveBy(idx, -1)">↑</button>
                    <button type="button" class="lc-rank__btn"
                            :aria-label="(a11y.moved_down || 'Move down') + ' ' + titleOf(key)"
                            :disabled="idx === order.length - 1"
                            @click="moveBy(idx, 1)">↓</button>
                </span>
            </li>
        </template>
    </ol>

    @if ($error)
        <p id="{{ $errorId }}" class="lc-select__error" role="alert">{{ $error }}</p>
    @endif

    <div id="{{ $liveId }}" class="lc-select__live" aria-live="polite" aria-atomic="true" x-text="liveMessage"></div>

    @once
        @include('select::styles')
    @endonce
    @once
        <script data-lc-sortable-rank-alpine>
            (function () {
                if (window.__loggedCloudSortableRankLoaded) return;
                window.__loggedCloudSortableRankLoaded = true;

                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('loggedCloudSortableRank', (config) => ({
                        items: config.items || [],
                        order: (config.order || []).slice(),
                        triggerId: config.triggerId,
                        a11y: config.a11y || {},
                        dragKey: null,
                        overKey: null,
                        activeIdx: 0,
                        liveMessage: '',

                        titleOf(key) {
                            const it = this.items.find((o) => o.key === key);
                            return it ? it.title : key;
                        },

                        moveBy(idx, delta) {
                            const dest = idx + delta;
                            if (dest < 0 || dest >= this.order.length) return;
                            const moved = this.order[idx];
                            this.order.splice(idx, 1);
                            this.order.splice(dest, 0, moved);
                            this.activeIdx = dest;
                            this.liveMessage = (delta < 0
                                ? (this.a11y.moved_up || 'Moved up')
                                : (this.a11y.moved_down || 'Moved down')) + ' ' + this.titleOf(moved);
                            this._notifyChange();
                            this.$nextTick(() => document.getElementById(this.triggerId + '__row-' + moved)?.focus());
                        },

                        moveActive(delta) {
                            const next = Math.max(0, Math.min(this.order.length - 1, this.activeIdx + delta));
                            this.activeIdx = next;
                            const key = this.order[next];
                            this.$nextTick(() => document.getElementById(this.triggerId + '__row-' + key)?.focus());
                        },

                        // Space cycles which direction the next ↑/↓ will go ·
                        // a screen-reader fallback for users who can't drag.
                        cycleDirection(e, key) {
                            // Default: bump down with shift-space, up otherwise.
                            const idx = this.order.indexOf(key);
                            this.moveBy(idx, e.shiftKey ? -1 : 1);
                        },

                        onDragStart(e, key) {
                            this.dragKey = key;
                            try { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', key); } catch (_) {}
                        },

                        onDragOver(key) {
                            if (!this.dragKey || key === this.dragKey) return;
                            this.overKey = key;
                            // Live re-sort while dragging · feels much nicer
                            // than just showing a drop-line.
                            const from = this.order.indexOf(this.dragKey);
                            const to = this.order.indexOf(key);
                            if (from < 0 || to < 0) return;
                            this.order.splice(from, 1);
                            this.order.splice(to, 0, this.dragKey);
                            // Announce the new position so screen readers
                            // tracking the dragged item hear where it is.
                            this.liveMessage = this.titleOf(this.dragKey) + ' at position ' + (to + 1);
                        },

                        onDrop() {
                            this.overKey = null;
                            this._notifyChange();
                        },

                        onDragEnd() {
                            this.dragKey = null;
                            this.overKey = null;
                        },

                        _notifyChange() {
                            this.$nextTick(() => {
                                this.$el.querySelectorAll('input[type=hidden]').forEach((el) =>
                                    el.dispatchEvent(new Event('change', { bubbles: true })));
                            });
                        },
                    }));
                });
            })();
        </script>
    @endonce
</div>
