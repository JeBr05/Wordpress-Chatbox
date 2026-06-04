=== Jeroen's Chatbox ===
Contributors: jeroen
Tags: chatbox, ai, openai, support, knowledge base, multilingual
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.9.3
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

= 0.9.3 =
* Added an "Avatar size in chat" option (Small, Medium, Large) on the Design tab; the in-conversation avatar is now larger by default and adjustable.
* Clarified the launcher option: choosing "Logo / avatar button" uses your uploaded profile picture as the round chat button, with a short hint in the Design tab.

= 0.9.2 =
* The built-in jailbreak detection now covers many more languages by default, including Chinese, Russian, Arabic, Japanese, Korean, Turkish, Polish and Hindi, on top of the existing English, Dutch, German, French, Spanish, Italian and Portuguese.
* Pattern matching is now multibyte aware so non-Latin scripts are detected reliably.
* The Website representative preset is much more detailed and now mirrors a full strict knowledge-base persona (scope and off-topic limits, never reveal the source, first-person voice, required clickable Markdown links for every page, clickable phone and email, concise answers under ~400 characters, follow-up questions and a blog fallback).
* Upgrading sites that still have the earlier, shorter auto-seeded preset are refreshed to the new detailed version automatically. Instructions you have edited yourself are never overwritten.

= 0.9.1 =
* Existing sites upgrading from 0.8.0 now automatically get the new protections enabled, since the old saved settings previously kept them switched off. On first load after the update, offensive-word blocking, the built-in multilingual word list, auto-flagging, jailbreak detection and multilingual jailbreak detection are all turned on once. Your own changes after that are respected.
* New and upgraded sites are seeded with the Website representative preset as the starting Instructions (only when you have not customised the Instructions field), so the company-style persona is visible and active out of the box.
* Version bump also refreshes the admin assets so the new preset selector and security options always appear after updating.

= 0.9.0 =
* Knowledge base editor now shows the page URL, a word count and an auto-fill summary button.
* Summaries can be auto-filled from the SEO meta description (Yoast, Rank Math, AIO SEO, SEOPress, Genesis) or a generated excerpt.
* Added an auto-generate summary on sync option per page when the summary is empty.
* Added a Category field per page that is included in the synced knowledge base.
* Added instruction presets, including a Website representative preset based on a strict knowledge base persona, plus Support and Sales presets, in English and Dutch.
* Added contact email, phone and address fields that presets use to share how to reach you.
* Added a profile picture (avatar) with circle, rounded, squircle and speech bubble shapes, shown in the header and next to answers.
* Added launcher styles: text button, round icon button or avatar button, with selectable icons.
* Added Markdown rendering in answers so links, bold text and lists are clickable and formatted.
* Added quick reply suggestion chips under the welcome message.
* Added a typing indicator animation.
* Security: blocking of common offensive words is on by default with a built-in multilingual list.
* Security: jailbreak detection now works in any language with a built-in pattern bank for Dutch, German, French, Spanish, Italian and Portuguese in addition to English.

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
