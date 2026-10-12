{{--
    Wave 0a — D3 primary button.
    Sentence case, indigo accent. Focus ring visible.
    Below sm every button is at least 44 px tall (touch target).
--}}
<button {{ $attributes->merge([
    'type' => 'submit',
    'class' => 'inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white hover:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50',
]) }}>
    {{ $slot }}
</button>