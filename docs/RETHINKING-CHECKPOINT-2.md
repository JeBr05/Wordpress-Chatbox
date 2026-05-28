# Rethinking checkpoint 2

## Main changes made after review

1. Settings storage

The plugin still uses the custom dashboard and REST saves, but the option is now also registered through the WordPress Settings API. This gives the option a native sanitize callback and makes the storage pattern closer to WordPress standards.

2. Browser safety

The API key remains server side. The browser only talks to WordPress REST routes. The chat and feedback routes now verify a REST nonce before doing work.

3. JavaScript data passing

The plugin now uses `wp_add_inline_script` for JavaScript config instead of `wp_localize_script`, because the config is data, not translation text.

4. Conversation context

The first version logged messages but did not send previous messages back to the AI. Version 0.2 adds short lived transient based session context. Site owners can turn this off.

5. Cost control

The first version had a per minute IP rate limit. Version 0.2 adds a per hour IP rate limit, max output tokens and a daily token budget.

6. Knowledge base sync

The first version exported every selected page into one file. Version 0.2 exports one temporary file per selected page. This should make retrieval and source chips better.

7. Sync status

The plugin can now check the latest OpenAI vector store file batch status after upload.

8. Source chips

The chat UI can show source chips when file search results are included in the API response.

## Still not finished

Streaming is still not implemented. It should be a separate version because Server Sent Events inside WordPress needs careful testing.

Partial resync is still not implemented. The current safe approach creates a new vector store on each sync.

Automated tests are still missing.
