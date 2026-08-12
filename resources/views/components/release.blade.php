<?php

use App\Models\App;
use App\Models\AppRelease;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Locked]
    public App $app;

    public string $search = '';
    public string $platformFilter = 'all';

    // Edit Modal State
    public bool $showEditModal = false;
    public ?int $editingReleaseId = null;
    public string $editVersion = '';
    public string $editPlatform = 'all';
    public bool $editIsMandatory = false;
    public ?string $editReleaseNotes = null;

    public function mount(App $app): void
    {
        $this->app = $app;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPlatformFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Open the edit modal and populate fields
     */
    public function editRelease(int $releaseId): void
    {
        $release = $this->app->releases()->findOrFail($releaseId);

        $this->editingReleaseId = $release->id;
        $this->editVersion = $release->version;
        $this->editPlatform = $release->platform ?? 'all';
        $this->editIsMandatory = (bool) $release->is_mandatory;
        $this->editReleaseNotes = $release->release_notes;

        $this->showEditModal = true;
    }

    /**
     * Save updated release attributes
     */
    public function saveRelease(): void
    {
        $this->validate([
            'editVersion' => ['required', 'string'],
            'editPlatform' => ['required', 'string', 'in:all,windows,macos,linux'],
            'editIsMandatory' => ['boolean'],
            'editReleaseNotes' => ['nullable', 'string'],
        ]);

        $release = $this->app->releases()->findOrFail($this->editingReleaseId);

        $release->update([
            'version' => ltrim($this->editVersion, 'v'),
            'platform' => $this->editPlatform,
            'is_mandatory' => $this->editIsMandatory,
            'release_notes' => $this->editReleaseNotes,
        ]);

        $this->showEditModal = false;
        $this->reset(['editingReleaseId', 'editVersion', 'editPlatform', 'editIsMandatory', 'editReleaseNotes']);

        session()->flash('success', "Release v{$release->version} updated successfully.");
    }

    /**
     * Delete a release record and its underlying stored file.
     */
    public function deleteRelease(int $releaseId): void
    {
        $release = $this->app->releases()->findOrFail($releaseId);

        if (Storage::disk('local')->exists($release->file_path)) {
            Storage::disk('local')->delete($release->file_path);
        }

        $release->delete();

        session()->flash('success', "Release v{$release->version} deleted successfully.");
    }

    public function with(): array
    {
        return [
            'releases' => $this->app->exists 
                ? $this->app->releases()
                    ->when($this->search, fn($q) => $q->where('version', 'like', "%{$this->search}%")->orWhere('release_notes', 'like', "%{$this->search}%"))
                    ->when($this->platformFilter !== 'all', fn($q) => $q->where('platform', $this->platformFilter))
                    ->latest()
                    ->paginate(10)
                : collect(),
        ];
    }
};
?>

