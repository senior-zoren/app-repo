<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component {
    public bool $isApiEnabled = true;
    public string $status = 'healthy'; // 'healthy', 'disabled', 'down'
    public string $dbStatus = 'unknown';

    public function mount(): void
    {
        $this->isApiEnabled = Cache::get('api_v1_enabled', true);
        $this->checkHealth();
    }

    public function toggleApiStatus(): void
    {
        $this->isApiEnabled = ! $this->isApiEnabled;
        Cache::forever('api_v1_enabled', $this->isApiEnabled);
        $this->checkHealth();
    }

    public function checkHealth(): void
    {
        $this->isApiEnabled = Cache::get('api_v1_enabled', true);

        try {
            $dbConnected = (bool) DB::connection()->getPdo();
            $this->dbStatus = $dbConnected ? 'connected' : 'disconnected';

            if (! $this->isApiEnabled) {
                $this->status = 'disabled';
            } elseif ($dbConnected) {
                $this->status = 'healthy';
            } else {
                $this->status = 'down';
            }
        } catch (\Throwable $e) {
            $this->status = 'down';
            $this->dbStatus = 'error';
        }
    }
};
?>

<div wire:poll.30s="checkHealth" class="p-5 rounded-2xl bg-slate-900 border border-slate-800 shadow-xl relative overflow-hidden space-y-4">
    
    <!-- Top Row: Label & Status Badge -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Distribution API</span>
            <span class="text-[10px] font-mono text-slate-500 bg-slate-950 border border-slate-800 px-1.5 py-0.5 rounded">v1</span>
        </div>

        <div class="flex items-center gap-1.5">
            @if ($status === 'healthy')
                <span class="flex h-2 w-2 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                <span class="text-[10px] font-mono font-bold text-emerald-400 uppercase tracking-wider">Active</span>
            @elseif ($status === 'disabled')
                <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                <span class="text-[10px] font-mono font-bold text-amber-400 uppercase tracking-wider">Maintenance</span>
            @else
                <span class="flex h-2 w-2 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                </span>
                <span class="text-[10px] font-mono font-bold text-rose-400 uppercase tracking-wider">Offline</span>
            @endif
        </div>
    </div>

    <!-- Middle Row: Interactive Switch Control -->
    <div class="flex items-center justify-between py-2 border-y border-slate-800/80">
        <div>
            <div class="text-sm font-semibold text-white">API Access Control</div>
            <p class="text-xs text-slate-400">
                {{ $isApiEnabled ? 'Public clients can fetch updates & binaries.' : 'Public requests return 503 Service Unavailable.' }}
            </p>
        </div>

        <!-- Toggle Switch -->
        <button 
            type="button" 
            wire:click="toggleApiStatus" 
            wire:loading.attr="disabled"
            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $isApiEnabled ? 'bg-indigo-600' : 'bg-slate-700' }}"
            role="switch" 
            aria-checked="{{ $isApiEnabled ? 'true' : 'false' }}"
        >
            <span 
                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $isApiEnabled ? 'translate-x-5' : 'translate-x-0' }}"
            ></span>
        </button>
    </div>

    <!-- Bottom Row: Endpoint Route Path & Re-check Trigger -->
    <div class="flex items-center justify-between text-xs pt-1">
        <code class="text-slate-400 font-mono text-[11px]">/api/v1/apps/{slug}/latest</code>
        
        <button 
            type="button" 
            wire:click="checkHealth" 
            wire:loading.attr="disabled"
            class="text-indigo-400 hover:text-indigo-300 transition text-[11px] font-medium flex items-center gap-1 disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="checkHealth">Re-check &rarr;</span>
            <span wire:loading wire:target="checkHealth">Syncing...</span>
        </button>
    </div>
</div>