<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $spaceId ? __('Edit Space') : __('New Space') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Cap warning --}}
            @if ($this->showWarningBanner)
                <div class="bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded"
                     data-testid="cap-warning">
                    <p class="text-sm">
                        You're using {{ $this->liveSpaceCount }} of {{ $this->maxSpaces }} Spaces
                        (the Free plan limit).
                    </p>
                </div>
            @endif

            {{-- Cap block (form pre-flight, before submit) --}}
            @if (! $spaceId && $this->isAtCap)
                <div class="bg-red-50 border border-red-200 text-red-900 px-4 py-3 rounded"
                     data-testid="cap-block">
                    <p class="text-sm">
                        You've reached the Free plan limit of {{ $this->maxSpaces }} Spaces.
                        Delete a Space or contact support.
                    </p>
                </div>
            @endif

            @if ($errors->has('cap'))
                <div class="bg-red-50 border border-red-200 text-red-900 px-4 py-3 rounded"
                     data-testid="cap-error">
                    <p class="text-sm">{{ $errors->first('cap') }}</p>
                </div>
            @endif

            <form wire:submit="save" class="space-y-4 bg-white shadow-sm rounded-lg p-6"
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
                    <p class="mt-1 text-xs text-gray-500">
                        Lowercase, hyphens only, up to 60 chars. Cannot be one of:
                        s, api, admin, login, register, dashboard, billing, embed, assets, up.
                    </p>
                    <x-input-error :messages="$errors->get('slug')" class="mt-2" />

                    @if ($slugAvailable === false)
                        <div class="mt-2 text-sm text-amber-700" data-testid="slug-taken">
                            That slug is taken. Try
                            <button type="button" wire:click="applySuggestion" class="underline font-medium"
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
                    <textarea wire:model="ask" id="ask" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" rows="3" maxlength="2000" required></textarea>
                    <x-input-error :messages="$errors->get('ask')" class="mt-2" />
                </div>

                {{-- Rating enabled --}}
                <div class="flex items-center">
                    <input wire:model="ratingEnabled" id="ratingEnabled" type="checkbox"
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                    <label for="ratingEnabled" class="ms-2 text-sm text-gray-700">
                        Collect a star rating (1–5)
                    </label>
                </div>

                {{-- Theme --}}
                <div>
                    <x-input-label for="theme" :value="__('Theme')" />
                    <select wire:model="theme" id="theme"
                            class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                        <option value="minimal_light">Minimal light</option>
                        <option value="minimal_dark">Minimal dark</option>
                        <option value="soft_color">Soft color</option>
                    </select>
                    <x-input-error :messages="$errors->get('theme')" class="mt-2" />
                </div>

                {{-- field_config --}}
                <div>
                    <x-input-label :value="__('Optional fields')" />
                    <p class="text-xs text-gray-500 mb-2">
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
                            <div class="flex items-center justify-between border border-gray-200 rounded px-3 py-2">
                                <span class="text-sm text-gray-800">{{ $label }}</span>
                                <div class="flex items-center gap-4 text-sm">
                                    <label class="inline-flex items-center gap-1">
                                        <input type="checkbox" wire:model="fieldConfig.{{ $key }}.enabled" />
                                        <span>Enabled</span>
                                    </label>
                                    <label class="inline-flex items-center gap-1">
                                        <input type="checkbox" wire:model="fieldConfig.{{ $key }}.required" />
                                        <span>Required</span>
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('spaces.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">
                        Cancel
                    </a>
                    <x-primary-button data-testid="space-save" :disabled="! $spaceId && $this->isAtCap">
                        {{ $spaceId ? __('Save changes') : __('Create Space') }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</div>
