<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateTemplate(
            'welcome',
            'Master! Your creation is alive at {url}',
            <<<'HTML'
<h2>Your {app_name} account is fully set up</h2>
<p>Hello {name}, your {app_name} account is fully set up and awaiting your brilliant commands.</p>
<h3>Your First Commands</h3>
<p>To bring your projects to life, please follow these simple steps:</p>
<ul>
    <li style="margin-bottom: 10px;"><strong>Step 1: Log In.</strong> <a href="{login_url}">Access your portal here</a> using the credentials you created.</li>
    <li style="margin-bottom: 10px;"><strong>Step 2: Set Up Your Workspace.</strong> Connect Google Search Console and adjust your settings to suit your exact requirements.</li>
    <li style="margin-bottom: 10px;"><strong>Step 3: Begin Creating.</strong> Review your keywords and create your first article from the main dashboard.</li>
</ul>
<p>If you need support, <a href="{support_url}">contact us here</a>.</p>
<p>Tutorials, demos, and examples are available on our <a href="{youtube_url}">YouTube channel</a>.</p>
<p>You can adjust your <a href="{email_settings_url}">email settings in your account</a>.</p>
HTML,
        );

        $this->updateTemplate(
            'weekly_activity',
            'Master! Review your weekly activity',
            <<<'HTML'
<h2>Review your weekly SEO activity</h2>
<p>Hello {name}, your {app_name} is moving forward. Here are the most relevant numbers for your account:</p>
<p style="color: #6b7280;">Reporting period: {activity_period}</p>
<ul>
    <li style="margin-bottom: 10px;"><strong>SEO Keywords:</strong> {keyword_count} ({keyword_change})</li>
    <li style="margin-bottom: 10px;"><strong>Impressions:</strong> {impressions} ({impressions_change})</li>
    <li style="margin-bottom: 10px;"><strong>Clicks:</strong> {clicks} ({clicks_change})</li>
</ul>
<p>Review all your data in your account. <a href="{dashboard_url}">Log in here</a>.</p>
<p>If you need support, <a href="{support_url}">contact us here</a>.</p>
<p>Tutorials, demos, and examples are available on our <a href="{youtube_url}">YouTube channel</a>.</p>
<p>You can adjust your <a href="{email_settings_url}">email settings in your account</a>.</p>
HTML,
        );

        $this->updateTemplate(
            'weekly_ideas',
            'Master! New ideas for your SEO content',
            <<<'HTML'
<h2>New ideas for your SEO content</h2>
<p>Hello {name}, we want to give you ideas and inspiration for your content, tailored to your account and ready to use on your website.</p>
<h3>High-potential keywords with lots of competitors</h3>
{competitive_ideas_html}
<h3>High-potential keywords with fewer competitors, but less traffic</h3>
{lower_traffic_ideas_html}
<p>Review all your data in your account. <a href="{keywords_url}">View your SEO keywords</a>.</p>
<p>If you need support, <a href="{support_url}">contact us here</a>.</p>
<p>Tutorials, demos, and examples are available on our <a href="{youtube_url}">YouTube channel</a>.</p>
<p>You can adjust your <a href="{email_settings_url}">email settings in your account</a>.</p>
HTML,
        );
    }

    public function down(): void
    {
        // Email templates are editable settings; do not overwrite later user edits on rollback.
    }

    private function updateTemplate(string $key, string $subject, string $body): void
    {
        DB::table('email_templates')
            ->where('template_key', $key)
            ->update([
                'subject' => $subject,
                'html_body' => $body,
                'updated_at' => now(),
            ]);
    }
};
