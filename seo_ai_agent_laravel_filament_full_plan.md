# Laravel Filament SEO AI Agent — Full Build Plan

## 0. Mission

Build a Laravel admin application that connects to Google Search Console, imports SEO keyword/query data, stores the data in MySQL, groups related keywords into content-topic clusters using an LLM, generates content briefs and drafts from those grouped keywords, and exposes everything through a Filament admin panel with human review before publishing.

The application must be built as a reusable Laravel admin starter, not as a quick one-off script.

---

## 1. Core Product Requirements

### 1.1 Main Goal

Create an AI-powered SEO agent app that can:

1. Connect to Google Search Console using OAuth.
2. Read keyword/query performance data.
3. Store the imported keyword metrics in a database.
4. Normalize and aggregate keywords.
5. Classify keyword intent.
6. Group related keywords that can be used in the same content piece.
7. Generate SEO content briefs from keyword groups.
8. Generate content drafts from approved briefs.
9. Review AI-generated drafts before publishing.
10. Allow an admin user to approve, reject, edit, or publish drafts.

---

## 2. Required Tech Stack

Use:

- Laravel
- MySQL
- Filament Admin Panel
- Spatie Laravel Permission
- Filament Shield, optional but recommended
- Laravel queues
- Laravel scheduler
- Google API Client
- OpenAI-compatible LLM provider
- Guzzle HTTP client

Suggested package list:

```bash
composer require filament/filament
composer require google/apiclient
composer require guzzlehttp/guzzle
composer require spatie/laravel-permission
composer require bezhansalleh/filament-shield
```

---

## 3. High-Level Architecture

```text
Google Search Console
        ↓
OAuth Connection
        ↓
Keyword Import Worker
        ↓
Raw Search Console Metrics
        ↓
Keyword Aggregation
        ↓
Keyword Classification
        ↓
Keyword Grouping / Clustering
        ↓
SEO Opportunity Scoring
        ↓
Content Brief Generation
        ↓
Content Draft Generation
        ↓
AI Quality Review
        ↓
Human Approval
        ↓
Optional CMS Publishing
```

---

## 4. Application Modules

Build these modules:

```text
1. Auth and Admin Panel
2. Google Search Console Integration
3. Raw Keyword Import
4. Keyword Aggregation
5. Keyword Classification
6. Keyword Grouping
7. SEO Opportunity Scoring
8. Prompt Management
9. Content Brief Generation
10. Content Draft Generation
11. Content Review
12. Optional Publishing
13. Logs and Audit Trail
```

---

## 5. Folder Structure

Use this structure:

```text
app/
  Actions/
    Seo/
      BuildKeywordOpportunitiesAction.php
      StoreKeywordGroupsAction.php
      CalculateOpportunityScoreAction.php

  Console/
    Commands/
      ImportSearchConsoleKeywords.php
      AggregateSeoKeywords.php
      GroupSeoKeywords.php
      GenerateSeoContentBrief.php
      GenerateSeoContentDraft.php
      ReviewSeoContentDraft.php

  Enums/
    SeoIntent.php
    SeoKeywordType.php
    SeoContentType.php
    SeoRecommendedAction.php
    SeoWorkflowStatus.php

  Filament/
    Resources/
      GscSiteResource.php
      SeoKeywordResource.php
      SeoKeywordGroupResource.php
      SeoContentBriefResource.php
      SeoContentDraftResource.php
      AiPromptResource.php
      GoogleOauthTokenResource.php

    Pages/
      SeoDashboard.php

    Widgets/
      KeywordOpportunityStats.php
      ImportStatusWidget.php
      DraftStatusWidget.php

  Jobs/
    ImportGscKeywordsJob.php
    AggregateSeoKeywordsJob.php
    GroupSeoKeywordsJob.php
    GenerateContentBriefJob.php
    GenerateContentDraftJob.php
    ReviewContentDraftJob.php

  Models/
    GscSite.php
    GoogleOauthToken.php
    GscKeywordMetric.php
    SeoKeyword.php
    SeoKeywordGroup.php
    SeoKeywordGroupKeyword.php
    SeoKeywordRelationship.php
    SeoContentBrief.php
    SeoContentDraft.php
    AiPrompt.php
    SeoAuditLog.php

  Services/
    GoogleSearchConsoleService.php
    GoogleOauthService.php
    SeoKeywordNormalizer.php
    KeywordGroupingService.php
    LlmContentService.php
    SeoPromptService.php
    SeoContentReviewService.php

  Support/
    JsonResponseParser.php
    SeoScoreCalculator.php

config/
  seo_agent.php
  seo_agent_prompts.php
  services.php

database/
  migrations/
  seeders/
```

---

## 6. Environment Variables

Add these to `.env.example`:

```env
APP_NAME="SEO AI Agent"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=seo_ai_agent
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/google/callback"

LLM_API_URL=
LLM_API_KEY=
LLM_MODEL=

SEO_DEFAULT_SITE_ID=1
SEO_IMPORT_DELAY_DAYS=3
SEO_IMPORT_ROW_LIMIT=25000
SEO_GROUPING_BATCH_SIZE=50
```

---

## 7. Database Design

### 7.1 `gsc_sites`

Stores Google Search Console properties.

