<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Embed builder
                <span class="ms-2 text-sm text-gray-500 font-normal">— {{ $this->space?->title }}</span>
            </h2>
            <a href="{{ route('spaces.index') }}" wire:navigate
               class="text-sm text-gray-600 hover:text-gray-900">
                Back to Spaces
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">

                @if (session('embed_saved'))
                    <div class="bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded mb-4"
                         data-testid="embed-saved">
                        {{ session('embed_saved') }}
                    </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    {{-- Form --}}
                    <form wire:submit="save"
                          class="bg-white shadow-sm rounded-lg p-6 space-y-4"
                          data-testid="embed-form">

                        <div>
                            <x-input-label for="layout" :value="__('Layout')" />
                            <select wire:model.live="layout" id="layout"
                                    class="block mt-1 w-full border-gray-300 rounded-md shadow-sm"
                                    data-testid="layout-select">
                                <option value="masonry">Masonry</option>
                                <option value="carousel">Carousel</option>
                            </select>
                            <x-input-error :messages="$errors->get('layout')" class="mt-2" />
                        </div>

                        <div class="flex items-center">
                            <input wire:model.live="darkMode" id="darkMode" type="checkbox"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <label for="darkMode" class="ms-2 text-sm text-gray-700">Dark mode</label>
                        </div>

                        <div class="flex items-center">
                            <input wire:model.live="animationEnabled" id="animationEnabled" type="checkbox"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <label for="animationEnabled" class="ms-2 text-sm text-gray-700">Animations</label>
                        </div>

                        <div>
                            <x-input-label for="backgroundColor" :value="__('Background color (hex, optional)')" />
                            <x-text-input wire:model.live="backgroundColor" id="backgroundColor"
                                          class="block mt-1 w-full" type="text" maxlength="7"
                                          placeholder="#1a2B3c" />
                            <x-input-error :messages="$errors->get('backgroundColor')" class="mt-2" />
                        </div>

                        <div class="flex items-center">
                            <input wire:model.live="showRating" id="showRating" type="checkbox"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <label for="showRating" class="ms-2 text-sm text-gray-700">Show rating</label>
                        </div>

                        <div>
                            <x-input-label for="itemLimitInput" :value="__('Item limit (1–50)')" />
                            <x-text-input wire:model.live="itemLimitInput" id="itemLimitInput"
                                          class="block mt-1 w-full" type="number" min="1" max="50"
                                          data-testid="item-limit-input" />
                            <p class="mt-1 text-xs text-gray-500">
                                Values above 50 are clamped to 50; values below 1 become 1.
                            </p>
                            <x-input-error :messages="$errors->get('itemLimitInput')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end">
                            <x-primary-button data-testid="embed-save">Save</x-primary-button>
                        </div>
                    </form>

                    {{-- Preview --}}
                    <div @class([
                            'bg-white shadow-sm rounded-lg p-6',
                            'embed-preview-dark' => $darkMode,
                        ])
                        @if ($this->isValidBackgroundColor())
                            style="background-color: {{ $backgroundColor }}"
                        @endif
                         data-testid="embed-preview"
                         data-layout="{{ $layout }}"
                         data-dark="{{ $darkMode ? '1' : '0' }}"
                         data-bg="@if ($this->isValidBackgroundColor()) {{ $backgroundColor }} @endif">

                        <h3 class="text-sm font-semibold text-gray-700 mb-3">
                            Preview ({{ $layout }}{{ $darkMode ? ', dark' : '' }}{{ $showRating ? '' : ', no rating' }})
                        </h3>

                        <div @class([
                                'space-y-3',
                                'embed-preview-carousel' => $layout === 'carousel',
                            ])
                             data-testid="preview-list"
                             data-layout="{{ $layout }}">
                            @forelse ($this->previewTestimonials as $t)
                                @php
                                    $photoUrl = $t->profile_photo
                                        ? \Illuminate\Support\Facades\Storage::disk('public')->url($t->profile_photo)
                                        : null;
                                    $socialHref = null;
                                    if (is_string($t->social_url) && $t->social_url !== '') {
                                        $parts = parse_url($t->social_url);
                                        if ($parts !== false && !empty($parts['scheme']) && !empty($parts['host'])) {
                                            $scheme = strtolower((string) $parts['scheme']);
                                            if ($scheme === 'http' || $scheme === 'https') {
                                                $socialHref = $t->social_url;
                                            }
                                        }
                                    }
                                @endphp
                                <article @class([
                                            'rounded p-3 border',
                                            'bg-gray-900 text-gray-100 border-gray-700' => $darkMode,
                                            'bg-white text-gray-900 border-gray-200' => ! $darkMode,
                                        ])
                                         data-testid="preview-card"
                                         data-layout="{{ $layout }}">
                                    <div class="flex items-center gap-2">
                                        @if ($photoUrl)
                                            <img src="{{ $photoUrl }}" alt=""
                                                 class="w-8 h-8 rounded-full object-cover flex-shrink-0"
                                                 data-testid="preview-photo" />
                                        @endif
                                        <div class="text-sm font-medium"
                                             data-testid="preview-name">{{ $t->name }}</div>
                                    </div>
                                    @if($t->company_name)
                                        <div class="text-xs opacity-75">{{ $t->company_name }}</div>
                                    @endif
                                    <p class="mt-1 text-sm"
                                       data-testid="preview-body">{{ $t->testimonial }}</p>
                                    @if($showRating && $t->rating !== null)
                                        <div class="mt-1 text-xs opacity-75"
                                             data-testid="preview-rating">
                                            @for ($i = 1; $i <= 5; $i++)
                                                {{ $i <= (int) $t->rating ? '★' : '☆' }}
                                            @endfor
                                        </div>
                                    @endif
                                    @if($socialHref)
                                        <a href="{{ $socialHref }}" target="_blank"
                                           rel="noopener noreferrer"
                                           class="inline-block mt-1 text-xs break-all underline"
                                           data-testid="preview-social">{{ $socialHref }}</a>
                                    @endif
                                </article>
                            @empty
                                <p class="text-sm opacity-75" data-testid="preview-empty">
                                    No public testimonials yet for this Space.
                                </p>
                            @endforelse
                        </div>
                    </div>

                </div>

                {{-- Snippet --}}
                <div class="mt-6 bg-white shadow-sm rounded-lg p-6"
                     data-testid="embed-snippet-box">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Embed snippet</h3>
                    <p class="text-xs text-gray-500 mb-3">
                        Paste this on your site. Uses the immutable <code>public_id</code>, so renaming the
                        Space's slug will not break it.
                    </p>
                    <pre class="bg-gray-50 border border-gray-200 rounded p-3 text-xs overflow-auto"
                         data-testid="embed-snippet">{{ $this->snippet() }}</pre>
                </div>

        </div>
    </div>
</div>