<div class="p-6 max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('home') }}" class="text-slate-400 hover:text-slate-200 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <h1 class="text-2xl font-bold text-white tracking-tight">{{ $app->name ?? 'Select App' }}</h1>
                <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full {{ ($app->is_active ?? false) ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-800 text-slate-400' }}">
                    {{ ($app->is_active ?? false) ? 'Active' : 'Inactive' }}
                </span>
            </div>
            <p class="text-sm text-slate-400 mt-1 font-mono">Slug: {{ $app->slug ?? 'N/A' }}</p>
        </div>

        @if($app->exists)
            <a 
                href="{{ route('apps.create-release', ['app_id' => $app->id]) }}" 
                class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-sm rounded-lg shadow-sm transition"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Upload New Release
            </a>
        @endif
    </div>

    <!-- Notifications -->
    @if (session()->has('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <!-- Controls -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-slate-900/50 p-4 rounded-xl border border-slate-800">
        <div class="relative md:col-span-2">
            <input 
                wire:model.live.debounce.300ms="search" 
                type="text" 
                placeholder="Search releases..." 
                class="w-full bg-slate-900 border border-slate-800 text-slate-200 text-sm rounded-lg pl-10 pr-4 py-2 focus:ring-2 focus:ring-indigo-500 outline-none"
            >
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
        </div>

        <select 
            wire:model.live="platformFilter" 
            class="w-full bg-slate-900 border border-slate-800 text-slate-200 text-sm rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none"
        >
            <option value="all">All Platforms</option>
            <option value="windows">Windows</option>
            <option value="macos">macOS</option>
            <option value="linux">Linux</option>
        </select>
    </div>

    <!-- Releases Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-950/50 text-slate-400 uppercase text-xs font-semibold border-b border-slate-800">
                    <tr>
                        <th class="px-6 py-4">Version</th>
                        <th class="px-6 py-4">Platform</th>
                        <th class="px-6 py-4">Size</th>
                        <th class="px-6 py-4">Checksum (SHA-256)</th>
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-medium">
                    @forelse($releases as $release)
                        <tr class="hover:bg-slate-800/30 transition">
                            <td class="px-6 py-4 font-mono font-bold text-white">
                                v{{ $release->version }}
                                @if($release->is_mandatory)
                                    <span class="ml-2 px-1.5 py-0.5 text-[10px] uppercase rounded bg-rose-500/10 text-rose-400 border border-rose-500/20">Mandatory</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 capitalize">{{ $release->platform }}</td>
                            <td class="px-6 py-4 font-mono text-slate-400">{{ number_format($release->file_size / 1048576, 2) }} MB</td>
                            <td class="px-6 py-4" x-data="{ copied: false }">
                                <div class="flex items-center space-x-2">
                                    <code class="bg-slate-950 px-2 py-1 rounded text-xs font-mono text-indigo-300 border border-slate-800 truncate max-w-[130px]">
                                        {{ Str::limit($release->checksum_sha256, 12, '...') }}
                                    </code>
                                    <button 
                                        @click="navigator.clipboard.writeText('{{ $release->checksum_sha256 }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                        class="text-slate-500 hover:text-slate-300"
                                    >
                                        <template x-if="!copied">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        </template>
                                        <template x-if="copied">
                                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        </template>
                                    </button>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-400 text-xs">{{ $release->created_at->format('M d, Y') }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="inline-flex items-center space-x-1">
                                    <!-- Edit Button -->
                                    <button 
                                        wire:click="editRelease({{ $release->id }})" 
                                        title="Edit Release" 
                                        class="p-1.5 bg-slate-950 border border-slate-800 hover:bg-indigo-600/20 text-slate-400 hover:text-indigo-400 rounded-lg transition"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>

                                    <!-- Delete Button -->
                                    <button 
                                        wire:confirm="Delete release v{{ $release->version }}?" 
                                        wire:click="deleteRelease({{ $release->id }})" 
                                        title="Delete Release" 
                                        class="p-1.5 bg-slate-950 border border-slate-800 hover:bg-rose-500/10 text-slate-400 hover:text-rose-400 rounded-lg transition"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                No releases recorded for {{ $app->name ?? 'this app' }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(!empty($releases) && method_exists($releases, 'hasPages') && $releases->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $releases->links() }}
            </div>
        @endif
    </div>

<!-- Edit Release Modal -->
@if($showEditModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
        <div class="bg-slate-900 border border-slate-800 w-full max-w-lg rounded-xl shadow-2xl p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="text-lg font-bold text-white">Edit Release Details</h3>
                <button type="button" wire:click="$set('showEditModal', false)" class="text-slate-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Wrap controls inside a form with wire:submit -->
            <form wire:submit="saveRelease" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Version</label>
                    <input type="text" wire:model="editVersion" class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-sm rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                    @error('editVersion') <span class="text-xs text-rose-400 mt-1">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Platform</label>
                    <select wire:model="editPlatform" class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-sm rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                        <option value="all">All Platforms</option>
                        <option value="windows">Windows</option>
                        <option value="macos">macOS</option>
                        <option value="linux">Linux</option>
                    </select>
                    @error('editPlatform') <span class="text-xs text-rose-400 mt-1">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center space-x-2">
                    <input type="checkbox" id="editIsMandatory" wire:model="editIsMandatory" class="rounded bg-slate-950 border-slate-800 text-indigo-600 focus:ring-0">
                    <label for="editIsMandatory" class="text-sm text-slate-300">Mark as Mandatory Update</label>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Release Notes / Changelog</label>
                    <textarea wire:model="editReleaseNotes" rows="3" class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-sm rounded-lg px-3 py-2 outline-none focus:border-indigo-500"></textarea>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-800">
                    <button type="button" wire:click="$set('showEditModal', false)" class="px-4 py-2 text-sm text-slate-400 hover:text-white">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-sm rounded-lg shadow-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
@endif
</div>