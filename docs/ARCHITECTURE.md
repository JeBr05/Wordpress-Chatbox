# Architecture

## Goal

Create a WordPress plugin that makes an AI chatbox usable by non technical site owners.

## Main components

1. Admin app

The admin app is a WordPress admin page with tabs. It uses REST endpoints for settings, content, sync, sync status and analytics.

2. Knowledge base export

Selected posts and pages are converted into temporary text files. Each file includes site name, site URL, generated date, title, type, URL, modified date, priority, tags, editor summary and body content.

3. OpenAI client

The plugin talks to these API endpoints:

`GET /models`
`POST /files`
`POST /vector_stores`
`DELETE /vector_stores/{vector_store_id}`
`POST /vector_stores/{vector_store_id}/file_batches`
`GET /vector_stores/{vector_store_id}/file_batches/{batch_id}`
`POST /responses`

4. Public chat

The front end chat sends a message to the WordPress REST API. WordPress then calls OpenAI. The API key never reaches the browser.

5. Session context

Short lived conversation context is stored in transients. The site owner can turn it off or change the message count and lifetime.

6. Analytics

Three custom tables store conversations, messages and events. Logging can be disabled.

## Database tables

`wp_jcb_conversations`
Stores session hashes, timestamps, page URL, IP hash and user agent.

`wp_jcb_messages`
Stores role, content, latency and token count.

`wp_jcb_events`
Stores sync events, feedback events and debug events.

## Privacy

IP addresses are hashed.
Conversation logging can be turned off.
Session context can be turned off.
Emails and phone numbers can be redacted before logging.
Log retention is controlled in settings.

## Extension points

Future versions can add CRM actions, WooCommerce order lookup, lead capture, custom post type filters, streaming and a Gutenberg block.