```sql
CREATE TABLE gsc_sites (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_url VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NULL,
    permission_level VARCHAR(100) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_imported_at DATETIME NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

---

### 7.2 `google_oauth_tokens`

Stores OAuth tokens.

```sql
CREATE TABLE google_oauth_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    provider VARCHAR(50) NOT NULL DEFAULT 'google',
    access_token LONGTEXT NOT NULL,
    refresh_token LONGTEXT NULL,
    expires_at DATETIME NULL,
    scope TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    INDEX idx_provider (provider),
    INDEX idx_user_id (user_id)
);
```

---

### 7.3 `gsc_keyword_metrics`

Stores raw imported Search Console rows.

```sql
CREATE TABLE gsc_keyword_metrics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    site_id BIGINT UNSIGNED NOT NULL,
    report_date DATE NOT NULL,

    query_text VARCHAR(500) NOT NULL,
    page_url TEXT NULL,
    country VARCHAR(10) NULL,
    device VARCHAR(30) NULL,

    clicks INT UNSIGNED NOT NULL DEFAULT 0,
    impressions INT UNSIGNED NOT NULL DEFAULT 0,
    ctr DECIMAL(10,6) NOT NULL DEFAULT 0,
    position DECIMAL(10,4) NOT NULL DEFAULT 0,

    imported_at DATETIME NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    UNIQUE KEY uniq_gsc_metric (
        site_id,
        report_date,
        query_text(191),
        page_url(191),
        country,
        device
    ),

    INDEX idx_site_date (site_id, report_date),
    INDEX idx_query (query_text(191)),
    INDEX idx_position (position),
    INDEX idx_impressions (impressions)
);
```

---

### 7.4 `seo_keywords`

Stores one normalized row per unique keyword/query.

```sql
CREATE TABLE seo_keywords (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    site_id BIGINT UNSIGNED NOT NULL,

    query_text VARCHAR(500) NOT NULL,
    normalized_query VARCHAR(500) NOT NULL,
    query_hash CHAR(64) NOT NULL,

    language VARCHAR(10) NULL,
    country VARCHAR(10) NULL,

    total_clicks INT UNSIGNED NOT NULL DEFAULT 0,
    total_impressions INT UNSIGNED NOT NULL DEFAULT 0,
    avg_ctr DECIMAL(10,6) NOT NULL DEFAULT 0,
    avg_position DECIMAL(10,4) NOT NULL DEFAULT 0,

    main_page_url TEXT NULL,

    intent ENUM(
        'informational',
        'commercial',
        'transactional',
        'navigational',
        'local',
        'support',
        'unknown'
    ) NOT NULL DEFAULT 'unknown',

    keyword_type ENUM(
        'primary_candidate',
        'secondary_candidate',
        'question',
        'brand',
        'product',
        'category',
        'problem',
        'comparison',
        'unknown'
    ) NOT NULL DEFAULT 'unknown',

    priority_score DECIMAL(10,4) NOT NULL DEFAULT 0,
    ai_confidence DECIMAL(5,4) NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    UNIQUE KEY uniq_site_keyword_hash (site_id, query_hash),
    INDEX idx_site_intent (site_id, intent),
    INDEX idx_site_impressions (site_id, total_impressions),
    INDEX idx_site_position (site_id, avg_position),
    INDEX idx_priority_score (priority_score),
    INDEX idx_query_text (query_text(191))
);
```

---

### 7.5 `seo_keyword_groups`

Stores a group of related keywords that can be used in the same content.

```sql
CREATE TABLE seo_keyword_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    site_id BIGINT UNSIGNED NOT NULL,

    group_name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NULL,

    primary_keyword_id BIGINT UNSIGNED NULL,

    group_intent ENUM(
        'informational',
        'commercial',
        'transactional',
        'navigational',
        'local',
        'support',
        'mixed',
        'unknown'
    ) NOT NULL DEFAULT 'unknown',

    content_type ENUM(
        'blog_article',
        'buying_guide',
        'category_page_improvement',
        'product_page_improvement',
        'faq_block',
        'comparison_page',
        'landing_page',
        'support_article',
        'no_content_needed'
    ) NOT NULL DEFAULT 'blog_article',

    recommended_action ENUM(
        'create_new_page',
        'improve_existing_page',
        'rewrite_meta',
        'add_faq',
        'merge_with_existing_content',
        'no_action'
    ) NOT NULL DEFAULT 'create_new_page',

    target_page_url TEXT NULL,

    total_clicks INT UNSIGNED NOT NULL DEFAULT 0,
    total_impressions INT UNSIGNED NOT NULL DEFAULT 0,
    avg_ctr DECIMAL(10,6) NOT NULL DEFAULT 0,
    avg_position DECIMAL(10,4) NOT NULL DEFAULT 0,

    opportunity_score DECIMAL(10,4) NOT NULL DEFAULT 0,

    ai_summary TEXT NULL,
    ai_confidence DECIMAL(5,4) NULL,

    status ENUM(
        'new',
        'review_needed',
        'approved',
        'brief_generated',
        'draft_generated',
        'published',
        'rejected'
    ) NOT NULL DEFAULT 'new',

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    INDEX idx_site_status (site_id, status),
    INDEX idx_site_score (site_id, opportunity_score),
    INDEX idx_primary_keyword (primary_keyword_id)
);
```

---

### 7.6 `seo_keyword_group_keywords`

Links keywords to keyword groups.

```sql
CREATE TABLE seo_keyword_group_keywords (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    group_id BIGINT UNSIGNED NOT NULL,
    keyword_id BIGINT UNSIGNED NOT NULL,

    role ENUM(
        'primary',
        'secondary',
        'supporting',
        'question',
        'faq',
        'semantic_variant',
        'internal_link_anchor'
    ) NOT NULL DEFAULT 'secondary',

    relevance_score DECIMAL(5,4) NOT NULL DEFAULT 0,
    priority_score DECIMAL(10,4) NOT NULL DEFAULT 0,

    usage_instruction TEXT NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    UNIQUE KEY uniq_group_keyword (group_id, keyword_id),
    INDEX idx_group_role (group_id, role),
    INDEX idx_keyword_id (keyword_id),
    INDEX idx_relevance (relevance_score)
);
```

---

### 7.7 `seo_keyword_relationships`

Stores relationship explanation between two keywords.

```sql
CREATE TABLE seo_keyword_relationships (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    site_id BIGINT UNSIGNED NOT NULL,

    keyword_id_a BIGINT UNSIGNED NOT NULL,
    keyword_id_b BIGINT UNSIGNED NOT NULL,

    relationship_type ENUM(
        'same_intent',
        'semantic_variant',
        'synonym',
        'parent_child',
        'subtopic',
        'question_answer',
        'comparison',
        'not_related'
    ) NOT NULL DEFAULT 'same_intent',

    similarity_score DECIMAL(5,4) NOT NULL DEFAULT 0,
    ai_confidence DECIMAL(5,4) NULL,

    reason TEXT NULL,

    source ENUM(
        'llm',
        'embedding',
        'manual',
        'rule'
    ) NOT NULL DEFAULT 'llm',

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    UNIQUE KEY uniq_keyword_pair (site_id, keyword_id_a, keyword_id_b),
    INDEX idx_site_type (site_id, relationship_type),
    INDEX idx_keyword_a (keyword_id_a),
    INDEX idx_keyword_b (keyword_id_b),
    INDEX idx_similarity (similarity_score)
);
```

---

### 7.8 `seo_content_briefs`

Stores AI-generated content briefs.

```sql
CREATE TABLE seo_content_briefs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    keyword_group_id BIGINT UNSIGNED NOT NULL,

    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NULL,
    meta_title VARCHAR(255) NULL,
    meta_description VARCHAR(500) NULL,
    h1 VARCHAR(255) NULL,

    primary_keyword VARCHAR(500) NOT NULL,
    secondary_keywords JSON NULL,
    faq_keywords JSON NULL,

    search_intent VARCHAR(100) NULL,
    content_type VARCHAR(100) NULL,
    recommended_action VARCHAR(100) NULL,

    outline JSON NULL,
    internal_link_suggestions JSON NULL,
    must_answer_questions JSON NULL,
    seo_notes JSON NULL,
    quality_warnings JSON NULL,

    status ENUM(
        'draft',
        'approved',
        'rejected'
    ) NOT NULL DEFAULT 'draft',

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    INDEX idx_keyword_group_id (keyword_group_id),
    INDEX idx_status (status)
);
```

---

### 7.9 `seo_content_drafts`

Stores generated content drafts.

```sql
CREATE TABLE seo_content_drafts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    keyword_group_id BIGINT UNSIGNED NOT NULL,
    brief_id BIGINT UNSIGNED NOT NULL,

    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NULL,
    meta_title VARCHAR(255) NULL,
    meta_description VARCHAR(500) NULL,

    html LONGTEXT NOT NULL,
    plain_text LONGTEXT NULL,
    faq JSON NULL,
    internal_link_suggestions JSON NULL,
    quality_checks JSON NULL,

    status ENUM(
        'draft',
        'needs_review',
        'approved',
        'published',
        'rejected'
    ) NOT NULL DEFAULT 'draft',

    published_url TEXT NULL,
    published_at DATETIME NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    INDEX idx_keyword_group_id (keyword_group_id),
    INDEX idx_brief_id (brief_id),
    INDEX idx_status (status)
);
```

---

### 7.10 `ai_prompts`

Stores editable prompts.

```sql
CREATE TABLE ai_prompts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    prompt_key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,

    system_prompt LONGTEXT NULL,
    user_prompt LONGTEXT NOT NULL,

    output_format JSON NULL,

    is_active TINYINT(1) NOT NULL DEFAULT 1,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    INDEX idx_prompt_key (prompt_key),
    INDEX idx_is_active (is_active)
);
```

---

### 7.11 `seo_audit_logs`

Stores important system actions.

```sql
CREATE TABLE seo_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id BIGINT UNSIGNED NULL,
    site_id BIGINT UNSIGNED NULL,

    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,

    action VARCHAR(100) NOT NULL,
    message TEXT NULL,
    context JSON NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_user_id (user_id),
    INDEX idx_site_id (site_id),
    INDEX idx_action (action)
);
```

---

## 8. Laravel Migration Requirements

Create migrations for all tables above.

Use Laravel schema builder, but make sure these MySQL indexes are created correctly:

```sql
query_text(191)
page_url(191)
```

If Laravel schema builder cannot create prefix indexes cleanly, create the index using raw SQL inside the migration.

Example:

```php
DB::statement('ALTER TABLE seo_keywords ADD INDEX idx_query_text (query_text(191))');
```

---

## 9. Models and Relationships

### 9.1 `GscSite`

Relations:

```text
GscSite hasMany GscKeywordMetric
GscSite hasMany SeoKeyword
GscSite hasMany SeoKeywordGroup
```

---

### 9.2 `SeoKeyword`

Relations:

```text
SeoKeyword belongsTo GscSite
SeoKeyword belongsToMany SeoKeywordGroup through seo_keyword_group_keywords
SeoKeyword hasMany SeoKeywordRelationship as keyword_id_a
SeoKeyword hasMany SeoKeywordRelationship as keyword_id_b
```

---

### 9.3 `SeoKeywordGroup`

Relations:

```text
SeoKeywordGroup belongsTo GscSite
SeoKeywordGroup belongsTo SeoKeyword as primaryKeyword
SeoKeywordGroup belongsToMany SeoKeyword through seo_keyword_group_keywords
SeoKeywordGroup hasMany SeoContentBrief
SeoKeywordGroup hasMany SeoContentDraft
```

---

### 9.4 `SeoContentBrief`

Relations:

```text
SeoContentBrief belongsTo SeoKeywordGroup
SeoContentBrief hasMany SeoContentDraft
```

---

### 9.5 `SeoContentDraft`

Relations:

```text
SeoContentDraft belongsTo SeoKeywordGroup
SeoContentDraft belongsTo SeoContentBrief
```

---

## 10. Admin Panel Requirements

Use Filament resources for:

```text
GscSiteResource
SeoKeywordResource
SeoKeywordGroupResource
SeoContentBriefResource
SeoContentDraftResource
AiPromptResource
GoogleOauthTokenResource
```

---

## 11. Admin Dashboard Pages

Create these admin pages:

```text
/admin
/admin/gsc-sites
/admin/seo-keywords
/admin/seo-keyword-groups
/admin/seo-content-briefs
/admin/seo-content-drafts
/admin/ai-prompts
/admin/seo-dashboard
```

---

## 12. Filament Resource Behavior

### 12.1 `GscSiteResource`

Columns:

```text
site_url
name
permission_level
is_active
last_imported_at
created_at
```

Actions:

```text
Import Keywords
Aggregate Keywords
Build Groups
View Keywords
```

---

### 12.2 `SeoKeywordResource`

Columns:

```text
query_text
intent
keyword_type
total_clicks
total_impressions
avg_ctr
avg_position
priority_score
main_page_url
created_at
```

Filters:

```text
intent
keyword_type
country
avg_position range
impressions range
```

Actions:

```text
Classify Keyword
View Groups
```

---

### 12.3 `SeoKeywordGroupResource`

Columns:

```text
group_name
group_intent
content_type
recommended_action
total_impressions
avg_position
opportunity_score
status
target_page_url
```

Filters:

```text
status
group_intent
content_type
recommended_action
```

Actions:

```text
Approve Group
Reject Group
Generate Brief
View Keywords
```

Relation manager:

```text
Group Keywords
```

---

### 12.4 `SeoContentBriefResource`

Columns:

```text
title
primary_keyword
search_intent
content_type
recommended_action
status
created_at
```

Actions:

```text
Approve Brief
Reject Brief
Generate Draft
```

---

### 12.5 `SeoContentDraftResource`

Columns:

```text
title
status
published_url
published_at
created_at
```

Actions:

```text
Approve Draft
Reject Draft
Review Draft
Publish Draft
```

---

### 12.6 `AiPromptResource`

Columns:

```text
prompt_key
name
is_active
updated_at
```

Actions:

```text
Edit
Test Prompt
```

---

## 13. Google Search Console Integration

### 13.1 OAuth Flow

Create routes:

```php
Route::get('/google/connect', [GoogleSearchConsoleAuthController::class, 'redirect']);
Route::get('/google/callback', [GoogleSearchConsoleAuthController::class, 'callback']);
```

OAuth scope:

```text
https://www.googleapis.com/auth/webmasters.readonly
```

---

### 13.2 Search Console Query

Use:

```http
POST https://www.googleapis.com/webmasters/v3/sites/{siteUrl}/searchAnalytics/query
```

Default dimensions:

```json
[
  "query",
  "page",
  "country",
  "device"
]
```

Default request body:

```json
{
  "startDate": "YYYY-MM-DD",
  "endDate": "YYYY-MM-DD",
  "dimensions": ["query", "page", "country", "device"],
  "type": "web",
  "rowLimit": 25000,
  "startRow": 0
}
```

---

## 14. Google Search Console Service

Create:

```text
app/Services/GoogleSearchConsoleService.php
```

Required methods:

```php
public function makeClient(): \Google\Client;

