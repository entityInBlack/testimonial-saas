<div>
    <x-slot name="header">
        <x-page-header
            :title="$spaceId ? __('Edit Space') : __('New Space')"
            data-testid="space-form-page-header"
        />
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            {{-- Cap warning --}}
            @if ($this->showWarningBanner)
                <div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm"
                     data-testid="cap-warning">
                    <p>
                        You're using {{ $this->liveSpaceCount }} of {{ $this->maxSpaces }} Spaces
                        (the Free plan limit).
                    </p>
                </div>
            @endif

            {{-- Cap block (form pre-flight, before submit) --}}
            @if (! $spaceId && $this->isAtCap)
                <div class="rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm"
                     data-testid="cap-block">
                    <p>
                        You've reached the Free plan limit of {{ $this->maxSpaces }} Spaces.
                        Delete a Space or contact support.
                    </p>
                </div>
            @endif

            @if ($errors->has('cap'))
                <div class="rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm"
                     data-testid="cap-error">
                    <p>{{ $errors->first('cap') }}</p>
                </div>
            @endif

            <form wire:submit="save" class="space-y-6 rounded-2xl border border-gray-200 bg-white p-5 sm:p-8 shadow-sm"
                  data-testid="space-form">
                @csrf

                {{-- Title --}}
                <div>
                    <x-input-label for="title" :value="__('Title (shown publicly)')" />
                    <x-text-input wire:model.live="title" id="title" class="block mt-1 w-full" type="text" maxlength="160" required />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>

                {{-- Name (internal) --}}
                <div>
                    <x-input-label for="name" :value="__('Name (internal)')" />
                    <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" maxlength="120"
                                  placeholder="Defaults to the title" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                {{-- Slug --}}
                <div>
                    <x-input-label for="slug" :value="__('Slug (used in /s/{slug})')" />
                    <x-text-input wire:model.blur="slug" wire:change="probeSlug" id="slug" class="block mt-1 w-full" type="text"
                                  maxlength="60" required
                                  data-testid="slug-input" />
                    <p class="mt-1 text-xs text-gray-600">
                        Lowercase, hyphens only, up to 60 chars. Cannot be one of:
                        s, api, admin, login, register, dashboard, billing, embed, assets, up.
                    </p>
                    <x-input-error :messages="$errors->get('slug')" class="mt-2" />

                    @if ($slugAvailable === false)
                        <div class="mt-2 text-sm text-red-700" data-testid="slug-taken">
                            That slug is taken. Try
                            <button type="button" wire:click="applySuggestion"
                                    class="app-lift inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-3 py-1.5 ml-1 rounded-xl bg-white border border-gray-300 underline text-sm font-medium text-indigo-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    data-testid="apply-suggestion">
                                {{ $slugSuggestion }}
                            </button>
                            instead.
                        </div>
                    @elseif ($slugAvailable === true)
                        <div class="mt-2 text-sm text-green-700" data-testid="slug-free">
                            Slug is available.
                        </div>
                    @endif
                </div>

                {{-- Subtitle --}}
                <div>
                    <x-input-label for="subtitle" :value="__('Subtitle (optional)')" />
                    <x-text-input wire:model="subtitle" id="subtitle" class="block mt-1 w-full" type="text" maxlength="255" />
                    <x-input-error :messages="$errors->get('subtitle')" class="mt-2" />
                </div>

                {{-- Ask --}}
                <div>
                    <x-input-label for="ask" :value="__('Ask (the prompt shown to respondents)')" />
                    <textarea wire:model="ask" id="ask" class="block mt-1 w-full border-gray-300 rounded-xl shadow-sm" rows="3" maxlength="2000" required></textarea>
                    <x-input-error :messages="$errors->get('ask')" class="mt-2" />
                </div>

                {{-- Rating enabled --}}
                <div class="flex items-center min-h-[44px]">
                    <input wire:model="ratingEnabled" id="ratingEnabled" type="checkbox"
                           class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                    <label for="ratingEnabled" class="ms-2 text-sm text-gray-700">
                        Collect a star rating (1–5)
                    </label>
                </div>

                {{-- Theme --}}
                <div>
                    <x-input-label for="theme" :value="__('Theme')" />
                    <select wire:model="theme" id="theme"
                            class="block mt-1 w-full min-h-[44px] sm:min-h-0 border-gray-300 rounded-xl shadow-sm">
                        <option value="minimal_light">Minimal light</option>
                        <option value="minimal_dark">Minimal dark</option>
                        <option value="soft_color">Soft color</option>
                    </select>
                    <x-input-error :messages="$errors->get('theme')" class="mt-2" />
                </div>

                {{-- field_config --}}
                <div>
                    <x-input-label :value="__('Optional fields')" />
                    <p class="text-sm text-gray-600 mb-2">
                        Name, email, and address are always on and required. The toggles below
                        are for the three optional fields the public form may collect.
                    </p>

                    @php
                        $fields = [
                            'company_name' => 'Company name',
                            'social_url'   => 'Social URL',
                            'profile_photo' => 'Profile photo',
                        ];
                    @endphp

                    <div class="space-y-2">
                        @foreach ($fields as $key => $label)
                            @php $row = $fieldConfig[$key] ?? ['enabled' => false, 'required' => false]; @endphp
                            <div class="flex flex-wrap items-center justify-between gap-2 border border-gray-200 rounded-xl px-3 py-2 min-h-[44px]">
                                <span class="text-sm text-gray-800">{{ $label }}</span>
                                <div class="flex flex-wrap items-center gap-4 text-sm">
                                    <label class="inline-flex items-center gap-1 min-h-[44px] sm:min-h-0">
                                        <input type="checkbox" wire:model="fieldConfig.{{ $key }}.enabled"
                                               class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        <span>Enabled</span>
                                    </label>
                                    <label class="inline-flex items-center gap-1 min-h-[44px] sm:min-h-0">
                                        <input type="checkbox" wire:model="fieldConfig.{{ $key }}.required"
                                               class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        <span>Required</span>
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="{{ route('spaces.index') }}" wire:navigate class="inline-flex items-center min-h-[44px] sm:min-h-0 px-3 text-sm font-semibold text-gray-600 hover:text-gray-900">
                        Cancel
                    </a>
                    <x-primary-button
                        class="!rounded-xl !shadow-lg !shadow-indigo-600/20"
                        data-testid="space-save"
                        :disabled="! $spaceId && $this->isAtCap"
                        wire:loading.attr="disabled"
                        wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $spaceId ? __('Save changes') : __('Create Space') }}</span>
                        <span wire:loading wire:target="save">Working…</span>
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</div>
