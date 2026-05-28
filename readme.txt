=== Jeroen's Chatbox ===
Contributors: jeroen
Tags: chatbox, ai, openai, support, knowledge base, multilingual
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build a site aware WordPress chatbox from selected pages and posts with OpenAI.

== Description ==

Jeroen's Chatbox gives site owners a dashboard to select content, add metadata, sync content to an OpenAI vector store and publish a multilingual chatbox with a shortcode or site wide auto embed.

== Features ==

* Knowledge base manager for pages and posts.
* Metadata per page.
* OpenAI vector store sync.
* Chat shortcode: [jeroens_chatbox].
* Site wide auto embed.
* Language setting for English, Dutch, German and French.
* Visibility settings for home page, pages, posts, archives and mobile.
* Exclude page IDs and URL paths.
* Custom chatbox name, welcome message, button text, color and position.
* Conversation context.
* Rate limits and daily token budget.
* Conversation analytics.

== Installation ==

1. Upload the plugin zip.
2. Activate Jeroen's Chatbox.
3. Go to Jeroen's Chatbox.
4. Add your OpenAI API key.
5. Select pages or posts.
6. Sync the knowledge base.
7. Add [jeroens_chatbox] to a page or enable auto embed in Channels.

== Frequently Asked Questions ==

= Why can I not see it on my website? =

Open Channels. Enable front end display. Then either enable auto embed or place [jeroens_chatbox] on a page. Also check excluded pages, excluded paths and mobile settings.

= Is my API key visible on the website? =

No. The API key is stored in WordPress and used server side.

== Changelog ==

= 0.4.0 =
* Added a plugin language setting.
* Added English, Dutch, German and French front end chatbox text.
* Added language aware AI answer rules.
* Added docs for language settings.

= 0.3.0 =
* Renamed the plugin to Jeroen's Chatbox.
* Added front end visibility settings.
* Added launcher text, start open, mobile and page type controls.
* Added excluded page IDs and URL paths.
* Removed temporary developer notes.
