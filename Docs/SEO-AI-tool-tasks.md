# SEO AI Tool Tasks — Page by Page

Source: `Docs/SEO_AI_tool.pdf`

Status:

- `[x]` Done
- `[ ]` Not done

## Page 1 — Feedback

- No development task. The page says: “This is very good!!”

## Page 2 — Automatic keyword imports

- [x] Import keywords automatically without requiring the user to click Import Keywords.

Current status: the daily import schedule, once-per-minute server cron, and scheduled queue worker are active. The scheduler and queue worker launch were verified from `storage/logs/scheduler.log`.

## Page 3 — Simplify AI Instructions

- [x] Explain how AI Instructions work.
- [x] Make the AI Instructions form simpler and easier to understand.

Current status: the page now explains the workflow, uses plain-language labels, keeps technical fields in a collapsed Advanced Settings section, and removes create/delete controls for the fixed system instructions.

## Page 4 — Keyword filters

- [x] Fix the Search Keywords filters.

Current status: the filters, Dashboard filter links, active-filter indicators, and clear-filter controls have been fixed.

## Page 5 — Emails and automation

- [x] Review the supplied email automation specification.
- [x] Send the welcome email after registration.
- [x] Add weekly SEO activity and SEO content-idea emails.
- [x] Add editable email templates and per-account weekly email preferences under Admin → Settings → Email Templates.

Current status: the templates, sending service, Monday schedule, and MailCatcher SMTP delivery are implemented. A real welcome email was captured successfully by MailCatcher.

## Page 6 — Spanish content generation

- [x] Fix content generation so selecting Spanish produces Spanish content.
- [x] Verify Spanish with a real generated brief and article.

Current status: the selected language is enforced for the brief, metadata, title, and article. Spanish output is checked and retried once if English is detected. A real 500-word production-path generation returned a Spanish title and article with 107 Spanish markers and no English markers.

## Page 7 — Footer links

- [x] Add a footer linking ChatWithSEO, ChatWith.io, and Tochat.be.
- [x] Show the footer on the login and registration pages.

## Page 8 — Generated-content publishing service

- [x] Create a general website webhook through which another website can consume AI-generated content.
- [x] Add an authenticated pull API for listing content and consuming one unread article per request.
- [x] Allow an article to be delivered from the Articles page.
- [ ] Verify delivery against the customer's real receiving website.

## Page 9 — WordPress publishing integration

- [x] Add a WordPress webhook publishing method.
- [x] Add WordPress post-by-email publishing.
- [x] Define and send the article title, body, metadata, and publishing status.
- [x] Add both WordPress methods under Admin → Settings → Publishing.
- [ ] Verify automatic post creation against the customer's real WordPress website.