public function listSites(): array;

public function fetchSearchAnalyticsRows(
    string $siteUrl,
    string $date,
    int $startRow = 0,
    int $rowLimit = 25000
): array;
```

Requirements:

1. Load OAuth token from database.
2. Refresh token when expired.
3. Save refreshed token.
4. Return rows in normalized array format.
5. Log errors to `seo_audit_logs`.

---

## 15. Import Keywords Command

Create command:

```bash
php artisan make:command ImportSearchConsoleKeywords
```

Signature:

```php
protected $signature = 'seo:import-gsc {site_id} {--date=}';
```

Behavior:

1. Load site by ID.
2. Default date is current date minus `SEO_IMPORT_DELAY_DAYS`.
3. Fetch GSC rows using pagination.
4. Store rows in `gsc_keyword_metrics`.
5. Use `updateOrInsert`.
6. Store import audit log.
7. Update `gsc_sites.last_imported_at`.

Pagination:

```text
startRow = 0
rowLimit = 25000
continue until returned rows count = 0
```

---

## 16. Keyword Normalization

Create:

```text
app/Services/SeoKeywordNormalizer.php
```

Methods:

```php
public function normalize(string $keyword): string;

public function hash(string $normalizedKeyword): string;
```

Normalization rules:

```text
1. Trim whitespace
2. Lowercase
3. Collapse multiple spaces
4. Remove invisible characters
5. Keep important symbols only if they affect meaning
6. Do not over-stem
```

Example:

```php
public function normalize(string $keyword): string
{
    $keyword = trim($keyword);
    $keyword = mb_strtolower($keyword);
    $keyword = preg_replace('/\s+/u', ' ', $keyword);
    return $keyword;
}

