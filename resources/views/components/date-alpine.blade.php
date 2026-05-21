@props([
    'name',
    'id' => null,
    'selected' => null,           // 'YYYY-MM-DD' or null
    'min' => null,                 // 'YYYY-MM-DD' or null
    'max' => null,                 // 'YYYY-MM-DD' or null
    'placeholder' => null,
    'label' => null,
    'labelledBy' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
    'firstDayOfWeek' => 1,         // 1 = Monday, 0 = Sunday
    'format' => 'Y-m-d',           // PHP format string used for the trigger label
])
@php
    $triggerId = $id ?? ($label ? \Illuminate\Support\Str::camel(\Illuminate\Support\Str::slug($label, '_')) : $name);
    $gridId = $triggerId.'-grid';
    $liveId = $triggerId.'-live';
    $errorId = $triggerId.'-error';
    $placeholder = $placeholder ?? 'Pick a date';

    // Week-day labels respecting firstDayOfWeek · short forms (Mo Tu …).
    $dayLabels = [];
    $start = (int) $firstDayOfWeek;
    $names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    for ($i = 0; $i < 7; $i++) {
        $dayLabels[] = substr($names[($start + $i) % 7], 0, 2);
    }
    $monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];

    // For the no-JS fallback we emit a native <input type=date> rather than
    // a <select> · much closer to the rich picker's UX.
    $config = [
        'selected' => $selected,
        'min' => $min,
        'max' => $max,
        'gridId' => $gridId,
        'triggerId' => $triggerId,
        'placeholder' => $placeholder,
        'firstDayOfWeek' => (int) $firstDayOfWeek,
        'monthNames' => $monthNames,
        'a11y' => [
            'selected' => 'Selected',
            'cleared' => 'Date cleared',
            'next_month' => 'Next month',
            'prev_month' => 'Previous month',
            'next_year' => 'Next year',
            'prev_year' => 'Previous year',
        ],
    ];
