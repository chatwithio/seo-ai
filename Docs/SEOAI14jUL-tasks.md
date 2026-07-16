# SEO AI Tasks from SEOAI14jUL.pdf

Source: `Docs/SEOAI14jUL.pdf` (10 pages)

## P0 - Core onboarding and import workflow

- [ ] **Make the dashboard quick-action links work**
  - "Review your top performing keywords" opens Search Keywords with the correct top-keyword view/filter.
  - "Discover high impression but low CTR keywords" opens Search Keywords with the correct opportunity view/filter.
  - "Create fresh content for your website" opens the correct content-generation workflow.
  - "Create your custom AI Agent" opens AI Instructions.
  - Acceptance: every link opens the intended page and applies the intended context or filters.

- [ ] **Add the SEO/Search Console help message to the Dashboard**
  - Display: "If you do not know what SEO is or you do not have a Search Console account, please contact us."
  - Link the contact action to `https://chatwith.io/s/link-to-whatsapp`.
  - Acceptance: the message is visible, readable, and the WhatsApp link opens correctly.

- [ ] **Add a clear loading state while connecting and syncing sites**
  - Show "Connecting your sites..." immediately after Google connection/sync starts.
  - Add a spinner or progress indicator and disable duplicate clicks while running.
  - Show success, empty-result, and failure states instead of leaving the page unchanged.
  - Automatically refresh the Managed Sites table when syncing finishes.
  - Acceptance: the user always knows whether the system is waiting, working, finished, or failed.

- [ ] **Show live keyword-import progress**
  - Show "Importing keywords..." immediately after import starts.
  - Display the current site, completed sites, total sites, and imported keyword count when available.
  - Provide a direct link to Active Jobs for detailed status.
  - Show clear completion, no-keywords-found, partial-failure, and failure messages.
  - Acceptance: import progress remains visible until the background process actually finishes.

- [ ] **Remove the date field from keyword imports**
  - Remove the date-selection modal/field from both Managed Sites and Search Keywords import actions.
  - Automatically import the most recent complete Search Console dataset.
  - Display the imported reporting period and completion time after the import instead of asking the user for a date.
  - Acceptance: importing requires one click and never asks the user to choose a date.

- [ ] **Refresh Search Keywords automatically after import**
  - Poll or refresh the table when the import finishes.
  - Ensure newly imported keywords are immediately visible without a manual browser reload.
  - Preserve intentional user filters, but clearly warn when active filters hide imported rows and provide "Clear filters".
  - Show the last successful import time and number of imported/updated keywords.
  - Acceptance: after a successful import, the user can see the new data or a precise explanation of why no rows are visible.

## P0 - Default SEO results

- [ ] **Automatically identify top-performing keywords after import**
  - Rank imported keywords using clicks, impressions, CTR, and position.
  - Present a clear "Top Keywords" result for each managed site.
  - Acceptance: the result is generated automatically after a successful import and is reachable from the Dashboard.

- [ ] **Generate recommended website text for top-performing keywords**
  - Create practical text suggestions the user can add to the relevant existing page.
  - Associate every suggestion with its keyword and target page URL.
  - Acceptance: each selected top keyword has a usable recommendation, generation status, and destination page.

- [ ] **Automatically identify high-impression, low/zero-click opportunities**
  - Use explicit, configurable thresholds for high impressions and low/zero clicks or CTR.
  - Present these opportunities separately from top-performing keywords.
  - Acceptance: the opportunity list is generated automatically and the Dashboard link opens it correctly.

- [ ] **Generate recommended website text for opportunity keywords**
  - Produce text intended to improve the relevant existing page or recommend a new page when appropriate.
  - Associate every suggestion with the keyword, target URL, and recommended action.
  - Acceptance: every selected opportunity has understandable, actionable text or a clearly reported generation failure.

## P1 - Search Keywords usability

- [ ] **Repair all Search Keywords filters**
  - Verify site, intent, clicks, impressions, CTR, position, and any active/inactive filters independently and in combination.
  - Correct contradictory behavior such as enabling "Has Clicks" while expecting zero-click opportunities.
  - Ensure filter state, URL state, result count, and table rows agree.
  - Add a prominent "Clear filters" action when filters produce no results.
  - Acceptance: each filter returns the expected tenant-owned records and combined filters use predictable logic.

- [ ] **Add Generate Content beside every keyword**
  - Add a visible "Generate Content" row action next to each keyword.
  - Preselect the keyword, site, target URL, and known search intent in the generation form.
  - Prevent duplicate submissions while a generation job is running.
  - Acceptance: a user can start content generation directly from a keyword row and track it in Active Jobs.

## P1 - Grouped Keywords usability

- [ ] **Explain what Grouped Keywords are and why they are useful**
  - Add short page guidance explaining that related keywords are grouped into one content topic/page plan.
  - Explain primary keyword, intent, content type, recommended action, and status in plain language.
  - Acceptance: the purpose and next step are understandable without SEO expertise.

- [ ] **Make each keyword group inspectable and actionable**
  - Show the number of keywords in each group.
  - Let the user open a group and see all included keywords and their metrics.
  - Add a clear action to create a Content Plan or Article from the group.
  - Show grouping progress and report groups created, keywords grouped, skipped keywords, and failures.
  - Acceptance: after grouping, the user can see what changed and immediately use the result.

## P1 - Speed and system feedback

- [ ] **Reduce end-to-end processing time**
  - Profile Google site sync, keyword import, aggregation, grouping, and content generation separately.
  - Avoid repeated API requests and unnecessary sequential processing.
  - Process independent sites concurrently within Google API and server limits.
  - Keep each user's work tenant-scoped and avoid one user's import blocking another user.
  - Acceptance: record baseline and improved timings for connection, first visible site, first visible keyword, and complete workflow.

- [ ] **Use consistent progress feedback across long-running actions**
  - Standardize queued, running, progress, completed, completed-with-no-results, partial-failure, failed, and cancelled states.
  - Surface the same state on the originating page and Active Jobs.
  - Include timestamps and meaningful error messages.
  - Acceptance: no long-running action appears to do nothing after the user clicks it.

## P2 - End-to-end verification

- [ ] **Test the complete first-user journey**
  - Connect Google account.
  - Sync accessible sites.
  - Import the latest keywords without choosing a date.
  - Confirm live progress and automatic table refresh.
  - Confirm top-keyword and low/zero-click opportunity results.
  - Generate suggested website text.
  - Group keywords and inspect group membership.
  - Generate content directly from a keyword and from a group.
  - Confirm all Dashboard quick links and filters.
  - Acceptance: complete the workflow without database or command-line intervention and document actual timings and failures.

## Suggested implementation order

1. Dashboard links and help message.
2. Site-sync loading/progress feedback.
3. One-click latest keyword import and live progress.
4. Automatic refresh and filter repairs.
5. Default top-keyword and opportunity analysis.
6. Website-text recommendations.
7. Group explanation, inspection, and next actions.
8. Generate Content keyword-row action.
9. Performance profiling and optimization.
10. Full end-to-end acceptance test.
