<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component 
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($this->email).'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Too many attempts. Try again in {$seconds} seconds.",
            ]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        $this->redirectIntended(default: route('home'), navigate: true);
    }
};
?>

<div class="bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">Sign In</h1>
        <p class="text-sm text-slate-400 mt-1">Access the app distribution dashboard</p>
    </div>

    <form wire:submit="login" class="space-y-4">
        <div>
            <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Email Address</label>
            <input 
                type="email" 
                wire:model="email" 
                required 
                autofocus
                class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-sm rounded-lg px-3.5 py-2.5 outline-none focus:border-indigo-500 transition"
            >
            @error('email') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Password</label>
            <input 
                type="password" 
                wire:model="password" 
                required 
                class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-sm rounded-lg px-3.5 py-2.5 outline-none focus:border-indigo-500 transition"
            >
            @error('password') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
        </div>

        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center space-x-2 cursor-pointer">
                <input type="checkbox" wire:model="remember" class="rounded bg-slate-950 border-slate-800 text-indigo-600 focus:ring-0">
                <span class="text-slate-400 text-xs">Remember me</span>
            </label>
        </div>

        <button 
            type="submit" 
            class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-sm rounded-lg shadow-sm transition"
        >
            Sign In
        </button>
    </form>
</div>