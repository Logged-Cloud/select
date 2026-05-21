@props([
    'name',
    'id' => null,
    'selected' => null,
    'max' => 5,
    'step' => 1,
    'allowZero' => true,
    'label' => null,
    'labelledBy' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
])
@php
    $groupId = $id ?? ($label ? \Illuminate\Support\Str::camel(\Illuminate\Support\Str::slug($label, '_')) : $name);
    $fallbackId = $groupId.'-fallback';
    $errorId = $groupId.'-error';
    $noJsLabel = config('select.copy.no_js_indicator', 'JS off');
    $noJsCopy = config('select.copy.no_js_warning', 'JavaScript is needed for the rich picker.');

    // The fallback uses 0..max as integer options · half-step nuance is a
    // JS-only thing because native <select> can't sanely express it.
    $fallbackItems = [];
    if ($allowZero) {
        $fallbackItems[] = ['key' => '0', 'title' => '0', 'subtitle' => '', 'svg' => ''];
    }
    for ($n = 1; $n <= (int) $max; $n++) {
        $fallbackItems[] = ['key' => (string) $n, 'title' => (string) $n, 'subtitle' => '', 'svg' => ''];
    }

    $stepFloat = ((float) $step) > 0 ? (float) $step : 1.0;
    $config = [
        'max' => (int) $max,
        'step' => $stepFloat,
        'allowZero' => (bool) $allowZero,
        'selected' => is_numeric($selected) ? (float) $selected : null,
        'groupId' => $groupId,
        'a11y' => [
            'stars' => 'stars',
            'star' => 'star',     // singular form · used when value === 1
            'cleared' => 'Rating cleared',
        ],
    ];
@endphp
<div x-data="loggedCloudRating({{ \Illuminate\Support\Js::from($config) }})"
     x-init="$nextTick(() => { if ($refs.fallback) { $refs.fallback.name = ''; } })"
     class="lc-rating {{ $error ? 'lc-rating--error' : '' }}"
     id="{{ $groupId }}">

    @include('select::partials.fallback', [
        'name' => $name, 'items' => $fallbackItems, 'selected' => is_numeric($selected) ? (string) (int) $selected : null,
        'multi' => false, 'fallbackId' => $fallbackId, 'required' => $required,
        'noJsLabel' => $noJsLabel, 'noJsCopy' => $noJsCopy,
    ])

    <input type="hidden" name="{{ $name }}" :value="value" x-ref="hidden"
           @if ($required) required @endif>

    <div role="slider"
         x-ref="track"
         tabindex="{{ $disabled ? '-1' : '0' }}"
         class="lc-rating__track"
         :class="{ 'is-empty': value === 0 && allowZero }"
         aria-orientation="horizontal"
         :aria-valuemin="allowZero ? 0 : step"
         :aria-valuemax="max"
         :aria-valuenow="value"
         :aria-valuetext="value + ' ' + (value === 1 ? (a11y.star || 'star') : (a11y.stars || 'stars'))"
         @if ($label) aria-label="{{ $label }}"
         @elseif ($labelledBy) aria-labelledby="{{ $labelledBy }}"
         @else aria-label="Rating" @endif
         @if ($required) aria-required="true" @endif
         @if ($disabled) aria-disabled="true" @endif
         @if ($error) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
         @keydown.arrow-right.prevent="bump(step)"
         @keydown.arrow-up.prevent="bump(step)"
         @keydown.arrow-left.prevent="bump(-step)"
         @keydown.arrow-down.prevent="bump(-step)"
         @keydown.home.prevent="set(allowZero ? 0 : step)"
         @keydown.end.prevent="set(max)"
         @mouseleave="hover = null">

        {{-- Render `max` star slots · each slot has TWO clickable halves
             when step < 1 so half-star ratings stay reachable by mouse.
             The fill state reads from `displayValue` which is hover-aware. --}}
        @for ($i = 1; $i <= (int) $max; $i++)
            <span class="lc-rating__star" :class="(displayValue() >= {{ $i }}) ? 'is-full' : (displayValue() >= {{ $i - 0.5 }} ? 'is-half' : 'is-empty')">
                {{-- Half-step hit area (left half) --}}
                <button type="button"
                        class="lc-rating__hit lc-rating__hit--left"
                        x-show="step < 1"
                        aria-label="{{ ($i - 0.5) }} stars"
                        @if ($disabled) disabled @endif
                        @click="set({{ $i - 0.5 }})"
                        @mouseenter="hover = {{ $i - 0.5 }}"></button>
                <button type="button"
                        class="lc-rating__hit lc-rating__hit--right"
                        aria-label="{{ $i }} {{ $i === 1 ? 'star' : 'stars' }}"
                        @if ($disabled) disabled @endif
                        @click="set({{ $i }})"
                        @mouseenter="hover = {{ $i }}"></button>

                <svg viewBox="0 0 24 24" class="lc-rating__star-bg" aria-hidden="true">
                    <path d="M12 2l3 7h7l-6 4 2 7-6-4-6 4 2-7-6-4h7z" fill="currentColor" />
                </svg>
                <svg viewBox="0 0 24 24" class="lc-rating__star-fg" aria-hidden="true">
                    <path d="M12 2l3 7h7l-6 4 2 7-6-4-6 4 2-7-6-4h7z" fill="currentColor" />
                </svg>
            </span>
        @endfor

        {{-- Clear button only renders when allow-zero is on AND there's
             something to clear · keeps a tiny "x" tucked to the right. --}}
        <button type="button"
                x-show="allowZero && value > 0"
                x-cloak
                class="lc-rating__clear"
                aria-label="Clear rating"
                @click="set(0)">
            <svg viewBox="0 0 24 24" width="12" height="12" fill="none"
                 stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M6 6l12 12M6 18L18 6"></path>
            </svg>
        </button>
    </div>

    @if ($error)
        <p id="{{ $errorId }}" class="lc-select__error" role="alert">{{ $error }}</p>
    @endif

    <div :id="groupId+'-live'" class="lc-select__live" aria-live="polite" aria-atomic="true" x-text="liveMessage"></div>

    @once
        @include('select::styles')
    @endonce
    @once
        <script data-lc-rating-alpine>
            (function () {
                if (window.__loggedCloudRatingLoaded) return;
                window.__loggedCloudRatingLoaded = true;

                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('loggedCloudRating', (config) => ({
                        max: config.max || 5,
                        step: config.step || 1,
                        allowZero: config.allowZero !== false,
                        groupId: config.groupId,
                        a11y: config.a11y || {},
                        value: config.selected ?? (config.allowZero === false ? config.step : 0),
                        hover: null,
                        liveMessage: '',

                        // Hover overrides the rendered fill while the cursor is
                        // over a hit area · falls back to the committed value.
                        displayValue() {
                            return this.hover !== null ? this.hover : this.value;
                        },

                        set(n) {
                            const min = this.allowZero ? 0 : this.step;
                            const clamped = Math.max(min, Math.min(n, this.max));
                            if (clamped === this.value) return;
                            this.value = clamped;
                            this.liveMessage = clamped === 0
                                ? (this.a11y.cleared || 'Rating cleared')
                                : clamped + ' ' + (clamped === 1 ? (this.a11y.star || 'star') : (this.a11y.stars || 'stars'));
                            if (this.$refs.hidden) {
                                this.$refs.hidden.value = clamped;
                                this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        },

                        bump(delta) {
                            this.set((this.value || 0) + delta);
                        },
                    }));
                });
            })();
        </script>
    @endonce
</div>
