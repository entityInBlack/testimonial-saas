<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight"
            data-testid="dashboard-title">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- ============================================================== --}}
            {{-- Free-plan info note (above the counters, below the title)     --}}
            {{-- Rendered ONLY when the owner has at least one live Space.     --}}
            {{-- EXACT copy. No link, no button, no CTA. No `/billing` in v1.  --}}
            {{-- ============================================================== --}}
            @if ($this->showFreePlanNote)
                <div class="bg-gray-50 border border-gray-200 text-gray-800 text-sm px-4 py-3 rounded"
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
                            class="py-2 px-1 border-b-2 text-sm font-medium {{ $tab === 'active' ? 'border-indigo-500 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                            data-testid="tab-active">
                        Active
                    </button>
                    <button type="button"
                            wire:click="setTab('deleted')"
                            class="py-2 px-1 border-b-2 text-sm font-medium {{ $tab === 'deleted' ? 'border-indigo-500 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
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
                    <div class="bg-white shadow-sm rounded-lg p-8 text-center"
                         data-testid="empty-no-spaces">
                        <p class="text-gray-700 mb-4">Create your first Space</p>
                        <a href="{{ route('spaces.new') }}" wire:navigate
                           class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded hover:bg-indigo-700"
                           data-testid="empty-create-space-link">
                            {{ __('New Space') }}
                        </a>
                    </div>
                @else
                    {{-- ============================================================ --}}
                    {{-- Counter cards                                              --}}
                    {{-- ============================================================ --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4"
                         data-testid="counter-grid">
                        <div class="bg-white shadow-sm rounded-lg p-4" data-testid="counter-total-testimonials">
                            <div class="text-xs text-gray-500 uppercase tracking-wide">Total testimonials</div>
                            <div class="text-2xl font-semibold text-gray-900 mt-1"
                                 data-testid="counter-total-testimonials-value">
                                {{ number_format($this->totalTestimonials) }}
                            </div>
                        </div>

                        <div class="bg-white shadow-sm rounded-lg p-4" data-testid="counter-total-customers">
                            <div class="text-xs text-gray-500 uppercase tracking-wide">Total customers</div>
                            <div class="text-2xl font-semibold text-gray-900 mt-1"
                                 data-testid="counter-total-customers-value">
                                {{ number_format($this->totalCustomers) }}
                            </div>
                        </div>

                        <div class="bg-white shadow-sm rounded-lg p-4" data-testid="counter-spaces-used">
                            <div class="text-xs text-gray-500 uppercase tracking-wide">Spaces used</div>
                            <div class="text-2xl font-semibold text-gray-900 mt-1"
                                 data-testid="counter-spaces-used-value">
                                {{ $this->spacesUsed }} of {{ $this->maxSpaces }}
                            </div>
                        </div>

                        <div class="bg-white shadow-sm rounded-lg p-4" data-testid="counter-average-rating">
                            <div class="text-xs text-gray-500 uppercase tracking-wide">Average rating</div>
                            <div class="text-2xl font-semibold text-gray-900 mt-1"
                                 data-testid="counter-average-rating-value">
                                @if ($this->averageRating === null)
                                    <span class="text-gray-400 text-base font-normal" data-testid="counter-average-rating-empty">No ratings yet</span>
                                @else
                                    {{ number_format($this->averageRating, 1) }}
                                @endif
                            </div>
                        </div>

                        <div class="bg-white shadow-sm rounded-lg p-4" data-testid="counter-open-deletion-requests">
                            <div class="text-xs text-gray-500 uppercase tracking-wide">Open deletion requests</div>
                            <div class="text-2xl font-semibold text-gray-900 mt-1"
                                 data-testid="counter-open-deletion-requests-value">
                                {{ number_format($this->openDeletionRequestsCount) }}
                            </div>
                        </div>
                    </div>

                    {{-- ============================================================ --}}
                    {{-- Open deletion-requests list                                --}}
                    {{-- ============================================================ --}}
                    <div class="bg-white shadow-sm rounded-lg" data-testid="deletion-requests-card">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-800">Open deletion requests</h3>
                            <p class="text-xs text-gray-500">
                                Requests matched to your live Spaces. Spaces deleted in the last
                                {{ $this->retentionDays }} days can be restored from the Deleted Spaces tab.
                            </p>
                        </div>
                        <div class="divide-y divide-gray-100" data-testid="deletion-requests-list">
                            @forelse ($this->openDeletionRequests as $req)
                                <div class="px-4 py-3 flex items-center justify-between"
                                     data-testid="deletion-request-row">
                                    <div>
                                        <div class="text-sm text-gray-900">
                                            {{ $req->testimonial?->name ?? $req->testimonial?->email ?? $req->email }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Space: {{ $req->space?->title ?? '—' }}
                                            · requested {{ $req->created_at?->diffForHumans() }}
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="px-4 py-6 text-center text-sm text-gray-500"
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
                        <div class="bg-white shadow-sm rounded-lg p-8 text-center"
                             data-testid="empty-no-testimonials">
                            <p class="text-gray-700">Share your link to start collecting.</p>
                        </div>
                    @endif

                    {{-- ============================================================ --}}
                    {{-- Time-series graph (Chart.js via CDN)                       --}}
                    {{-- ============================================================ --}}
                    <div class="bg-white shadow-sm rounded-lg p-4" data-testid="graph-card">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                            <h3 class="text-sm font-semibold text-gray-800">Submissions over time</h3>

                            <div class="flex flex-wrap items-center gap-2" data-testid="graph-controls">
                                <label class="text-xs text-gray-500" for="graph-granularity">Granularity</label>
                                <select id="graph-granularity"
                                        wire:model.live="granularity"
                                        wire:loading.attr="disabled"
                                        wire:target="granularity"
                                        class="text-sm border-gray-300 rounded"
                                        data-testid="graph-granularity">
                                    <option value="day">Day</option>
                                    <option value="week">Week</option>
                                    <option value="month">Month</option>
                                </select>

                                <label class="text-xs text-gray-500 ml-2" for="graph-range">Range</label>
                                <select id="graph-range"
                                        wire:model.live="range"
                                        wire:loading.attr="disabled"
                                        wire:target="range"
                                        class="text-sm border-gray-300 rounded"
                                        data-testid="graph-range">
                                    <option value="7d">7d</option>
                                    <option value="30d">30d</option>
                                    <option value="90d">90d</option>
                                    <option value="all">All time</option>
                                </select>

                                <label class="text-xs text-gray-500 ml-2" for="graph-space">Space</label>
                                <select id="graph-space"
                                        wire:model.live="spaceId"
                                        wire:loading.attr="disabled"
                                        wire:target="spaceId"
                                        class="text-sm border-gray-300 rounded"
                                        data-testid="graph-space">
                                    <option value="">All</option>
                                    @foreach ($this->liveSpaces as $space)
                                        <option value="{{ $space->id }}">{{ $space->title }}</option>
                                    @endforeach
                                </select>
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
                <div class="bg-white shadow-sm rounded-lg"
                     data-testid="dashboard-deleted-list">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <p class="text-sm text-gray-600">
                            Spaces deleted in the last {{ $this->retentionDays }} days can be restored.
                            Older Spaces are kept as tombstones to claim the slug forever — they cannot
                            be restored.
                        </p>
                    </div>

                    @if (session('status'))
                        <div class="bg-green-50 border-b border-green-200 text-green-900 px-4 py-3 text-sm"
                             data-testid="status-flash">
                            {{ session('status') }}
                        </div>
                    @endif

                    @error('restore')
                        <div class="bg-red-50 border-b border-red-200 text-red-900 px-4 py-3 text-sm"
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
