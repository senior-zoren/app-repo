<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Sign In') - {{ config('app.name', 'App Distribution') }}</title>

    <!-- Tailwind CSS & Livewire Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full font-sans antialiased text-slate-100 bg-slate-950 selection:bg-indigo-500 selection:text-white">

    <div class="min-h-screen flex flex-col justify-center items-center p-4 sm:p-6 lg:p-8">
        <!-- Branding / Logo Header -->
        <div class="sm:mx-auto sm:w-full sm:max-w-md text-center mb-6">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-indigo-600/10 border border-indigo-500/20 text-indigo-400 mb-3">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5-3.75 3.75m0 0 3.75 3.75M12.75 6.75 9 10.5m-6 3.75 3.75 3.75m0 0 3.75-3.75M9 17.25 12.75 13.5m0 0 3.75 3.75" />
                </svg>
            </div>
            <h2 class="text-xl font-semibold tracking-tight text-white">
                {{ config('app.name', 'App Distribution Portal') }}
            </h2>
        </div>

        <!-- Main Content Area -->
        <main class="w-full max-w-md">
            @yield('content')
        </main>

        <!-- Footer Note -->
        <footer class="mt-8 text-center text-xs text-slate-500">
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </footer>
    </div>

    @livewireScripts
</body>
</html>