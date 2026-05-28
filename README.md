# AI Knowledge Chatbot

AI Knowledge Chatbot is a WordPress plugin that lets site owners build a chatbot from selected WordPress pages and posts using an OpenAI API key.

The admin interface is inspired by modern knowledge base dashboards. You select content, add metadata, sync it to an OpenAI vector store, then publish the chatbot with a shortcode or a global floating widget.

## Current status

Version 0.2.0 is a developer checkpoint. It is installable, but it still needs testing on a staging WordPress site before public release.

## Main features

1. Knowledge Base Manager

Select public pages and posts. Add summaries, tags and priority. Sync selected content to OpenAI.

2. One file per page sync

Each selected page becomes its own text document before upload. This improves retrieval, source display and future partial resync support.

3. OpenAI Responses API support

The chatbot calls the OpenAI Responses API and can use the file search tool with the configured vector store.

4. Front end chatbot

Use `[aikb_chatbot]` anywhere in WordPress, or enable auto embed for the whole site.

5. Admin tabs

Knowledge Base, Assistants, Tools, Channels, Design, Analytics, Security, OpenAI API and Settings.

6. Privacy and security controls

API key encryption, public nonce checks, per minute and per hour rate limits, daily token budget, conversation logging toggle, session memory toggle, retention cleanup, email and phone redaction.

7. Analytics

Stores conversation counts, message counts, recent messages, feedback events, token usage and average latency when logging is enabled.

## Requirements

WordPress 6.4 or newer.
PHP 8.0 or newer.
PHP cURL extension for OpenAI file uploads.
An OpenAI API key.

## Install

1. Download the plugin zip.
2. In WordPress admin, go to Plugins, Add New, Upload Plugin.
3. Upload the zip and activate it.
4. Go to AI Chatbot.
5. Open OpenAI API and save your API key.
6. Open Knowledge Base and select pages.
7. Click Sync to Vector Store.
8. Use Check sync status until processing is complete.
9. Add `[aikb_chatbot]` to a page, or enable auto embed in Channels.

## GitHub setup

```bash
git init
git add .
git commit -m "Initial AI Knowledge Chatbot plugin"
git branch -M main
git remote add origin git@github.com:your-name/ai-knowledge-chatbot.git
git push -u origin main
```

## Important files

`ai-knowledge-chatbot.php` is the plugin entry point.
`includes/class-aikb-rest-controller.php` contains REST endpoints.
`includes/class-aikb-openai-client.php` contains OpenAI API calls.
`includes/class-aikb-knowledge-base.php` handles export and sync.
`includes/class-aikb-session.php` stores short lived chat context.
`assets/admin.js` powers the admin app.
`assets/chat.js` powers the front end chatbot.

## OpenAI data flow

1. Selected WordPress content is exported to temporary plain text files.
2. Each file is uploaded to OpenAI with purpose `assistants`.
3. A vector store is created.
4. Uploaded files are attached to the vector store with a file batch.
5. Chat requests call the Responses API with the `file_search` tool.
6. When included by the API response, source hints are returned to the chat UI.

## Security choices

The API key is encrypted before storage using WordPress salts.
Admin routes require `manage_options`.
Public chat and feedback routes require a WordPress REST nonce.
Public chat uses IP based transient rate limiting.
A daily token budget can stop spending for the day.
Output is escaped in WordPress and in the chat UI.
Conversation logging can be disabled.
Session context can be disabled.
Personal data redaction can be enabled.
Old logs are removed by WP Cron.

## Known limits

This release does not stream responses yet.
This release creates a fresh vector store on sync. This avoids stale content and is simpler for users.
This release does not include a compiled Gutenberg block. Use the shortcode.
This release does not implement WooCommerce or CRM tools yet.
This release does not have automated browser tests yet.

## Roadmap

See `docs/ROADMAP.md`.

## License

GPL v2 or later.
