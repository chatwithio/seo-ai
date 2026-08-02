<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\PublishingSetting;
use App\Models\SeoAuditLog;
use App\Models\SeoKeyword;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
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

        return $this->sendTemplate(
            $user,
            'weekly_activity',
            app(WeeklySeoActivityService::class)->forUser($user->id),
        );
    }

    public function sendWeeklyIdeas(User $user): bool
    {
        $settings = PublishingSetting::firstOrCreate(['user_id' => $user->id]);

        if (! $settings->weekly_ideas_email_enabled) {
            return false;
        }

        $ideasService = app(WeeklySeoIdeasService::class);
        $ideas = $ideasService->forUser($user->id);

        $competitiveIdeas = $ideas->take(3);
        $lowerTrafficIdeas = $ideas->slice(3, 3);
        $emptyMessage = '<p>No new keyword opportunities were found for this section this week.</p>';

        $competitiveIdeasHtml = $competitiveIdeas->isEmpty()
            ? $emptyMessage
            : $this->ideasList($competitiveIdeas, $ideasService);

        $lowerTrafficIdeasHtml = $lowerTrafficIdeas->isEmpty()
            ? $emptyMessage
            : $this->ideasList($lowerTrafficIdeas, $ideasService);

        return $this->sendTemplate($user, 'weekly_ideas', [
            'competitive_ideas_html' => $competitiveIdeasHtml,
            'lower_traffic_ideas_html' => $lowerTrafficIdeasHtml,
            'ideas_html' => $this->ideasList($ideas, $ideasService),
        ]);
    }

    /**
     * @param  Collection<int, SeoKeyword>  $ideas
     */
    private function ideasList(Collection $ideas, WeeklySeoIdeasService $ideasService): string
    {
        if ($ideas->isEmpty()) {
            return '<p>No new keyword opportunities were found this week.</p>';
        }

        return '<ul style="margin: 8px 0 20px; padding-left: 22px;">'
            .$ideas->map(function (SeoKeyword $keyword) use ($ideasService): string {
                return '<li style="margin-bottom: 10px;"><strong>'.e($keyword->query_text).'</strong><br>'
                    .'<span style="color: #4b5563;">'.Number::format($keyword->total_impressions).' impressions · '
                    .Number::format($keyword->total_clicks).' clicks</span><br>'
                    .'<a href="'.e($ideasService->keywordUrl($keyword)).'">View keyword</a></li>';
            })->implode('')
            .'</ul>';
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
            'email_settings_url' => url('/admin/settings/email'),
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
