# Automation for SEO Emails — Page by Page

Source: `Docs/Automation for SEO Emails.pdf`

Status:

- `[x]` Done
- `[ ]` Not done
- `N/A` Not applicable to this SEO application

## Page 1 — Welcome email

- [x] Send a welcome email when a user creates an account.

## Page 2 — Welcome template

- [x] Add the supplied welcome message as an editable template.
- [x] Support account, login, support, and tutorial links through placeholders.

## Page 3 — Social weekly activity

- N/A — The application has no social-network modules or social activity data.

## Page 4 — Weekly SEO activity

- [x] Send a weekly account summary with keyword count, impressions, clicks, and article count.
- [x] Allow each account to enable or disable this email.

## Page 5 — Weekly SEO content ideas

- [x] Select high-impression keyword opportunities for each account.
- [x] Send up to six ideas with links back to Search Keywords.
- [x] Allow each account to enable or disable this email.

## Admin settings

- [x] Add Admin → Settings → Email Templates.
- [x] Make the Welcome, Weekly SEO Activity, and Weekly SEO Ideas subjects and bodies editable.
- [x] Add template activation controls and document the available placeholders.

## Production delivery

- [x] Configure Laravel to use the installed MailCatcher SMTP server on `127.0.0.1:1025`.
- [x] Verify the real welcome-email template is captured by MailCatcher from `no-reply@chatwithseo.ai`.