public function hash(string $normalizedKeyword): string
{
    return hash('sha256', $normalizedKeyword);
}
```

---

## 17. Aggregate Keywords Command

Create command:

```bash
php artisan make:command AggregateSeoKeywords
```

Signature:

```php
protected $signature = 'seo:aggregate-keywords {site_id} {--days=30}';
```

Behavior:

1. Read `gsc_keyword_metrics` for the last N days.
2. Group by normalized keyword.
3. Sum clicks.
4. Sum impressions.
5. Average CTR.
6. Average position.
7. Choose main page URL by highest clicks/impressions.
8. Calculate priority score.
9. Store in `seo_keywords`.

---

## 18. Opportunity Score

Create:

```text
app/Support/SeoScoreCalculator.php
```

Formula:

```php
public function calculate(
    int $impressions,
    float $ctr,
    float $position
): float {
    if ($impressions <= 0) {
        return 0;
    }

    if ($position >= 4 && $position <= 10) {
        $positionWeight = 1.5;
    } elseif ($position > 10 && $position <= 20) {
        $positionWeight = 1.2;
    } elseif ($position > 20 && $position <= 50) {
        $positionWeight = 0.6;
    } else {
        $positionWeight = 0.2;
    }

    return round(
        log($impressions + 1) * max(0.1, 1 - $ctr) * $positionWeight,
        4
    );
}
```

---

## 19. Prompt System

Use config first, then later allow DB overrides.

Create:

```text
config/seo_agent_prompts.php
```

Prompts required:

```text
system
keyword_classification
keyword_grouping
content_brief
content_draft
content_review
meta_rewrite
```

---

## 20. System Prompt

```text
You are an expert SEO strategist, content editor, and technical SEO analyst.

