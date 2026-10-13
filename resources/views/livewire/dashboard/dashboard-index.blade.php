<div>
    <x-slot name="header">
        <x-page-header
            :title="__('Dashboard')"
            data-testid="dashboard-page-header"
        />
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            {{-- ============================================================== --}}
            {{-- Free-plan info note (above the counters, below the title)     --}}
            {{-- Rendered ONLY when the owner has at least one live Space.     --}}
            {{-- EXACT copy. No link, no button, no CTA. No `/billing` in v1.  --}}
            {{-- ============================================================== --}}
            @if ($this->showFreePlanNote)
                <div class="rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-gray-800"
                     data-testid="free-plan-note">
                    You're on the Free plan ({{ $this->maxSpaces }} Spaces, {{ $this->maxTestimonialsPerSpace }} testimonials per Space).
                </div>
            @endif

            {{-- ============================================================== --}}
            {{-- Tabs (Active / Deleted Spaces)                                --}}
            {{-- ============================================================== --}}
            <div class="border-b border-gray-200" data-testid="dashboard-tabs">
                <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                    <button type="button"
                            wire:click="setTab('active')"
                            class="min-h-[44px] sm:min-h-0 py-2 px-1 border-b-2 text-sm font-medium {{ $tab === 'active' ? 'border-indigo-500 text-indigo-700' : 'border-transparent text-gray-600 hover:text-gray-900' }}"
                            data-testid="tab-active">
                        Active
                    </button>
                    <button type="button"
                            wire:click="setTab('deleted')"
                            class="min-h-[44px] sm:min-h-0 py-2 px-1 border-b-2 text-sm font-medium {{ $tab === 'deleted' ? 'border-indigo-500 text-indigo-700' : 'border-transparent text-gray-600 hover:text-gray-900' }}"
                            data-testid="tab-deleted">
                        Deleted Spaces
                    </button>
                </nav>
            </div>

            @if ($tab === 'active')
                {{-- ============================================================ --}}
                {{-- Empty state: no live Spaces                                --}}
                {{-- ============================================================ --}}
                @if ($this->spacesUsed === 0)
                    <div class="rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-sm"
                         data-testid="empty-no-spaces">
                        <div class="mx-auto mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5v10.5H3.75z M3.75 10.5h16.5" />
                            </svg>
                        </div>
                        <p class="text-gray-700 mb-4">Create your first Space</p>
                        <a href="{{ route('spaces.new') }}" wire:navigate
                           class="app-lift inline-flex items-center justify-center min-h-[44px] px-6 py-3 rounded-xl bg-indigo-600 text-white text-sm font-semibold shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                           data-testid="empty-create-space-link">
                            {{ __('New Space') }}
                        </a>
                    </div>
                @else
                    {{-- ============================================================ --}}
                    {{-- Counter cards                                              --}}
                    {{-- ============================================================ --}}
                    <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-5 gap-4"
                         data-testid="counter-grid">
                        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm min-w-0" data-testid="counter-total-testimonials">
                            <div class="flex flex-col gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <div class="text-sm text-gray-600 break-words">Total testimonials</div>
                                    <div class="mt-1 text-3xl font-bold tracking-tight text-gray-900 break-words"
                                         data-testid="counter-total-testimonials-value">
                                        {{ number_format($this->totalTestimonials) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm min-w-0" data-testid="counter-total-customers">
                            <div class="flex flex-col gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <div class="text-sm text-gray-600 break-words">Total customers</div>
                                    <div class="mt-1 text-3xl font-bold tracking-tight text-gray-900 break-words"
                                         data-testid="counter-total-customers-value">
                                        {{ number_format($this->totalCustomers) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm min-w-0" data-testid="counter-spaces-used">
                            <div class="flex flex-col gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <div class="text-sm text-gray-600 break-words">Spaces used</div>
                                    <div class="mt-1 text-3xl font-bold tracking-tight text-gray-900 break-words"
                                         data-testid="counter-spaces-used-value">
                                        {{ $this->spacesUsed }} of {{ $this->maxSpaces }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm min-w-0" data-testid="counter-average-rating">
                            <div class="flex flex-col gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.563.563 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <div class="text-sm text-gray-600 break-words">Average rating</div>
                                    <div class="mt-1 text-3xl font-bold tracking-tight text-gray-900 break-words"
                                         data-testid="counter-average-rating-value">
                                        @if ($this->averageRating === null)
                                            <span class="text-base font-normal text-gray-600" data-testid="counter-average-rating-empty">No ratings yet</span>
                                        @else
                                            {{ number_format($this->averageRating, 1) }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm min-w-0 col-span-2 lg:col-span-1" data-testid="counter-open-deletion-requests">
                            <div class="flex flex-col gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <div class="text-sm text-gray-600 break-words">Open deletion requests</div>
                                    <div class="mt-1 text-3xl font-bold tracking-tight text-gray-900 break-words"
                                         data-testid="counter-open-deletion-requests-value">
                                        {{ number_format($this->openDeletionRequestsCount) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ============================================================ --}}
                    {{-- Open deletion-requests list                                --}}
                    {{-- ============================================================ --}}
                    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm" data-testid="deletion-requests-card">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <h3 class="text-base font-bold tracking-tight text-gray-900">Open deletion requests</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                Requests matched to your live Spaces. Spaces deleted in the last
                                {{ $this->retentionDays }} days can be restored from the Deleted Spaces tab.
                            </p>
                        </div>
                        <div class="divide-y divide-gray-100" data-testid="deletion-requests-list">
                            @forelse ($this->openDeletionRequests as $req)
                                <div class="px-5 py-3 flex items-center justify-between"
                                     data-testid="deletion-request-row">
                                    <div class="min-w-0">
                                        <div class="text-sm text-gray-900">
                                            {{ $req->testimonial?->name ?? $req->testimonial?->email ?? $req->email }}
                                        </div>
                                        <div class="text-xs text-gray-600">
                                            Space: {{ $req->space?->title ?? '—' }}
                                            · requested {{ $req->created_at?->diffForHumans() }}
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="px-5 py-6 text-center text-sm text-gray-600"
                                     data-testid="deletion-requests-empty">
                                    No open deletion requests.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    {{-- ============================================================ --}}
                    {{-- Empty state: live Spaces but no testimonials               --}}
                    {{-- ============================================================ --}}
                    @if ($this->totalTestimonials === 0)
                        <div class="rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-sm"
                             data-testid="empty-no-testimonials">
                            <div class="mx-auto mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                                </svg>
                            </div>
                            <p class="text-gray-700">Share your link to start collecting.</p>
                        </div>
                    @endif

                    {{-- ============================================================ --}}
                    {{-- Time-series graph (Chart.js via CDN)                       --}}
                    {{-- ============================================================ --}}
                    <div class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-6 shadow-xl shadow-indigo-600/10" data-testid="graph-card">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                            <h3 class="text-base font-bold tracking-tight text-gray-900">Submissions over time</h3>

                            <div class="flex flex-wrap items-start gap-3" data-testid="graph-controls">
                                <div class="flex flex-col gap-1">
                                    <label class="text-sm text-gray-600" for="graph-granularity">Granularity</label>
                                    <div class="flex items-center gap-2">
                                        <select id="graph-granularity"
                                                wire:model.live="granularity"
                                                wire:loading.attr="disabled"
                                                wire:target="granularity"
                                                class="min-h-[44px] sm:min-h-0 text-sm border-gray-300 rounded-xl"
                                                data-testid="graph-granularity">
                                            <option value="day">Day</option>
                                            <option value="week">Week</option>
                                            <option value="month">Month</option>
                                        </select>
                                        <span class="text-xs text-gray-600"
                                              wire:loading
                                              wire:target="granularity"
                                              data-testid="graph-granularity-loading">Loading…</span>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-1">
                                    <label class="text-sm text-gray-600" for="graph-range">Range</label>
                                    <div class="flex items-center gap-2">
                                        <select id="graph-range"
                                                wire:model.live="range"
                                                wire:loading.attr="disabled"
                                                wire:target="range"
                                                class="min-h-[44px] sm:min-h-0 text-sm border-gray-300 rounded-xl"
                                                data-testid="graph-range">
                                            <option value="7d">7d</option>
                                            <option value="30d">30d</option>
                                            <option value="90d">90d</option>
                                            <option value="all">All time</option>
                                        </select>
                                        <span class="text-xs text-gray-600"
                                              wire:loading
                                              wire:target="range"
                                              data-testid="graph-range-loading">Loading…</span>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-1">
                                    <label class="text-sm text-gray-600" for="graph-space">Space</label>
                                    <select id="graph-space"
                                            wire:model.live="spaceId"
                                            wire:loading.attr="disabled"
                                            wire:target="spaceId"
                                            class="min-h-[44px] sm:min-h-0 text-sm border-gray-300 rounded-xl"
                                            data-testid="graph-space">
                                        <option value="">All</option>
                                        @foreach ($this->liveSpaces as $space)
                                            <option value="{{ $space->id }}">{{ $space->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="w-full overflow-x-auto"
                             data-testid="graph-canvas-wrap"
                             wire:ignore
                             data-chartjs-src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"
                             x-data="{}"
                             x-init="(async () => {
                                 const canvas = $el.querySelector('canvas#dashboard-graph');
                                 if (!canvas) return;
                                 try {
                                     if (typeof window.Chart === 'undefined') {
                                         if (!window.__chartJsLoading) {
                                             window.__chartJsLoading = new Promise((resolve, reject) => {
                                                 const src = $el.getAttribute('data-chartjs-src');
                                                 const s = document.createElement('script');
                                                 s.src = src;
                                                 s.async = true;
                                                 s.onload = () => resolve();
                                                 s.onerror = () => { window.__chartJsLoading = null; reject(new Error('chart.js failed to load: ' + src)); };
                                                 document.head.appendChild(s);
                                             });
                                         }
                                         await window.__chartJsLoading;
                                     }
                                 } catch (e) {
                                     console.warn(e && e.message ? e.message : e);
                                     return;
                                 }
                                 if (!canvas.isConnected) return;
                                 if (canvas._chart) { canvas._chart.destroy(); }
                                 const labels = JSON.parse(canvas.getAttribute('data-labels') || '[]');
                                 const counts = JSON.parse(canvas.getAttribute('data-counts') || '[]');
                                 canvas._chart = new window.Chart(canvas, {
                                     type: 'bar',
                                     data: {
                                         labels: labels,
                                         datasets: [{
                                             label: 'Submissions',
                                             data: counts,
                                             backgroundColor: 'rgba(79, 70, 229, 0.6)',
                                             borderColor: 'rgba(79, 70, 229, 1)',
                                             borderWidth: 1,
                                         }],
                                     },
                                     options: {
                                         responsive: true,
                                         maintainAspectRatio: false,
                                         scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                                     },
                                 });
                             })()"
                             x-on:dashboard-graph-updated.window="(() => {
                                 const canvas = $el.querySelector('canvas#dashboard-graph');
                                 if (!canvas) return;
                                 const detail = ($event && $event.detail) || {};
                                 const labels = detail.labels || [];
                                 const counts = detail.counts || [];
                                 // Always write the attributes first so a later
                                 // mount (or a chart-after-data event) sees the
                                 // newest values even if the chart is not built
                                 // yet.
                                 canvas.setAttribute('data-labels', JSON.stringify(labels));
                                 canvas.setAttribute('data-counts', JSON.stringify(counts));
                                 if (!canvas._chart) return;
                                 canvas._chart.data.labels = labels;
                                 canvas._chart.data.datasets[0].data = counts;
                                 canvas._chart.update();
                             })()">
                            <canvas id="dashboard-graph"
                                    data-testid="graph-canvas"
                                    data-labels='@json($this->graphData["labels"])'
                                    data-counts='@json($this->graphData["counts"])'
                                    style="min-height: 240px; max-height: 320px;"></canvas>
                        </div>
                    </div>
                @endif
            @else
                {{-- ============================================================ --}}
                {{-- Deleted Spaces tab — same UI as Step 2's SpaceDeleted      --}}
                {{-- page (list, Restore action, "delete a Space first" notice, --}}
                {{-- retention copy). The restore handler is on the dashboard  --}}
                {{-- component (same logic, no redirect away from /dashboard).  --}}
                {{-- ============================================================ --}}
                <div class="rounded-2xl border border-gray-200 bg-white shadow-sm"
                     data-testid="dashboard-deleted-list">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <p class="text-sm text-gray-600">
                            Spaces deleted in the last {{ $this->retentionDays }} days can be restored.
                            Older Spaces are kept as tombstones to claim the slug forever — they cannot
                            be restored.
                        </p>
                    </div>

                    @if (session('status'))
                        <div class="mx-5 mt-4 rounded-xl bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm"
                             data-testid="status-flash">
                            {{ session('status') }}
                        </div>
                    @endif

                    @error('restore')
                        <div class="mx-5 mt-4 rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm"
                             data-testid="restore-error">
                            {{ $message }}
                        </div>
                    @enderror

                    <div class="divide-y divide-gray-100">
                        <x-spaces.deleted-list
                            :spaces="$this->deletedSpaces"
                            action="restoreDeletedSpace"
                        />
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
