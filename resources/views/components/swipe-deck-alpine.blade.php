@props([
    'name',
    'id' => null,
    'items' => [],
    'selected' => [],            // keys already accepted (resume state)
    'label' => null,
    'labelledBy' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
    'threshold' => 80,           // px distance before a swipe commits
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
            'image' => (string) ($get('image') ?? ''),
        ];
    })->values()->all();

    $config = [
        'items' => $normalised,
        'accepted' => collect($selected)->map(fn ($v) => (string) $v)->values()->all(),
        'triggerId' => $triggerId,
        'threshold' => (int) $threshold,
        'a11y' => [
            'accepted' => 'Accepted',
            'skipped' => 'Skipped',
            'undone' => 'Undone',
            'done' => 'Deck complete',
        ],
    ];
@endphp
<div x-data="loggedCloudSwipeDeck({{ \Illuminate\Support\Js::from($config) }})"
     x-init="$nextTick(() => { if ($refs.fallback) { $refs.fallback.name = ''; } })"
     class="lc-deck {{ $error ? 'lc-deck--error' : '' }}"
     id="{{ $triggerId }}">

    @include('select::partials.fallback', [
        'name' => $name, 'items' => $normalised, 'selected' => $config['accepted'],
        'multi' => true, 'fallbackId' => $fallbackId, 'required' => $required,
        'noJsLabel' => $noJsLabel, 'noJsCopy' => $noJsCopy,
    ])

    <template x-for="key in accepted" :key="'h-'+key">
        <input type="hidden" :name="@js($name).concat('[]')" :value="key">
    </template>

    <div class="lc-deck__stage"
         role="group"
         aria-roledescription="card deck · swipe or use buttons"
         @if ($label) aria-label="{{ $label }}" @endif
         @if ($labelledBy) aria-labelledby="{{ $labelledBy }}" @endif>

        {{-- Pre-render every card · the top of the stack is the active one.
             Stacking is via z-index + a tiny scale offset so the 2nd / 3rd
             cards peek through behind. --}}
        @foreach ($normalised as $i => $opt)
            @php
                $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $opt['key']);
                $cardId = $triggerId.'__card-'.$safe;
            @endphp
            {{-- Only the top card + the two peeking below are kept in the
                 layout · x-if (server-rendered conditional via x-show on a
                 narrow band) drops the DOM weight from N to 3 for big decks. --}}
            <div id="{{ $cardId }}"
                 class="lc-deck__card"
                 :class="{
                     'is-top': cursor === {{ $i }},
                     'is-next': cursor + 1 === {{ $i }},
                     'is-after': cursor + 2 === {{ $i }},
                     'is-gone-right': lastDir === 'right' && {{ $i }} === cursor - 1,
                     'is-gone-left':  lastDir === 'left'  && {{ $i }} === cursor - 1,
                 }"
                 x-show="{{ $i }} >= cursor - 1 && {{ $i }} <= cursor + 2"
                 :style="cursor === {{ $i }} && drag ? ('transform: translate(' + drag.dx + 'px,' + drag.dy + 'px) rotate(' + (drag.dx / 18) + 'deg)') : ''"
                 :aria-hidden="cursor !== {{ $i }} ? 'true' : 'false'"
                 :tabindex="cursor === {{ $i }} ? 0 : -1"
                 @pointerdown="if (cursor === {{ $i }}) onPointerDown($event)"
                 @keydown.arrow-right.prevent="if (cursor === {{ $i }}) accept()"
                 @keydown.arrow-left.prevent="if (cursor === {{ $i }}) skip()"
                 @keydown.enter.prevent="if (cursor === {{ $i }}) accept()"
                 @keydown.backspace.prevent="undo()">
                @if (! empty($opt['image']))
                    <img class="lc-deck__image" src="{{ $opt['image'] }}" alt="" loading="lazy">
                @elseif (! empty($opt['svg']))
                    <span class="lc-deck__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="64" height="64" fill="none"
                             stroke="currentColor" stroke-width="1.5"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="{{ $opt['svg'] }}"></path>
                        </svg>
                    </span>
                @endif
                <h3 class="lc-deck__title">{{ $opt['title'] }}</h3>
                @if (! empty($opt['subtitle']))
                    <p class="lc-deck__subtitle">{{ $opt['subtitle'] }}</p>
                @endif
            </div>
        @endforeach

        <div class="lc-deck__done" x-show="cursor >= items.length" x-cloak>
            <p x-text="a11y.done"></p>
        </div>
    </div>

    <div class="lc-deck__controls" x-show="cursor < items.length" x-cloak>
        <button type="button" class="lc-deck__btn lc-deck__btn--skip" @click="skip()" aria-label="Skip" :disabled="cursor >= items.length">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none"
                 stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M6 18L18 6"/></svg>
        </button>
        <button type="button" class="lc-deck__btn lc-deck__btn--undo" @click="undo()" aria-label="Undo last" :disabled="cursor === 0">↺</button>
        <button type="button" class="lc-deck__btn lc-deck__btn--accept" @click="accept()" aria-label="Accept" :disabled="cursor >= items.length">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none"
                 stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
        </button>
    </div>

    @if ($error)
        <p id="{{ $errorId }}" class="lc-select__error" role="alert">{{ $error }}</p>
    @endif

    <div id="{{ $liveId }}" class="lc-select__live" aria-live="polite" aria-atomic="true" x-text="liveMessage"></div>

    @once
        @include('select::styles')
    @endonce
    @once
        <script data-lc-swipe-deck-alpine>
            (function () {
                if (window.__loggedCloudSwipeDeckLoaded) return;
                window.__loggedCloudSwipeDeckLoaded = true;

                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('loggedCloudSwipeDeck', (config) => ({
                        items: config.items || [],
                        accepted: (config.accepted || []).slice(),
                        threshold: config.threshold || 80,
                        triggerId: config.triggerId,
                        a11y: config.a11y || {},
                        // History of decisions so undo can rewind. Each
                        // entry: { idx, action: 'accept'|'skip' }.
                        history: [],
                        cursor: 0,
                        drag: null,
                        lastDir: null,
                        liveMessage: '',

                        init() {
                            // Resume mode · if the host passed `selected`,
                            // skip past those items in the original order
                            // so the user picks up where they left off.
                            if (this.accepted.length > 0) {
                                const acceptedSet = new Set(this.accepted);
                                for (let i = 0; i < this.items.length; i++) {
                                    if (acceptedSet.has(this.items[i].key)) {
                                        this.cursor = i + 1;
                                    }
                                }
                            }
                        },

                        accept() {
                            const cur = this.items[this.cursor];
                            if (!cur) return;
                            if (!this.accepted.includes(cur.key)) this.accepted.push(cur.key);
                            this.history.push({ idx: this.cursor, action: 'accept' });
                            this.lastDir = 'right';
                            this.cursor++;
                            this.liveMessage = (this.a11y.accepted || 'Accepted') + ' ' + cur.title;
                            this.drag = null;
                            this._notifyChange();
                        },

                        skip() {
                            const cur = this.items[this.cursor];
                            if (!cur) return;
                            this.history.push({ idx: this.cursor, action: 'skip' });
                            this.lastDir = 'left';
                            this.cursor++;
                            this.liveMessage = (this.a11y.skipped || 'Skipped') + ' ' + cur.title;
                            this.drag = null;
                        },

                        undo() {
                            const last = this.history.pop();
                            if (!last) return;
                            // If the popped move was an accept, remove the
                            // most-recent matching key from `accepted` (only
                            // the one tied to this card · keys are unique).
                            if (last.action === 'accept') {
                                const cardKey = this.items[last.idx].key;
                                const i = this.accepted.lastIndexOf(cardKey);
                                if (i >= 0) this.accepted.splice(i, 1);
                            }
                            this.cursor = last.idx;
                            this.lastDir = null;
                            this.liveMessage = this.a11y.undone || 'Undone';
                            this._notifyChange();
                        },

                        // Pointer events let us share one handler across
                        // mouse, touch, and pen · no need for separate
                        // touchstart / mousedown branches.
                        onPointerDown(e) {
                            if (this.cursor >= this.items.length) return;
                            const startX = e.clientX, startY = e.clientY;
                            const startCursor = this.cursor;
                            const target = e.currentTarget;
                            try { target.setPointerCapture(e.pointerId); } catch (_) {}
                            this.drag = { dx: 0, dy: 0 };
                            const move = (ev) => {
                                // If cursor advanced via keyboard / button mid-
                                // drag, ignore subsequent moves on the stale
                                // target · prevents a dragged-out card from
                                // animating back via stale state.
                                if (this.cursor !== startCursor) return;
                                this.drag = { dx: ev.clientX - startX, dy: ev.clientY - startY };
                            };
                            const cleanup = () => {
                                target.removeEventListener('pointermove', move);
                                target.removeEventListener('pointerup', up);
                                target.removeEventListener('pointercancel', up);
                                try { target.releasePointerCapture(e.pointerId); } catch (_) {}
                            };
                            const up = () => {
                                cleanup();
                                if (this.cursor !== startCursor || !this.drag) {
                                    this.drag = null;
                                    return;
                                }
                                if (this.drag.dx > this.threshold) this.accept();
                                else if (this.drag.dx < -this.threshold) this.skip();
                                else this.drag = null;
                            };
                            target.addEventListener('pointermove', move);
                            target.addEventListener('pointerup', up);
                            target.addEventListener('pointercancel', up);
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
