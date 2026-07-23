<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PublishingSetting;
use App\Models\SeoContentDraft;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContentFeedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $settings = $this->authenticate($request);
        $limit = max(1, min((int) $request->integer('limit', 20), 100));

        $articles = $this->publishableQuery($settings)
            ->with(['brief', 'group.site'])
            ->latest('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $articles->map(fn (SeoContentDraft $article): array => $this->articlePayload($article)),
            'meta' => [
                'count' => $articles->count(),
                'limit' => $limit,
                'unread_count' => $this->publishableQuery($settings)
                    ->whereNull('api_read_at')
                    ->count(),
            ],
        ]);
    }

    public function nextUnread(Request $request): JsonResponse
    {
        $settings = $this->authenticate($request);

        $article = DB::transaction(function () use ($settings): ?SeoContentDraft {
            $article = $this->publishableQuery($settings)
                ->whereNull('api_read_at')
                ->oldest('id')
                ->lockForUpdate()
                ->first();

            if (! $article) {
                return null;
            }

            $article->updateQuietly(['api_read_at' => now()]);

            return $article->load(['brief', 'group.site']);
        });

        if (! $article) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No unread content is available.',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->articlePayload($article),
            'meta' => [
                'remaining_unread' => $this->publishableQuery($settings)
                    ->whereNull('api_read_at')
                    ->count(),
            ],
        ]);
    }

    private function authenticate(Request $request): PublishingSetting
    {
        $apiCode = (string) ($request->header('X-API-Code') ?: $request->query('api_code', ''));

        abort_if($apiCode === '', 401, 'API code is required.');

        $settings = PublishingSetting::query()
            ->where('content_api_enabled', true)
            ->where('content_api_key_hash', hash('sha256', $apiCode))
            ->first();

        abort_unless($settings, 401, 'The API code is invalid or the Content API is disabled.');

        return $settings;
    }

    private function publishableQuery(PublishingSetting $settings): Builder
    {
        return SeoContentDraft::query()
            ->where('user_id', $settings->user_id)
            ->whereIn('status', ['draft', 'needs_review', 'approved']);
    }

    /**
     * @return array<string, mixed>
     */
    private function articlePayload(SeoContentDraft $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'html' => $article->html,
            'plain_text' => $article->plain_text ?: strip_tags($article->html),
            'meta_title' => $article->meta_title,
            'meta_description' => $article->meta_description,
            'primary_keyword' => $article->brief?->primary_keyword,
            'language' => $article->language,
            'status' => $article->status,
            'read_at' => $article->api_read_at?->toIso8601String(),
            'site' => [
                'id' => $article->group?->site?->id,
                'name' => $article->group?->site?->name,
                'url' => $article->group?->site?->site_url,
            ],
            'created_at' => $article->created_at?->toIso8601String(),
            'updated_at' => $article->updated_at?->toIso8601String(),
        ];
    }
}
