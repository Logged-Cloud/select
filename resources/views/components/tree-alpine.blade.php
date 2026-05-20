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
    'iconSize' => null,
    'expandedDepth' => 0,
    'leavesOnly' => true,
])
@php
    // Recursive normalisation · each node carries {key, title, subtitle, svg,
    // children?, depth}. The flat list lets Alpine drive a roving-tabindex
    // pattern through visible rows; children are filtered out when their
    // ancestor is collapsed.
    $nextId = 0;
    $normalise = function ($items, $depth, &$flat) use (&$normalise, &$nextId) {
        foreach ($items as $item) {
            $get = is_array($item) ? fn ($k) => $item[$k] ?? null : fn ($k) => $item->{$k} ?? null;
            $children = $get('children') ?? [];
            $node = [
                'key' => (string) $get('key'),
                'title' => (string) $get('title'),
                'subtitle' => (string) ($get('subtitle') ?? ''),
                'svg' => (string) ($get('svg') ?? ''),
                'depth' => $depth,
                'leaf' => empty($children),
                'idx' => $nextId++,
            ];
            $flat[] = $node;
            if (! empty($children)) {
                $normalise($children, $depth + 1, $flat);
            }
        }
    };
    $flat = [];
    $normalise($items, 0, $flat);
    // Build a parent-pointer map · each non-leaf carries the indexes of its
    // direct children (consecutive nodes at depth+1 until a sibling appears).
    $childrenMap = [];
    $childrenMap[-1] = [];
    foreach ($flat as $pos => $node) {
        $parent = -1;
        // The closest preceding node at depth-1 is this node's parent.
        for ($k = $pos - 1; $k >= 0; $k--) {
            if ($flat[$k]['depth'] === $node['depth'] - 1) { $parent = $flat[$k]['idx']; break; }
            if ($flat[$k]['depth'] < $node['depth'] - 1) break;
        }
        $childrenMap[$parent][] = $node['idx'];
        if (! isset($childrenMap[$node['idx']])) {
            $childrenMap[$node['idx']] = [];
        }
    }

    $triggerId = $id ?? ($label ? \Illuminate\Support\Str::camel(\Illuminate\Support\Str::slug($label, '_')) : $name);
    $listboxId = $triggerId.'-tree';
    $liveId = $triggerId.'-live';
    $fallbackId = $triggerId.'-fallback';
    $errorId = $triggerId.'-error';
    $iconSize = $iconSize ?? config('select.behavior.icon_size', '1.5rem');
    $placeholder = $placeholder ?? config('select.copy.placeholder', 'Pick an item');
    $noJsLabel = config('select.copy.no_js_indicator', 'JS off');
    $noJsCopy = config('select.copy.no_js_warning', 'JavaScript is needed for the rich picker.');

    $config = [
        'flat' => $flat,
        'childrenMap' => $childrenMap,
        'selected' => $selected,
        'listboxId' => $listboxId,
        'triggerId' => $triggerId,
        'placeholder' => $placeholder,
        'expandedDepth' => (int) $expandedDepth,
        'leavesOnly' => (bool) $leavesOnly,
        'a11y' => [
            'expanded' => 'Expanded',
            'collapsed' => 'Collapsed',
            'selected' => 'Selected',
        ],
    ];

    // For the no-JS fallback, surface the LEAVES of the tree as the native
    // <select> options · branch headings rarely make sense as form values.
    $flatLeaves = array_filter($flat, fn ($n) => $n['leaf']);
