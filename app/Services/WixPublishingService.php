<?php

namespace App\Services;

use App\Models\ContentPublicationAttempt;
use App\Models\SeoContentDraft;
use App\Models\SitePublishingConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class WixPublishingService
{
    private const BASE_URL = 'https://www.wixapis.com';

    /**
     * @return array{message: string, published_url: ?string, external_id: string}
     */
    public function publish(SeoContentDraft $draft, SitePublishingConnection $connection): array
    {
        $this->assertConnection($draft, $connection);
        $settings = $connection->settings ?? [];
        $html = $draft->html;

        Log::info('[WixPublishing] Starting article publish to Wix', [
            'draft_id' => $draft->id,
            'title' => $draft->title,
            'site_id' => $connection->site_id,
            'wix_site_id' => $settings['wix_site_id'] ?? null,
            'post_status' => $settings['post_status'] ?? 'draft',
        ]);

        if ($draft->featured_image_status === 'ready' && filled($draft->featured_image_url)) {
            $html = '<p><img src="'.e($draft->featured_image_url).'" alt="'.e($draft->featured_image_alt ?: $draft->title).'" /></p>'.$html;
        }

        $document = $this->convertHtmlToRicos($html, $connection);

        if (! is_array($document) || empty($document['nodes'] ?? null)) {
            $document = $this->buildFallbackRicosDocument($html);
        }

        $currentAttempt = ContentPublicationAttempt::query()
            ->where('seo_content_draft_id', $draft->id)
            ->where('content_version', max(1, (int) $draft->content_version))
            ->where('channel', 'wix')
            ->first();
        $previousId = $currentAttempt?->external_id ?: ContentPublicationAttempt::query()
            ->where('seo_content_draft_id', $draft->id)
            ->where('channel', 'wix')
            ->where('status', 'succeeded')
            ->whereNotNull('external_id')
            ->latest('id')
            ->value('external_id');

        $rawMemberId = (string) ($settings['member_id'] ?? '');
        $memberId = (filled($rawMemberId) && Str::isUuid($rawMemberId)) ? $rawMemberId : null;

        $draftPost = [
            'title' => $draft->title,
            'richContent' => $document,
            'excerpt' => Str::limit(strip_tags((string) ($draft->meta_description ?: $draft->plain_text)), 500, ''),
            'language' => $this->languageCode($draft->language),
            'commentingEnabled' => true,
        ];

        if (filled($memberId)) {
            $draftPost['memberId'] = $memberId;
        }

        $draftPostId = null;

        // If updating a previously tracked draft, try PATCH first; if it fails, fall back to POST
        if ($previousId) {
            try {
                $response = $this->request($connection)
                    ->patch(self::BASE_URL.'/blog/v3/draft-posts/'.rawurlencode($previousId), [
                        'draftPost' => ['id' => $previousId, ...$draftPost],
                    ]);

                if ($response->successful()) {
                    $draftPostId = (string) ($response->json('draftPost.id') ?: $previousId);
                }
            } catch (\Throwable) {
                // If PATCH returns 401/404 because draft was published/deleted, fall back to POST
                $draftPostId = null;
            }
        }

        if (! $draftPostId) {
            $response = $this->request($connection)
                ->post(self::BASE_URL.'/blog/v3/draft-posts', [
                    'draftPost' => $draftPost,
                    'publish' => false,
                ])
                ->throw();

            $draftPostId = (string) ($response->json('draftPost.id') ?: '');
        }

        if ($draftPostId === '') {
            throw new RuntimeException('Wix created the draft but did not return its ID.');
        }

        // Persist the draft ID before publishing so a failed publish retry updates
        // the same Wix draft instead of creating another external post.
        $currentAttempt?->update(['external_id' => $draftPostId]);

        $shouldPublish = ($settings['post_status'] ?? 'draft') === 'publish';
        $publishedUrl = null;

        if ($shouldPublish) {
            $published = $this->request($connection)
                ->withBody('{}', 'application/json')
                ->post(self::BASE_URL.'/blog/v3/draft-posts/'.rawurlencode($draftPostId).'/publish');

            if (! $published->successful()) {
                // If already published, verify post existence
                $published = $this->request($connection)
                    ->get(self::BASE_URL.'/blog/v3/posts/'.rawurlencode($draftPostId));
            }

            $postId = (string) ($published->json('postId') ?? $published->json('post.id') ?? $draftPostId);

            // Fetch live post details to get slug and URL
            $postDetails = $this->request($connection)
                ->get(self::BASE_URL.'/blog/v3/posts/'.rawurlencode($postId));

            $slug = (string) ($postDetails->json('post.slug') ?? $published->json('post.slug') ?? '');
            $siteUrl = (string) ($draft->site?->site_url ?? $draft->group?->site?->site_url ?? '');

            if ($slug !== '' && $siteUrl !== '') {
                $publishedUrl = rtrim($siteUrl, '/').'/post/'.ltrim($slug, '/');
            } else {
                $publishedUrl = $postDetails->json('post.url')
                    ?? $postDetails->json('post.link')
                    ?? $published->json('post.url');
            }
        }

        return [
            'message' => $shouldPublish ? 'Article published to Wix.' : 'Article saved as a Wix draft.',
            'published_url' => $publishedUrl ?? null,
            'external_id' => $draftPostId,
        ];
    }

    /**
     * Fetch the last active/created members from Wix site (up to limit).
     *
     * @return array<string, string> Map of memberId => Label (Name / Email)
     */
    public function listMembers(SitePublishingConnection|string $connectionOrApiKey, ?string $siteId = null, int $limit = 10): array
    {
        if ($connectionOrApiKey instanceof SitePublishingConnection) {
            $apiKey = (string) ($connectionOrApiKey->credentials['api_key'] ?? '');
            $siteId = (string) ($connectionOrApiKey->settings['wix_site_id'] ?? '');
        } else {
            $apiKey = (string) $connectionOrApiKey;
            $siteId = (string) $siteId;
        }

        if (blank($apiKey) || blank($siteId)) {
            return [];
        }

        $limit = max(1, min($limit, 50));

        $client = Http::baseUrl(self::BASE_URL)
            ->connectTimeout(10)
            ->timeout(20)
            ->acceptJson()
            ->withHeaders([
                'Authorization' => $apiKey,
                'wix-site-id' => $siteId,
            ]);

        // 1. Try Wix Members v1 query endpoint
        $response = $client->post('/members/v1/members/query', [
            'query' => [
                'paging' => [
                    'limit' => $limit,
                ],
                'sort' => [
                    [
                        'fieldName' => 'createdDate',
                        'order' => 'DESC',
                    ],
                ],
            ],
        ]);

        if (! $response->successful()) {
            // Fallback 1: Query without explicit sort
            $response = $client->post('/members/v1/members/query', [
                'paging' => ['limit' => $limit],
            ]);
        }

        if (! $response->successful()) {
            // Fallback 2: GET /members/v1/members
            $response = $client->get('/members/v1/members', [
                'paging.limit' => $limit,
            ]);
        }

        $members = $response->json('members') ?? [];
        $options = [];

        foreach ($members as $member) {
            $id = (string) ($member['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $firstName = $member['contact']['firstName'] ?? '';
            $lastName = $member['contact']['lastName'] ?? '';
            $contactName = trim("{$firstName} {$lastName}");

            $name = trim((string) (
                $member['profile']['nickname']
                ?? ($contactName !== '' ? $contactName : null)
                ?? $member['profile']['slug']
                ?? ''
            ));
            $email = trim((string) ($member['loginEmail'] ?? ($member['contact']['emails'][0] ?? '')));

            if ($name !== '' && $email !== '') {
                $options[$id] = "{$name} ({$email})";
            } elseif ($name !== '') {
                $options[$id] = $name;
            } elseif ($email !== '') {
                $options[$id] = $email;
            } else {
                $options[$id] = "Member {$id}";
            }
        }

        if (empty($members)) {
            // Fallback 3: Check author memberIds from recent blog posts
            $postsResponse = $client->get('/blog/v3/posts', ['paging.limit' => $limit]);
            if (! $postsResponse->successful()) {
                $postsResponse = $client->get('/blog/v3/draft-posts', ['paging.limit' => $limit]);
            }
            $posts = $postsResponse->json('posts') ?? $postsResponse->json('draftPosts') ?? [];
            foreach ($posts as $post) {
                $postMemberId = (string) ($post['memberId'] ?? '');
                if ($postMemberId !== '' && ! isset($options[$postMemberId])) {
                    $options[$postMemberId] = "Author from recent post ({$postMemberId})";
                }
            }
        }

        return $options;
    }

    /**
     * Convert HTML content to Wix Ricos document format.
     *
     * @return array{nodes: array<int, array<string, mixed>>}
     */
    public function convertHtmlToRicos(string $html, ?SitePublishingConnection $connection = null): array
    {
        return $this->buildFallbackRicosDocument($html);
    }

    /**
     * Fallback HTML parser that converts HTML blocks to valid Wix Ricos nodes.
     *
     * @return array{nodes: array<int, array<string, mixed>>}
     */
    public function buildFallbackRicosDocument(string $html): array
    {
        $nodes = [];
        $cleanHtml = trim($html);

        if ($cleanHtml === '') {
            return ['nodes' => []];
        }

        $blocks = preg_split('/(<\/(?:p|h[1-6]|ul|ol|blockquote)>)/i', $cleanHtml, -1, PREG_SPLIT_DELIM_CAPTURE);
        $current = '';

        foreach ($blocks as $piece) {
            $current .= $piece;
            if (preg_match('/<\/(?:p|h[1-6]|ul|ol|blockquote)>$/i', $current)) {
                $node = $this->parseHtmlBlockToRicosNode($current);
                if ($node) {
                    $nodes[] = $node;
                }
                $current = '';
            }
        }

        if (trim($current) !== '') {
            $node = $this->parseHtmlBlockToRicosNode($current);
            if ($node) {
                $nodes[] = $node;
            }
        }

        if (empty($nodes)) {
            $nodes[] = [
                'type' => 'PARAGRAPH',
                'id' => (string) Str::uuid(),
                'nodes' => [
                    [
                        'type' => 'TEXT',
                        'id' => (string) Str::uuid(),
                        'textData' => [
                            'text' => strip_tags($cleanHtml),
                            'decorations' => [],
                        ],
                    ],
                ],
            ];
        }

        return ['nodes' => $nodes];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseHtmlBlockToRicosNode(string $block): ?array
    {
        $block = trim($block);
        if ($block === '') {
            return null;
        }

        // Heading h1-h6
        if (preg_match('/^<h([1-6])\b[^>]*>(.*?)<\/h\1>$/is', $block, $matches)) {
            $level = (int) $matches[1];
            $text = html_entity_decode(strip_tags($matches[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (trim($text) === '') {
                return null;
            }

            return [
                'type' => 'HEADING',
                'id' => (string) Str::uuid(),
                'headingData' => ['level' => $level],
                'nodes' => [
                    [
                        'type' => 'TEXT',
                        'id' => (string) Str::uuid(),
                        'textData' => [
                            'text' => $text,
                            'decorations' => [],
                        ],
                    ],
                ],
            ];
        }

        // Image tag
        if (preg_match('/<img\b[^>]*src=["\']([^"\']+)["\'][^>]*>/i', $block, $imgMatches)) {
            $src = $imgMatches[1];
            $alt = '';
            if (preg_match('/alt=["\']([^"\']*)["\']/i', $block, $altMatches)) {
                $alt = $altMatches[1];
            }

            return [
                'type' => 'IMAGE',
                'id' => (string) Str::uuid(),
                'imageData' => [
                    'image' => [
                        'src' => ['url' => $src],
                    ],
                    'altText' => $alt,
                ],
                'nodes' => [],
            ];
        }

        // Paragraph
        $text = html_entity_decode(strip_tags($block), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (trim($text) === '') {
            return null;
        }

        return [
            'type' => 'PARAGRAPH',
            'id' => (string) Str::uuid(),
            'nodes' => [
                [
                    'type' => 'TEXT',
                    'id' => (string) Str::uuid(),
                    'textData' => [
                        'text' => $text,
                        'decorations' => [],
                    ],
                ],
            ],
        ];
    }

    public function testConnection(SitePublishingConnection $connection): string
    {
        // 1. Test Wix Blog Drafts access
        $blogResponse = $this->request($connection)
            ->get(self::BASE_URL.'/blog/v3/draft-posts', ['paging.limit' => 1]);

        if ($blogResponse->status() === 401) {
            throw new RuntimeException('Wix API Key is invalid or expired (HTTP 401 Unauthorized).');
        }

        if ($blogResponse->status() === 403) {
            throw new RuntimeException(
                'Wix API permission denied (HTTP 403 Forbidden). Please check: '
                .'1) In Wix Dashboard > Settings > API Keys, edit your API key and grant "Manage Blog" (or "All Permissions"). '
                .'2) Verify your Wix Site ID matches the site where the API key was created.'
            );
        }

        if (! $blogResponse->successful()) {
            throw new RuntimeException('Wix Blog connection failed with HTTP '.$blogResponse->status().'.');
        }

        // 2. Test Ricos conversion
        $ricosWorking = false;
        try {
            $ricosResponse = $this->request($connection)
                ->post(self::BASE_URL.'/ricos/v1/ricos-document/convert/to-ricos', [
                    'html' => '<p>SEO AI connection test</p>',
                ]);
            $ricosWorking = $ricosResponse->successful() && is_array($ricosResponse->json('document'));
        } catch (\Throwable) {
            $ricosWorking = false;
        }

        $statusMessage = $ricosWorking
            ? 'Wix Blog access and Ricos conversion verified successfully.'
            : 'Wix Blog access verified (using built-in Rich Content formatter).';

        $connection->update([
            'last_tested_at' => now(),
            'last_test_status' => 'success',
            'last_test_message' => $statusMessage,
        ]);

        return $statusMessage;
    }

    private function request(SitePublishingConnection $connection): PendingRequest
    {
        $credentials = $connection->credentials ?? [];
        $settings = $connection->settings ?? [];

        if (blank($credentials['api_key'] ?? null) || blank($settings['wix_site_id'] ?? null)) {
            throw new RuntimeException('Wix API key and Wix Site ID are required.');
        }

        return Http::baseUrl(self::BASE_URL)
            ->connectTimeout(15)
            ->timeout(60)
            ->acceptJson()
            ->withHeaders([
                'Authorization' => $credentials['api_key'],
                'wix-site-id' => $settings['wix_site_id'],
            ]);
    }

    private function assertConnection(SeoContentDraft $draft, SitePublishingConnection $connection): void
    {
        if (! $connection->is_enabled || $connection->provider !== 'wix') {
            throw new RuntimeException('Wix publishing is not enabled for this site.');
        }

        if ((int) $connection->user_id !== (int) $draft->user_id || (int) $connection->site_id !== (int) $draft->site_id) {
            throw new RuntimeException('This Wix connection does not belong to the article site.');
        }
    }

    private function languageCode(?string $language): string
    {
        return match (strtolower((string) $language)) {
            'spanish' => 'es',
            'french' => 'fr',
            'italian' => 'it',
            'german' => 'de',
            'portuguese' => 'pt',
            default => 'en',
        };
    }
}
