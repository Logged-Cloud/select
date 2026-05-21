@props([
    'name',                       // base name · emits {name}_start + {name}_end hidden inputs
    'id' => null,
    'startSelected' => null,      // 'YYYY-MM-DD' or null
    'endSelected' => null,        // 'YYYY-MM-DD' or null
    'min' => null,
    'max' => null,
    'placeholder' => null,
    'label' => null,
    'labelledBy' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
    'firstDayOfWeek' => 1,
])
@php
    $triggerId = $id ?? ($label ? \Illuminate\Support\Str::camel(\Illuminate\Support\Str::slug($label, '_')) : $name);
    $gridId = $triggerId.'-grid';
    $liveId = $triggerId.'-live';
    $errorId = $triggerId.'-error';
    $placeholder = $placeholder ?? 'Pick a date range';

    $allNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    $start = (int) $firstDayOfWeek;
    $dayLabels = [];
    for ($i = 0; $i < 7; $i++) {
        $dayLabels[] = substr($allNames[($start + $i) % 7], 0, 2);
    }
    $monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];

    $config = [
        'startValue' => $startSelected,
        'endValue' => $endSelected,
        'min' => $min,
        'max' => $max,
        'gridId' => $gridId,
        'triggerId' => $triggerId,
        'placeholder' => $placeholder,
        'firstDayOfWeek' => (int) $firstDayOfWeek,
        'monthNames' => $monthNames,
        'a11y' => [
            'start_set' => 'Start date set',
            'end_set' => 'End date set',
            'cleared' => 'Range cleared',
        ],
    ];