@endphp
<div x-data="loggedCloudTree({{ \Illuminate\Support\Js::from($config) }})"
     x-init="$nextTick(() => { if ($refs.fallback) { $refs.fallback.name = ''; } })"
     class="lc-select lc-select--tree {{ $error ? 'lc-select--error' : '' }}"
     style="--lc-icon-size: {{ $iconSize }};"
     @click.outside="close()"
     @keydown.escape.window="if (open) { close(); }">

    @include('select::partials.fallback', [
        'name' => $name, 'items' => array_values($flatLeaves), 'selected' => $selected,
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
         aria-haspopup="tree"
         aria-controls="{{ $listboxId }}"
         :aria-expanded="open"
         :aria-activedescendant="open ? optionId(activeIdx) : null"
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

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms class="lc-select__menu">
        <div class="lc-select__sheet-handle" aria-hidden="true"></div>
        <ul id="{{ $listboxId }}"
            role="tree"
            x-ref="listbox"
            tabindex="-1"
            class="lc-tree"
            @if ($label) aria-label="{{ $label }}" @else aria-label="{{ $placeholder }}" @endif
            @keydown.arrow-down.prevent="moveCursor(1)"
            @keydown.arrow-up.prevent="moveCursor(-1)"
            @keydown.arrow-right.prevent="onRight()"
            @keydown.arrow-left.prevent="onLeft()"
            @keydown.home.prevent="cursor = 0"
            @keydown.end.prevent="cursor = visibleIdxs().length - 1"
            @keydown.enter.prevent="pickActive()"
            @keydown.space.prevent="pickActive()">
            @foreach ($flat as $node)
                @php
                    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $node['key']);
                    $rowId = $listboxId.'__row-'.$safe;
                @endphp
                <li id="{{ $rowId }}"
                    role="treeitem"
                    :aria-level="{{ $node['depth'] + 1 }}"
                    :aria-selected="selectedIdx === {{ $node['idx'] }} ? 'true' : 'false'"
                    @if (! $node['leaf']) :aria-expanded="expanded[{{ $node['idx'] }}] ? 'true' : 'false'" @endif
                    :class="{ 'is-active': activeIdx === {{ $node['idx'] }}, 'is-selected': selectedIdx === {{ $node['idx'] }} }"
                    class="lc-tree__row lc-tree__row--depth-{{ $node['depth'] }}"
                    x-show="isVisible({{ $node['idx'] }})"
                    @click="onRowClick({{ $node['idx'] }}, {{ $node['leaf'] ? 'true' : 'false' }})"
                    @mouseenter="activeIdx = {{ $node['idx'] }}">
                    <span class="lc-tree__indent" aria-hidden="true" style="width: {{ $node['depth'] * 1.1 }}rem"></span>
                    @if (! $node['leaf'])
                        <button type="button" class="lc-tree__twisty" :aria-label="(expanded[{{ $node['idx'] }}] ? a11y.collapsed : a11y.expanded) + ' ' + @js($node['title'])"
                                @click.stop="toggleExpanded({{ $node['idx'] }})">
                            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round"
                                 :class="expanded[{{ $node['idx'] }}] ? 'lc-tree__twisty-open' : ''">
                                <polyline points="9 6 15 12 9 18"></polyline>
                            </svg>
                        </button>
                    @else
                        <span class="lc-tree__leaf-dot" aria-hidden="true"></span>
                    @endif
                    @if (! empty($node['svg']))
                        <span class="lc-tree__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none"
                                 stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <path d="{{ $node['svg'] }}"></path>
                            </svg>
                        </span>
                    @endif
                    <span class="lc-tree__body">
                        <span class="lc-tree__title">{{ $node['title'] }}</span>
                        @if (! empty($node['subtitle']))
                            <span class="lc-tree__subtitle">{{ $node['subtitle'] }}</span>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
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
        <script data-lc-tree-alpine>
            (function () {
                if (window.__loggedCloudTreeLoaded) return;
                window.__loggedCloudTreeLoaded = true;

                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('loggedCloudTree', (config) => ({
                        flat: config.flat || [],
                        childrenMap: config.childrenMap || {},
                        listboxId: config.listboxId,
                        triggerId: config.triggerId,
                        placeholder: config.placeholder || '',
                        expandedDepth: config.expandedDepth | 0,
                        leavesOnly: !!config.leavesOnly,
                        a11y: config.a11y || {},
                        open: false,
                        value: config.selected || '',
                        selectedIdx: -1,
                        activeIdx: -1,
                        cursor: 0,
                        liveMessage: '',
                        // Map<int, bool> · which non-leaf indexes are expanded.
                        expanded: {},

                        get selected() {
                            return this.selectedIdx >= 0 ? this.flat[this.selectedIdx] : null;
                        },

                        init() {
                            // Default-expand the first N depths so a fresh
                            // open already shows some structure.
                            for (const node of this.flat) {
                                if (!node.leaf && node.depth < this.expandedDepth) {
                                    this.expanded[node.idx] = true;
                                }
                            }
                            // Restore selection from the initial `value`.
                            this.selectedIdx = this.flat.findIndex((n) => n.key === this.value);
                            // Expand ancestors of the selected row so it's
                            // visible when the menu opens.
                            if (this.selectedIdx >= 0) {
                                this._expandAncestors(this.selectedIdx);
                            }
                        },

                        _expandAncestors(idx) {
                            const me = this.flat[idx];
                            if (!me) return;
                            // Walk up: closest preceding row at me.depth - 1, etc.
                            let depth = me.depth;
                            for (let p = idx - 1; p >= 0 && depth > 0; p--) {
                                const r = this.flat[p];
                                if (r.depth === depth - 1) {
                                    this.expanded[r.idx] = true;
                                    depth--;
                                }
                                if (r.depth < depth - 1) break;
                            }
                        },

                        toggle() {
                            this.open ? this.close() : this.openMenu();
                        },

                        openMenu() {
                            this.open = true;
                            const vis = this.visibleIdxs();
                            // Cursor lands on the selection if visible, else
                            // the first visible row.
                            const sel = vis.indexOf(this.selectedIdx);
                            this.cursor = sel >= 0 ? sel : 0;
                            this.activeIdx = vis[this.cursor] ?? -1;
                            if (window.matchMedia && window.matchMedia('(max-width: 640px)').matches) {
                                window.lcLockBodyScroll();
                                this._lockedScroll = true;
                            }
                            this.$nextTick(() => this.$refs.listbox?.focus());
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

                        optionId(idx) {
                            if (idx < 0 || !this.flat[idx]) return null;
                            return this.listboxId + '__row-' + window.lcSafeId(this.flat[idx].key);
                        },

                        // Walk ancestors · a row is hidden if ANY ancestor is collapsed.
                        isVisible(idx) {
                            const node = this.flat[idx];
                            if (!node) return false;
                            if (node.depth === 0) return true;
                            // Find ancestor chain by walking up.
                            let depth = node.depth;
                            for (let p = idx - 1; p >= 0 && depth > 0; p--) {
                                const r = this.flat[p];
                                if (r.depth === depth - 1) {
                                    if (!this.expanded[r.idx]) return false;
                                    depth--;
                                }
                                if (r.depth < depth - 1) return false;
                            }
                            return true;
                        },

                        visibleIdxs() {
                            const out = [];
                            for (const node of this.flat) {
                                if (this.isVisible(node.idx)) out.push(node.idx);
                            }
                            return out;
                        },

                        moveCursor(delta) {
                            const vis = this.visibleIdxs();
                            this.cursor = Math.max(0, Math.min(this.cursor + delta, vis.length - 1));
                            this.activeIdx = vis[this.cursor];
                        },

                        onRight() {
                            // Right on a collapsed branch expands; on an expanded
                            // branch moves cursor to its first child; on a leaf
                            // does nothing.
                            const node = this.flat[this.activeIdx];
                            if (!node || node.leaf) return;
                            if (!this.expanded[node.idx]) {
                                this.expanded[node.idx] = true;
                                this.liveMessage = (this.a11y.expanded || 'Expanded') + ' ' + node.title;
                            } else {
                                this.moveCursor(1);
                            }
                        },

                        onLeft() {
                            // Left on an expanded branch collapses; on a leaf or
                            // collapsed branch moves cursor to its parent.
                            const node = this.flat[this.activeIdx];
                            if (!node) return;
                            if (!node.leaf && this.expanded[node.idx]) {
                                this.expanded[node.idx] = false;
                                this.liveMessage = (this.a11y.collapsed || 'Collapsed') + ' ' + node.title;
                                return;
                            }
                            // Walk back to the parent row.
                            for (let p = this.activeIdx - 1; p >= 0; p--) {
                                if (this.flat[p].depth === node.depth - 1) {
                                    const vis = this.visibleIdxs();
                                    this.cursor = vis.indexOf(this.flat[p].idx);
                                    this.activeIdx = this.flat[p].idx;
                                    break;
                                }
                            }
                        },

                        toggleExpanded(idx) {
                            this.expanded[idx] = !this.expanded[idx];
                            this.liveMessage = (this.expanded[idx]
                                ? (this.a11y.expanded || 'Expanded')
                                : (this.a11y.collapsed || 'Collapsed')) + ' ' + this.flat[idx].title;
                        },

                        onRowClick(idx, isLeaf) {
                            this.activeIdx = idx;
                            const vis = this.visibleIdxs();
                            this.cursor = vis.indexOf(idx);
                            // Click on a branch toggles expansion; click on a
                            // leaf picks it. If leavesOnly is false, click on
                            // a branch ALSO picks the branch.
                            if (!isLeaf && this.leavesOnly) {
                                this.toggleExpanded(idx);
                                return;
                            }
                            this.pickAt(idx);
                        },

                        pickActive() {
                            if (this.activeIdx < 0) return;
                            const node = this.flat[this.activeIdx];
                            if (!node.leaf && this.leavesOnly) {
                                this.toggleExpanded(this.activeIdx);
                                return;
                            }
                            this.pickAt(this.activeIdx);
                        },

                        pickAt(idx) {
                            const node = this.flat[idx];
                            if (!node) return;
                            this.selectedIdx = idx;
                            this.value = node.key;
                            this.liveMessage = (this.a11y.selected || 'Selected') + ' ' + node.title;
                            if (this.$refs.hidden) {
                                this.$refs.hidden.value = node.key;
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
