<?php

namespace App\Http\Controllers;

use App\Models\GoogleOauthToken;
use Google\Client;
use Google\Service\Oauth2;
use GuzzleHttp\HandlerStack;
use Illuminate\Http\Request;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Process\PhpExecutableFinder;

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
        if (! $request->has('code')) {
            return redirect('/admin')->with('error', 'Google OAuth failed.');
        }

        $client = $this->getClient();
        $token = $client->fetchAccessTokenWithAuthCode($request->code);

        if (isset($token['error'])) {
            return redirect('/admin')->with('error', 'Google OAuth failed: '.$token['error']);
        }

        $client->setAccessToken($token);

        try {
            $oauth2 = new Oauth2($client);
            $userInfo = $oauth2->userinfo->get();
            $email = $userInfo->getEmail();
        } catch (\Exception $e) {
            return redirect('/admin')->with('error', 'Failed to retrieve user email: '.$e->getMessage());
        }

        if (empty($email)) {
            return redirect('/admin')->with('error', 'Google account email is required but was not found.');
        }

        $existing = GoogleOauthToken::where([
            'user_id' => auth()->id(),
            'provider' => 'google',
            'email' => $email,
        ])->first();

        $userId = (int) auth()->id();

        GoogleOauthToken::updateOrCreate(
            ['user_id' => auth()->id(), 'provider' => 'google', 'email' => $email],
            [
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? ($existing ? $existing->refresh_token : null),
                'expires_at' => isset($token['expires_in']) ? now()->addSeconds($token['expires_in']) : null,
                'scope' => $token['scope'] ?? null,
            ]
        );

        if ($userId > 0) {
            $php = (new PhpExecutableFinder)->find(false) ?: 'php';
            $basePath = base_path();
            exec('cd '.escapeshellarg($basePath).' && '.escapeshellarg($php)." artisan seo:sync-gsc-sites --user-id={$userId} > /dev/null 2>&1 &");
        }

        return redirect('/admin/gsc-sites')->with('success', 'Google account connected. Connecting your sites now...');
    }

    private function getClient(): Client
    {
        $client = new Client;
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect_uri'));
        $client->addScope('https://www.googleapis.com/auth/webmasters.readonly');
        $client->addScope('https://www.googleapis.com/auth/userinfo.email');
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $stack = HandlerStack::create();
        $stack->push(function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                $request = $request->withHeader('Connection', 'close')
                    ->withProtocolVersion('1.1');
                $options['version'] = 1.1;
                $options['curl'][CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
                $options['curl'][CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
                $options['curl'][CURLOPT_FRESH_CONNECT] = true;

                return $handler($request, $options);
            };
        });

        $httpClient = new \GuzzleHttp\Client([
            'handler' => $stack,
            'timeout' => 15.0,
            'connect_timeout' => 5.0,
        ]);
        $client->setHttpClient($httpClient);

        return $client;
    }
}
