=== AI Knowledge Chatbot ===
Contributors: open-source-contributors
Tags: ai, chatbot, openai, knowledge base, support
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create a site aware AI chatbot from selected WordPress pages and posts using an OpenAI API key.

== Description ==

AI Knowledge Chatbot gives site owners a dashboard to select pages, add metadata, sync content to an OpenAI vector store and publish a chatbot with a shortcode or auto embed.

Version 0.2.0 is a developer checkpoint and should be tested on staging first.

== Features ==

* Knowledge base manager for public pages and posts.
* OpenAI Responses API integration.
* OpenAI file search with vector store sync.
* One uploaded file per selected page.
* Chat shortcode: [aikb_chatbot].
* Optional global floating chatbot.
* API key encryption.
* REST nonce checks.
* Per minute and per hour rate limits.
* Daily token budget.
* Short lived session context.
* Conversation logging toggle.
* Log retention cleanup.
* Analytics dashboard.

== Installation ==

1. Upload the plugin zip in WordPress admin.
2. Activate the plugin.
3. Go to AI Chatbot.
4. Save your OpenAI API key.
5. Select pages in Knowledge Base.
6. Click Sync to Vector Store.
7. Add [aikb_chatbot] to a page or enable auto embed.

== Changelog ==

= 0.2.0 =
* Added Settings API registration.
* Added public REST nonce check.
* Added session context through transients.
* Added per hour rate limit.
* Added daily token budget.
* Added max output token setting.
* Changed sync to one file per selected page.
* Added sync status check endpoint.
* Added source chips support.
* Switched JavaScript config to wp_add_inline_script.

= 0.1.0 =
* Initial developer release.
