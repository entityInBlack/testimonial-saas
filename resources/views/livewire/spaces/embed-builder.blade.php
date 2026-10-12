<div>
    {{-- A7: page header. Title "Embed builder", subtitle the Space
         name, "Back to Spaces" as a secondary-style link in the
         actions slot. The actions slot is the default slot; the
         component already wraps it in a flex row and stacks
         it below the title on <sm. --}}
    <x-slot name="header">
        <x-page-header
            title="Embed builder"
            subtitle="{{ $this->space?->title }}">
            <a href="{{ route('spaces.index') }}" wire:navigate
               class="inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-sm text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
               data-testid="embed-back-link">
                Back to Spaces
            </a>
        </x-page-header>
    </x-slot>

    {{-- A8: same container as the header bar. The header bar uses
         max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 (layouts/app.blade.php
         line 20); we mirror those gutters here so the content's
         left edge equals the title's at every width. --}}
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            @if (session('embed_saved'))
                <div class="bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded mb-4"
                     data-testid="embed-saved">
                    {{ session('embed_saved') }}
                </div>
            @endif

            {{-- A6: items-start so the two cards do not stretch to
                 match each other's height. The preview card now
                 caps its own height with overflow-y-auto, so the
                 snippet card underneath is reachable without
                 scrolling past a screen-filling preview. --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

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
                        {{-- A4: the input has min=1 max=50 in the HTML
                             and the Livewire save() clamps anything
                             outside that range. The hint now matches
                             the real behaviour: the input refuses
                             values outside the browser-allowed range
                             (the spinner / arrow keys stop at the
                             bounds), and the server clamps anything
                             that does slip through. --}}
                        <p class="mt-1 text-xs text-gray-500"
                           data-testid="item-limit-hint">
                            The input is limited to whole numbers between 1 and 50.
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
                     data-ts-dark="{{ $darkMode ? 'on' : 'off' }}"
                     data-bg="@if ($this->isValidBackgroundColor()) {{ $backgroundColor }} @endif">

                    <h3 @class(['text-sm font-semibold mb-3', 'text-gray-100' => $darkMode, 'text-gray-700' => ! $darkMode])>
                        Preview ({{ $layout }}{{ $darkMode ? ', dark' : '' }}{{ $showRating ? '' : ', no rating' }})
                    </h3>

                    {{-- A2: a real masonry / carousel preview, with
                         the dark mode visibly applied. The wrapper
                         caps its own height (A6) so the snippet
                         card below is reachable without scrolling
                         past a screen-filling preview. The
                         data-ts-layout attribute is the explicit
                         marker the test uses; data-layout is
                         already asserted by the existing test
                         and is kept for that reason. --}}
                    <div @class([
                            'embed-preview-list',
                            'embed-preview-carousel' => $layout === 'carousel',
                            'embed-preview-masonry' => $layout === 'masonry',
                        ])
                         data-testid="preview-list"
                         data-layout="{{ $layout }}"
                         data-ts-layout="{{ $layout }}">
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
                                        'embed-preview-card',
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

                    {{-- A2: preview CSS — scoped to the embed builder
                         page so it cannot leak anywhere else. The
                         embed-preview-masonry class uses CSS
                         multi-column at three widths; the
                         embed-preview-carousel class becomes a
                         horizontal scroller with snap. The
                         embed-preview-dark class on the parent
                         already flips the chrome; the cards
                         inside use the data-dark switch on the
                         parent for their own colours. --}}
                    <style>
                        [data-testid="embed-preview"] .embed-preview-list {
                            max-height: 420px;
                            overflow-y: auto;
                            padding: 4px;
                            box-sizing: border-box;
                        }
                        [data-testid="embed-preview"] .embed-preview-list.embed-preview-masonry {
                            column-count: 1;
                            column-gap: 12px;
                        }
                        @media (min-width: 480px) {
                            [data-testid="embed-preview"] .embed-preview-list.embed-preview-masonry {
                                column-count: 2;
                            }
                        }
                        @media (min-width: 960px) {
                            [data-testid="embed-preview"] .embed-preview-list.embed-preview-masonry {
                                column-count: 3;
                            }
                        }
                        [data-testid="embed-preview"] .embed-preview-list.embed-preview-masonry .embed-preview-card {
                            break-inside: avoid;
                            display: inline-block;
                            width: 100%;
                            margin-bottom: 12px;
                            box-sizing: border-box;
                        }
                        [data-testid="embed-preview"] .embed-preview-list.embed-preview-carousel {
                            display: flex;
                            gap: 12px;
                            overflow-x: auto;
                            scroll-snap-type: x mandatory;
                            -webkit-overflow-scrolling: touch;
                            padding-bottom: 4px;
                        }
                        [data-testid="embed-preview"] .embed-preview-list.embed-preview-carousel .embed-preview-card {
                            flex: 0 0 260px;
                            scroll-snap-align: start;
                        }
                        [data-testid="embed-preview"].embed-preview-dark {
                            background-color: #111827;
                            color: #f3f4f6;
                        }
                    </style>
                </div>

            </div>

            {{-- Snippet --}}
            <div class="mt-6 bg-white shadow-sm rounded-lg p-6"
                 data-testid="embed-snippet-box">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <h3 class="text-sm font-semibold text-gray-700">Embed snippet</h3>
                    {{-- A9 (optional): copy button. Alpine is
                         already on the page (Livewire ships it),
                         so the fallback (selecting the snippet
                         text) uses zero new libraries. The
                         clipboard path is wrapped in try/catch
                         and the fallback is always available so
                         the page never breaks when
                         navigator.clipboard is unavailable. --}}
                    <button type="button"
                            x-data="{ copied: false, fallback() { const el = document.querySelector('[data-testid=embed-snippet]'); if (!el) return; const range = document.createRange(); range.selectNodeContents(el); const sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(range); try { document.execCommand('copy'); this.copied = true; setTimeout(() => this.copied = false, 1500); } catch (e) { this.copied = false; } } }"
                            x-on:click="(async () => { try { if (navigator.clipboard && navigator.clipboard.writeText) { await navigator.clipboard.writeText($el.dataset.snippet); copied = true; setTimeout(() => copied = false, 1500); } else { fallback(); } } catch (e) { fallback(); } })()"
                            data-snippet="{{ $this->snippet() }}"
                            data-testid="embed-copy-button"
                            class="inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-3 py-1.5 bg-white border border-gray-300 rounded-md text-xs font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        <span x-show="!copied">Copy snippet</span>
                        <span x-show="copied" x-cloak>Copied</span>
                    </button>
                </div>
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
