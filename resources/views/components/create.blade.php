<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\App;
use App\Models\AppRelease;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

new class extends Component
{
    use WithFileUploads;

    public ?int $app_id = null;
    public bool $isPreSelected = false;
    public string $version = '';
    public string $platform = 'windows';
    public $binaryFile;
    public string $release_notes = '';
    public bool $is_mandatory = false;

    public function mount(?int $app_id = null): void
    {
        // Support pre-selecting app via route parameter or query string (?app_id=3)
        $targetAppId = request()->query('app_id', $app_id);

        if ($targetAppId && App::where('id', $targetAppId)->where('is_active', true)->exists()) {
            $this->app_id = (int) $targetAppId;
            $this->isPreSelected = true;
        } else {
            $firstApp = App::where('is_active', true)->first();
            if ($firstApp) {
                $this->app_id = $firstApp->id;
            }
        }
    }

    public function unlockAppSelection(): void
    {
        $this->isPreSelected = false;
    }

    public function rules(): array
    {
        return [
            'app_id' => 'required|exists:apps,id',
            'version' => 'required|string|regex:/^v?[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9.]+)?$/',
            'platform' => 'required|in:windows,macos,linux,all',
            'binaryFile' => 'required|file|max:512000', // 500MB max
            'release_notes' => 'nullable|string',
            'is_mandatory' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'version.regex' => 'Version must follow SemVer format (e.g., 1.0.0 or v2.1.0-beta).',
            'binaryFile.required' => 'Please attach an executable binary package.',
            'binaryFile.max' => 'The binary package size cannot exceed 500MB.',
        ];
    }

    public function save()
    {
        $this->validate();

        $app = App::findOrFail($this->app_id);
        $version = ltrim($this->version, 'v');

        $originalFileName = $this->binaryFile->getClientOriginalName();
        $extension = strtolower($this->binaryFile->getClientOriginalExtension());
        $baseName = pathinfo($originalFileName, PATHINFO_FILENAME);

        // Temp path where Livewire stashed the uploaded file
        $tempPath = $this->binaryFile->getRealPath();

        // Check if the uploaded file is an .exe file that needs zipping
        if ($extension === 'exe') {
            $zipBaseName = Str::slug($baseName);
            $safeFileName = "{$zipBaseName}.zip";
            $zipTempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('zip_') . '.zip';

            // Create the zip archive in PHP temp folder using root namespace reference
            $zip = new \ZipArchive();
            if ($zip->open($zipTempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                // Add the original .exe into the zip archive
                $zip->addFile($tempPath, "{$zipBaseName}.exe");
                $zip->close();
            }

            $directory = "releases/{$app->slug}/{$version}";
            $storedPath = "{$directory}/{$safeFileName}";

            // Save directly via binary stream content to prevent Windows file-handle lock errors
            Storage::disk('local')->put($storedPath, file_get_contents($zipTempPath));

            // Cleanup temporary server zip
            @unlink($zipTempPath);
        } else {
            // Standard handling for files that are already .zip, .dmg, etc.
            $safeFileName = Str::slug($baseName) . ($extension ? '.' . $extension : '');
            $storedPath = $this->binaryFile->storeAs(
                "releases/{$app->slug}/{$version}",
                $safeFileName,
                'local'
            );
        }

        // Compute SHA256 checksum of stored file
        $sha256 = hash('sha256', Storage::disk('local')->get($storedPath));

        AppRelease::create([
            'app_id' => $app->id,
            'version' => $version,
            'platform' => $this->platform,
            'file_path' => $storedPath,
            'file_size' => Storage::disk('local')->size($storedPath),
            'checksum_sha256' => $sha256,
            'release_notes' => $this->release_notes,
            'is_mandatory' => $this->is_mandatory,
        ]);

        session()->flash('success', "Release v{$version} published successfully for {$app->name}.");

        return redirect()->route('home');
    }

    public function removeFile(): void
    {
        $this->binaryFile = null;
    }

    public function with(): array
    {
        return [
            'apps' => App::where('is_active', true)->get(),
        ];
    }
};
?>

<div class="max-w-4xl mx-auto py-10 px-4 sm:px-6">
    <!-- Header -->
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-indigo-400 uppercase tracking-wider mb-1">
                <span>Release Deployment</span>
                <span>•</span>
                <span>Binary Storage</span>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Publish Binary Release</h1>
            <p class="text-sm text-slate-400 mt-1">Upload compiled binaries, set SemVer metadata, and calculate target checksums.</p>
        </div>
        <a 
            href="{{ route('home') }}" 
            class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium text-xs border border-slate-700/60 transition"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Dashboard
        </a>
    </div>

    <!-- Main Card -->
    <form wire:submit="save" class="bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-6 sm:p-8 space-y-6">
        
        <!-- Application & Version Row -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- App Selection (2 cols) -->
            <div class="md:col-span-2">
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">
                        Target Application <span class="text-indigo-400">*</span>
                    </label>

                    @if ($isPreSelected)
                        <button 
                            type="button" 
                            wire:click="unlockAppSelection" 
                            class="text-xs text-slate-400 hover:text-indigo-400 transition underline underline-offset-2"
                        >
                            Change Application
                        </button>
                    @endif
                </div>

                @if ($isPreSelected && ($selectedApp = $apps->firstWhere('id', $app_id)))
                    <!-- Read-Only Badge/Input when pre-selected from table -->
                    <div class="relative flex items-center justify-between bg-slate-950 border border-indigo-500/30 rounded-xl px-4 py-3 text-sm text-slate-100 shadow-inner">
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-lg bg-indigo-500/20 text-indigo-400 flex items-center justify-center font-bold text-xs uppercase border border-indigo-500/30">
                                {{ substr($selectedApp->name, 0, 2) }}
                            </div>
                            <div>
                                <span class="font-semibold text-white">{{ $selectedApp->name }}</span>
                                <span class="text-xs font-mono text-indigo-300 ml-2">({{ $selectedApp->slug }})</span>
                            </div>
                        </div>
                        
                        <!-- Pre-selected lock badge -->
                        <span class="inline-flex items-center gap-1 text-[11px] font-medium text-indigo-400 bg-indigo-500/10 px-2.5 py-1 rounded-md border border-indigo-500/20">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Auto-Selected
                        </span>
                    </div>
                @else
                    <!-- Standard Select Dropdown if no query parameter was passed or user clicked change -->
                    <div class="relative">
                        <select 
                            wire:model.live="app_id" 
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-slate-100 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition appearance-none cursor-pointer"
                        >
                            <option value="">-- Select an Application --</option>
                            @forelse ($apps as $app)
                                <option value="{{ $app->id }}">{{ $app->name }} ({{ $app->slug }})</option>
                            @empty
                                <option value="">No active applications available</option>
                            @endforelse
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                    </div>
                @endif

                @error('app_id') 
                    <p class="text-xs text-rose-400 mt-1.5 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ $message }}
                    </p> 
                @enderror
            </div>

            <!-- Version Input (1 col) -->
            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                    Version (SemVer) <span class="text-indigo-400">*</span>
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500 font-mono text-sm">v</span>
                    <input 
                        wire:model="version" 
                        type="text" 
                        placeholder="1.0.0" 
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl pl-8 pr-4 py-3 text-sm text-white font-mono placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition"
                    />
                </div>
                @error('version') <p class="text-xs text-rose-400 mt-1.5 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ $message }}</p> @enderror
            </div>
        </div>

        <!-- Target Platform Selector Cards -->
        <div>
            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2.5">
                Target Platform <span class="text-indigo-400">*</span>
            </label>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <!-- Windows -->
                <label class="relative flex flex-col items-center justify-center p-3.5 rounded-xl border cursor-pointer transition {{ $platform === 'windows' ? 'bg-indigo-600/10 border-indigo-500 text-white' : 'bg-slate-950/60 border-slate-800 text-slate-400 hover:border-slate-700 hover:text-slate-300' }}">
                    <input type="radio" wire:model.live="platform" value="windows" class="sr-only" />
                    <svg class="w-6 h-6 mb-1.5" fill="currentColor" viewBox="0 0 24 24"><path d="M0 3.449L9.75 2.1v9.451H0m10.95-9.601L24 0v11.4H10.95M0 12.6h9.75v9.451L0 20.699M10.95 12.6H24V24l-13.05-1.835"/></svg>
                    <span class="text-xs font-semibold">Windows</span>
                    <span class="text-[10px] text-slate-500 mt-0.5">.exe / .msi</span>
                </label>

                <!-- macOS -->
                <label class="relative flex flex-col items-center justify-center p-3.5 rounded-xl border cursor-pointer transition {{ $platform === 'macos' ? 'bg-indigo-600/10 border-indigo-500 text-white' : 'bg-slate-950/60 border-slate-800 text-slate-400 hover:border-slate-700 hover:text-slate-300' }}">
                    <input type="radio" wire:model.live="platform" value="macos" class="sr-only" />
                    <svg class="w-6 h-6 mb-1.5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 4.2c.62-.75 1.04-1.8 0.92-2.85-.9.04-2 .6-2.65 1.36-.58.67-1.09 1.75-.95 2.78 1.01.08 2.05-.54 2.68-1.29"/></svg>
                    <span class="text-xs font-semibold">macOS</span>
                    <span class="text-[10px] text-slate-500 mt-0.5">.dmg / .pkg</span>
                </label>

                <!-- Linux -->
                <label class="relative flex flex-col items-center justify-center p-3.5 rounded-xl border cursor-pointer transition {{ $platform === 'linux' ? 'bg-indigo-600/10 border-indigo-500 text-white' : 'bg-slate-950/60 border-slate-800 text-slate-400 hover:border-slate-700 hover:text-slate-300' }}">
                    <input type="radio" wire:model.live="platform" value="linux" class="sr-only" />
                    <svg class="w-6 h-6 mb-1.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.002 0c-2.835 0-4.66 2.051-4.66 4.908 0 .866.231 1.83.627 2.723C6.721 8.528 4.8 11.082 4.8 14.152c0 .878.147 1.721.417 2.505C3.393 17.514 2 18.73 2 20.401c0 1.983 2.43 3.599 5.426 3.599 1.981 0 3.722-.711 4.576-1.787.854 1.076 2.595 1.787 4.576 1.787 2.996 0 5.426-1.616 5.426-3.599 0-1.671-1.393-2.887-3.217-3.744.27-.784.417-1.627.417-2.505 0-3.07-1.921-5.624-3.169-6.521.396-.893.627-1.857.627-2.723C16.662 2.051 14.837 0 12.002 0z"/></svg>
                    <span class="text-xs font-semibold">Linux</span>
                    <span class="text-[10px] text-slate-500 mt-0.5">.AppImage / .deb</span>
                </label>

                <!-- Universal -->
                <label class="relative flex flex-col items-center justify-center p-3.5 rounded-xl border cursor-pointer transition {{ $platform === 'all' ? 'bg-indigo-600/10 border-indigo-500 text-white' : 'bg-slate-950/60 border-slate-800 text-slate-400 hover:border-slate-700 hover:text-slate-300' }}">
                    <input type="radio" wire:model.live="platform" value="all" class="sr-only" />
                    <svg class="w-6 h-6 mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                    <span class="text-xs font-semibold">Universal</span>
                    <span class="text-[10px] text-slate-500 mt-0.5">All Architectures</span>
                </label>
            </div>
        </div>

        <!-- Binary Upload Dropzone -->
        <div>
            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                Binary Executable Package <span class="text-indigo-400">*</span>
            </label>
            
            <div 
                x-data="{ uploading: false, progress: 0, dragging: false, errorMessage: '' }"
                x-on:livewire-upload-start="uploading = true; errorMessage = ''"
                x-on:livewire-upload-finish="uploading = false"
                x-on:livewire-upload-error="uploading = false; console.log($event); errorMessage = 'Upload failed. Check file size limits or network status.'"
                x-on:livewire-upload-progress="progress = $event.detail.progress"
                @dragover.prevent="dragging = true"
                @dragleave.prevent="dragging = false"
                @drop="dragging = false"
                :class="dragging ? 'border-indigo-500 bg-indigo-500/10' : 'border-slate-800 hover:border-slate-700 bg-slate-950/50'"
                class="border-2 border-dashed rounded-2xl p-6 sm:p-8 text-center relative transition group"
            >
                <template x-if="errorMessage">
                    <p class="text-xs text-rose-400 mt-2 font-medium" x-text="errorMessage"></p>
                </template>
                <input wire:model="binaryFile" type="file" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" />

                @if ($binaryFile)
                    <div class="relative z-20 flex flex-col items-center justify-center">
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center mb-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <span class="text-sm font-semibold text-emerald-400 max-w-xs truncate">{{ $binaryFile->getClientOriginalName() }}</span>
                        <span class="text-xs text-slate-400 mt-1 font-mono">
                            {{ number_format($binaryFile->getSize() / 1024 / 1024, 2) }} MB
                        </span>

                        <button 
                            type="button" 
                            wire:click="removeFile"
                            class="mt-3 text-xs text-rose-400 hover:text-rose-300 underline z-30 cursor-pointer"
                        >
                            Remove and select another file
                        </button>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center">
                        <div class="w-12 h-12 rounded-xl bg-slate-800/80 text-slate-400 border border-slate-700/60 flex items-center justify-center mb-3 group-hover:scale-105 group-hover:text-indigo-400 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        </div>
                        <p class="text-sm font-medium text-slate-200">
                            Click to upload or drag & drop binary package
                        </p>
                        <p class="text-xs text-slate-500 mt-1">
                            Supports .exe, .dmg, .AppImage, .zip, .tar.gz (Max 500MB)
                        </p>
                    </div>
                @endif

                <!-- Upload Progress Bar -->
                <div x-show="uploading" x-cloak class="mt-4 relative z-20">
                    <div class="flex items-center justify-between text-xs text-slate-400 mb-1.5 max-w-xs mx-auto">
                        <span>Uploading binary...</span>
                        <span x-text="`${progress}%`" class="font-mono text-indigo-400"></span>
                    </div>
                    <div class="w-full bg-slate-800 h-2 rounded-full overflow-hidden max-w-xs mx-auto">
                        <div class="bg-indigo-500 h-2 transition-all duration-150 rounded-full" :style="`width: ${progress}%`"></div>
                    </div>
                </div>
            </div>
            @error('binaryFile') <p class="text-xs text-rose-400 mt-1.5 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ $message }}</p> @enderror
        </div>

        <!-- Release Notes -->
        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Release Notes</label>
                <span class="text-[10px] text-slate-500">Markdown formatting supported</span>
            </div>
            <textarea 
                wire:model="release_notes" 
                rows="4" 
                placeholder="• Fixed issue where client log parser crashed&#10;• Improved synchronization speeds over API&#10;• Updated security certificates" 
                class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3.5 text-xs text-slate-200 font-mono placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition"
            ></textarea>
            @error('release_notes') <p class="text-xs text-rose-400 mt-1.5 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ $message }}</p> @enderror
        </div>

        <!-- Mandatory Update Card -->
        <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800 flex items-start gap-3">
            <input 
                wire:model="is_mandatory" 
                id="is_mandatory"
                type="checkbox" 
                class="mt-1 rounded bg-slate-900 border-slate-700 text-indigo-600 focus:ring-indigo-500/20 w-4 h-4 cursor-pointer" 
            />
            <label for="is_mandatory" class="cursor-pointer">
                <span class="text-xs font-semibold text-slate-200 block">Mark as Mandatory Update</span>
                <span class="text-xs text-slate-400 block mt-0.5">Enforces automatic update installation on desktop client startup before permitting core application execution.</span>
            </label>
        </div>

        <!-- Submit Button -->
        <div class="pt-4 border-t border-slate-800 flex items-center justify-end gap-3">
            <a 
                href="{{ route('home') }}" 
                class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium text-xs transition"
            >
                Cancel
            </a>

            <button 
                type="submit" 
                wire:loading.attr="disabled" 
                class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-xs font-semibold rounded-xl shadow-lg shadow-indigo-600/20 transition cursor-pointer"
            >
                <span wire:loading.remove wire:target="save">Upload & Publish Release</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Calculating SHA256 & Saving...
                </span>
            </button>
        </div>

    </form>
</div>