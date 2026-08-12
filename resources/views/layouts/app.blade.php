<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-900 text-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'App Repository Admin')</title>

    <!-- Tailwind CSS / Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
    @stack('styles')
</head>
<body class="h-full bg-slate-900 font-sans antialiased text-slate-200">

    <div class="min-h-full flex flex-col">
    <!-- Top Navigation Bar -->
    <header class="bg-slate-800 border-b border-slate-700/80 sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo / Brand -->
                <div class="flex items-center gap-3">
                    <a href="{{ route('home') }}" class="flex items-center gap-3 group">
                        <div class="w-9 h-9 rounded-lg bg-indigo-600 flex items-center justify-center font-bold text-white shadow-lg shadow-indigo-500/20 group-hover:bg-indigo-500 transition">
                            AR
                        </div>
                        <div>
                            <span class="font-bold text-white tracking-wide text-lg">App Repo</span>
                            <span class="text-xs bg-indigo-500/20 text-indigo-400 px-2 py-0.5 rounded-full ml-2 border border-indigo-500/30 font-mono">v4.0</span>
                        </div>
                    </a>
                </div>

                <!-- Navigation Links & User Actions -->
                <div class="flex items-center gap-6">
                    <nav class="flex items-center gap-2 text-sm font-medium">
                        <a 
                            href="{{ route('home') }}" 
                            class="px-3 py-2 rounded-md transition {{ request()->routeIs('home*') ? 'bg-slate-700/60 text-indigo-400 font-semibold' : 'text-slate-300 hover:text-white hover:bg-slate-700/30' }}"
                        >
                            Applications
                        </a>
                    </nav>

                    <div class="h-5 w-px bg-slate-700"></div>

                    <!-- User Profile & Logout -->
                    <div class="flex items-center gap-4">
                        @auth
                            <span class="text-xs text-slate-400 font-medium">
                                {{ auth()->user()->name ?? auth()->user()->email }}
                            </span>

                            <form action="{{ route('logout') }}" method="POST" class="inline">
                                @csrf
                                <button 
                                    type="submit" 
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-400 bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 transition"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l3 3m0 0l-3 3m3-3H2.25" />
                                    </svg>
                                    Logout
                                </button>
                            </form>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </header>

        <!-- Main Content Area -->
        <main class="flex-1 py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                @yield('content')
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-slate-800 py-4 text-center text-xs text-slate-500">
            App Repository Distribution Management System
        </footer>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>