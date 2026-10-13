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
    //   - consented + is_wall_of_love on  -> "Live"
    //   - consented + is_wall_of_love off -> "Consented"
    //   - consent_given=0
    //     - consented_at set       -> "Consent withdrawn"
    //     - consented_at null      -> "No consent"
    $hasConsentAudit = (bool) $row->consented_at;
    $consentState = $row->consent_given
        ? ($row->is_wall_of_love ? 'live' : 'consented')
        : ($hasConsentAudit ? 'withdrawn' : 'none');
@endphp

<article @class([
        'rounded-2xl border border-gray-200 bg-white shadow-sm p-4 sm:p-5',
        'ring-1 ring-amber-200' => $row->is_hidden,
    ])
    data-testid="inbox-row"
    data-row-id="{{ $row->id }}">

    <div class="flex flex-col sm:flex-row sm:items-start gap-3">

        {{-- Photo --}}
        @if ($photoUrl)
            <div class="flex-shrink-0">
                <img src="{{ $photoUrl }}" alt=""
                     class="h-12 w-12 rounded-full object-cover"
                     data-testid="row-photo">
            </div>
        @endif

        <div class="flex-1 min-w-0">
            {{-- Identity: name + company on one line --}}
            <div class="text-[15px] flex flex-wrap items-baseline gap-x-2">
                <span class="font-semibold text-gray-900" data-testid="row-name">{{ $row->name }}</span>
                @if ($row->company_name)
                    <span class="text-[13px] text-gray-600" data-testid="row-company">{{ $row->company_name }}</span>
                @endif
            </div>

            {{-- Meta: small line(s) under the header --}}
            <div class="mt-1 text-xs text-gray-600 flex flex-col gap-0.5 sm:flex-row sm:flex-wrap sm:gap-x-2 sm:gap-y-0.5"
                 data-testid="row-meta">
                <span data-testid="row-email">{{ $row->email }}</span>
                <span data-testid="row-submitted-at">{{ optional($row->submitted_at)->format('Y-m-d H:i') }}</span>
                <span data-testid="row-address">{{ $row->address }}</span>

                {{-- Hidden flag chip --}}
                @if ($row->is_hidden)
                    <span class="inline-flex items-center self-start px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800"
                          data-testid="row-hidden-chip">
                        Hidden
                    </span>
                @endif
            </div>

            @if ($row->rating)
                <div class="mt-2 text-sm text-gray-600 flex items-center gap-1" data-testid="row-rating">
                    @for ($i = 1; $i <= 5; $i++)
                        <span @class(['text-amber-500' => $i <= (int) $row->rating, 'text-gray-300' => $i > (int) $row->rating]) aria-hidden="true">{{ $i <= (int) $row->rating ? '★' : '☆' }}</span>
                    @endfor
                    <span>Rating: {{ $row->rating }}/5</span>
                </div>
            @endif

            {{-- Consent badge --}}
            <div class="mt-2">
                @if ($consentState === 'live')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"
                          data-testid="row-consent-badge" data-consent="live">
                        Live
                    </span>
                @elseif ($consentState === 'consented')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700"
                          data-testid="row-consent-badge" data-consent="consented">
                        Consented
                    </span>
                @elseif ($consentState === 'withdrawn')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-50 text-yellow-800"
                          data-testid="row-consent-badge" data-consent="withdrawn">
                        Consent withdrawn
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700"
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
                              class="block w-full rounded-xl border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500 min-h-[44px] sm:min-h-0"
                              data-testid="row-edit-textarea"></textarea>
                    @if ($row->space && $row->space->rating_enabled)
                        <input type="number" min="1" max="5"
                               wire:model="editRating"
                               class="w-24 rounded-xl border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500 min-h-[44px] sm:min-h-0"
                               data-testid="row-edit-rating">
                    @endif
                    <div class="flex flex-wrap gap-2">
                        <button type="button"
                                wire:click="saveEdit"
                                class="inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 app-lift"
                                data-testid="row-edit-save">
                            Save
                        </button>
                        <button type="button"
                                wire:click="cancelEdit"
                                class="inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-4 py-2 rounded-xl bg-white border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50 app-lift"
                                data-testid="row-edit-cancel">
                            Cancel
                        </button>
                    </div>
                </div>
            @else
                <p class="mt-2 text-[15px] leading-relaxed text-gray-700 whitespace-pre-line break-words"
                   data-testid="row-testimonial">{{ $row->testimonial }}</p>
            @endif

            @if ($flash)
                <p class="mt-2 text-sm text-red-700" data-testid="row-flash">{{ $flash }}</p>
            @endif
        </div>

    </div>

    {{-- Actions footer --}}
    <div class="mt-4 pt-3 sm:pt-4 border-t border-gray-200 flex flex-col gap-2"
         data-testid="row-actions">

        @if ($showSoftDelete ?? true)
            <div class="flex flex-wrap items-center gap-2">
                <button type="button"
                        wire:click="toggleFavorite"
                        class="inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-3 py-2 text-sm rounded-xl border {{ $row->is_favorite ? 'bg-yellow-50 border-yellow-300 text-yellow-800' : 'border-gray-300 text-gray-700 hover:bg-gray-50' }} app-lift"
                        data-testid="row-favorite">
                    {{ $row->is_favorite ? 'Unfavorite' : 'Favorite' }}
                </button>

                <button type="button"
                        wire:click="toggleWallOfLove"
                        wire:loading.attr="disabled"
                        wire:target="toggleWallOfLove"
                        @disabled(! $row->consent_given)
                        title="{{ $row->consent_given ? '' : 'Blocked — no consent on this row' }}"
                        class="inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-3 py-2 text-sm rounded-xl border {{ $row->is_wall_of_love ? 'bg-pink-50 border-pink-300 text-pink-800' : 'border-gray-300 text-gray-700 hover:bg-gray-50' }} {{ $row->consent_given ? '' : 'opacity-50 cursor-not-allowed' }} app-lift"
                        data-testid="row-walloflove">
                    <span wire:loading.remove wire:target="toggleWallOfLove">Wall of Love{{ $row->is_wall_of_love ? ' ✓' : '' }}</span>
                    <span wire:loading wire:target="toggleWallOfLove">Working…</span>
                </button>

                <button type="button"
                        wire:click="toggleHidden"
                        class="inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-3 py-2 text-sm rounded-xl border {{ $row->is_hidden ? 'bg-amber-50 border-amber-300 text-amber-800' : 'border-gray-300 text-gray-700 hover:bg-gray-50' }} app-lift"
                        data-testid="row-hide">
                    {{ $row->is_hidden ? 'Unhide' : 'Hide' }}
                </button>

                <button type="button"
                        wire:click="startEdit"
                        class="inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-3 py-2 text-sm rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-50 app-lift"
                        data-testid="row-edit">
                    Edit
                </button>

                @if ($row->consent_given)
                    <button type="button"
                            wire:click="withdrawConsent"
                            wire:loading.attr="disabled"
                            wire:target="withdrawConsent"
                            wire:confirm="Withdraw consent for '{{ $row->name }}'? The row stays but the consent audit fields are kept as history."
                            class="inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-3 py-2 text-sm rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-50 app-lift"
                            data-testid="row-withdraw">
                        <span wire:loading.remove wire:target="withdrawConsent">Withdraw consent</span>
                        <span wire:loading wire:target="withdrawConsent">Working…</span>
                    </button>
                @endif
            </div>

            {{-- Delete sits visually apart from the rest --}}
            <div>
                <button type="button"
                        wire:click="softDelete"
                        wire:loading.attr="disabled"
                        wire:target="softDelete"
                        wire:confirm="Move '{{ $row->name }}' to Trash? You can restore it from the Trash tab within {{ (int) config('purge.retention_days', 30) }} days."
                        class="inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-3 py-2 text-sm rounded-xl border border-red-200 text-red-700 hover:bg-red-50 app-lift"
                        data-testid="row-delete">
                    <span wire:loading.remove wire:target="softDelete">Delete</span>
                    <span wire:loading wire:target="softDelete">Working…</span>
                </button>
            </div>
        @endif

        @if (($showRestore ?? false) && $row->deleted_at)
            <div class="flex flex-wrap items-center gap-2">
                <button type="button"
                        wire:click="restore"
                        wire:loading.attr="disabled"
                        wire:target="restore"
                        class="inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-3 py-2 text-sm rounded-xl border border-emerald-300 text-emerald-800 bg-emerald-50 hover:bg-emerald-100 app-lift"
                        data-testid="row-restore">
                    <span wire:loading.remove wire:target="restore">Restore</span>
                    <span wire:loading wire:target="restore">Working…</span>
                </button>

                @if ($showForget ?? false)
                    <button type="button"
                            wire:click="forget"
                            wire:loading.attr="disabled"
                            wire:target="forget"
                            wire:confirm="Forget '{{ $row->name }}' permanently? This deletes the testimonial and its photo, and closes any matching open deletion request."
                            class="inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-3 py-2 text-sm rounded-xl border border-red-300 text-red-800 bg-red-50 hover:bg-red-100 app-lift"
                            data-testid="row-forget">
                        <span wire:loading.remove wire:target="forget">Forget now</span>
                        <span wire:loading wire:target="forget">Working…</span>
                    </button>
                @endif
            </div>
        @endif
    </div>
</article>
