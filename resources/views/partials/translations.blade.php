{{--
    Hands the active locale — and optionally some message groups — to the React
    islands.

    The locale is emitted on EVERY page (included from each layout's <head>),
    because the language switcher lives in every layout and needs to know which
    language is active to show the right flag. Without it the switcher always
    reads "Indonesian" and switching looks broken even when it worked.

    Message groups are opt-in per page so this never grows into shipping the
    whole lang/ directory on every request. Merged, not replaced, so a layout
    and the page inside it can each contribute their own groups.

    Layout:  @include('partials.translations')
    Page:    @include('partials.translations', ['groups' => ['requester']])
--}}
@php
    $localeMessages = collect($groups ?? [])
        ->mapWithKeys(fn (string $group) => [$group => trans($group)])
        ->all();
@endphp
<script>
    window.__LOCALE__ = @json(app()->getLocale());
    window.__LANG__ = Object.assign(window.__LANG__ || {}, @json($localeMessages));
</script>
