# Statamic Standard Site

A Statamic 6 add-on that syncs entries to [standard.site](https://standard.site) lexicons on the AT Protocol.

## What this does

When you save an entry in a configured collection, this add-on creates or updates a `site.standard.document` record on your AT Protocol PDS. It also serves the `/.well-known/site.standard.publication` verification endpoint and provides a Statamic tag for document verification link tags.

Built on top of [`publish-php/atproto-standard-site`](https://github.com/publish-php/atproto-standard-site), a focused PHP XRPC client for standard.site lexicons.

## Requirements

- Statamic 6+
- PHP 8.3+
- A Bluesky/AT Protocol account with an [app password](https://bsky.app/settings/app-passwords)

## Limitations

- **Multi-site / localization:** Only the origin entry is synced to the AT Protocol. Localized entries are not synced independently, and deleting the origin entry does not cascade to delete localized AT Protocol records. Multi-site support is planned for a future release.
- **Drafts:** Only published entries are synced. The AT Protocol is currently 100% public — drafts are never published to the PDS.
- **Content source:** The content field is determined by convention (the field with handle `content`). Per-entry content override is not supported at this time.

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
6. Go to **Settings → Standard Site → Collections** tab and enable syncing for the collections you want
7. Add the verification tag to your template `<head>` (see below)

## Verification

### Publication

The add-on automatically serves `/.well-known/site.standard.publication` on your domain, returning your publication's AT-URI.

### Document

Add this tag to your template's `<head>`:

```antlers
{{ standard_site:document_link }}
```

This outputs the `<link rel="site.standard.document">` tag for the current entry.

## Content conversion

The `content` field is converted to [`at.markpub.markdown`](https://markpub.at)
for the document's `content` union, plus a plaintext `textContent`. Bard fields
(including Bard **sets**) are supported.

### Assets resolve to absolute URLs

Any asset referenced in your content — inline Bard images, or `assets` fields
inside a Bard set — is resolved to a **fully-qualified absolute URL** via
Statamic's own asset container configuration. This works for every driver
(local disk, S3, Scaleway, custom CDN) with no configuration. External readers
(e.g. Standard Reader) can therefore load your media directly.

### Media that Markdown can't represent renders as pure Markdown

Markdown has no native syntax for video or audio, and raw HTML embeds
(`<video>`/`<audio>`) don't survive: the most popular Standard.Site reader
(Standard Reader) doesn't render Markdown as generic HTML — it parses each block
into AT Protocol's facet-based richtext model, where a block survives only if it
has non-empty text (like a link) or is a **standalone image**. An image wrapped
in a link contributes no text, so a "clickable poster"
(`[![alt](poster)](media)`) collapses to an empty block and is dropped entirely.

So media falls back to constructs that the reader's model actually keeps:

- **Images** → `![alt](url)`
- **Video / audio _with_ a poster** → the poster as a standalone image,
  followed by a separate labelled link to the media:

  ```markdown
  ![alt](poster_url)

  [label](media_url)
  ```

- **Video / audio _without_ a poster** → a plain link `[label](media_url)`
- **Other files** → `[label](url)` link

This is a general rule keyed off the asset's media type, not a special case for
any particular set.

The link **label** is the media asset's alt text when present; when it's empty
(common for talk media) it defaults to `Watch the video` / `Listen to the audio`
— a link with no label is itself empty text and would be dropped by the reader.

### The `poster` convention (video/audio poster frames)

> **⚠️ Convention alert — this changes output based on a field handle.**

Within a single Bard set, if an **image** asset's field **handle contains
`poster`** (e.g. `poster`, `video_poster`), it is treated as the poster frame
for a video/audio asset in the same set. It is rendered as that media's
standalone poster image (immediately above the media's link) rather than as a
separate image elsewhere in the set:

```markdown
![A talk slide](https://cdn.example.com/slide_poster.jpg)

[A talk slide](https://cdn.example.com/slide.mp4)
```

**To opt out** of this behavior, rename the field handle so it does **not**
contain `poster` (e.g. `thumbnail`, `still`). The image will then render as its
own standalone `![alt](url)` image alongside the video's link.

If a `poster`-handled image has no video or audio in the same set to attach to,
it falls back to rendering as a normal image (it is never silently dropped).

### Set field handling

Each field inside a Bard set is resolved through its real Statamic fieldtype, so
this works for **any** set blueprint — nothing is hardwired to specific field
names (aside from the documented `poster` convention above):

- `assets` fields → resolved asset URL(s) with media-type dispatch (above)
- Nested `bard` fields (e.g. a slide description) → rendered as Markdown at full
  fidelity
- Text/textarea/markdown fields → included as text

## License

MIT
