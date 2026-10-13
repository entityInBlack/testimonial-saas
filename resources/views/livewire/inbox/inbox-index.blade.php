<div>
    <x-slot name="header">
        <x-page-header
            :title="__('Inbox')"
            data-testid="inbox-page-header"
        />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            @if (session('status'))
                <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm"
                     data-testid="status-flash">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('inbox_error'))
                <div class="rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm"
                     data-testid="inbox-error">
                    {{ session('inbox_error') }}
                </div>
            @endif

            {{-- Space selector --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-3 sm:p-4 shadow-sm" data-testid="space-selector">
                <label for="inbox-space" class="block text-sm text-gray-600 mb-1">
                    Space
                </label>
                <select id="inbox-space"
                        wire:model.live="spaceId"
                        class="w-full sm:max-w-md min-h-[44px] sm:min-h-0 rounded-xl border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                        data-testid="inbox-space-select">
                    <option value="">— Select a Space —</option>
                    @foreach ($this->spaces as $space)
                        <option value="{{ $space->id }}">{{ $space->title }}</option>
                    @endforeach
                </select>
                @if ($this->spaces->isEmpty())
                    <p class="mt-2 text-sm text-gray-600" data-testid="no-spaces-msg">
                        You don't have any Spaces yet.
                    </p>
                @endif
            </div>

            @if ($this->selectedSpace)
                {{-- Filter tabs --}}
                <div class="rounded-2xl border border-gray-200 bg-white shadow-sm" data-testid="filter-tabs">
                    <div class="flex flex-wrap gap-2 p-3" role="tablist">
                        @php
                            $tabs = [
                                'all'       => 'All',
                                'favorites' => 'Favorites',
                                'wall'      => 'Wall of Love',
                                'hidden'    => 'Hidden',
                                'trash'     => 'Trash',
                            ];
                        @endphp
                        @foreach ($tabs as $key => $label)
                            <button type="button"
                                    wire:click="$set('filter', '{{ $key }}')"
                                    wire:key="filter-{{ $key }}"
                                    data-testid="filter-tab-{{ $key }}"
                                    @class([
                                        'app-lift min-h-[44px] sm:min-h-0 px-4 py-2 text-sm rounded-full border focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                                        'bg-indigo-600 border-indigo-600 text-white' => $this->filter === $key,
                                        'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' => $this->filter !== $key,
                                    ])>
                                {{ $label }}
                                <span class="ml-1 text-xs"
                                      data-testid="filter-count-{{ $key }}">
                                    ({{ $this->counts[$key] ?? 0 }})
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- The list --}}
                <div class="space-y-3" data-testid="inbox-list">
                    @forelse ($this->testimonials as $row)
                        <livewire:inbox.inbox-row
                            :key="'row-'.$row->id.'-'.$this->filter"
                            :testimonial-id="$row->id"
                            :show-restore="$this->filter === 'trash'"
                            :show-forget="$this->filter === 'trash'"
                            :show-soft-delete="$this->filter !== 'trash'"
                        />
                    @empty
                        <div class="rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-sm"
                             data-testid="empty-state">
                            <div class="mx-auto mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 9v.906a2.25 2.25 0 01-1.183 1.981l-6.478 3.218M11.25 21v-9.045M21.75 9L12 14.25 2.25 9m19.5 0a2.25 2.25 0 00-1.183-1.98L12 3.75 3.433 7.02A2.25 2.25 0 002.25 9" />
                                </svg>
                            </div>
                            @if ($this->filter === 'trash')
                                <p class="text-sm text-gray-600">Trash is empty.</p>
                            @else
                                <p class="text-sm text-gray-600">
                                    No testimonials yet. Share the public link
                                    <code class="font-mono text-xs">/s/{{ $this->selectedSpace->slug }}</code>
                                    to start collecting.
                                </p>
                            @endif
                        </div>
                    @endforelse
                </div>

                {{-- Pagination --}}
                <div class="mt-2" data-testid="pagination">
                    {{ $this->testimonials->links() }}
                </div>
            @else
                <div class="rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-sm"
                     data-testid="empty-state">
                    <div class="mx-auto mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 9v.906a2.25 2.25 0 01-1.183 1.981l-6.478 3.218M11.25 21v-9.045M21.75 9L12 14.25 2.25 9m19.5 0a2.25 2.25 0 00-1.183-1.98L12 3.75 3.433 7.02A2.25 2.25 0 002.25 9" />
                        </svg>
                    </div>
                    <p class="text-sm text-gray-600">Select a Space to see its inbox.</p>
                </div>
            @endif
        </div>
    </div>
</div>