@endphp
<div x-data="loggedCloudDate({{ \Illuminate\Support\Js::from($config) }})"
     x-init="$nextTick(() => { if ($refs.fallback) { $refs.fallback.disabled = true; $refs.fallback.removeAttribute('name'); } })"
     class="lc-select lc-select--date {{ $error ? 'lc-select--error' : '' }}"
     @click.outside="close()"
     @keydown.escape.window="if (open) { close(); }">

    {{-- Progressive enhancement · native date input visible only when JS is off. --}}
    <noscript>
        <style>[data-lc-date-fallback="{{ $triggerId }}"] { display: inline-block !important; }</style>
    </noscript>
    <input type="date"
           x-ref="fallback"
           data-lc-date-fallback="{{ $triggerId }}"
           name="{{ $name }}"
           value="{{ $selected }}"
           @if ($min) min="{{ $min }}" @endif
           @if ($max) max="{{ $max }}" @endif
           @if ($required) required @endif
           style="display:none;">

    <input type="hidden" name="{{ $name }}" :value="value" x-ref="hidden"
           @if ($required) required @endif>

    <div id="{{ $triggerId }}"
         x-ref="trigger"
         tabindex="{{ $disabled ? '-1' : '0' }}"
         class="lc-select__trigger"
         :class="{ 'is-open': open }"
         role="combobox"
         aria-haspopup="dialog"
         aria-controls="{{ $gridId }}"
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
            <span x-text="formatted() || @js($placeholder)"
                  :class="value ? 'lc-select__placeholder--filled' : 'lc-select__placeholder'"></span>
        </span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round"
             class="lc-select__chevron" aria-hidden="true">
            <rect x="3" y="4" width="18" height="17" rx="2"/>
            <line x1="3" y1="9" x2="21" y2="9"/>
            <line x1="8" y1="2" x2="8" y2="6"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
        </svg>
    </div>

    <div x-show="open" x-cloak class="lc-select__backdrop" @click="close()"></div>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms class="lc-select__menu lc-select__menu--date" role="dialog" aria-label="Date picker">
        <div class="lc-select__sheet-handle" aria-hidden="true"></div>

        {{-- Month / year header with nav arrows. --}}
        <div class="lc-date__header">
            <button type="button" class="lc-date__nav" @click="navMonth(-1)" :aria-label="a11y.prev_month">‹</button>
            <span class="lc-date__title" aria-live="polite" x-text="monthNames[viewMonth] + ' ' + viewYear"></span>
            <button type="button" class="lc-date__nav" @click="navMonth(1)" :aria-label="a11y.next_month">›</button>
        </div>

        {{-- Day-of-week row + 6-week grid. --}}
        <table id="{{ $gridId }}" class="lc-date__grid"
               role="grid"
               aria-readonly="false"
               :aria-label="monthNames[viewMonth] + ' ' + viewYear">
            <thead>
                <tr>
                    @foreach ($dayLabels as $d)
                        <th scope="col" abbr="{{ $names[($start + $loop->index) % 7] }}">{{ $d }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody @keydown.arrow-right.prevent="moveFocus(1)"
                   @keydown.arrow-left.prevent="moveFocus(-1)"
                   @keydown.arrow-down.prevent="moveFocus(7)"
                   @keydown.arrow-up.prevent="moveFocus(-7)"
                   @keydown.home.prevent="moveFocus(-(focusDay - 1 + 7) % 7)"
                   @keydown.end.prevent="moveFocus(6 - ((focusDay - 1 + 7) % 7))"
                   @keydown.page-down.prevent="navMonth(1)"
                   @keydown.page-up.prevent="navMonth(-1)"
                   @keydown.enter.prevent="pickFocused()"
                   @keydown.space.prevent="pickFocused()">
                <template x-for="week in weeks()" :key="week.idx">
                    <tr>
                        <template x-for="cell in week.cells" :key="cell.iso">
                            <td role="gridcell"
                                :id="gridId + '__cell-' + cell.iso"
                                :class="{
                                    'lc-date__cell': true,
                                    'is-other': cell.otherMonth,
                                    'is-today': cell.today,
                                    'is-selected': cell.iso === value,
                                    'is-focused': cell.iso === focusedIso(),
                                    'is-disabled': cell.disabled,
                                }"
                                :aria-selected="cell.iso === value ? 'true' : 'false'"
                                :aria-disabled="cell.disabled ? 'true' : 'false'"
                                :tabindex="cell.iso === focusedIso() ? 0 : -1"
                                @click="if (!cell.disabled) pickIso(cell.iso)"
                                @mouseenter="if (!cell.disabled) focusIso(cell.iso)">
                                <span x-text="cell.day"></span>
                            </td>
                        </template>
                    </tr>
                </template>
            </tbody>
        </table>

        <div class="lc-date__footer">
            <button type="button" class="lc-date__action" @click="pickToday()">Today</button>
            <button type="button" class="lc-date__action" x-show="value" x-cloak @click="clear()">Clear</button>
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
        <script data-lc-date-alpine>
            (function () {
                if (window.__loggedCloudDateLoaded) return;
                window.__loggedCloudDateLoaded = true;

                // ── helpers ────────────────────────────────────────────
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
                    window.Alpine.data('loggedCloudDate', (config) => ({
                        gridId: config.gridId,
                        triggerId: config.triggerId,
                        placeholder: config.placeholder || '',
                        monthNames: config.monthNames || [],
                        firstDayOfWeek: config.firstDayOfWeek | 0,
                        min: config.min || null,
                        max: config.max || null,
                        a11y: config.a11y || {},
                        value: config.selected || '',
                        open: false,
                        viewYear: 0,
                        viewMonth: 0,
                        focusDay: 1,
                        liveMessage: '',

                        init() {
                            // Park the view on the selected date if any, else today.
                            const start = parseIso(this.value) || (() => {
                                const t = new Date();
                                return { y: t.getFullYear(), m: t.getMonth(), d: t.getDate() };
                            })();
                            this.viewYear = start.y;
                            this.viewMonth = start.m;
                            this.focusDay = start.d;
                        },

                        focusedIso() {
                            return isoOf(this.viewYear, this.viewMonth, this.focusDay);
                        },

                        formatted() {
                            const p = parseIso(this.value);
                            if (!p) return '';
                            return this.value;
                        },

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

                        // ── min/max gate ───────────────────────────────
                        outOfRange(y, m, d) {
                            const iso = isoOf(y, m, d);
                            if (this.min && iso < this.min) return true;
                            if (this.max && iso > this.max) return true;
                            return false;
                        },

                        // ── grid builder · always 6 weeks (42 cells) ───
                        weeks() {
                            const y = this.viewYear, m = this.viewMonth;
                            const first = new Date(y, m, 1);
                            // Convert JS getDay() (0=Sun) into the offset from
                            // the configured firstDayOfWeek.
                            const leadIn = (first.getDay() - this.firstDayOfWeek + 7) % 7;
                            const todayIso = (() => {
                                const t = new Date();
                                return isoOf(t.getFullYear(), t.getMonth(), t.getDate());
                            })();
                            const cells = [];
                            // Previous-month tail
                            const prevDays = daysInMonth(y, m - 1);
                            for (let i = leadIn; i > 0; i--) {
                                const day = prevDays - i + 1;
                                const py = m === 0 ? y - 1 : y;
                                const pm = m === 0 ? 11 : m - 1;
                                cells.push({
                                    iso: isoOf(py, pm, day),
                                    day,
                                    otherMonth: true,
                                    today: isoOf(py, pm, day) === todayIso,
                                    disabled: this.outOfRange(py, pm, day),
                                });
                            }
                            // Current-month days
                            const cur = daysInMonth(y, m);
                            for (let d = 1; d <= cur; d++) {
                                cells.push({
                                    iso: isoOf(y, m, d),
                                    day: d,
                                    otherMonth: false,
                                    today: isoOf(y, m, d) === todayIso,
                                    disabled: this.outOfRange(y, m, d),
                                });
                            }
                            // Trailing days to reach 42 cells (6 weeks).
                            let trail = 1;
                            while (cells.length < 42) {
                                const ny = m === 11 ? y + 1 : y;
                                const nm = m === 11 ? 0 : m + 1;
                                cells.push({
                                    iso: isoOf(ny, nm, trail),
                                    day: trail,
                                    otherMonth: true,
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
                            // Clamp focusDay if the new month is shorter.
                            const max = daysInMonth(y, m);
                            if (this.focusDay > max) this.focusDay = max;
                        },

                        // Move the focused day by ±N days, crossing month
                        // boundaries by changing viewMonth/viewYear.
                        moveFocus(delta) {
                            const cur = new Date(this.viewYear, this.viewMonth, this.focusDay);
                            cur.setDate(cur.getDate() + delta);
                            this.viewYear = cur.getFullYear();
                            this.viewMonth = cur.getMonth();
                            this.focusDay = cur.getDate();
                        },

                        focusIso(iso) {
                            const p = parseIso(iso);
                            if (!p) return;
                            this.viewYear = p.y;
                            this.viewMonth = p.m;
                            this.focusDay = p.d;
                        },

                        pickIso(iso) {
                            const p = parseIso(iso);
                            if (!p || this.outOfRange(p.y, p.m, p.d)) return;
                            this.value = iso;
                            this.viewYear = p.y;
                            this.viewMonth = p.m;
                            this.focusDay = p.d;
                            this.liveMessage = (this.a11y.selected || 'Selected') + ' ' + iso;
                            if (this.$refs.hidden) {
                                this.$refs.hidden.value = iso;
                                this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                            this.close();
                        },

                        pickFocused() {
                            this.pickIso(this.focusedIso());
                        },

                        pickToday() {
                            const t = new Date();
                            this.pickIso(isoOf(t.getFullYear(), t.getMonth(), t.getDate()));
                        },

                        clear() {
                            this.value = '';
                            this.liveMessage = this.a11y.cleared || 'Date cleared';
                            if (this.$refs.hidden) {
                                this.$refs.hidden.value = '';
                                this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        },
                    }));
                });
            })();
        </script>
    @endonce
</div>
