@props([
    'name',
    'id' => null,
    'selected' => null,
    'scale' => 5,                  // 5 / 7 / 10 (NPS)
    'minLabel' => null,            // e.g. 'Strongly disagree'
    'maxLabel' => null,            // e.g. 'Strongly agree'
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

    $scale = max(2, (int) $scale);
    // NPS-style scales start at 0, 1-5/1-7 style start at 1. Use start=0 for
    // 10+, start=1 otherwise · matches the convention surveys expect.
    $start = $scale >= 10 ? 0 : 1;
    $end = $start === 0 ? $scale : $scale;
    $points = range($start, $end);

    $fallbackItems = collect($points)->map(fn ($p) => [
        'key' => (string) $p, 'title' => (string) $p, 'subtitle' => '', 'svg' => '',
    ])->all();

    $config = [
        'points' => $points,
        'selected' => is_numeric($selected) ? (int) $selected : null,
        'groupId' => $groupId,
        'a11y' => [
            'selected' => 'Selected',
        ],
    ];
@endphp
<div x-data="loggedCloudLikert({{ \Illuminate\Support\Js::from($config) }})"
     x-init="$nextTick(() => { if ($refs.fallback) { $refs.fallback.name = ''; } })"
     class="lc-likert {{ $error ? 'lc-likert--error' : '' }}"
     id="{{ $groupId }}"
     role="radiogroup"
     @if ($label) aria-label="{{ $label }}" @endif
     @if ($labelledBy) aria-labelledby="{{ $labelledBy }}" @endif
     @if ($required) aria-required="true" @endif>

    @include('select::partials.fallback', [
        'name' => $name, 'items' => $fallbackItems,
        'selected' => $selected !== null ? (string) $selected : null,
        'multi' => false, 'fallbackId' => $fallbackId, 'required' => $required,
        'noJsLabel' => $noJsLabel, 'noJsCopy' => $noJsCopy,
    ])

    <input type="hidden" name="{{ $name }}" :value="value" x-ref="hidden"
           @if ($required) required @endif>

    @if ($minLabel || $maxLabel)
        <div class="lc-likert__endpoints" aria-hidden="true">
            <span class="lc-likert__endpoint">{{ $minLabel }}</span>
            <span class="lc-likert__endpoint">{{ $maxLabel }}</span>
        </div>
    @endif

    <div class="lc-likert__row">
        @foreach ($points as $i => $p)
            <button type="button"
                    :id="@js($groupId).concat('__opt-' + {{ $p }})"
                    role="radio"
                    :aria-checked="value === {{ $p }} ? 'true' : 'false'"
                    :tabindex="(value === {{ $p }} || (value === null && {{ $i }} === 0)) ? 0 : -1"
                    @if ($disabled) aria-disabled="true" disabled @endif
                    :class="{ 'is-selected': value === {{ $p }} }"
                    class="lc-likert__pip"
                    aria-label="{{ $p }} of {{ $end }}"
                    @click="pick({{ $p }})"
                    @keydown.arrow-right.prevent="moveBy(1)"
                    @keydown.arrow-up.prevent="moveBy(1)"
                    @keydown.arrow-left.prevent="moveBy(-1)"
                    @keydown.arrow-down.prevent="moveBy(-1)"
                    @keydown.home.prevent="pick(points[0])"
                    @keydown.end.prevent="pick(points[points.length - 1])"
                    @keydown.space.prevent="pick({{ $p }})"
                    @keydown.enter.prevent="pick({{ $p }})">
                {{ $p }}
            </button>
        @endforeach
    </div>

    @if ($error)
        <p id="{{ $errorId }}" class="lc-select__error" role="alert">{{ $error }}</p>
    @endif

    <div :id="groupId+'-live'" class="lc-select__live" aria-live="polite" aria-atomic="true" x-text="liveMessage"></div>

    @once
        @include('select::styles')
    @endonce
    @once
        <script data-lc-likert-alpine>
            (function () {
                if (window.__loggedCloudLikertLoaded) return;
                window.__loggedCloudLikertLoaded = true;

                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('loggedCloudLikert', (config) => ({
                        points: config.points || [],
                        groupId: config.groupId,
                        a11y: config.a11y || {},
                        value: config.selected,
                        liveMessage: '',

                        pick(n) {
                            this.value = n;
                            this.liveMessage = (this.a11y.selected || 'Selected') + ' ' + n;
                            if (this.$refs.hidden) {
                                this.$refs.hidden.value = n;
                                this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                            // Move focus to the picked pip so roving-tabindex
                            // stays consistent with the WAI-ARIA radiogroup
                            // pattern.
                            this.$nextTick(() => document.getElementById(this.groupId + '__opt-' + n)?.focus());
                        },

                        moveBy(delta) {
                            const cur = this.points.indexOf(this.value);
                            const idx = Math.max(0, Math.min(this.points.length - 1, (cur < 0 ? 0 : cur) + delta));
                            this.pick(this.points[idx]);
                        },
                    }));
                });
            })();
        </script>
    @endonce
</div>
