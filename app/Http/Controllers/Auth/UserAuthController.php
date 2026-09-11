<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SeoContentDraft;
use App\Models\SeoKeyword;
use App\Models\User;
use App\Services\SeoEmailAutomationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class UserAuthController extends Controller
{
    public function showRegister()
    {
        if (Auth::check()) {
            return redirect('/admin');
        }

        return view('auth.register', [
            'platformCounters' => [
                'accounts' => User::query()->count(),
                'keywords' => SeoKeyword::query()->count(),
                'articles' => SeoContentDraft::query()->count(),
            ],
        ]);
    }

    public function register(Request $request, SeoEmailAutomationService $emailService)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['required', 'string', 'min:7', 'max:30', 'regex:/^[+]?[0-9\s\-\(\).]{7,30}$/'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'phone.regex' => 'Please enter a valid phone number.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        Auth::login($user);

        $emailService->sendWelcome($user);

        return redirect('/admin');
    }

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect('/admin');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended('/admin');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/users/login');
    }
}
