<?php

use App\Models\App;
use App\Models\AppRelease;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    // State properties
    public string $search = '';
    public string $statusFilter = 'all';

    // Modal state & form inputs
    public bool $showCreateModal = false;
    public ?int $editingAppId = null;
    public string $name = '';
    public string $slug = '';
    public string $description = '';

    // Synchronize filters in query string
    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
    ];

    // Reset pagination when filtering
    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }

    // Auto-slug generator
    public function updatedName($value): void
    {
        if (empty($this->slug)) {
            $this->slug = \Illuminate\Support\Str::slug($value);
        }
    }

    // Modal Management
    public function openCreateModal(): void
    {
        $this->reset(['editingAppId', 'name', 'slug', 'description']);
        $this->resetValidation();
        $this->showCreateModal = true;
    }

    public function editApp(int $id): void
    {
        $app = App::findOrFail($id);
        $this->editingAppId = $app->id;
        $this->name = $app->name;
        $this->slug = $app->slug;
        $this->description = $app->description ?? '';
        
        $this->resetValidation();
        $this->showCreateModal = true;
    }

    // Toggle Active Status
    public function toggleAppStatus(int $id): void
    {
        $app = App::findOrFail($id);
        $app->update(['is_active' => !$app->is_active]);
        
        session()->flash('success', "Status updated for {$app->name}.");
    }

    // Delete Action
    public function deleteApp(int $id): void
    {
        $app = App::findOrFail($id);
        $appName = $app->name;
        $app->delete();

        session()->flash('success', "Application '{$appName}' and its releases were deleted.");
    }

    // Computed properties for header summary cards
    #[\Livewire\Attributes\Computed]
    public function totalApps(): int
    {
        return App::count();
    }

    #[\Livewire\Attributes\Computed]
    public function activeAppsCount(): int
    {
        return App::where('is_active', true)->count();
    }

    #[\Livewire\Attributes\Computed]
    public function totalReleases(): int
    {
        return AppRelease::count();
    }

    // Computed property for paginated data table
    #[\Livewire\Attributes\Computed]
    public function apps()
    {
        return App::query()
            ->with(['releases' => fn($q) => $q->orderByDesc('id')])
            ->withCount('releases')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('slug', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('is_active', $this->statusFilter === 'active');
            })
            ->latest()
            ->paginate(10);
    }

    // Save action (handles both Create and Update)
    public function saveApp(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:apps,slug,' . $this->editingAppId,
            'description' => 'nullable|string|max:1000',
        ]);

        if ($this->editingAppId) {
            $app = App::findOrFail($this->editingAppId);
            $app->update([
                'name' => $validated['name'],
                'slug' => \Illuminate\Support\Str::slug($validated['slug']),
                'description' => $validated['description'],
            ]);
            $message = 'Application updated successfully!';
        } else {
            App::create([
                'name' => $validated['name'],
                'slug' => \Illuminate\Support\Str::slug($validated['slug']),
                'description' => $validated['description'],
                'is_active' => true,
            ]);
            $message = 'Application created successfully!';
        }

        $this->reset(['name', 'slug', 'description', 'showCreateModal', 'editingAppId']);
        session()->flash('success', $message);
    }
};
?>

