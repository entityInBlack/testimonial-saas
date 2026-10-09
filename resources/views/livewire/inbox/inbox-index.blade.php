<div>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight" data-testid="inbox-title">
                {{ __('Inbox') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-5xl mx-auto px-3 sm:px-6 lg:px-8 space-y-4">

            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded text-sm"
                     data-testid="status-flash">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('inbox_error'))
                <div class="bg-red-50 border border-red-200 text-red-900 px-4 py-3 rounded text-sm"
                     data-testid="inbox-error">
                    {{ session('inbox_error') }}
                </div>
            @endif

            {{-- Space selector --}}
            <div class="bg-white shadow-sm rounded-lg p-3 sm:p-4" data-testid="space-selector">
                <label for="inbox-space" class="block text-xs font-medium text-gray-600 mb-1">
                    Space
                </label>
                <select id="inbox-space"
                        wire:model.live="spaceId"
                        class="w-full sm:max-w-md rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">— Select a Space —</option>
                    @foreach ($this->spaces as $space)
                        <option value="{{ $space->id }}">{{ $space->title }} ({{ '/s/'.$space->slug }})</option>
                    @endforeach
                </select>
                @if ($this->spaces->isEmpty())
                    <p class="mt-2 text-xs text-gray-500" data-testid="no-spaces-msg">
                        You don't have any Spaces yet.
                    </p>
                @endif
            </div>

            @if ($this->selectedSpace)
                {{-- Filter tabs --}}
                <div class="bg-white shadow-sm rounded-lg" data-testid="filter-tabs">
                    <div class="flex flex-wrap gap-1 p-2 border-b border-gray-100" role="tablist">
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
                                        'px-3 py-1.5 text-xs sm:text-sm rounded-md',
                                        'bg-indigo-50 text-indigo-700 font-medium' => $this->filter === $key,
                                        'text-gray-600 hover:text-gray-900'        => $this->filter !== $key,
                                    ])>
                                {{ $label }}
                                <span class="ml-1 text-[10px] text-gray-400"
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
                        <div class="bg-white shadow-sm rounded-lg p-6 text-center text-sm text-gray-500"
                             data-testid="empty-state">
                            @if ($this->filter === 'trash')
                                Trash is empty.
                            @else
                                No testimonials yet. Share the public link
                                <code class="font-mono text-xs">/s/{{ $this->selectedSpace->slug }}</code>
                                to start collecting.
                            @endif
                        </div>
                    @endforelse
                </div>

                {{-- Pagination --}}
                <div class="mt-2" data-testid="pagination">
                    {{ $this->testimonials->links() }}
                </div>
            @else
                <div class="bg-white shadow-sm rounded-lg p-6 text-center text-sm text-gray-500"
                     data-testid="empty-state">
                    Select a Space to see its inbox.
                </div>
            @endif
        </div>
    </div>
</div>
