@props([
    'name',
    'id' => null,
    'selected' => [],
    'firstDayOfWeek' => 1,    // 1 = Monday, 0 = Sunday
    'label' => null,
    'labelledBy' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
])
@php
    $groupId = $id ?? ($label ? \Illuminate\Support\Str::camel(\Illuminate\Support\Str::slug($label, '_')) : $name);
    $errorId = $groupId.'-error';
    $fallbackId = $groupId.'-fallback';
    $noJsLabel = config('select.copy.no_js_indicator', 'JS off');
    $noJsCopy = config('select.copy.no_js_warning', 'JavaScript is needed for the rich picker.');

    $allDays = ['sun','mon','tue','wed','thu','fri','sat'];
    $allLabels = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    $start = (int) $firstDayOfWeek;
    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $idx = ($start + $i) % 7;
        $days[] = ['key' => $allDays[$idx], 'short' => substr($allLabels[$idx], 0, 2), 'full' => $allLabels[$idx]];
    }

    $selectedKeys = collect($selected)->map(fn ($v) => (string) $v)->values()->all();

    // Build fallback items as {key, title} shape the partial expects.
    $fallbackItems = collect($days)->map(fn ($d) => [
        'key' => $d['key'], 'title' => $d['full'], 'subtitle' => '', 'svg' => '',
    ])->all();

    $config = [
        'days' => $days,
        'selected' => $selectedKeys,
        'groupId' => $groupId,
        'a11y' => [
            'added' => 'Selected',
            'removed' => 'Cleared',
        ],
    ];
@endphp
<div x-data="loggedCloudSchedule({{ \Illuminate\Support\Js::from($config) }})"
     x-init="$nextTick(() => { if ($refs.fallback) { $refs.fallback.name = ''; } })"
     class="lc-schedule {{ $error ? 'lc-schedule--error' : '' }}"
     id="{{ $groupId }}"
     role="group"
     @if ($label) aria-label="{{ $label }}" @endif
     @if ($labelledBy) aria-labelledby="{{ $labelledBy }}" @endif
     @if ($required) aria-required="true" @endif>

    @include('select::partials.fallback', [
        'name' => $name, 'items' => $fallbackItems, 'selected' => $selectedKeys,
        'multi' => true, 'fallbackId' => $fallbackId, 'required' => $required,
        'noJsLabel' => $noJsLabel, 'noJsCopy' => $noJsCopy,
    ])

    <template x-for="day in values" :key="'h-'+day">
        <input type="hidden" :name="@js($name).concat('[]')" :value="day">
    </template>

    <div class="lc-schedule__row">
        @foreach ($days as $i => $day)
            <button type="button"
                    class="lc-schedule__pill"
                    :class="{ 'is-on': isOn(@js($day['key'])) }"
                    :aria-pressed="isOn(@js($day['key'])) ? 'true' : 'false'"
                    aria-label="{{ $day['full'] }}"
                    @if ($disabled) aria-disabled="true" disabled @endif
                    @click="toggle(@js($day['key']))"
                    @keydown.space.prevent="toggle(@js($day['key']))"
                    @keydown.enter.prevent="toggle(@js($day['key']))">
                {{ $day['short'] }}
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
        <script data-lc-schedule-alpine>
            (function () {
                if (window.__loggedCloudScheduleLoaded) return;
                window.__loggedCloudScheduleLoaded = true;

                document.addEventListener('alpine:init', () => {
                    window.Alpine.data('loggedCloudSchedule', (config) => ({
                        days: config.days || [],
                        groupId: config.groupId,
                        a11y: config.a11y || {},
                        values: Array.isArray(config.selected) ? [...config.selected] : [],
                        liveMessage: '',

                        isOn(day) {
                            return this.values.includes(day);
                        },

                        toggle(day) {
                            const idx = this.values.indexOf(day);
                            const full = (this.days.find((d) => d.key === day) || {}).full || day;
                            if (idx >= 0) {
                                this.values.splice(idx, 1);
                                this.liveMessage = (this.a11y.removed || 'Cleared') + ' ' + full;
                            } else {
                                this.values.push(day);
                                this.liveMessage = (this.a11y.added || 'Selected') + ' ' + full;
                            }
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