<div>
    <!-- Flash Messages -->
    @if (session()->has('success'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    <!-- Page Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Applications Overview</h1>
            <p class="text-sm text-slate-400 mt-1">Manage distributed binary packages, releases, and updates.</p>
        </div>

        <button 
            type="button" 
            wire:click="openCreateModal"
            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-sm shadow-lg shadow-indigo-600/20 transition cursor-pointer"
        >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add New App
        </button>
    </div>

    <!-- Quick Stats Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700/60 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Apps</span>
                <span class="p-2 rounded-lg bg-indigo-500/10 text-indigo-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                </span>
            </div>
            <div class="text-2xl font-bold text-white mt-2">{{ $this->totalApps }}</div>
            <p class="text-xs text-slate-400 mt-1">{{ $this->activeAppsCount }} Active apps</p>
        </div>

        <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700/60 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Releases</span>
                <span class="p-2 rounded-lg bg-emerald-500/10 text-emerald-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/></svg>
                </span>
            </div>
            <div class="text-2xl font-bold text-white mt-2">{{ $this->totalReleases }}</div>
            <p class="text-xs text-slate-400 mt-1">Binaries on server</p>
        </div>

        <div class="p-4 rounded-xl bg-slate-800/80 border border-slate-700/60 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Storage Security</span>
                <span class="p-2 rounded-lg bg-blue-500/10 text-blue-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </span>
            </div>
            <div class="text-2xl font-bold text-emerald-400 mt-2">Protected</div>
            <p class="text-xs text-slate-400 mt-1">storage/app/private</p>
        </div>

        <livewire:api-health-card />
    </div>

    <!-- Controls Bar -->
    <div class="p-4 rounded-t-xl bg-slate-800 border border-slate-700/80 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="relative w-full sm:w-80">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <input 
                wire:model.live.debounce.300ms="search" 
                type="text" 
                placeholder="Search apps by name or slug..."
                class="w-full pl-9 pr-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
            />
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto">
            <select 
                wire:model.live="statusFilter"
                class="w-full sm:w-auto bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-indigo-500"
            >
                <option value="all">All Statuses</option>
                <option value="active">Active Only</option>
                <option value="inactive">Inactive Only</option>
            </select>
        </div>
    </div>

    <!-- Apps Table -->
    <div class="overflow-x-auto bg-slate-800 border-x border-b border-slate-700/80 rounded-b-xl shadow-xl">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-900/60 text-xs uppercase tracking-wider text-slate-400 border-b border-slate-700/80">
                <tr>
                    <th scope="col" class="px-6 py-3.5 font-semibold">Application</th>
                    <th scope="col" class="px-6 py-3.5 font-semibold">Status</th>
                    <th scope="col" class="px-6 py-3.5 font-semibold">Latest Version</th>
                    <th scope="col" class="px-6 py-3.5 font-semibold">Releases</th>
                    <th scope="col" class="px-6 py-3.5 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse ($this->apps as $app)
                    @php
                        $latestRelease = $app->releases->first();
                    @endphp
                    <tr class="hover:bg-slate-800/40 transition group">
                        <!-- App Details -->
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 border border-indigo-500/30 text-indigo-300 flex items-center justify-center font-bold text-xs uppercase shadow-sm shrink-0">
                                    {{ substr($app->name, 0, 2) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-white text-sm truncate">{{ $app->name }}</span>
                                        <span class="px-1.5 py-0.5 text-[10px] font-mono rounded bg-slate-800 text-indigo-300 border border-slate-700/60">{{ $app->slug }}</span>
                                    </div>
                                    <p class="text-xs text-slate-400 truncate max-w-xs mt-0.5">{{ $app->description ?? 'No description provided.' }}</p>
                                </div>
                            </div>
                        </td>

                        <!-- Active Status -->
                        <td class="px-6 py-4">
                            <button 
                                wire:click="toggleAppStatus({{ $app->id }})"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition cursor-pointer {{ $app->is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20' : 'bg-slate-800 text-slate-400 border border-slate-700 hover:bg-slate-700/60' }}"
                            >
                                @if ($app->is_active)
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                    Active
                                @else
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span>
                                    Inactive
                                @endif
                            </button>
                        </td>

                        <!-- Latest Release -->
                        <td class="px-6 py-4">
                            @if ($latestRelease)
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold font-mono bg-slate-950 text-slate-200 border border-slate-800">
                                        v{{ $latestRelease->version }}
                                    </span>
                                    @if ($latestRelease->platform)
                                        <span class="text-[10px] font-medium uppercase px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 border border-slate-700/50">
                                            {{ $latestRelease->platform }}
                                        </span>
                                    @endif
                                </div>
                            @else
                                <span class="text-xs text-slate-500 italic">No releases yet</span>
                            @endif
                        </td>

                        <!-- Total Releases Count -->
                        <td class="px-6 py-4 text-xs text-slate-400 font-medium">
                            <div class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                <span>{{ $app->releases_count }} {{ Str::plural('release', $app->releases_count) }}</span>
                            </div>
                        </td>

                        <!-- Action Menu Dropdown -->
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            <div class="inline-flex items-center p-1 rounded-lg bg-slate-900 border border-slate-800 shadow-inner">
                                <!-- New Release -->
                                <a 
                                    href="{{ route('apps.create-release', ['app_id' => $app->id]) }}" 
                                    title="Create Release"
                                    class="p-1.5 rounded-md hover:bg-indigo-600/20 text-indigo-400 hover:text-indigo-300 transition"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                </a>

                                <div class="w-px h-3.5 bg-slate-800 mx-0.5"></div>

                                <!-- View Releases (Uses App Slug) -->
                                <a 
                                    href="{{ route('apps.releases.show', ['app' => $app]) }}" 
                                    title="View All Releases"
                                    class="p-1.5 rounded-md hover:bg-emerald-500/20 text-slate-400 hover:text-emerald-400 transition"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>

                                <div class="w-px h-3.5 bg-slate-800 mx-0.5"></div>

                                <!-- Edit App -->
                                <button 
                                    wire:click="editApp({{ $app->id }})" 
                                    title="Edit Application"
                                    class="p-1.5 rounded-md hover:bg-slate-800 text-slate-400 hover:text-slate-200 transition"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>

                                <div class="w-px h-3.5 bg-slate-800 mx-0.5"></div>

                                <!-- Delete App -->
                                <button 
                                    wire:confirm="Are you sure you want to delete this app and all its binary releases?"
                                    wire:click="deleteApp({{ $app->id }})" 
                                    title="Delete Application"
                                    class="p-1.5 rounded-md hover:bg-rose-500/10 text-slate-400 hover:text-rose-400 transition"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                            <div class="flex flex-col items-center justify-center">
                                <div class="w-12 h-12 rounded-full bg-slate-800/60 border border-slate-700/50 flex items-center justify-center mb-3 text-slate-500">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                                </div>
                                <p class="text-sm font-medium text-slate-300">No applications found.</p>
                                <p class="text-xs text-slate-500 mt-1">Try adjusting your search or create a new application.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Pagination Links -->
        <div class="px-6 py-4 border-t border-slate-700/80 bg-slate-900/40">
            {{ $this->apps->links() }}
        </div>
    </div>

    <!-- Create/Edit Application Modal -->
    <div 
        x-data="{ show: @entangle('showCreateModal') }"
        x-show="show"
        x-cloak
        x-on:keydown.escape.window="show = false"
        class="fixed inset-0 z-50 overflow-y-auto"
    >
        <!-- Backdrop -->
        <div 
            x-show="show"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm"
            x-on:click="show = false"
        ></div>

        <!-- Modal Content Container -->
        <div class="flex min-h-full items-center justify-center p-4">
            <div 
                x-show="show"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95 translateY(4px)"
                x-transition:enter-end="opacity-100 scale-100 translateY(0)"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 scale-100 translateY(0)"
                x-transition:leave-end="opacity-0 scale-95 translateY(4px)"
                class="relative w-full max-w-md bg-slate-800 border border-slate-700 rounded-xl shadow-2xl p-6 z-10"
            >
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-white">{{ $editingAppId ? 'Edit Application' : 'Create New Application' }}</h3>
                    <button wire:click="$set('showCreateModal', false)" class="text-slate-400 hover:text-white cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form wire:submit="saveApp" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-400 mb-1">Application Name</label>
                        <input 
                            wire:model.live="name" 
                            type="text" 
                            placeholder="e.g. CHRONO Log Parser" 
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200 focus:outline-none focus:border-indigo-500"
                        />
                        @error('name') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-400 mb-1">Slug</label>
                        <input 
                            wire:model="slug" 
                            type="text" 
                            placeholder="e.g. chrono-log-parser" 
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200 focus:outline-none focus:border-indigo-500 font-mono text-xs"
                        />
                        @error('slug') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-400 mb-1">Description</label>
                        <textarea 
                            wire:model="description" 
                            rows="3" 
                            placeholder="Brief description of the desktop tool..." 
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-200 focus:outline-none focus:border-indigo-500"
                        ></textarea>
                        @error('description') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3 pt-2">
                        <button 
                            type="button" 
                            wire:click="$set('showCreateModal', false)" 
                            class="px-4 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-200 text-sm font-medium cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium cursor-pointer shadow-lg shadow-indigo-600/20"
                        >
                            {{ $editingAppId ? 'Update Application' : 'Save Application' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>