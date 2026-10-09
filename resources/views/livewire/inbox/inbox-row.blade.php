@php
    /**
     * The row component receives `$showRestore`, `$showForget`, and
     * `$showSoftDelete` flags from the parent InboxIndex. The parent
     * only sets showRestore/showForget when the current filter is
     * 'trash', so action affordances are filter-scoped.
     */
    /** @var \App\Models\Testimonial $row */
    $row = $testimonial;
    $photoUrl = $row->profile_photo ? \Illuminate\Support\Facades\Storage::disk('public')->url($row->profile_photo) : null;

    // Consent badge states:
    //   - consented + WoL on       -> "Live"
    //   - consented + WoL off      -> "Consented"
    //   - consent_given=0
    //     - consented_at set       -> "Consent withdrawn"
    //     - consented_at null      -> "No consent"
    $hasConsentAudit = (bool) $row->consented_at;
    $consentState = $row->consent_given
        ? ($row->is_wall_of_love ? 'live' : 'consented')
        : ($hasConsentAudit ? 'withdrawn' : 'none');
@endphp

<article @class([
        'bg-white shadow-sm rounded-lg p-3 sm:p-4',
        'ring-1 ring-amber-200' => $row->is_hidden,
    ])
    data-testid="inbox-row"
    data-row-id="{{ $row->id }}">

    <div class="flex flex-col sm:flex-row sm:items-start gap-3">

        {{-- Photo --}}
        @if ($photoUrl)
            <div class="flex-shrink-0">
                <img src="{{ $photoUrl }}" alt=""
                     class="h-12 w-12 sm:h-14 sm:w-14 rounded-full object-cover"
                     data-testid="row-photo">
            </div>
        @endif

        <div class="flex-1 min-w-0">
            {{-- Header line --}}
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                <span class="font-medium text-gray-900" data-testid="row-name">{{ $row->name }}</span>
                <span class="text-gray-400">·</span>
                <span class="text-gray-500" data-testid="row-email">{{ $row->email }}</span>
                <span class="text-gray-400">·</span>
                <span class="text-gray-500" data-testid="row-submitted-at">
                    {{ optional($row->submitted_at)->format('Y-m-d H:i') }}
                </span>

                {{-- Hidden flag chip --}}
                @if ($row->is_hidden)
                    <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-800"
                          data-testid="row-hidden-chip">
                        Hidden
                    </span>
                @endif
            </div>

            <div class="mt-1 text-xs text-gray-500" data-testid="row-address">
                {{ $row->address }}
            </div>

            @if ($row->company_name)
                <div class="mt-0.5 text-xs text-gray-500" data-testid="row-company">
                    {{ $row->company_name }}
                </div>
            @endif

            @if ($row->rating)
                <div class="mt-1 text-xs text-gray-700" data-testid="row-rating">
                    Rating: {{ $row->rating }}/5
                </div>
            @endif

            {{-- Consent badge --}}
            <div class="mt-1">
                @if ($consentState === 'live')
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-100 text-green-800"
                          data-testid="row-consent-badge" data-consent="live">
                        Live
                    </span>
                @elseif ($consentState === 'consented')
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-700"
                          data-testid="row-consent-badge" data-consent="consented">
                        Consented
                    </span>
                @elseif ($consentState === 'withdrawn')
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-50 text-yellow-800"
                          data-testid="row-consent-badge" data-consent="withdrawn">
                        Consent withdrawn
                    </span>
                @else
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-700"
                          data-testid="row-consent-badge" data-consent="none">
                        No consent
                    </span>
                @endif
            </div>

            {{-- Body / Edit --}}
            @if ($editing)
                <div class="mt-3 space-y-2" data-testid="row-edit-form">
                    <textarea wire:model="editTestimonial"
                              rows="4"
                              class="block w-full rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                              data-testid="row-edit-textarea"></textarea>
                    @if ($row->space && $row->space->rating_enabled)
                        <input type="number" min="1" max="5"
                               wire:model="editRating"
                               class="w-24 rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                               data-testid="row-edit-rating">
                    @endif
                    <div class="flex flex-wrap gap-2">
                        <button type="button"
                                wire:click="saveEdit"
                                class="inline-flex items-center px-2.5 py-1 rounded-md bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700"
                                data-testid="row-edit-save">
                            Save
                        </button>
                        <button type="button"
                                wire:click="cancelEdit"
                                class="inline-flex items-center px-2.5 py-1 rounded-md bg-white border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-50"
                                data-testid="row-edit-cancel">
                            Cancel
                        </button>
                    </div>
                </div>
            @else
                <p class="mt-2 text-sm text-gray-800 whitespace-pre-line break-words"
                   data-testid="row-testimonial">{{ $row->testimonial }}</p>
            @endif

            @if ($flash)
                <p class="mt-2 text-xs text-red-600" data-testid="row-flash">{{ $flash }}</p>
            @endif
        </div>

        {{-- Actions (right column on >= sm, bottom row on mobile) --}}
        <div class="flex flex-wrap sm:flex-col gap-1 sm:items-end w-full sm:w-auto"
             data-testid="row-actions">

            @if ($showSoftDelete ?? true)
                <div class="flex flex-wrap gap-1">
                    <button type="button"
                            wire:click="toggleFavorite"
                            class="px-2 py-1 text-[11px] rounded border {{ $row->is_favorite ? 'bg-yellow-50 border-yellow-300 text-yellow-800' : 'border-gray-300 text-gray-700 hover:bg-gray-50' }}"
                            data-testid="row-favorite">
                        {{ $row->is_favorite ? 'Unfavorite' : 'Favorite' }}
                    </button>

                    <button type="button"
                            wire:click="toggleWallOfLove"
                            @disabled(! $row->consent_given)
                            title="{{ $row->consent_given ? '' : 'Blocked — no consent on this row' }}"
                            class="px-2 py-1 text-[11px] rounded border {{ $row->is_wall_of_love ? 'bg-pink-50 border-pink-300 text-pink-800' : 'border-gray-300 text-gray-700 hover:bg-gray-50' }} {{ $row->consent_given ? '' : 'opacity-50 cursor-not-allowed' }}"
                            data-testid="row-walloflove">
                        {{ $row->is_wall_of_love ? 'WoL ✓' : 'Wall of Love' }}
                    </button>

                    <button type="button"
                            wire:click="toggleHidden"
                            class="px-2 py-1 text-[11px] rounded border {{ $row->is_hidden ? 'bg-amber-50 border-amber-300 text-amber-800' : 'border-gray-300 text-gray-700 hover:bg-gray-50' }}"
                            data-testid="row-hide">
                        {{ $row->is_hidden ? 'Unhide' : 'Hide' }}
                    </button>

                    <button type="button"
                            wire:click="startEdit"
                            class="px-2 py-1 text-[11px] rounded border border-gray-300 text-gray-700 hover:bg-gray-50"
                            data-testid="row-edit">
                        Edit
                    </button>

                    @if ($row->consent_given)
                        <button type="button"
                                wire:click="withdrawConsent"
                                wire:confirm="Withdraw consent for '{{ $row->name }}'? The row stays but the consent audit fields are kept as history."
                                class="px-2 py-1 text-[11px] rounded border border-gray-300 text-gray-700 hover:bg-gray-50"
                                data-testid="row-withdraw">
                            Withdraw consent
                        </button>
                    @endif

                    <button type="button"
                            wire:click="softDelete"
                            wire:confirm="Move '{{ $row->name }}' to Trash? You can restore it from the Trash tab within {{ (int) config('purge.retention_days', 30) }} days."
                            class="px-2 py-1 text-[11px] rounded border border-red-200 text-red-700 hover:bg-red-50"
                            data-testid="row-delete">
                        Delete
                    </button>
                </div>
            @endif

            @if (($showRestore ?? false) && $row->deleted_at)
                <div class="flex flex-wrap gap-1 mt-1">
                    <button type="button"
                            wire:click="restore"
                            class="px-2 py-1 text-[11px] rounded border border-emerald-300 text-emerald-800 bg-emerald-50 hover:bg-emerald-100"
                            data-testid="row-restore">
                        Restore
                    </button>

                    @if ($showForget ?? false)
                        <button type="button"
                                wire:click="forget"
                                wire:confirm="Forget '{{ $row->name }}' permanently? This deletes the testimonial and its photo, and closes any matching open deletion request."
                                class="px-2 py-1 text-[11px] rounded border border-red-300 text-red-800 bg-red-50 hover:bg-red-100"
                                data-testid="row-forget">
                            Forget now
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </div>
</article>
