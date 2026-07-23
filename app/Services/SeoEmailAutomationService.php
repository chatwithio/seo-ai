<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\PublishingSetting;
use App\Models\SeoAuditLog;
use App\Models\SeoContentDraft;
use App\Models\SeoKeyword;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Number;
use Throwable;

class SeoEmailAutomationService
{
    public function sendWelcome(User $user): bool
    {
        return $this->sendTemplate($user, 'welcome', []);
    }

    public function sendWeeklyActivity(User $user): bool
    {
        $settings = PublishingSetting::firstOrCreate(['user_id' => $user->id]);

        if (! $settings->weekly_activity_email_enabled) {
            return false;
        }

        $keywords = SeoKeyword::where('user_id', $user->id);

        return $this->sendTemplate($user, 'weekly_activity', [
            'keyword_count' => Number::format((clone $keywords)->count()),
            'impressions' => Number::format((int) (clone $keywords)->sum('total_impressions')),
            'clicks' => Number::format((int) (clone $keywords)->sum('total_clicks')),
            'article_count' => Number::format(SeoContentDraft::where('user_id', $user->id)->count()),
        ]);
    }

    public function sendWeeklyIdeas(User $user): bool
    {
        $settings = PublishingSetting::firstOrCreate(['user_id' => $user->id]);

        if (! $settings->weekly_ideas_email_enabled) {
            return false;
        }

        $ideas = SeoKeyword::query()
            ->contentOpportunitiesForUser($user->id)
            ->orderByDesc('total_impressions')
            ->limit(6)
            ->get();

        $ideasHtml = $ideas->isEmpty()
            ? '<p>No new keyword opportunities were found this week.</p>'
            : '<ul>'.$ideas->map(function (SeoKeyword $keyword): string {
                $url = url('/admin/seo-keywords').'?search='.urlencode($keyword->query_text);

                return '<li><strong>'.e($keyword->query_text).'</strong> — '
                    .Number::format($keyword->total_impressions).' impressions, '
                    .Number::format($keyword->total_clicks).' clicks '
                    .'— <a href="'.e($url).'">View keyword</a></li>';
            })->implode('').'</ul>';

        return $this->sendTemplate($user, 'weekly_ideas', [
            'ideas_html' => $ideasHtml,
        ]);
    }

    private function sendTemplate(User $user, string $templateKey, array $variables): bool
    {
        $template = EmailTemplate::where('template_key', $templateKey)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return false;
        }

        $variables = [
            'name' => $user->name,
            'app_name' => config('app.name', 'SEO AI Agent'),
            'url' => config('app.url'),
            'login_url' => url('/users/login'),
            'dashboard_url' => url('/admin'),
            'keywords_url' => url('/admin/seo-keywords'),
            'support_url' => 'https://chatwith.io/s/link-to-whatsapp',
            'youtube_url' => 'https://www.youtube.com/@LinktoWhatsApp',
            ...$variables,
        ];

        $subject = $this->render($template->subject, $variables);
        $html = $this->render($template->html_body, $variables);

        try {
            Mail::html($html, function ($message) use ($user, $subject): void {
                $message->to($user->email, $user->name)->subject($subject);
            });

            SeoAuditLog::create([
                'user_id' => $user->id,
                'entity_type' => 'email_automation',
                'action' => 'email_sent',
                'message' => "{$template->name} sent to {$user->email}.",
                'context' => ['template_key' => $templateKey],
            ]);

            return true;
        } catch (Throwable $exception) {
            SeoAuditLog::create([
                'user_id' => $user->id,
                'entity_type' => 'email_automation',
                'action' => 'email_failed',
                'message' => $exception->getMessage(),
                'context' => ['template_key' => $templateKey],
            ]);

            report($exception);

            return false;
        }
    }

    private function render(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace('{'.$key.'}', (string) $value, $content);
        }

        return $content;
    }
}
