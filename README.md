# Statamic Standard Site

A Statamic 6 add-on that syncs entries to [standard.site](https://standard.site) lexicons on the AT Protocol.

## What this does

When you save an entry in a configured collection, this add-on creates or updates a `site.standard.document` record on your AT Protocol PDS. It also serves the `/.well-known/site.standard.publication` verification endpoint and provides a Statamic tag for document verification link tags.

Built on top of [`publish-php/atproto-standard-site`](https://github.com/publish-php/atproto-standard-site), a focused PHP XRPC client for standard.site lexicons.

## Requirements

- Statamic 6+
- PHP 8.3+
- A Bluesky/AT Protocol account with an [app password](https://bsky.app/settings/app-passwords)

## Installation

```bash
composer require publish-php/statamic-standard-site
```

After installation, go to **CP → Settings → Standard Site** to configure your credentials and publication record.

## Quick Start

1. Generate a Bluesky app password at Settings → App Passwords
2. Open **CP → Settings → Standard Site**
3. Enter your handle and app password
4. Click **"Check for existing publications"** to discover any existing records
5. Select an existing publication or create a new one
6. Add the Standard Site section to your collection blueprint (coming in next release)

## Verification

### Publication

The add-on automatically serves `/.well-known/site.standard.publication` on your domain, returning your publication's AT-URI.

### Document

Add this tag to your template's `<head>`:

```antlers
{{ standard_site:document_link }}
```

This outputs the `<link rel="site.standard.document">` tag for the current entry.

## License

MIT
