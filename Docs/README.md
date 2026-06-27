# SEO AI Agent - Technical Documentation

Welcome to the technical documentation for the **SEO AI Agent** project. This Laravel 11 based application is designed to automate the process of extracting, processing, and generating high-quality SEO content using Google Search Console data and OpenAI's Large Language Models (LLMs).

## Table of Contents
1. [System Architecture](#1-system-architecture)
2. [Database Schema](#2-database-schema)
3. [Services & Integrations](#3-services--integrations)
4. [Console Commands & Jobs](#4-console-commands--jobs)
5. [Task Scheduler](#5-task-scheduler)
6. [Admin Panel (Filament)](#6-admin-panel-filament)
7. [Environment & Setup](#7-environment--setup)

---

## 1. System Architecture

The application is built on the standard **Laravel 11** architecture, utilizing **Filament v3** for a robust admin panel. 

The core flow of the application is:
1. **Connect to Google Search Console (GSC)** via OAuth.
2. **Import SEO Data** (Keywords, impressions, clicks) into local storage.
3. **Aggregate & Normalize** metrics.
4. **Group Keywords** using LLM clustering.
5. **Generate SEO Content Briefs** dynamically.
6. **Generate Content Drafts** from the briefs.
7. **Review & Audit** generated drafts against Google Helpful Content guidelines using AI.

---

## 2. Database Schema

The database relies on several tightly-coupled Eloquent models to represent the SEO workflow:

- **`GscSite`**: Represents a connected Google Search Console property.
- **`GoogleOauthToken`**: Stores access and refresh tokens for Google OAuth.
- **`GscKeywordMetric`**: Stores raw, daily keyword metrics (clicks, impressions, CTR, position) fetched from GSC.
- **`SeoKeyword`**: Aggregated and normalized keywords.
- **`SeoKeywordGroup`**: Clusters of keywords targeting a specific search intent or topic, determined by the LLM.
- **`SeoKeywordGroupKeyword`**: Pivot table associating `SeoKeyword`s to an `SeoKeywordGroup` along with their specific role (e.g., primary, secondary, semantic).
- **`SeoContentBrief`**: Generated content strategies (H1, meta tags, outlines) for a `SeoKeywordGroup`.
- **`SeoContentDraft`**: Final generated HTML output based on a Brief.
- **`AiPrompt`**: Configurable system/user prompts for the LLM.
- **`SeoAuditLog`**: Centralized auditing table tracking the success/failure of GSC imports and LLM calls.

---

## 3. Services & Integrations

The system decouples logic into dedicated Service classes located in `app/Services/`.

### Google Search Console
- **`GoogleSearchConsoleService`**: Initializes the Google Client using stored OAuth tokens. Automatically refreshes expired tokens. Exposes methods to list verified domains and fetch Search Analytics (`fetchSearchAnalyticsRows`).

### Open AI (LLM)
- **`LlmContentService`**: A wrapper around Guzzle HTTP configured to communicate with the OpenAI API. It handles structured outputs via JSON schema enforcement.
- **`SeoPromptService`**: Interacts with the `AiPrompt` database table to resolve dynamic `{placeholders}` into fully-fledged prompts for the LLM.

### Processing
- **`SeoKeywordNormalizer`**: Standardizes keywords by removing special characters, excessive whitespace, and converting to lowercase. It generates SHA-256 hashes to prevent duplicate entries.
- **`KeywordGroupingService`**: Extracts the highest-opportunity orphaned keywords and feeds them into the LLM to cluster them logically into `SeoKeywordGroup` entities.
- **`SeoContentGenerationService`**: Orchestrates the 3-step content pipeline:
  1. `generateBrief()`
  2. `generateDraft()`
  3. `reviewDraft()`

---

## 4. Console Commands & Jobs

The application makes heavy use of Laravel Artisan commands to run backend processes.

| Command | Description |
|---|---|
| `seo:seed-prompts` | Seeds the database with default AI prompts from `config/seo_agent_prompts.php`. |
| `seo:import-gsc {site_id}` | Connects to GSC and paginates over analytic rows for the past N days, storing them in `gsc_keyword_metrics`. |
| `seo:aggregate-keywords {site_id}` | Aggregates daily raw metrics into the master `seo_keywords` table. |
| `seo:group-keywords {site_id}` | Triggers the LLM to cluster the top N unassigned keywords into Groups. |
| `seo:generate-brief {group_id}` | Generates an SEO content brief. |
| `seo:generate-draft {brief_id}` | Writes an HTML article based on the brief. |
| `seo:review-draft {draft_id}` | Submits the draft for AI QA review. |

**Jobs:**
- **`ImportGscKeywordsJob`**: A queueable job that wraps the `seo:import-gsc` command, ensuring that large data pulls don't block the HTTP request lifecycle.

---

## 5. Task Scheduler

The Laravel task scheduler runs automatically via the server cron. Configurations are stored in `routes/console.php`.

- **02:00 AM**: Dispatches `ImportGscKeywordsJob` for all active `GscSite` properties.
- **04:00 AM**: Runs the `seo:aggregate-keywords` command for all active properties.

---

## 6. Admin Panel (Filament)

The UI is entirely built on **FilamentPHP v3**. 

### Authentication & Authorization
- **Spatie Laravel Permission** handles roles (`super_admin`, `seo_manager`, `seo_editor`, `viewer`).
- Users must log in via `/admin`.
- The default super admin is `admin@chatwithseo.ai` (Password: `password`).

### Resources
Filament Resources exist for all major models, allowing administrators to manually inspect GSC Sites, Keywords, Groups, Briefs, Drafts, and AI Prompts.

---

## 7. Environment & Setup

Ensure the following variables are configured correctly in `.env`:

```env
# Database Settings
DB_CONNECTION=mysql
DB_DATABASE=chatwithseo_ai
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Google Settings
GOOGLE_CLIENT_ID="[Enter Google Client ID]"
GOOGLE_CLIENT_SECRET="[Enter Google Client Secret]"
GOOGLE_REDIRECT_URI="${APP_URL}/google/callback"

# LLM / OpenAI Settings
LLM_API_URL="https://api.openai.com/v1/chat/completions"
LLM_API_KEY="[Enter OpenAI API Key]"
LLM_MODEL="gpt-4o"
```

---

## 8. Obtaining API Keys

### Google Search Console OAuth Credentials
To fetch data from Google Search Console, you must create an OAuth application in the Google Cloud Console.

1. Go to the [Google Cloud Console](https://console.cloud.google.com/).
2. **Create a new project** (e.g., "SEO Agent App").
3. Navigate to **APIs & Services > Library** and enable the **Google Search Console API**.
4. Go to **APIs & Services > OAuth consent screen**.
   - Choose **External** (or Internal if using Google Workspace).
   - Fill out the App name, Support email, and Developer contact information.
   - Under Scopes, add the Search Console API scope: `https://www.googleapis.com/auth/webmasters.readonly`.
   - Add your test user emails (e.g., the Google account attached to your Search Console).
5. Go to **APIs & Services > Credentials**.
   - Click **Create Credentials > OAuth client ID**.
   - Select **Web application** as the application type.
   - Under **Authorized redirect URIs**, add exactly what matches your `.env` `GOOGLE_REDIRECT_URI` (e.g., `https://yourdomain.com/google/callback` or `http://localhost/google/callback` if testing locally).
   - Click **Create**.
6. Copy the **Client ID** and **Client Secret** generated and place them into your `.env` file under `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`.

### OpenAI API Key
The application relies on OpenAI's `gpt-4o` to cluster keywords and generate content.

1. Go to the [OpenAI Platform Dashboard](https://platform.openai.com/).
2. Log in or create an account.
3. Navigate to **API Keys** on the left menu (or under your profile settings).
4. Click **Create new secret key**. Give it a descriptive name like "SEO Agent".
5. Copy the generated secret key. Note that you will not be able to see it again once you close the modal.
6. Paste the key into your `.env` file under `LLM_API_KEY`. (Ensure you have set up billing on your OpenAI account to use the API).