@endphp
<div x-data="loggedCloudDateRange({{ \Illuminate\Support\Js::from($config) }})"
     x-init="$nextTick(() => { if ($refs.fallback) { $refs.fallback.querySelectorAll('input').forEach(i => i.removeAttribute('name')); } })"
     class="lc-select lc-select--date {{ $error ? 'lc-select--error' : '' }}"
     @click.outside="close()"
     @keydown.escape.window="if (open) { close(); }">

    {{-- No-JS fallback · two native <input type=date> inputs side-by-side.
         The whole span is x-ref'd so Alpine can clear both names on boot. --}}
    <noscript>
        <style>[data-lc-range-fallback="{{ $triggerId }}"] { display: inline-flex !important; }</style>
    </noscript>
    <span data-lc-range-fallback="{{ $triggerId }}" x-ref="fallback" style="display:none; gap:.5rem">
        <input type="date" name="{{ $name }}_start" value="{{ $startSelected }}" @if ($min) min="{{ $min }}" @endif @if ($max) max="{{ $max }}" @endif>
        <input type="date" name="{{ $name }}_end"   value="{{ $endSelected }}"   @if ($min) min="{{ $min }}" @endif @if ($max) max="{{ $max }}" @endif>
    </span>

    <input type="hidden" name="{{ $name }}_start" :value="startValue" x-ref="hiddenStart" @if ($required) required @endif>
    <input type="hidden" name="{{ $name }}_end"   :value="endValue"   x-ref="hiddenEnd"   @if ($required) required @endif>

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
                  :class="(startValue || endValue) ? 'lc-select__placeholder--filled' : 'lc-select__placeholder'"></span>
        </span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round"
             class="lc-select__chevron" aria-hidden="true">
            <rect x="3" y="4" width="18" height="17" rx="2"/>
            <line x1="3" y1="9" x2="21" y2="9"/>
        </svg>
    </div>

    <div x-show="open" x-cloak class="lc-select__backdrop" @click="close()"></div>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms class="lc-select__menu lc-select__menu--date" role="dialog" aria-label="Date range picker">
        <div class="lc-select__sheet-handle" aria-hidden="true"></div>

        <div class="lc-date__header">
            <button type="button" class="lc-date__nav" @click="navMonth(-1)" aria-label="Previous month">‹</button>
            <span class="lc-date__title" aria-live="polite" x-text="monthNames[viewMonth] + ' ' + viewYear"></span>
            <button type="button" class="lc-date__nav" @click="navMonth(1)" aria-label="Next month">›</button>
        </div>

        <div class="lc-date__mode" aria-live="polite">
            <span x-text="selecting === 'start' ? 'Pick a start date' : 'Pick an end date'"></span>
        </div>

        <table id="{{ $gridId }}" class="lc-date__grid"
               role="grid"
               :aria-label="monthNames[viewMonth] + ' ' + viewYear">
            <thead>
                <tr>
                    @foreach ($dayLabels as $d)
                        <th scope="col">{{ $d }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <template x-for="week in weeks()" :key="week.idx">
                    <tr>
                        <template x-for="cell in week.cells" :key="cell.iso">
                            <td role="gridcell"
                                :class="{
                                    'lc-date__cell': true,
                                    'is-other': cell.otherMonth,
                                    'is-today': cell.today,
                                    'is-start': cell.iso === startValue,
                                    'is-end': cell.iso === endValue,
                                    'is-in-range': inRange(cell.iso),
                                    'is-disabled': cell.disabled,
                                }"
                                :aria-selected="cell.iso === startValue || cell.iso === endValue ? 'true' : 'false'"
                                :aria-disabled="cell.disabled ? 'true' : 'false'"
                                @click="if (!cell.disabled) pickIso(cell.iso)"
                                @mouseenter="if (!cell.disabled) hoverIso = cell.iso"
                                @mouseleave="hoverIso = null">
                                <span x-text="cell.day"></span>
                            </td>
                        </template>
                    </tr>
                </template>
            </tbody>
        </table>

        <div class="lc-date__footer">
            <button type="button" class="lc-date__action" @click="clear()">Clear</button>
            <button type="button" class="lc-date__action" x-show="startValue && endValue" @click="close()">Done</button>
        </div>
    </div>

    @if ($error)
        <p id="{{ $errorId }}" class="lc-select__error" role="alert">{{ $error }}</p>
    @endif

    <div id="{{ $liveId }}" class="lc-select__live" aria-live="polite" aria-atomic="true" x-text="liveMessage"></div>

    @once
        @include('select::styles')
    @endonce
    @once
        <script data-lc-date-range-alpine>
            (function () {
                if (window.__loggedCloudDateRangeLoaded) return;
                window.__loggedCloudDateRangeLoaded = true;

                const pad = (n) => String(n).padStart(2, '0');
                const isoOf = (y, m, d) => y + '-' + pad(m + 1) + '-' + pad(d);
                const parseIso = (s) => {
                    if (!s) return null;
                    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s);
                    if (!m) return null;
                    return { y: +m[1], m: +m[2] - 1, d: +m[3] };
                };
                const daysInMonth = (y, m) => new Date(y, m + 1, 0).getDate();

                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('loggedCloudDateRange', (config) => ({
                        gridId: config.gridId,
                        triggerId: config.triggerId,
                        placeholder: config.placeholder || '',
                        monthNames: config.monthNames || [],
                        firstDayOfWeek: config.firstDayOfWeek | 0,
                        min: config.min || null,
                        max: config.max || null,
                        a11y: config.a11y || {},
                        startValue: config.startValue || '',
                        endValue: config.endValue || '',
                        open: false,
                        viewYear: 0,
                        viewMonth: 0,
                        selecting: 'start',     // 'start' or 'end'
                        hoverIso: null,         // tracked while in 'end' mode for the preview shade
                        liveMessage: '',

                        init() {
                            const seed = parseIso(this.startValue) || (() => {
                                const t = new Date();
                                return { y: t.getFullYear(), m: t.getMonth() };
                            })();
                            this.viewYear = seed.y;
                            this.viewMonth = seed.m;
                            // If only a start is set, pre-arm the end picker.
                            if (this.startValue && !this.endValue) this.selecting = 'end';
                        },

                        toggle() { this.open ? this.close() : this.openMenu(); },
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

                        outOfRange(y, m, d) {
                            const iso = isoOf(y, m, d);
                            if (this.min && iso < this.min) return true;
                            if (this.max && iso > this.max) return true;
                            return false;
                        },

                        // A cell is "in range" if start is set and either
                        // end is set OR a hover preview is active in end-pick
                        // mode · matches stock date-range UX.
                        inRange(iso) {
                            if (!this.startValue) return false;
                            const tail = this.endValue || (this.selecting === 'end' ? this.hoverIso : null);
                            if (!tail) return false;
                            const lo = this.startValue < tail ? this.startValue : tail;
                            const hi = this.startValue < tail ? tail : this.startValue;
                            return iso > lo && iso < hi;
                        },

                        weeks() {
                            const y = this.viewYear, m = this.viewMonth;
                            const first = new Date(y, m, 1);
                            const leadIn = (first.getDay() - this.firstDayOfWeek + 7) % 7;
                            const todayIso = (() => {
                                const t = new Date();
                                return isoOf(t.getFullYear(), t.getMonth(), t.getDate());
                            })();
                            const cells = [];
                            const prevDays = daysInMonth(y, m - 1);
                            for (let i = leadIn; i > 0; i--) {
                                const day = prevDays - i + 1;
                                const py = m === 0 ? y - 1 : y;
                                const pm = m === 0 ? 11 : m - 1;
                                cells.push({
                                    iso: isoOf(py, pm, day),
                                    day, otherMonth: true,
                                    today: isoOf(py, pm, day) === todayIso,
                                    disabled: this.outOfRange(py, pm, day),
                                });
                            }
                            const cur = daysInMonth(y, m);
                            for (let d = 1; d <= cur; d++) {
                                cells.push({
                                    iso: isoOf(y, m, d),
                                    day: d, otherMonth: false,
                                    today: isoOf(y, m, d) === todayIso,
                                    disabled: this.outOfRange(y, m, d),
                                });
                            }
                            let trail = 1;
                            while (cells.length < 42) {
                                const ny = m === 11 ? y + 1 : y;
                                const nm = m === 11 ? 0 : m + 1;
                                cells.push({
                                    iso: isoOf(ny, nm, trail),
                                    day: trail, otherMonth: true,
                                    today: isoOf(ny, nm, trail) === todayIso,
                                    disabled: this.outOfRange(ny, nm, trail),
                                });
                                trail++;
                            }
                            const out = [];
                            for (let w = 0; w < 6; w++) {
                                out.push({ idx: w, cells: cells.slice(w * 7, w * 7 + 7) });
                            }
                            return out;
                        },

                        navMonth(delta) {
                            let m = this.viewMonth + delta;
                            let y = this.viewYear;
                            while (m < 0) { m += 12; y--; }
                            while (m > 11) { m -= 12; y++; }
                            this.viewMonth = m;
                            this.viewYear = y;
                        },

                        pickIso(iso) {
                            if (this.selecting === 'start') {
                                this.startValue = iso;
                                this.endValue = '';
                                this.selecting = 'end';
                                this.liveMessage = (this.a11y.start_set || 'Start set') + ': ' + iso;
                                this._dispatch();
                                return;
                            }
                            // selecting === 'end' · if user picked a date
                            // before the current start, swap them so the
                            // range stays valid.
                            if (iso < this.startValue) {
                                this.endValue = this.startValue;
                                this.startValue = iso;
                            } else {
                                this.endValue = iso;
                            }
                            this.selecting = 'start';
                            this.liveMessage = (this.a11y.end_set || 'End set') + ': ' + this.endValue;
                            this._dispatch();
                            // Auto-close on completing a range · matches the
                            // expectation from native pickers and tags-style
                            // flows where the form should move on.
                            this.close();
                        },

                        summary() {
                            if (!this.startValue && !this.endValue) return '';
                            if (this.startValue && !this.endValue) return this.startValue + ' → …';
                            return this.startValue + ' → ' + this.endValue;
                        },

                        clear() {
                            this.startValue = '';
                            this.endValue = '';
                            this.selecting = 'start';
                            this.liveMessage = this.a11y.cleared || 'Range cleared';
                            this._dispatch();
                        },

                        _dispatch() {
                            if (this.$refs.hiddenStart) this.$refs.hiddenStart.dispatchEvent(new Event('change', { bubbles: true }));
                            if (this.$refs.hiddenEnd) this.$refs.hiddenEnd.dispatchEvent(new Event('change', { bubbles: true }));
                        },
                    }));
                });
            })();
        </script>
    @endonce
</div>
