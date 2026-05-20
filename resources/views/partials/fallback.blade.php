{{--
    Progressive-enhancement fallback · renders a native <select> that is
    hidden by default and only shown when JavaScript is disabled (via a
    <noscript>-injected CSS rule). When Alpine boots, the Alpine wrapper's
    x-init clears the native select's `name` attribute so the form does
    not double-post the same field.

    Caller passes:
      @include('select::partials.fallback', [
          'name'      => 'prey_type',
          'items'     => $normalised,        // [{key, title, ...}]
          'selected'  => $selected,          // string|array|null
          'multi'     => false,              // <select multiple>?
          'fallbackId'=> 'preyType-fallback',
          'required'  => false,
          'noJsLabel' => $config['copy']['no_js_indicator'] ?? 'JS off',
          'noJsCopy'  => $config['copy']['no_js_warning'] ?? '...',
      ])
--}}
@php
    $selectedList = is_array($selected ?? null) ? array_map('strval', $selected) : [$selected !== null ? (string) $selected : null];
@endphp
<noscript>
    <style>
        [data-lc-fallback="{{ $fallbackId }}"] { display: block !important; }
        [data-lc-no-js="{{ $fallbackId }}"] { display: inline !important; }
    </style>
</noscript>
<select
    data-lc-fallback="{{ $fallbackId }}"
    id="{{ $fallbackId }}"
    name="{{ $multi ? $name.'[]' : $name }}"
    @if ($multi) multiple @endif
    @if ($required) required @endif
    class="lc-select__fallback"
    style="display:none;"
    x-ref="fallback">
    @unless ($multi)
        <option value="">— select —</option>
    @endunless
    @foreach ($items as $opt)
        <option value="{{ $opt['key'] }}" @selected(in_array($opt['key'], $selectedList, true))>{{ $opt['title'] }}</option>
    @endforeach
</select>
<span class="lc-no-js" data-lc-no-js="{{ $fallbackId }}" style="display:none;" title="{{ $noJsCopy }}">
    <span aria-hidden="true">●</span>
    <span class="lc-no-js__text">{{ $noJsLabel }}</span>
</span>
