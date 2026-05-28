# Security Policy

## Reporting a vulnerability

Open a private security advisory on GitHub if available. If not, contact the maintainer by email.

Do not publish exploit details before a fix is available.

## Security design

Admin endpoints require `manage_options`.
The OpenAI API key never appears in front-end JavaScript.
Public chat requests are rate limited.
Chat output is escaped.
Conversation logging can be disabled.
