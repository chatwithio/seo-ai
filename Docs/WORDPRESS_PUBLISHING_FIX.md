# WordPress publishing correction — 28 September 2026

Changes in this checkout:
- Strip outer Markdown HTML fences when article HTML is read or saved, including rich-text paragraph wrappers; preserve internal code samples.
- Add Publishing Settings → WordPress author ID or email. Accept `2`, `author=2`, or an existing WordPress email. WP Webhooks requests require an explicit author.
- Import ready featured images with `create_url_attachment`, then set `_thumbnail_id` through `create_post` metadata. Failed image imports stop before post creation. An image must already be generated/uploaded and its URL publicly reachable.
- Preserve automatic publishing's existing account-enable and approved-article requirements.

## Deployment to seoai.tochat.be

Deploy the changed application files and the new migration from this checkout first. Then, from the deployed project directory, run:

```bash
php artisan migrate --force
php artisan view:clear
php artisan queue:restart
```

In the article owner's account, configure:
- WordPress author: `2` (or `author=2`, or the WordPress user's email).
- WordPress post status: Publish immediately.
- Enable WordPress webhook.
- Enable Auto-publish new articles for automatic delivery after approval.

In WordPress → WP Webhooks Pro, enable/allow both `create_post` and `create_url_attachment` for the Receive Data URL. The latter action was rejected as unavailable/disabled by the live receiver during testing. The signing secret can stay blank for the existing API-key URL.

## Verification and limits

- 36 focused tests passed with 105 assertions against an isolated MySQL test database.
- Temporary live WordPress draft #1217 was read back with `post_author=2` and clean HTML without fences, then moved to trash.
- Live image import is blocked by the receiver's unavailable/disabled `create_url_attachment` action; no post was created by that failed test.
- This checkout's migration was applied and its admin account's author set to 2. These are local changes; remote deployment and remote account settings remain unverified.
- Article #516 on seoai.tochat.be is inaccessible to the supplied admin account. Its existing WordPress copy has not been changed.
