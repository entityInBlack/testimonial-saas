{{--
    Wave 0a — shared page header (R7 / design 3.5 #5).
    Adopted by wave 1; not used by any existing page today.

    Props:
        $title     string  — required. The page title.
        $subtitle  string  — optional. A short helper under the title.

    Slots:
        default (actions)  — buttons or links placed to the right of the
                             title on >=sm, and on a wrapping row below
                             the title on <sm.

    Stacking rule:
        <sm:    title on top, then optional subtitle, then a wrapping
                flex row of actions.
        >=sm:   one row — title (+ optional subtitle) on the left,
                actions on the right, with `sm:items-end` so a longer
                subtitle aligns visually.

    Wave 0b — declare props (F1) so $title and $subtitle do not leak
    onto the rendered <header> as stray HTML attributes.
--}}
@props([
    'title' => '',
    'subtitle' => null,
])
<header {{ $attributes }} data-testid="page-header">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between sm:gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 break-words"
                data-testid="page-header-title">{{ $title }}</h1>
            @isset($subtitle)
                <p class="mt-1 text-sm text-gray-600"
                   data-testid="page-header-subtitle">{{ $subtitle }}</p>
            @endisset
        </div>
        @if (! empty(trim((string) $slot)))
            <div class="flex flex-wrap items-center gap-2"
                 data-testid="page-header-actions">
                {{ $slot }}
            </div>
        @endif
    </div>
</header>
