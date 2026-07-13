<?php

namespace App\Services;

use App\Models\GoogleOauthToken;
use App\Models\SeoAuditLog;
use Exception;
use Google\Client;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\RequestInterface;

class GoogleSearchConsoleService
{
    public function makeClient(?GoogleOauthToken $tokenModel = null): Client
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

        if (! $tokenModel) {
            throw new Exception('A Google account token is required.');
        }

        $token = [
            'access_token' => $tokenModel->access_token,
            'refresh_token' => $tokenModel->refresh_token,
            'created' => time(),
            'expires_in' => $tokenModel->expires_at ? ($tokenModel->expires_at->timestamp - time()) : 3600,
        ];

        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired() && $tokenModel->refresh_token) {
            $newAccessToken = $client->fetchAccessTokenWithRefreshToken($tokenModel->refresh_token);

            if (! isset($newAccessToken['error'])) {
                $tokenModel->update([
                    'access_token' => $newAccessToken['access_token'],
                    'expires_at' => isset($newAccessToken['expires_in']) ? now()->addSeconds($newAccessToken['expires_in']) : null,
                ]);
            } else {
                throw new Exception('Google token refresh failed: '.($newAccessToken['error_description'] ?? $newAccessToken['error']));
            }
        } elseif ($client->isAccessTokenExpired()) {
            throw new Exception('Google access token expired and no refresh token is available.');
        }

        return $client;
    }

    public function listSites(?GoogleOauthToken $tokenModel = null): array
    {
        $client = $this->makeClient($tokenModel);
        $service = new SearchConsole($client);

        try {
            $sites = $service->sites->listSites();
            $result = [];
            foreach ($sites->getSiteEntry() as $site) {
                $result[] = [
                    'siteUrl' => $site->getSiteUrl(),
                    'permissionLevel' => $site->getPermissionLevel(),
                ];
            }

            return $result;
        } catch (Exception $e) {
            SeoAuditLog::create([
                'user_id' => $tokenModel?->user_id,
                'entity_type' => 'system',
                'action' => 'gsc_list_sites_failed',
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function fetchSearchAnalyticsRows(string $siteUrl, string $date, int $startRow = 0, int $rowLimit = 25000, ?GoogleOauthToken $tokenModel = null): array
    {
        $client = $this->makeClient($tokenModel);
        $service = new SearchConsole($client);

        $request = new SearchAnalyticsQueryRequest;
        $request->setStartDate($date);
        $request->setEndDate($date);
        $request->setDimensions(['query', 'page', 'country', 'device']);
        $request->setType('web');
        $request->setRowLimit($rowLimit);
        $request->setStartRow($startRow);

        try {
            $response = $service->searchanalytics->query($siteUrl, $request);
            $rows = $response->getRows();

            $result = [];
            if ($rows) {
                foreach ($rows as $row) {
                    $keys = $row->getKeys();
                    $result[] = [
                        'query' => $keys[0] ?? '',
                        'page' => $keys[1] ?? '',
                        'country' => $keys[2] ?? '',
                        'device' => $keys[3] ?? '',
                        'clicks' => $row->getClicks(),
                        'impressions' => $row->getImpressions(),
                        'ctr' => $row->getCtr(),
                        'position' => $row->getPosition(),
                    ];
                }
            }

            return $result;
        } catch (Exception $e) {
            SeoAuditLog::create([
                'user_id' => $tokenModel?->user_id,
                'entity_type' => 'system',
                'action' => 'gsc_fetch_failed',
                'message' => $e->getMessage(),
                'context' => ['siteUrl' => $siteUrl, 'date' => $date, 'startRow' => $startRow],
            ]);
            throw $e;
        }
    }

    public function fetchSearchAnalyticsRowsForRange(string $siteUrl, string $startDate, string $endDate, int $startRow = 0, int $rowLimit = 25000, ?GoogleOauthToken $tokenModel = null): array
    {
        $client = $this->makeClient($tokenModel);
        $service = new SearchConsole($client);

        $request = new SearchAnalyticsQueryRequest;
        $request->setStartDate($startDate);
        $request->setEndDate($endDate);
        $request->setDimensions(['query', 'page', 'country', 'device']);
        $request->setType('web');
        $request->setRowLimit($rowLimit);
        $request->setStartRow($startRow);

        try {
            $response = $service->searchanalytics->query($siteUrl, $request);
            $rows = $response->getRows();

            $result = [];
            if ($rows) {
                foreach ($rows as $row) {
                    $keys = $row->getKeys();
                    $result[] = [
                        'query' => $keys[0] ?? '',
                        'page' => $keys[1] ?? '',
                        'country' => $keys[2] ?? '',
                        'device' => $keys[3] ?? '',
                        'clicks' => $row->getClicks(),
                        'impressions' => $row->getImpressions(),
                        'ctr' => $row->getCtr(),
                        'position' => $row->getPosition(),
                    ];
                }
            }

            return $result;
        } catch (Exception $e) {
            SeoAuditLog::create([
                'user_id' => $tokenModel?->user_id,
                'entity_type' => 'system',
                'action' => 'gsc_fetch_range_failed',
                'message' => $e->getMessage(),
                'context' => ['siteUrl' => $siteUrl, 'startDate' => $startDate, 'endDate' => $endDate, 'startRow' => $startRow],
            ]);
            throw $e;
        }
    }
}