Your job is to help generate useful, people-first content based on real Google Search Console data.

Rules:
1. Do not create spam content.
2. Do not create thin pages.
3. Do not keyword stuff.
4. Do not invent fake facts, fake statistics, fake prices, fake reviews, fake guarantees, or fake product claims.
5. Do not generate content only to manipulate search rankings.
6. Always match the user's search intent.
7. Prefer improving existing pages over creating unnecessary new pages.
8. If a query is ambiguous, explain the ambiguity.
9. Return valid JSON only when JSON is requested.
10. Do not wrap JSON in markdown.
11. Do not include explanations outside the requested output format.

The goal is to produce high-quality content that helps real users and improves existing website quality.
```

---

## 21. Keyword Classification Prompt

```text
You are an SEO keyword classification engine.

Classify the keyword based on user intent.

Allowed intent values:
- informational
- commercial
- transactional
- navigational
- local
- support
- unknown

Allowed keyword_type values:
- primary_candidate
- secondary_candidate
- question
- brand
- product
- category
- problem
- comparison
- unknown

Return valid JSON only.
Do not wrap JSON in markdown.

Input:
Keyword: {{keyword}}
Target page: {{target_page}}
Clicks: {{clicks}}
Impressions: {{impressions}}
CTR: {{ctr}}
Average position: {{position}}

JSON structure:
{
  "keyword": "",
  "intent": "",
  "keyword_type": "",
  "should_create_new_content": true,
  "should_improve_existing_page": true,
  "reason": "",
  "risk": "low",
  "ai_confidence": 0.95
}
```

---

## 22. Keyword Grouping Prompt

```text
You are an SEO keyword clustering engine.

Your job is to group keywords that can be targeted by the same content page.

Rules:
1. Group keywords only when one page can satisfy the same search intent.
2. Do not group keywords only because they share one word.
3. Do not mix different intent types unless the same page naturally answers both.
4. Do not group product keywords with informational guide keywords unless the content type is a buying guide.
5. Do not group brand navigational keywords with generic informational keywords.
6. Prefer fewer, stronger groups over many weak groups.
7. Every group must have one primary keyword.
8. Secondary keywords must support the primary keyword.
9. Question keywords should be marked as FAQ when useful.
10. If a keyword does not belong with any group, return it in ungrouped_keywords.
11. Return valid JSON only.
12. Do not wrap JSON in markdown.

Input keywords:
{{keywords_json}}

Return this JSON structure:

{
  "groups": [
    {
      "group_name": "",
      "group_intent": "informational",
      "content_type": "blog_article",
      "recommended_action": "create_new_page",
      "primary_keyword": "",
      "target_page_url": "",
      "ai_summary": "",
      "ai_confidence": 0.95,
      "keywords": [
        {
          "keyword": "",
          "role": "primary",
          "relevance_score": 1.0,
          "priority_score": 0,
          "usage_instruction": ""
        }
      ]
    }
  ],
  "relationships": [
    {
      "keyword_a": "",
      "keyword_b": "",
      "relationship_type": "same_intent",
      "similarity_score": 0.9,
      "reason": ""
    }
  ],
  "ungrouped_keywords": [
    {
      "keyword": "",
      "reason": ""
    }
  ]
}
```

---

## 23. Content Brief Prompt

```text
You are an expert SEO content strategist.

Create a useful, people-first content brief from this keyword group.

Do not create spam content.
Do not keyword stuff.
Do not create thin content.
Do not invent facts.
Do not recommend creating a new page if the better solution is improving the existing target page.

Use the Google Search Console data to understand the opportunity:
- High impressions with low CTR may mean title/meta improvement.
- Position 4 to 10 may mean the page needs better relevance and content depth.
- Position 10 to 20 may mean the page needs stronger content and internal links.
- Very low impressions may not deserve content generation yet.

Return valid JSON only.
Do not wrap JSON in markdown.

Input keyword group:
{{keyword_group_json}}

JSON structure:
{
  "title": "",
  "slug": "",
  "meta_title": "",
  "meta_description": "",
  "h1": "",
  "primary_keyword": "",
  "secondary_keywords": [],
  "faq_keywords": [],
  "search_intent": "",
  "content_type": "",
  "recommended_action": "improve_existing_page",
  "content_angle": "",
  "outline": [
    {
      "heading": "",
      "points": []
    }
  ],
  "must_answer_questions": [],
  "internal_link_suggestions": [],
  "seo_notes": [],
  "quality_warnings": []
}
```

---

## 24. Content Draft Prompt

```text
You are an expert SEO editor and ecommerce content writer.

