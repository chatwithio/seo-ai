# SEO AI Tool — 27 July — Page-by-Page Tasks

Source: `Docs/SEO_AI_tool_27Jul.pdf`

Status:

- `[x]` Done
- `[~]` Partly done
- `[ ]` Not done

## Page 1 — Dashboard feedback

- No development task. The page says: “This is very good!!”

## Page 2 — Automatic keyword imports

- [x] Import keywords automatically without waiting for a user to click Import.

Verified current status:

- All active sites are scheduled for keyword import every day at 02:00.
- The Laravel scheduler is running every minute.
- The scheduled queue worker is running.
- The 26 July 02:00 import callback completed.
- The latest site import is `2026-07-26 02:00:05`.
- There are no pending or failed queue jobs.

## Page 3 — Simplify AI Instructions

- [x] Explain how AI Instructions work.
- [x] Simplify the AI Instructions interface.

Current status:

- The page contains a plain-language workflow explanation.
- Technical fields are inside a collapsed Advanced Settings section.
- Fixed system instructions can be edited but not created or deleted from the interface.

## Page 4 — Search Keyword filters

- [x] Fix the Search Keywords filters.

Current status:

- Site and intent filters are tenant-scoped.
- Has Clicks and Has Impressions filters are implemented.
- Top Impressions and High Impressions, Low Clicks filters use separate query scopes.
- Active filters have a visible “Clear all filters” button.
- Dashboard links open the matching Search Keywords filter.

## Page 5 — Email automation

- [x] Review and implement the separate email automation specification.
- [x] Send the welcome email after registration.
- [x] Add weekly SEO activity and content-idea emails.
- [x] Add editable email templates.
- [x] Add per-account email preferences under `/admin/settings/email`.

## Page 6 — Spanish content generation

- [x] Ensure Spanish selection creates Spanish content instead of English content.

Current status:

- Language is passed into brief and draft generation.
- Spanish output is checked and retried if it appears to be English.
- Site-wide language defaults are available under `/admin/settings/content`.

## Page 7 — Footer links

- [x] A shared footer is displayed on login, registration, and admin pages.
- [x] The footer links are exactly:
  - `https://tochat.be`
  - `https://social.tochat.be`
  - `https://seoai.tochat.be`

Verified current status:

- All three links render on the login and admin pages.

## Page 8 — Generated-content service

- [x] Create a service that lets another website consume generated content.
- [x] Add an API to list publishable content.
- [x] Add an API that returns one unread article and marks it read.
- [x] Add general webhook delivery.

External verification still required:

- Test delivery against the customer’s real receiving website when its endpoint is available.

## Page 9 — WordPress integration options

- [x] Add WordPress webhook publishing.
- [x] Add WordPress post-by-email publishing.
- [x] Add configurable WordPress post status.
- [x] Put publishing configuration under `/admin/settings/publishing`.

External verification still required:

- Test automatic post creation against the customer’s real WordPress website.

## Page 10 — Video notes

### Minute 00:57 — Keywords are not imported automatically

- [x] Automatic daily imports are implemented and currently executing.

### Minute 01:59 — Dashboard keyword tables show the same data

- [x] Make the first table show Top Keywords by impressions.
- [x] Make the second table show High Impressions, Low Clicks opportunities.

Current status:

- The two tables use different model scopes and different filtering rules.

## Page 11 — New SEO content draft fails

- [x] The New SEO Content Draft page loads successfully.
- [x] Manual standalone drafts can be created without a keyword group or content plan.
- [x] Existing keyword groups and content plans can be selected from tenant-scoped fields.
- [x] Manual drafts can be edited, listed, published, and consumed through the Content API.

Implemented fix:

- `keyword_group_id` and `brief_id` now support null values for standalone articles.
- The hidden empty fields were replaced with optional Keyword Group and Content Plan selectors.
- Selecting a content plan automatically synchronizes its keyword group.
- The model automatically creates a slug and plain-text version for manually entered content.
- Relationship selections are limited to the current user.

Verified current status:

- A standalone article was created and edited successfully.
- Linked AI-generated drafts still preserve their content plan and keyword group.
- The create and edit pages both return HTTP 200.
- A standalone article was published through a mocked general webhook.
- A standalone article was returned successfully through the Content API.

## Page 12 — Repeated draft failure

- This page repeats Page 11 and does not add a separate task.

## Remaining implementation tasks

All internal implementation tasks from this PDF are complete.

External acceptance tests remain:

1. Verify general webhook delivery against the customer’s real receiving endpoint.
2. Verify automatic WordPress post creation against the customer’s real WordPress endpoint.
