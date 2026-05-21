@props([
    'name',
    'id' => null,
    'selected' => null,           // 'HH:MM' (24h) or null
    'minuteStep' => 5,
    'use24h' => true,
    'placeholder' => null,
    'label' => null,
    'labelledBy' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
])
@php
    $triggerId = $id ?? ($label ? \Illuminate\Support\Str::camel(\Illuminate\Support\Str::slug($label, '_')) : $name);
    $fallbackId = $triggerId.'-fallback';
    $errorId = $triggerId.'-error';
    $placeholder = $placeholder ?? 'Pick a time';

    $config = [
        'selected' => $selected,
        'minuteStep' => max(1, (int) $minuteStep),
        'use24h' => (bool) $use24h,
        'triggerId' => $triggerId,
        'placeholder' => $placeholder,
        'a11y' => [
            'hours' => 'hours',
            'minutes' => 'minutes',
            'selected' => 'Selected',
        ],
    ];
@endphp
<div x-data="loggedCloudTime({{ \Illuminate\Support\Js::from($config) }})"
     x-init="$nextTick(() => { if ($refs.fallback) { $refs.fallback.disabled = true; $refs.fallback.removeAttribute('name'); } })"
     class="lc-select lc-select--time {{ $error ? 'lc-select--error' : '' }}"
     @click.outside="close()"
     @keydown.escape.window="if (open) { close(); }">

    {{-- Native time input is a strictly better no-JS fallback than a
         <select> with 288 options · gets focus + name on init clear. --}}
    <noscript>
        <style>[data-lc-time-fallback="{{ $triggerId }}"] { display: inline-block !important; }</style>
    </noscript>
    <input type="time"
           x-ref="fallback"
           data-lc-time-fallback="{{ $triggerId }}"
           name="{{ $name }}"
           value="{{ $selected }}"
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
            <circle cx="12" cy="12" r="9"/>
            <polyline points="12 7 12 12 15 14"/>
        </svg>
    </div>

    <div x-show="open" x-cloak class="lc-select__backdrop" @click="close()"></div>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms class="lc-select__menu lc-select__menu--time" role="dialog" aria-label="Time picker">
        <div class="lc-select__sheet-handle" aria-hidden="true"></div>

        <div class="lc-time__cols">
            {{-- Hours column · scrollable list, snap-aligned. The "active"
                 row is the centred one; clicking any row picks that hour. --}}
            <div class="lc-time__col" role="listbox" aria-label="Hours"
                 x-ref="hcol"
                 @keydown.arrow-up.prevent="bumpHour(-1)"
                 @keydown.arrow-down.prevent="bumpHour(1)"
                 @keydown.home.prevent="setHour(0)"
                 @keydown.end.prevent="setHour(use24h ? 23 : 11)">
                <template x-for="h in hourValues" :key="'h-'+h">
                    <button type="button"
                            class="lc-time__cell"
                            :class="{ 'is-active': h === hour }"
                            :aria-selected="h === hour ? 'true' : 'false'"
                            role="option"
                            @click="setHour(h)"
                            x-text="pad(h)"></button>
                </template>
            </div>

            <span class="lc-time__colon" aria-hidden="true">:</span>

            <div class="lc-time__col" role="listbox" aria-label="Minutes"
                 x-ref="mcol"
                 @keydown.arrow-up.prevent="bumpMinute(-minuteStep)"
                 @keydown.arrow-down.prevent="bumpMinute(minuteStep)"
                 @keydown.home.prevent="setMinute(0)"
                 @keydown.end.prevent="setMinute(60 - minuteStep)">
                <template x-for="m in minuteValues" :key="'m-'+m">
                    <button type="button"
                            class="lc-time__cell"
                            :class="{ 'is-active': m === minute }"
                            :aria-selected="m === minute ? 'true' : 'false'"
                            role="option"
                            @click="setMinute(m)"
                            x-text="pad(m)"></button>
                </template>
            </div>

            <div class="lc-time__col lc-time__col--ampm" x-show="!use24h" role="listbox" aria-label="AM or PM">
                <button type="button"
                        class="lc-time__cell"
                        :class="{ 'is-active': period === 'AM' }"
                        :aria-selected="period === 'AM' ? 'true' : 'false'"
                        role="option"
                        @click="setPeriod('AM')">AM</button>
                <button type="button"
                        class="lc-time__cell"
                        :class="{ 'is-active': period === 'PM' }"
                        :aria-selected="period === 'PM' ? 'true' : 'false'"
                        role="option"
                        @click="setPeriod('PM')">PM</button>
            </div>
        </div>

        <div class="lc-time__footer">
            <button type="button" class="lc-date__action" @click="pickNow()">Now</button>
            <button type="button" class="lc-date__action" @click="commit()">Done</button>
        </div>
    </div>

    @if ($error)
        <p id="{{ $errorId }}" class="lc-select__error" role="alert">{{ $error }}</p>
    @endif

    @once
        @include('select::styles')
    @endonce
    @once
        <script data-lc-time-alpine>
            (function () {
                if (window.__loggedCloudTimeLoaded) return;
                window.__loggedCloudTimeLoaded = true;

                const pad = (n) => String(n).padStart(2, '0');

                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('loggedCloudTime', (config) => ({
                        triggerId: config.triggerId,
                        placeholder: config.placeholder || '',
                        minuteStep: config.minuteStep || 5,
                        use24h: config.use24h !== false,
                        a11y: config.a11y || {},
                        open: false,
                        value: config.selected || '',
                        hour: 0,
                        minute: 0,
                        period: 'AM',
                        liveMessage: '',

                        init() {
                            const m = /^(\d{2}):(\d{2})$/.exec(this.value || '');
                            if (m) {
                                const h = parseInt(m[1], 10);
                                this.minute = parseInt(m[2], 10);
                                if (this.use24h) {
                                    this.hour = h;
                                } else {
                                    this.period = h >= 12 ? 'PM' : 'AM';
                                    this.hour = ((h + 11) % 12) + 1; // 1..12
                                }
                            } else {
                                this.hour = this.use24h ? 12 : 12;
                                this.minute = 0;
                                this.period = 'PM';
                            }
                        },

                        pad,

                        get hourValues() {
                            const out = [];
                            const max = this.use24h ? 24 : 12;
                            const start = this.use24h ? 0 : 1;
                            const end = this.use24h ? 23 : 12;
                            for (let i = start; i <= end; i++) out.push(i);
                            return out;
                        },

                        get minuteValues() {
                            const out = [];
                            for (let i = 0; i < 60; i += this.minuteStep) out.push(i);
                            return out;
                        },

                        setHour(h) { this.hour = h; this._scrollIntoView('hcol', h); },
                        setMinute(m) {
                            // Snap to the configured minute-step so manual
                            // arrow-key moves don't leave 'off-grid' values.
                            const snapped = Math.round(m / this.minuteStep) * this.minuteStep;
                            this.minute = ((snapped % 60) + 60) % 60;
                            this._scrollIntoView('mcol', this.minute);
                        },
                        setPeriod(p) { this.period = p; },

                        bumpHour(d) {
                            const max = this.use24h ? 23 : 12;
                            const min = this.use24h ? 0 : 1;
                            let next = this.hour + d;
                            if (this.use24h) {
                                next = ((next % 24) + 24) % 24;
                            } else {
                                if (next < 1) next = 12;
                                if (next > 12) next = 1;
                            }
                            this.setHour(next);
                        },
                        bumpMinute(d) {
                            let next = this.minute + d;
                            next = ((next % 60) + 60) % 60;
                            this.setMinute(next);
                        },

                        _scrollIntoView(refName, val) {
                            const col = this.$refs[refName];
                            if (!col) return;
                            // Find the active child and centre it in the column.
                            this.$nextTick(() => {
                                const active = col.querySelector('.is-active');
                                if (active && active.scrollIntoView) {
                                    active.scrollIntoView({ block: 'center', behavior: 'smooth' });
                                }
                            });
                        },

                        formatted() {
                            if (!this.value) return '';
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
                            this.$nextTick(() => {
                                this._scrollIntoView('hcol', this.hour);
                                this._scrollIntoView('mcol', this.minute);
                            });
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

                        commit() {
                            let h = this.hour;
                            if (!this.use24h) {
                                // 12 AM = 00, 12 PM = 12, otherwise the
                                // hour value carries through with the period.
                                if (this.period === 'AM') h = h === 12 ? 0 : h;
                                else                     h = h === 12 ? 12 : h + 12;
                            }
                            const iso = pad(h) + ':' + pad(this.minute);
                            this.value = iso;
                            this.liveMessage = (this.a11y.selected || 'Selected') + ' ' + iso;
                            if (this.$refs.hidden) {
                                this.$refs.hidden.value = iso;
                                this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                            this.close();
                        },

                        pickNow() {
                            const d = new Date();
                            const m = d.getMinutes();
                            // Snap "now" to the minute-step so the value
                            // matches the column grid the user can see.
                            this.minute = Math.round(m / this.minuteStep) * this.minuteStep % 60;
                            if (this.use24h) {
                                this.hour = d.getHours();
                            } else {
                                const h = d.getHours();
                                this.period = h >= 12 ? 'PM' : 'AM';
                                this.hour = ((h + 11) % 12) + 1;
                            }
                            this.commit();
                        },
                    }));
                });
            })();
        </script>
    @endonce
</div>