Write a complete, useful, people-first content draft based on the approved brief.

Rules:
- Write for humans first.
- Do not keyword stuff.
- Do not invent fake facts.
- Do not invent fake prices.
- Do not invent fake reviews.
- Do not invent fake product guarantees.
- Do not make medical, legal, or financial claims unless source data is provided.
- Use clear H2 and H3 structure.
- Include practical explanations.
- Include examples only when they are safe and generic.
- Include FAQ only if it genuinely helps the user.
- Keep paragraphs readable.
- Use HTML output.
- Return valid JSON only.
- Do not wrap the JSON in markdown.

Approved brief:
{{brief_json}}

JSON structure:
{
  "title": "",
  "slug": "",
  "meta_title": "",
  "meta_description": "",
  "html": "",
  "plain_text": "",
  "faq": [
    {
      "question": "",
      "answer": ""
    }
  ],
  "internal_link_suggestions": [],
  "quality_checks": {
    "keyword_stuffing_risk": "low",
    "thin_content_risk": "low",
    "needs_human_review": true
  }
}
```

---

## 25. Content Review Prompt

```text
You are a strict SEO content quality reviewer.

Review the AI-generated draft before publication.

Check for:
- Thin content
- Keyword stuffing
- Fake claims
- Duplicate intent
- Wrong search intent
- Over-optimization
- Missing user questions
- Weak title
- Weak meta description
- Unsafe claims
- Bad ecommerce UX

Return valid JSON only.
Do not wrap JSON in markdown.

Draft:
{{draft_json}}

Original brief:
{{brief_json}}

JSON structure:
{
  "approved": false,
  "score": 0,
  "problems": [],
  "required_fixes": [],
  "optional_improvements": [],
  "final_recommendation": ""
}
```

---

## 26. SEO Prompt Service

Create:

```text
app/Services/SeoPromptService.php
```

Required methods:

```php
public function systemPrompt(): string;

public function keywordClassificationPrompt(array $data): string;

public function keywordGroupingPrompt(array $keywords): string;

public function contentBriefPrompt(array $keywordGroup): string;

public function contentDraftPrompt(array $brief): string;

public function contentReviewPrompt(array $draft, array $brief): string;

private function render(string $promptKey, array $variables): string;
```

Requirements:

1. Load prompt from DB if active prompt exists.
2. Fallback to config prompt.
3. Replace variables using `{{variable_name}}`.
4. Return final prompt string.

---

## 27. LLM Content Service

Create:

```text
app/Services/LlmContentService.php
```

Required methods:

```php
public function classifyKeyword(array $keywordData): array;

public function groupKeywords(array $keywords): array;

public function generateContentBrief(array $keywordGroup): array;

public function generateDraft(array $brief): array;

public function reviewContent(array $draft, array $brief): array;

private function sendJsonRequest(string $userPrompt): array;
```

Requirements:

1. Use OpenAI-compatible chat completion format.
2. Send system prompt as role `system`.
3. Send task prompt as role `user`.
4. Request JSON output when provider supports it.
5. Parse JSON safely.
6. Throw clear exception if JSON is invalid.
7. Log raw response only in safe debug mode.

---

## 28. Keyword Grouping Service

Create:

```text
app/Services/KeywordGroupingService.php
```

Required methods:

```php
public function getCandidateKeywords(int $siteId, int $limit = 50): array;

public function groupCandidates(int $siteId): array;

public function storeGroups(int $siteId, array $llmOutput): void;
```

Candidate keyword selection rules:

```text
1. total_impressions >= 50
2. avg_position between 4 and 50
3. not already assigned to an approved/published group
4. order by priority_score desc
5. batch size from SEO_GROUPING_BATCH_SIZE
```

---

## 29. Store Keyword Groups

When LLM returns groups:

1. Find primary keyword by exact normalized match.
2. Create `seo_keyword_groups`.
3. Insert members into `seo_keyword_group_keywords`.
4. Insert relationships into `seo_keyword_relationships`.
5. Mark group status as `review_needed`.
6. Log action to `seo_audit_logs`.

Important:

Do not trust the LLM blindly. Validate:

```text
1. Keyword exists in database.
2. Primary keyword exists inside group keywords.
3. Relevance scores are between 0 and 1.
4. AI confidence is between 0 and 1.
5. Enum values are valid.
6. Invalid records should be skipped or marked for review.
```

---

## 30. Commands

Create these commands:

```bash
php artisan seo:import-gsc {site_id} {--date=}
php artisan seo:aggregate-keywords {site_id} {--days=30}
php artisan seo:group-keywords {site_id} {--limit=50}
php artisan seo:generate-brief {keyword_group_id}
php artisan seo:generate-draft {brief_id}
php artisan seo:review-draft {draft_id}
```

---

## 31. Jobs

Create these jobs:

```text
ImportGscKeywordsJob
AggregateSeoKeywordsJob
GroupSeoKeywordsJob
GenerateContentBriefJob
GenerateContentDraftJob
ReviewContentDraftJob
```

Each job must:

1. Use `ShouldQueue`.
2. Use retries.
3. Use timeout.
4. Log failure to `seo_audit_logs`.
5. Not duplicate existing data.

---

## 32. Scheduler

In `routes/console.php` or Console Kernel, schedule:

```php
Schedule::command('seo:import-gsc 1')
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::command('seo:aggregate-keywords 1 --days=30')
    ->dailyAt('03:30')
    ->withoutOverlapping();

Schedule::command('seo:group-keywords 1 --limit=50')
    ->dailyAt('04:00')
    ->withoutOverlapping();
