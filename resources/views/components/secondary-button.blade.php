{{--
    Wave 0a — D3 secondary button.
    Sentence case, white surface with a gray border. Used when the page
    offers a non-destructive action next to a primary (e.g. Cancel).
--}}
<button {{ $attributes->merge([
    'type' => 'button',
    'class' => 'inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-sm text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50',
]) }}>
    {{ $slot }}
</button>