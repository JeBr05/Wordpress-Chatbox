=== Jeroen's Chatbox ===
Contributors: jeroen
Tags: chatbox, ai, openai, support, knowledge base, multilingual
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.8.0
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
* Admin panel language support for English, Dutch, German and French.
* Design controls for font colour, background colour, chat bubbles, live preview and preset themes.
* Visibility settings for everyone, logged in users, admins only, selected users, home page, pages, posts, archives and mobile.
* Exclude page IDs and URL paths.
* Custom chatbox name, welcome message, button text, colours, bubble style, preset design theme and position.
* Conversation context.
* Rate limits and daily token budget.
* Security tab with message length limits, blocked words, IP blocklist, auto flag scoring, jailbreak detection, content flags, behavioral checks, whitelists and test tool.
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

= 0.8.0 =
* Rebuilt the Security tab into a clearer full security dashboard.
* Added master security switch.
* Added configurable rate limiting by session token and IP address.
* Added message length limits.
* Added blocked words and phrases with warn or block action.
* Added IP blocklist.
* Added auto flag scoring with threshold and action settings.
* Added jailbreak detection, abuse detection, content flags and behavioral analysis.
* Added whitelist settings for trusted session tokens and IP addresses.
* Added a security rule test tool.

= 0.7.1 =
* Added stronger chatbox only CSS with important rules to stop theme CSS overriding chat text colours.
* Improved the close button shape, alignment, sizing and focus state.
* Updated preview button and close colours to match the selected design text colour.

= 0.7.0 =
* Moved the live design preview into a right side sticky preview column.
* Added clearer design layout with preview above preset themes.
* Added visitor visibility controls for everyone, logged in users, admins only or selected WordPress users.
* Added selected WordPress user picker for testing the chatbox before public launch.

= 0.6.0 =
* Replaced unclear full width colour bars with clear swatch, picker and hex controls.
* Added live design preview while editing colours and bubble style.
* Added preset design themes.
* Saved design changes still only affect the public website after saving.

= 0.5.0 =
* Added font colour and background colour settings.
* Added user and assistant chat bubble colour settings.
* Added chat bubble style setting.
* Added admin panel language support for English, Dutch, German and French.
* Added automatic admin reload after language changes.

= 0.3.0 =
* Renamed the plugin to Jeroen's Chatbox.
* Added front end visibility settings.
* Added launcher text, start open, mobile and page type controls.
* Added excluded page IDs and URL paths.
* Removed temporary developer notes.
