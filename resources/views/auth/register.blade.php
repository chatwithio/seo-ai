<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - SEO Agent</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #080c14;
            color: #f3f4f6;
            color-scheme: dark;
        }
        .glow-orb {
            position: absolute;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, rgba(0, 0, 0, 0) 70%);
            filter: blur(60px);
            z-index: 0;
        }
        .glow-orb-2 {
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(245, 158, 11, 0.1) 0%, rgba(0, 0, 0, 0) 70%);
            filter: blur(50px);
            z-index: 0;
        }
        .glass-card {
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .stats-row {
            transition: background-color 200ms ease, border-color 200ms ease;
        }
        .stats-row:hover {
            background-color: rgba(255, 255, 255, 0.03);
        }
        @media (prefers-reduced-motion: reduce) {
            .stats-live-ping {
                animation: none !important;
            }
        }
    </style>
</head>
<body class="relative flex min-h-screen items-start justify-center overflow-x-hidden p-4 py-8 sm:items-center">
    <!-- Decorative Glowing Background Orbs -->
    <div class="glow-orb -top-20 -left-20"></div>
    <div class="glow-orb-2 -bottom-20 -right-20"></div>
    <div class="glow-orb top-1/3 right-1/4"></div>

    <div class="w-full max-w-md z-10">
        <!-- Logo Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold tracking-tight bg-gradient-to-r from-amber-400 via-orange-400 to-indigo-400 bg-clip-text text-transparent">
                SEO AI Agent
            </h1>
            <p class="mt-2 text-sm text-gray-400">Scale your organic traffic in background</p>
        </div>

        <!-- Card Container -->
        <div class="glass-card rounded-2xl p-8 shadow-2xl">
            <h2 class="text-xl font-semibold text-white mb-6 text-center">Create your free account</h2>

            <!-- Errors Alert -->
            @if ($errors->any())
                <div class="mb-5 p-4 rounded-xl bg-red-950/50 border border-red-500/30 text-red-300 text-sm">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ url('/users/create') }}" method="POST" class="space-y-5">
                @csrf

                <!-- Name Input -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-300 mb-1.5">Full Name</label>
                    <input
                        type="text"
                        name="name"
                        id="name"
                        value="{{ old('name') }}"
                        required
                        autofocus
                        placeholder="John Doe"
                        class="w-full rounded-xl bg-gray-900/60 border border-gray-700/50 px-4 py-3 text-white placeholder-gray-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500/50 outline-none transition duration-200"
                    >
                </div>

                <!-- Email Input -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-300 mb-1.5">Email Address</label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        value="{{ old('email') }}"
                        required
                        placeholder="you@example.com"
                        class="w-full rounded-xl bg-gray-900/60 border border-gray-700/50 px-4 py-3 text-white placeholder-gray-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500/50 outline-none transition duration-200"
                    >
                </div>

                <!-- Password Input -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-300 mb-1.5">Password</label>
                    <input
                        type="password"
                        name="password"
                        id="password"
                        required
                        placeholder="Minimun 8 characters"
                        class="w-full rounded-xl bg-gray-900/60 border border-gray-700/50 px-4 py-3 text-white placeholder-gray-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500/50 outline-none transition duration-200"
                    >
                </div>

                <!-- Password Confirmation Input -->
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-300 mb-1.5">Confirm Password</label>
                    <input
                        type="password"
                        name="password_confirmation"
                        id="password_confirmation"
                        required
                        placeholder="Repeat your password"
                        class="w-full rounded-xl bg-gray-900/60 border border-gray-700/50 px-4 py-3 text-white placeholder-gray-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500/50 outline-none transition duration-200"
                    >
                </div>

                <!-- Submit Button -->
                <button
                    type="submit"
                    class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-medium shadow-lg hover:shadow-orange-500/20 active:scale-[0.98] transition duration-200 outline-none"
                >
                    Create Account
                </button>
            </form>

            <!-- Redirect to Login -->
            <div class="mt-6 text-center text-sm text-gray-400">
                Already have an account?
                <a href="{{ url('/users/login') }}" class="font-medium text-amber-400 hover:text-amber-300 hover:underline transition duration-150 ml-1">
                    Sign in here
                </a>
            </div>
        </div>

        <section class="mt-4 w-full" aria-label="SEO AI Agent platform activity">
            <div class="glass-card overflow-hidden rounded-2xl shadow-lg ring-1 ring-white/[0.05]">
                <div class="h-px bg-gradient-to-r from-transparent via-amber-500/30 to-transparent" aria-hidden="true"></div>

                <div class="px-5 py-4 sm:px-6">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="relative flex size-2" aria-hidden="true">
                            <span class="stats-live-ping absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400/80 opacity-60"></span>
                            <span class="relative inline-flex size-2 rounded-full bg-emerald-400"></span>
                        </span>
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-gray-400">Live platform activity</p>
                    </div>

                    <div class="divide-y divide-white/[0.06]">
                        <div class="stats-row grid grid-cols-[3rem_2rem_minmax(0,1fr)] items-center gap-3 py-3">
                            <span class="text-right text-2xl font-semibold tabular-nums leading-none text-white">{{ number_format($platformCounters['accounts']) }}</span>
                            <span class="flex size-8 items-center justify-center rounded-lg bg-indigo-400/10" aria-hidden="true">
                                <svg width="17" height="17" class="shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#818cf8" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                                </svg>
                            </span>
                            <span class="text-sm font-medium text-gray-200">Accounts Created</span>
                        </div>
                        <div class="stats-row grid grid-cols-[3rem_2rem_minmax(0,1fr)] items-center gap-3 py-3">
                            <span class="text-right text-2xl font-semibold tabular-nums leading-none text-white">{{ number_format($platformCounters['keywords']) }}</span>
                            <span class="flex size-8 items-center justify-center rounded-lg bg-emerald-400/10" aria-hidden="true">
                                <svg width="17" height="17" class="shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#34d399" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                    <circle cx="11" cy="11" r="8" />
                                    <path d="m21 21-4.35-4.35" />
                                </svg>
                            </span>
                            <span class="text-sm font-medium text-gray-200">Keywords Scanned</span>
                        </div>
                        <div class="stats-row grid grid-cols-[3rem_2rem_minmax(0,1fr)] items-center gap-3 py-3">
                            <span class="text-right text-2xl font-semibold tabular-nums leading-none text-white">{{ number_format($platformCounters['articles']) }}</span>
                            <span class="flex size-8 items-center justify-center rounded-lg bg-amber-400/10" aria-hidden="true">
                                <svg width="17" height="17" class="shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#fbbf24" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
                                    <path d="M14 2v6h6M8 13h8M8 17h8" />
                                </svg>
                            </span>
                            <span class="text-sm font-medium text-gray-200">Articles Created</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <x-product-footer />
    </div>
</body>
</html>
