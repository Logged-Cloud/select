@props([
    'name',
    'id' => null,
    'selected' => null,
    'min' => 0,
    'max' => 100,
    'step' => 1,
    'suffix' => null,
    'showSlider' => true,
    'label' => null,
    'labelledBy' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
])
@php
    $groupId = $id ?? ($label ? \Illuminate\Support\Str::camel(\Illuminate\Support\Str::slug($label, '_')) : $name);
    $errorId = $groupId.'-error';

    $initial = is_numeric($selected) ? (float) $selected : (float) $min;
    $config = [
        'value' => $initial,
        'min' => (float) $min,
        'max' => (float) $max,
        'step' => (float) $step,
        'suffix' => $suffix,
        'showSlider' => (bool) $showSlider,
        'groupId' => $groupId,
        'a11y' => [
            'incremented' => 'incremented to',
            'decremented' => 'decremented to',
        ],
    ];
@endphp
<div x-data="loggedCloudNumberStepper({{ \Illuminate\Support\Js::from($config) }})"
     x-init="$nextTick(() => { if ($refs.fallback) { $refs.fallback.removeAttribute('name'); $refs.fallback.disabled = true; } })"
     class="lc-stepper {{ $error ? 'lc-stepper--error' : '' }}"
     id="{{ $groupId }}">

    {{-- No-JS fallback · a native <input type=number> with the same min /
         max / step. Hidden by default; revealed only inside <noscript>.
         The Alpine wrapper clears its name on boot so the hidden input
         is the sole poster once JS takes over. --}}
    <noscript>
        <style>[data-lc-stepper-fallback="{{ $groupId }}"] { display: inline-block !important; }</style>
    </noscript>
    <input type="number"
           x-ref="fallback"
           data-lc-stepper-fallback="{{ $groupId }}"
           name="{{ $name }}"
           value="{{ $initial }}"
           min="{{ $min }}"
           max="{{ $max }}"
           step="{{ $step }}"
           @if ($required) required @endif
           style="display:none;">

    <input type="hidden" name="{{ $name }}" :value="value" x-ref="hidden"
           @if ($required) required @endif>

    <div class="lc-stepper__row"
         role="spinbutton"
         tabindex="{{ $disabled ? '-1' : '0' }}"
         :aria-valuemin="min"
         :aria-valuemax="max"
         :aria-valuenow="value"
         :aria-valuetext="value + (suffix ? ' ' + suffix : '')"
         @if ($label) aria-label="{{ $label }}"
         @elseif ($labelledBy) aria-labelledby="{{ $labelledBy }}"
         @else aria-label="Number" @endif
         @if ($required) aria-required="true" @endif
         @if ($disabled) aria-disabled="true" @endif
         @if ($error) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
         @keydown.arrow-up.prevent="bump($event.shiftKey ? step*10 : step)"
         @keydown.arrow-right.prevent="bump($event.shiftKey ? step*10 : step)"
         @keydown.arrow-down.prevent="bump(-($event.shiftKey ? step*10 : step))"
         @keydown.arrow-left.prevent="bump(-($event.shiftKey ? step*10 : step))"
         @keydown.home.prevent="set(min)"
         @keydown.end.prevent="set(max)"
         @keydown.page-up.prevent="bump(step*10)"
         @keydown.page-down.prevent="bump(-step*10)">
        <button type="button" class="lc-stepper__btn" :disabled="value <= min" @click="bump(-step)" aria-label="Decrement">−</button>
        <div class="lc-stepper__value">
            <span x-text="formatted()"></span>
            <span class="lc-stepper__suffix" x-show="suffix" x-text="suffix"></span>
        </div>
        <button type="button" class="lc-stepper__btn" :disabled="value >= max" @click="bump(step)" aria-label="Increment">+</button>
    </div>

    <input type="range"
           x-show="showSlider"
           x-cloak
           class="lc-stepper__slider"
           :min="min" :max="max" :step="step"
           x-model.number="value"
           :aria-label="(@js($label) || 'Value') + ' slider'"
           @if ($disabled) disabled @endif
           @input="syncHidden()">

    @if ($error)
        <p id="{{ $errorId }}" class="lc-select__error" role="alert">{{ $error }}</p>
    @endif

    <div :id="groupId+'-live'" class="lc-select__live" aria-live="polite" aria-atomic="true" x-text="liveMessage"></div>

    @once
        @include('select::styles')
    @endonce
    @once
        <script data-lc-number-stepper-alpine>
            (function () {
                if (window.__loggedCloudNumberStepperLoaded) return;
                window.__loggedCloudNumberStepperLoaded = true;

                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('loggedCloudNumberStepper', (config) => ({
                        value: config.value,
                        min: config.min,
                        max: config.max,
                        step: config.step,
                        suffix: config.suffix,
                        showSlider: !!config.showSlider,
                        groupId: config.groupId,
                        a11y: config.a11y || {},
                        liveMessage: '',

                        set(n) {
                            const next = Math.max(this.min, Math.min(this.max, n));
                            // Round to step grid so 0.1 + 0.2 doesn't yield
                            // 0.30000000000000004 in the hidden input.
                            const stepped = Math.round(next / this.step) * this.step;
                            const clamped = Math.max(this.min, Math.min(this.max, stepped));
                            if (clamped === this.value) return;
                            const dir = clamped > this.value ? 'incremented' : 'decremented';
                            this.value = clamped;
                            this.liveMessage = (this.a11y[dir] || dir + ' to') + ' ' + this.formatted();
                            this.syncHidden();
                        },

                        bump(delta) {
                            this.set((this.value || 0) + delta);
                        },

                        // Strip trailing zeros from the formatted display
                        // when the step is fractional (e.g. 0.5) so the
                        // value reads cleanly.
                        formatted() {
                            if (Number.isInteger(this.step)) return String(this.value);
                            // Match the step's decimal count.
                            const digits = String(this.step).split('.')[1]?.length || 0;
                            return Number(this.value).toFixed(digits);
                        },

                        syncHidden() {
                            if (this.$refs.hidden) {
                                this.$refs.hidden.value = this.value;
                                this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        },
                    }));
                });
            })();
        </script>
    @endonce
</div>
