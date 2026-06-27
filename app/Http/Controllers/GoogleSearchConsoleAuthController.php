<?php

namespace App\Http\Controllers;

use App\Models\GoogleOauthToken;
use Google\Client;
use Illuminate\Http\Request;

class GoogleSearchConsoleAuthController extends Controller
{
    public function redirect()
    {
        $client = $this->getClient();
        $authUrl = $client->createAuthUrl();

        return redirect()->away($authUrl);
    }

    public function callback(Request $request)
    {
        if (!$request->has('code')) {
            return redirect('/admin')->with('error', 'Google OAuth failed.');
        }

        $client = $this->getClient();
        $token = $client->fetchAccessTokenWithAuthCode($request->code);

        if (isset($token['error'])) {
            return redirect('/admin')->with('error', 'Google OAuth failed: ' . $token['error']);
        }

        GoogleOauthToken::updateOrCreate(
            ['user_id' => auth()->id(), 'provider' => 'google'],
            [
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'expires_at' => isset($token['expires_in']) ? now()->addSeconds($token['expires_in']) : null,
                'scope' => $token['scope'] ?? null,
            ]
        );

        return redirect('/admin/gsc-sites')->with('success', 'Google account connected.');
    }

    private function getClient(): Client
    {
        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect_uri'));
        $client->addScope('https://www.googleapis.com/auth/webmasters.readonly');
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }
}