```

Server cron:

```bash
* * * * * cd /var/www/seo-ai-agent && php artisan schedule:run >> /dev/null 2>&1
```

---

## 33. Content Brief Generation Flow

Input:

```text
keyword_group_id
```

Steps:

1. Load keyword group.
2. Load group keywords.
3. Build keyword group JSON.
4. Send to LLM using content brief prompt.
5. Validate JSON.
6. Store in `seo_content_briefs`.
7. Mark group as `brief_generated`.
8. Log action.

---

## 34. Content Draft Generation Flow

Input:

```text
brief_id
```

Steps:

1. Load brief.
2. Ensure brief status is `approved`.
3. Send brief JSON to LLM.
4. Validate JSON.
5. Store in `seo_content_drafts`.
6. Mark draft status as `needs_review`.
7. Mark group as `draft_generated`.
8. Log action.

---

## 35. Content Review Flow

Input:

```text
draft_id
```

Steps:

1. Load draft.
2. Load related brief.
3. Send both to review prompt.
4. Validate JSON.
5. Store review result in `quality_checks`.
6. If approved by AI, keep status as `needs_review`.
7. Never auto-publish.
8. Human approval is still required.

---

## 36. Human Review Rules

Do not auto-publish AI content.

Every draft must pass:

```text
1. AI review
2. Human review
3. Manual approval
4. Optional publish action
```

The admin user must be able to:

```text
Approve group
Reject group
Approve brief
Reject brief
Approve draft
Reject draft
Publish draft
```

---

## 37. Permissions

Use roles:

```text
super_admin
seo_manager
seo_editor
viewer
```

Permissions:

```text
access admin panel
view seo keywords
manage seo keywords
view keyword groups
manage keyword groups
generate briefs
generate drafts
review drafts
approve drafts
publish drafts
manage prompts
manage google connections
```

---

## 38. Publishing

Publishing is optional in v1.

Recommended v1:

```text
Generate draft only
Human copies draft manually
No auto-publish
```

Recommended v2:

```text
Publish to WordPress via REST API
Publish to Magento CMS page
Publish to Magento blog extension
Publish to category description
Publish to product FAQ block
```

Never publish automatically without approval.

---

## 39. Logs and Error Handling

Log these actions:

```text
google_connected
gsc_import_started
gsc_import_finished
gsc_import_failed
keywords_aggregated
keyword_grouping_started
keyword_grouping_finished
keyword_grouping_failed
brief_generated
draft_generated
draft_reviewed
draft_approved
draft_rejected
draft_published
prompt_updated
```

Use `seo_audit_logs`.

---

## 40. Validation Rules

### 40.1 LLM JSON Validation

Every LLM response must be validated.

Reject response if:

```text
1. JSON is invalid.
2. Required fields are missing.
3. Enum value is invalid.
4. Score is outside allowed range.
5. Primary keyword does not exist.
6. Draft HTML is empty.
7. Content tries to invent fake facts.
```

---

### 40.2 Keyword Group Validation

Rules:

```text
1. Group must have at least 1 keyword.
2. Group must have exactly 1 primary keyword.
3. Primary keyword must exist in seo_keywords.
4. Secondary keywords must exist in seo_keywords.
5. Relevance score must be 0 to 1.
6. AI confidence must be 0 to 1.
```

---

### 40.3 Brief Validation

Rules:

```text
1. title required
2. primary_keyword required
3. outline required
4. content_type required
5. recommended_action required
6. must_answer_questions must be array
```

---

### 40.4 Draft Validation

Rules:

```text
1. title required
2. html required
3. meta_title required
4. meta_description required
5. plain_text required or generated from html
6. quality_checks required after review
```

---

## 41. Security Requirements

1. Do not expose Google OAuth tokens in admin tables unless masked.
2. Encrypt tokens if possible.
3. Do not show full LLM API keys.
4. Use policies for all admin resources.
5. Do not allow non-admin users to access Filament.
6. Rate-limit manual AI generation actions.
7. Log prompt changes.
8. Escape rendered draft preview safely.
9. Do not execute HTML from AI output directly in admin without sanitization.
10. Store original AI output only if debug mode is enabled.

---

## 42. Performance Requirements

1. Import GSC data in batches.
2. Use queue jobs for LLM calls.
3. Do not group thousands of keywords in one LLM request.
4. Use 30 to 100 keywords per grouping batch.
5. Add indexes on metrics, keyword hash, group status, and opportunity score.
6. Use pagination in Filament tables.
7. Avoid loading all related keywords in list views.

---

## 43. Testing Requirements

Create tests for:

```text
SeoKeywordNormalizerTest
SeoScoreCalculatorTest
KeywordGroupingServiceTest
LlmJsonParserTest
GoogleSearchConsoleServiceTest
ContentBriefGenerationTest
ContentDraftGenerationTest
```

Minimum test cases:

```text
1. Keyword normalization works.
2. Same keyword produces same hash.
3. Opportunity score ranks high-impression low-CTR query higher.
4. Invalid LLM JSON throws exception.
5. Keyword group cannot save without primary keyword.
6. Draft cannot be generated from unapproved brief.
7. Google token refresh updates token row.
```

---

## 44. Development Milestones

### Milestone 1 — Laravel Admin Starter

Deliver:

```text
Laravel installed
Filament installed
Admin user creation works
Spatie permissions installed
Basic dashboard works
```

Acceptance:

```text
/admin opens
Admin login works
User can access Filament
Non-admin user cannot access Filament
```

---

### Milestone 2 — Database and Models

Deliver:

```text
All SEO tables migrated
Models created
Relationships created
Seed basic prompts
```

Acceptance:

```text
php artisan migrate:fresh --seed works
Filament resources can list/create/edit records
```

---

### Milestone 3 — Google Search Console OAuth

Deliver:

```text
Google connect route
Google callback route
Token storage
Site list import
```

Acceptance:

```text
Admin can connect Google account
Admin can list Search Console sites
Selected site is stored in gsc_sites
```

---

### Milestone 4 — Keyword Import

Deliver:

```text
seo:import-gsc command
GSC data stored in gsc_keyword_metrics
Pagination works
Audit logs created
```

Acceptance:

```text
php artisan seo:import-gsc 1 --date=YYYY-MM-DD imports rows
Duplicate run does not create duplicates
```

---

### Milestone 5 — Keyword Aggregation

Deliver:

```text
seo:aggregate-keywords command
seo_keywords populated
priority_score calculated
main_page_url selected
```

Acceptance:

```text
Aggregated keywords appear in admin
Scores appear correctly
```

---

### Milestone 6 — LLM Prompt System

Deliver:

```text
Prompt config
Prompt DB table
SeoPromptService
LlmContentService
JSON parser
```

Acceptance:

```text
Prompt can be edited in admin
LLM returns valid parsed JSON
Invalid JSON fails clearly
```

---

### Milestone 7 — Keyword Grouping

Deliver:

```text
seo:group-keywords command
LLM grouping prompt
Groups stored
Group members linked
Relationships stored
```

Acceptance:

```text
Related keywords are grouped
Unrelated keywords are not forced into groups
Group appears in admin with member keywords
```

---

### Milestone 8 — Content Briefs

Deliver:

```text
Generate brief action
seo:generate-brief command
Brief stored
Brief approval flow
```

Acceptance:

```text
Admin can generate brief from approved group
Admin can approve/reject brief
```

---

### Milestone 9 — Content Drafts

Deliver:

```text
Generate draft action
seo:generate-draft command
Draft stored
Draft preview in admin
```

Acceptance:

```text
Draft only generates from approved brief
Draft status is needs_review
Admin can approve/reject draft
```

---

### Milestone 10 — AI Review

Deliver:

```text
seo:review-draft command
Quality checks stored
Review result visible in admin
```

Acceptance:

```text
Draft quality review identifies problems
Human approval is still required
```

---

### Milestone 11 — Optional Publishing

Deliver only if requested:

```text
WordPress publisher
Magento CMS publisher
Publish audit logs
Published URL storage
```

Acceptance:

```text
Approved draft can be published manually
Published URL is stored
Publishing action is logged
```

---

## 45. Agent Execution Instructions

When implementing this project, follow these rules:

1. Do not skip migrations.
2. Do not hardcode prompts only in services; use config and allow DB override.
3. Do not auto-publish generated content.
4. Do not store multiple keywords in comma-separated fields.
5. Use relationship tables.
6. Use queue jobs for slow AI calls.
7. Add validation before saving LLM output.
8. Add Filament resources for every major table.
9. Add audit logs for every important action.
10. Keep each service small and testable.
11. Do not mix Google API code inside controllers.
12. Do not mix LLM prompt building inside commands.
13. Use commands for CLI workflows.
14. Use jobs for async workflows.
15. Use services for business logic.
16. Use actions for state transitions.

---

## 46. First Implementation Order

Follow this exact order:

```text
1. Install Laravel
2. Install Filament
3. Install Spatie Permission
4. Create admin user
5. Create migrations
6. Create models
7. Create Filament resources
8. Create prompts config
9. Create prompt seeder
10. Create Google OAuth service
11. Create Search Console service
12. Create import command
13. Create keyword aggregation command
14. Create score calculator
15. Create LLM service
16. Create keyword grouping service
17. Create grouping command
18. Create brief generation
19. Create draft generation
20. Create review generation
21. Add admin actions
22. Add dashboard widgets
23. Add tests
24. Add scheduler
25. Add README setup instructions
```

---

## 47. Expected Final App Capabilities

At the end, the app must allow an admin to:

```text
1. Log in to /admin
2. Connect Google Search Console
3. Select a site
4. Import keyword data
5. View raw keyword metrics
6. View aggregated keywords
7. Classify keywords
8. Group related keywords
9. Review keyword groups
10. Generate content briefs
11. Approve content briefs
12. Generate content drafts
13. Review content quality
14. Approve or reject drafts
15. Optionally publish approved content
16. View audit logs
17. Edit prompts
```

---

## 48. Definition of Done

The project is not complete until:

```text
php artisan migrate:fresh --seed works
php artisan test works
/admin login works
Google OAuth works
GSC import works
Keyword aggregation works
Keyword grouping works
Content brief generation works
Content draft generation works
Draft review works
Human approval is enforced
Audit logs are written
No AI draft is auto-published
README explains setup
```

---

## 49. README Setup Commands

The final repository must include these setup commands:

```bash
git clone <repo-url> seo-ai-agent
cd seo-ai-agent

cp .env.example .env

composer install

php artisan key:generate

php artisan migrate --seed

php artisan make:filament-user

php artisan queue:table
php artisan migrate

php artisan serve
```

Queue worker:

```bash
php artisan queue:work
```

Scheduler cron:

```bash
* * * * * cd /var/www/seo-ai-agent && php artisan schedule:run >> /dev/null 2>&1
```

---

## 50. Notes for Claude Code / Codex

The agent should implement one milestone at a time.

After each milestone:

1. Run migrations.
2. Run tests.
3. Check PHP syntax.
4. Check Laravel routes.
5. Check Filament admin loads.
6. Commit changes.

Suggested commit style:

```text
feat: install filament admin starter
feat: add seo database schema
feat: add google search console oauth
feat: import gsc keyword metrics
feat: aggregate seo keywords
feat: add llm prompt system
feat: group related seo keywords
feat: generate content briefs
feat: generate content drafts
feat: add ai draft review
```

Do not move to the next milestone if the current one fails.
