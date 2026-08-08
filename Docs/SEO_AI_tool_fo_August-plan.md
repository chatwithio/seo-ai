# August SEO AI Tool Implementation Plan

Source: `Docs/SEO_AI_tool_fo_August.pdf`

## PDF Requirements

| PDF page | Planned result |
| --- | --- |
| 1 | Cover page; no implementation work |
| 2 | A dashboard card for keywords first discovered during the last seven days, with the exact data-update time |
| 3 | An Articles navigation badge showing articles created since the current user's last Articles visit |
| 4-5 | A tenant-scoped Improve Content list and detail workflow that can generate a complete rewrite draft |
| 6 | One automatic AI featured image for generated articles, with upload, retry, regenerate, and skip controls |
| 7 | Production Wix publishing and a Mono content-generation prototype |

## 1. Data Foundation

- Add forward-only migrations instead of editing migrations that may already have run in production.
- Add `discovered_at` to keywords. Existing rows remain the deployment baseline; newly inserted aggregate rows receive the discovery time.
- Add `keywords_updated_at` to managed sites and update it after successful keyword aggregation.
- Add `articles_last_viewed_at` to users and initialize existing users at deployment time.
- Add direct site ownership, improvement-source fields, versioning, and featured-image metadata to articles.
- Add a tenant-owned content-improvements table containing the site, URL, 90-day performance, scan dates, AI recommendation, workflow state, and generated draft link.
- Add site publishing connections and per-article/per-version publication attempts.

## 2. Dashboard and Articles Badge

- Keep daily Search Console imports and present a rolling seven-day discovery view.
- Add a matching dashboard card titled **New keywords this week**.
- Show up to five keywords ordered by impressions, including site, clicks, impressions, and a Generate Content action.
- Show `Weekly view · Updated [date and time]` using the latest successful aggregation.
- Add a danger-colored Articles badge capped at `99+`.
- Count only articles created after the current user's `articles_last_viewed_at`.
- Clear the badge only for that user when the Articles list opens.

## 3. Improve Content

- Add `/admin/content-improvements` and `/admin/content-improvements/{record}` with a top-level **Improve Content** navigation item.
- Run a lightweight page-only Search Console scan for the latest 90 available days.
- Store the top 20 pages per active site, ranked by clicks, and refresh them weekly after keyword aggregation.
- Show URL, site, clicks, impressions, CTR, scan period, recommendation state, and a View Ideas action.
- Generate recommendations on demand and cache them for the current scan period.
- Fetch the current page with bounded time/size limits and extract its title, metadata, headings, and main text.
- Show the recommendation, reasoning, target keywords, and an Open Current Page link.
- Generate a complete improved article draft without changing the live page.
- Send the rewrite through the existing review workflow, associate it with its site/source URL, and prevent accidental duplicate drafts.

## 4. AI Featured Images

- Generate the image after the article text and before review/publishing.
- Use configurable `gpt-image-2`, medium-quality `1536x1024` output through `/v1/images/generations`.
- Build the prompt from the article title, keyword, site context, language, and summary; request an editorial image without text or invented logos.
- Decode the returned image and store it on a configurable public filesystem disk.
- Add image preview, upload replacement, alt-text editing, retry, regenerate, and skip controls to Articles.
- Record failures in the audit log, but allow publishing without an image.
- Add optional featured-image fields to the Content API, general webhook, WordPress webhook/email, and Wix payloads.

## 5. Wix Publishing

- Add a Wix tab after WordPress Webhook in `/admin/settings/publishing`.
- Configure one Wix-connected managed site per user account with enabled state, priority, encrypted API key, Wix site ID, Blog member ID, post status, and Test Connection action.
- Convert article HTML to Wix Ricos rich content before creating the Blog post.
- Add Wix to manual publishing, automatic fallback order, and multiple-channel publishing.
- Persist the external Wix post ID and URL so retries update the existing post instead of duplicating it.
- Publish normally when no image is available.

