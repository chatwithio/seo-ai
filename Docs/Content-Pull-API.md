# Content Pull API

Configure and enable the API under **Admin → Settings → Publishing → Content Pull API**.

## Authentication

Send the account's private API code in the `X-API-Code` header:

```bash
curl -H "Accept: application/json" \
     -H "X-API-Code: YOUR_API_CODE" \
     "https://chatwithseo.ai/api/v1/content"
```

The API code can also be supplied as the `api_code` query parameter when a client cannot send custom headers.

## List publishable content

```http
GET /api/v1/content
```

This returns generated content belonging to the API-code owner. It does not change read status.

Optional query parameter:

- `limit`: Number of articles to return, from 1 to 100. Default: 20.

## Read the next unread article

```http
GET /api/v1/content/unread
```

This returns the oldest unread generated article and immediately marks it read. Calls are atomic, so concurrent consumers cannot receive the same unread article.

If three articles are unread:

1. The first request returns article 1.
2. The second request returns article 2.
3. The third request returns article 3.
4. The fourth request returns:

```json
{
    "success": true,
    "data": null,
    "message": "No unread content is available."
}
```

If an article's title, metadata, or body is edited after it was read, it becomes unread again so the consuming website can retrieve the updated content.

## Article data

Each article includes:

- ID, title, and slug
- HTML and plain-text content
- Meta title and meta description
- Primary keyword
- Content workflow status
- Read timestamp
- Managed-site ID, name, and URL
- Creation and update timestamps

The API only returns content that has not already been rejected or published through another publishing channel.
