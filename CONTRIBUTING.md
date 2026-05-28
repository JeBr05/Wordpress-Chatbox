# Contributing

Thanks for improving Jeroen's Chatbox.

## Local checks

Run PHP lint before opening a pull request.

```bash
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```

## Code style

Keep the plugin simple to install.
Avoid required build steps.
Escape output late.
Sanitize input at boundaries.
Keep the API key server side.

## Pull requests

Explain the problem.
Explain the change.
Add screenshots for UI changes.
Mention manual test steps.
