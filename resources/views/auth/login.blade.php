<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SEO Agent</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #080c14;
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
            <h2 class="text-xl font-semibold text-white mb-6 text-center">Sign in to your account</h2>

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

            <form action="{{ url('/users/login') }}" method="POST" class="space-y-5">
                @csrf

                <!-- Email Input -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-300 mb-1.5">Email Address</label>
                    <input 
                        type="email" 
                        name="email" 
                        id="email" 
                        value="{{ old('email') }}" 
                        required 
                        autofocus
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
                        placeholder="••••••••" 
                        class="w-full rounded-xl bg-gray-900/60 border border-gray-700/50 px-4 py-3 text-white placeholder-gray-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500/50 outline-none transition duration-200"
                    >
                </div>

                <!-- Remember Me Checkbox -->
                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center text-gray-300 cursor-pointer">
                        <input 
                            type="checkbox" 
                            name="remember" 
                            class="rounded bg-gray-900 border-gray-700 text-amber-500 focus:ring-amber-500 focus:ring-offset-gray-900 mr-2"
                        >
                        Remember me
                    </label>
                </div>

                <!-- Submit Button -->
                <button 
                    type="submit" 
                    class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-medium shadow-lg hover:shadow-orange-500/20 active:scale-[0.98] transition duration-200 outline-none"
                >
                    Sign In
                </button>
            </form>

            <!-- Redirect to Register -->
            <div class="mt-6 text-center text-sm text-gray-400">
                Don't have an account? 
                <a href="{{ url('/users/create') }}" class="font-medium text-amber-400 hover:text-amber-300 hover:underline transition duration-150 ml-1">
                    Create new account
                </a>
            </div>
        </div>

        <x-product-footer />
    </div>
</body>
</html>
