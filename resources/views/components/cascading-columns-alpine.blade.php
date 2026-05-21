@props([
    'name',
    'id' => null,
    'items' => [],                // same recursive shape as tree-alpine
    'selected' => null,
    'label' => null,
    'labelledBy' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
    'leavesOnly' => true,
    'maxColumns' => 4,
])
@php
    // Recursive flatten mirrors tree-alpine · same shape so callers can swap
    // the two by changing one tag.
    $nextId = 0;
    $normalise = function ($items, $depth, $parentIdx, &$flat) use (&$normalise, &$nextId) {
        foreach ($items as $item) {
            $get = is_array($item) ? fn ($k) => $item[$k] ?? null : fn ($k) => $item->{$k} ?? null;
            $children = $get('children') ?? [];
            $idx = $nextId++;
            $flat[] = [
                'idx' => $idx,
                'parent' => $parentIdx,
                'key' => (string) $get('key'),
                'title' => (string) $get('title'),
                'subtitle' => (string) ($get('subtitle') ?? ''),
                'depth' => $depth,
                'leaf' => empty($children),
            ];
            if (! empty($children)) {
                $normalise($children, $depth + 1, $idx, $flat);
            }
        }
    };
    $flat = [];
    $normalise($items, 0, -1, $flat);
    // Build a depth → indices map for the top-level column rendering.
    $rootIdxs = collect($flat)->filter(fn ($n) => $n['depth'] === 0)->pluck('idx')->all();

    $triggerId = $id ?? ($label ? \Illuminate\Support\Str::camel(\Illuminate\Support\Str::slug($label, '_')) : $name);
    $fallbackId = $triggerId.'-fallback';
    $errorId = $triggerId.'-error';
    $noJsLabel = config('select.copy.no_js_indicator', 'JS off');
    $noJsCopy = config('select.copy.no_js_warning', 'JavaScript is needed for the rich picker.');

    $flatLeaves = array_values(array_filter($flat, fn ($n) => $n['leaf']));

    $config = [
        'flat' => $flat,
        'rootIdxs' => array_values($rootIdxs),
        'selected' => $selected,
        'triggerId' => $triggerId,
        'leavesOnly' => (bool) $leavesOnly,
        'maxColumns' => max(2, (int) $maxColumns),
        'a11y' => [
            'selected' => 'Selected',
        ],
    ];
