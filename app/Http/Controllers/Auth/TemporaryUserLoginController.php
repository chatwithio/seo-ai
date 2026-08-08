<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SeoAuditLog;
use App\Services\TemporaryUserLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TemporaryUserLoginController extends Controller
{
    public function __invoke(Request $request, string $token, TemporaryUserLoginService $links): RedirectResponse
    {
        $link = $links->consume($token, $request->ip(), $request->userAgent());

        if (! $link) {
            return redirect()->route('login')->withErrors([
                'email' => 'This temporary login link is invalid, expired, or has already been used.',
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::login($link->user);
        $request->session()->regenerate();

        SeoAuditLog::create([
            'user_id' => $link->user_id,
            'entity_type' => 'temporary_login',
            'entity_id' => $link->id,
            'action' => 'temporary_login_used',
            'message' => 'A temporary debug login link was used.',
            'context' => [
                'ip' => $request->ip(),
                'used_at' => $link->used_at?->toIso8601String(),
            ],
        ]);

        return redirect($link->redirect_path);
    }
}
