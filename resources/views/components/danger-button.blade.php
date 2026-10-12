{{--
    Wave 0a — D3 danger button.
    Sentence case, red-600 accent. Used for destructive actions
    (Delete, Forget, etc.).
--}}
<button {{ $attributes->merge([
    'type' => 'submit',
    'class' => 'inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-sm text-white hover:bg-red-700 active:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50',
]) }}>
    {{ $slot }}
</button>