@endphp
<div x-data="loggedCloudCascadingColumns({{ \Illuminate\Support\Js::from($config) }})"
     x-init="$nextTick(() => { if ($refs.fallback) { $refs.fallback.name = ''; } })"
     class="lc-cascade {{ $error ? 'lc-cascade--error' : '' }}"
     id="{{ $triggerId }}">

    @include('select::partials.fallback', [
        'name' => $name, 'items' => $flatLeaves, 'selected' => $selected,
        'multi' => false, 'fallbackId' => $fallbackId, 'required' => $required,
        'noJsLabel' => $noJsLabel, 'noJsCopy' => $noJsCopy,
    ])

    <input type="hidden" name="{{ $name }}" :value="value" x-ref="hidden"
           @if ($required) required @endif>

    <div class="lc-cascade__columns"
         role="group"
         :style="'--lc-cascade-cols:' + Math.min(maxColumns, path.length + 1)"
         @if ($label) aria-label="{{ $label }}" @endif
         @if ($labelledBy) aria-labelledby="{{ $labelledBy }}" @endif
         @if ($required) aria-required="true" @endif>

        {{-- The first column is always the roots. Subsequent columns
             are computed dynamically from `path` · each entry in path
             is a parent-idx and its column is the children of that idx. --}}
        <template x-for="(col, ci) in columns" :key="'col-'+ci">
            <ul class="lc-cascade__col" role="listbox" :aria-label="'Column ' + (ci + 1)">
                <template x-for="i in col" :key="'row-'+i">
                    <li class="lc-cascade__row"
                        role="option"
                        :id="@js($triggerId).concat('__row-' + i)"
                        :aria-selected="flat[i].key === value ? 'true' : 'false'"
                        :class="{
                            'is-on-path': path.includes(i),
                            'is-selected': flat[i].key === value,
                            'is-branch': !flat[i].leaf,
                        }"
                        :tabindex="(activeCol === ci && activeIdxs[ci] === i) ? 0 : -1"
                        @click="onRowClick(ci, i)"
                        @keydown.arrow-down.prevent="moveActive(ci, 1)"
                        @keydown.arrow-up.prevent="moveActive(ci, -1)"
                        @keydown.arrow-right.prevent="onRight(ci, i)"
                        @keydown.arrow-left.prevent="onLeft(ci)"
                        @keydown.enter.prevent="pickAt(ci, i)"
                        @keydown.space.prevent="pickAt(ci, i)">
                        <span class="lc-cascade__title" x-text="flat[i].title"></span>
                        <span class="lc-cascade__chev" x-show="!flat[i].leaf" aria-hidden="true">›</span>
                    </li>
                </template>
            </ul>
        </template>
    </div>

    @if ($error)
        <p id="{{ $errorId }}" class="lc-select__error" role="alert">{{ $error }}</p>
    @endif

    <div :id="triggerId+'-live'" class="lc-select__live" aria-live="polite" aria-atomic="true" x-text="liveMessage"></div>

    @once
        @include('select::styles')
    @endonce
    @once
        @include('select::partials.search-helpers')
    @endonce
    @once
        <script data-lc-cascading-columns-alpine>
            (function () {
                if (window.__loggedCloudCascadingColumnsLoaded) return;
                window.__loggedCloudCascadingColumnsLoaded = true;

                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('loggedCloudCascadingColumns', (config) => ({
                        flat: config.flat || [],
                        rootIdxs: config.rootIdxs || [],
                        triggerId: config.triggerId,
                        leavesOnly: !!config.leavesOnly,
                        maxColumns: config.maxColumns || 4,
                        a11y: config.a11y || {},
                        value: config.selected || '',
                        path: [],                // parent-idx per opened column
                        activeCol: 0,
                        activeIdxs: [],           // [colIndex] → current row idx
                        liveMessage: '',
                        _childMap: null,

                        init() {
                            // Pre-compute children per parent-idx so each
                            // column's idx-list is O(1) to look up.
                            this._childMap = new Map();
                            for (const n of this.flat) {
                                if (!this._childMap.has(n.parent)) this._childMap.set(n.parent, []);
                                this._childMap.get(n.parent).push(n.idx);
                            }
                            // Guard against an empty items array · no roots
                            // means no first-column active row and the keyboard
                            // handlers would crash on undefined idxs.
                            this.activeIdxs = this.rootIdxs.length > 0 ? [this.rootIdxs[0]] : [];
                            // If a value is preselected, walk down to expand
                            // the matching column path.
                            if (this.value) {
                                const start = this.flat.find((n) => n.key === this.value);
                                if (start) this._expandPathTo(start.idx);
                            }
                        },

                        _expandPathTo(targetIdx) {
                            // Walk ancestors from target → root, push into path
                            // in root → leaf order.
                            const chain = [];
                            let cur = targetIdx;
                            while (cur >= 0) {
                                chain.unshift(cur);
                                cur = this.flat[cur]?.parent ?? -1;
                            }
                            // chain[0..n-2] are parents (path); chain[n-1] is
                            // the value · activeIdxs mirrors chain.
                            this.path = chain.slice(0, -1);
                            this.activeIdxs = chain;
                        },

                        get columns() {
                            // Column 0 = roots; each subsequent column is the
                            // children of path[i-1]. Stops growing past
                            // maxColumns.
                            const out = [this.rootIdxs];
                            for (const parentIdx of this.path) {
                                if (out.length >= this.maxColumns) break;
                                out.push(this._childMap.get(parentIdx) || []);
                            }
                            return out;
                        },

                        onRowClick(col, idx) {
                            this.activeCol = col;
                            this.activeIdxs[col] = idx;
                            const node = this.flat[idx];
                            if (node.leaf) {
                                this.pickAt(col, idx);
                                return;
                            }
                            // Branch · open the children column to the right.
                            this.path = this.path.slice(0, col);
                            this.path.push(idx);
                            this.activeCol = col + 1;
                            // Seed the new column's active row to its first child.
                            const kids = this._childMap.get(idx) || [];
                            this.activeIdxs = this.activeIdxs.slice(0, col + 1);
                            if (kids.length > 0) this.activeIdxs.push(kids[0]);
                            // Announce the column open so screen-reader users
                            // hear that a new column appeared without losing
                            // focus context.
                            this.liveMessage = 'Opened ' + node.title + ': ' + kids.length + ' items';
                        },

                        moveActive(col, delta) {
                            const colIdxs = this.columns[col] || [];
                            const cur = colIdxs.indexOf(this.activeIdxs[col]);
                            const next = Math.max(0, Math.min(colIdxs.length - 1, (cur < 0 ? 0 : cur) + delta));
                            this.activeIdxs[col] = colIdxs[next];
                            this.$nextTick(() => document.getElementById(this.triggerId + '__row-' + colIdxs[next])?.focus());
                        },

                        onRight(col, idx) {
                            // Right opens the children column (or moves to
                            // it if already open) · feels like Finder.
                            const node = this.flat[idx];
                            if (node.leaf) return;
                            this.onRowClick(col, idx);
                            this.$nextTick(() => {
                                const next = this.columns[col + 1];
                                if (next && next[0] !== undefined) {
                                    document.getElementById(this.triggerId + '__row-' + next[0])?.focus();
                                }
                            });
                        },

                        onLeft(col) {
                            // Left collapses to the parent column.
                            if (col === 0) return;
                            this.path = this.path.slice(0, col - 1);
                            this.activeCol = col - 1;
                            const focusIdx = this.activeIdxs[col - 1];
                            this.activeIdxs = this.activeIdxs.slice(0, col);
                            this.$nextTick(() => document.getElementById(this.triggerId + '__row-' + focusIdx)?.focus());
                        },

                        pickAt(col, idx) {
                            const node = this.flat[idx];
                            if (!node.leaf && this.leavesOnly) {
                                this.onRowClick(col, idx);
                                return;
                            }
                            this.value = node.key;
                            this.liveMessage = (this.a11y.selected || 'Selected') + ' ' + node.title;
                            if (this.$refs.hidden) {
                                this.$refs.hidden.value = node.key;
                                this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        },
                    }));
                });
            })();
        </script>
    @endonce
</div>
