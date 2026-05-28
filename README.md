# Jeroen's Chatbox

Jeroen's Chatbox is a WordPress plugin that lets site owners build a chatbox from selected WordPress pages and posts using an OpenAI API key and an OpenAI vector store.

## What it does

1. Select pages and posts for the knowledge base.
2. Add summaries, tags and priority per page.
3. Sync selected content to an OpenAI vector store.
4. Publish the chatbox with the shortcode `[jeroens_chatbox]` or site wide auto embed.
5. Control visibility for home page, pages, posts, archives and mobile.
6. Exclude specific page IDs or URL paths.
7. Change the chatbox name, welcome message, button text, color and position.
8. Protect usage with nonces, rate limits and a daily token budget.
9. View analytics and recent messages when logging is enabled.

## Installation

1. Download the plugin zip.
2. Go to Plugins in WordPress.
3. Upload the zip.
4. Activate Jeroen's Chatbox.
5. Open Jeroen's Chatbox in the WordPress admin menu.
6. Add your OpenAI API key.
7. Select one or more pages.
8. Sync the knowledge base.
9. Go to Channels and either enable auto embed or copy `[jeroens_chatbox]` to a page.

## Front end visibility

If the chatbox does not show on the website, open Channels and check these settings.

1. Enable Jeroen's Chatbox on the front end.
2. Enable auto embed if you want it visible on every allowed public page.
3. Check the page type rules.
4. Check excluded page IDs.
5. Check excluded URL paths.
6. Check the mobile setting.
7. Raise the stacking order if another theme element covers the button.

## Shortcode

Use this shortcode anywhere in WordPress.

`[jeroens_chatbox]`

## GitHub setup

```bash
git init
git add .
git commit -m "Initial Jeroen's Chatbox plugin"
git branch -M main
git remote add origin git@github.com:your-name/jeroens-chatbox.git
git push -u origin main
```

## Notes

The API key is only used server side. It is not printed in front end JavaScript.

Test on a staging site before using it on a public website.