## 6. Mono Prototype

- Add a non-scheduled Mono Quick Creator service and `seo:mono-generate-example` command.
- Configure it with `MONO_API_BASE_URL`, `MONO_API_TOKEN`, and `MONO_TEMPLATE_ID`.
- Accept company, business type, services, language, tone, and audience options and call Mono's `/generate-content` endpoint.
- Store the returned example privately and never log the bearer token.
- Keep Mono out of customer publishing methods until Mono provides an existing-site article publish/update endpoint.

## 7. Publishing Reliability

- Track one publication attempt per article version and channel.
- With multiple-channel publishing disabled, try configured methods in priority order until one succeeds.
- With multiple-channel publishing enabled, deliver through every method and retry only failed methods.
- Increment the article version when publishable content or its featured image changes.
- Store channel, version, status, external ID/URL, error, request fingerprint, and timestamps.
- Preserve successful channel history and prevent duplicate external posts.

## Interfaces and Configuration

New commands:

- `seo:refresh-content-improvements {--user-id=} {--site-id=}`
- `seo:mono-generate-example`

New optional content response fields:

- `site_id`
- `source_url`
- `featured_image: { url, alt }`

New image configuration:

```env
OPENAI_IMAGE_MODEL=gpt-image-2
OPENAI_IMAGE_SIZE=1536x1024
OPENAI_IMAGE_QUALITY=medium
ARTICLE_IMAGE_DISK=public
```

New Mono configuration:

```env
MONO_API_BASE_URL=https://qc-api.yggdrasil.dev-mono.net/api/v1
MONO_API_TOKEN=
MONO_TEMPLATE_ID=
```

## Tests and Rollout

- Test keyword discovery, tenant isolation, ordering, and update timestamps.
- Test the per-user Articles badge, clearing behavior, and `99+` cap.
- Fake Search Console/page responses for 90-day ranking, failures, caching, and duplicate-draft prevention.
- Fake OpenAI image responses for decoding, storage, replacement, retry, and non-blocking failure.
- Fake Wix requests for authentication, Ricos conversion, draft/publish behavior, idempotent retries, and multiple channels.
- Fake Mono responses and missing-configuration errors.
- Correct the existing root-route test to expect the intentional `/admin` redirect.
- Run migrations, PHPUnit, syntax checks, and the frontend production build.
- In production, run forward migrations, ensure `storage:link` exists, rebuild cached configuration, restart workers, and verify scheduler operation.
- Smoke-test one AI image, one Wix draft, one Wix published post, and one Mono example before enabling Wix automatic publishing broadly.

## Agreed Defaults

- Daily keyword imports remain enabled; the dashboard shows a rolling seven-day view.
- Existing keywords are not marked new during deployment.
- Opening Articles clears the current user's badge.
- Content-improvement scans use the latest 90 days and create full rewrite drafts on demand.
- AI image failure does not prevent publishing.
- Wix uses manually entered credentials for one selected managed site per user account.
- Mono remains a content-generation prototype until its API exposes an existing-site publishing contract.

## Implementation Status — August 8, 2026

- Implemented and locally verified: forward migrations, keyword discovery card and timestamp, Articles badge, tenant-scoped Improve Content routes, 90-day scans, scan-period display, queued recommendation/rewrite/image work, rewrite uniqueness, featured-image controls and payloads, one Wix site per account, Wix Blog and Ricos connection checks, publication attempts, versioning, failed-channel retry filtering, Content API fields, and the Mono prototype.
- Automated checks completed: 9 PHPUnit tests with 14 assertions, PHP syntax checks, Pint, `git diff --check`, registered route/schedule checks, successful production frontend build, and an empty healthy database queue.
- Production-only verification still required: generate one real OpenAI image, connect and test one real Wix account, create one Wix draft and one published Wix post, run one Mono example with real credentials, and confirm the host cron invokes `php artisan schedule:run` every minute.
