<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('template_key')->unique();
            $table->string('name');
            $table->string('subject');
            $table->longText('html_body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('email_templates')->insert([
            [
                'template_key' => 'welcome',
                'name' => 'Welcome Email',
                'subject' => 'Master! Your creation is alive at {url}',
                'html_body' => <<<'HTML'
<h2>Your {app_name} account is ready</h2>
<p>Hello {name}, your account is fully set up and awaiting your commands.</p>
<h3>Your first steps</h3>
<ol>
    <li><strong>Log in:</strong> <a href="{login_url}">Access your portal</a>.</li>
    <li><strong>Connect Google:</strong> Add your Google Search Console account.</li>
    <li><strong>Begin creating:</strong> Review your keywords and create your first article.</li>
</ol>
<p>Need support? <a href="{support_url}">Contact us on WhatsApp</a>.</p>
<p>See tutorials and examples on our <a href="{youtube_url}">YouTube channel</a>.</p>
HTML,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'template_key' => 'weekly_activity',
                'name' => 'Weekly SEO Activity',
                'subject' => 'Master! Review your weekly SEO activity',
                'html_body' => <<<'HTML'
<h2>Your weekly SEO activity</h2>
<p>Hello {name}, {app_name} is moving forward. Here are the most relevant numbers for your account:</p>
<ul>
    <li><strong>SEO Keywords:</strong> {keyword_count}</li>
    <li><strong>Impressions:</strong> {impressions}</li>
    <li><strong>Clicks:</strong> {clicks}</li>
    <li><strong>Articles:</strong> {article_count}</li>
</ul>
<p><a href="{dashboard_url}">Review all your data in your account</a>.</p>
<p>Need support? <a href="{support_url}">Contact us on WhatsApp</a>.</p>
<p>See tutorials and examples on our <a href="{youtube_url}">YouTube channel</a>.</p>
HTML,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'template_key' => 'weekly_ideas',
                'name' => 'Weekly SEO Content Ideas',
                'subject' => 'Master! New ideas for your SEO content',
                'html_body' => <<<'HTML'
<h2>New ideas for your SEO content</h2>
<p>Hello {name}, here are keyword opportunities and content ideas tailored to your account:</p>
{ideas_html}
<p><a href="{keywords_url}">Review all your SEO keywords</a>.</p>
<p>Need support? <a href="{support_url}">Contact us on WhatsApp</a>.</p>
<p>See tutorials and examples on our <a href="{youtube_url}">YouTube channel</a>.</p>
HTML,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
