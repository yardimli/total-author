# Total Author: branding and optional integrations

Development stays at http://localhost:8023. The intended production domain is total-author.com; no deployment or DNS changes were made.

## Branding

Set these values in .env:

```dotenv
APP_NAME="Total Author"
APP_URL=http://localhost:8023
APP_LOGO=/images/logo.png
APP_FAVICON=/images/favicon.png
```

Logo/favicon values may be public paths or HTTPS URLs. Replace the supplied files or point the settings at your own assets. Titles, navigation, auth pages, footer, legal pages, and the live manuscript title read the configured name. Paper is the default; existing explicit user theme preferences remain in effect. Captured screenshot PNGs are static and retain the branding shown at capture time.

After changing settings, run `php artisan config:clear` locally (or rebuild the config cache on deployment). Keep development browser URLs consistent with APP_URL for OAuth session cookies.

## Google sign-in

```dotenv
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_CALLBACK_URL="${APP_URL}/login/google/callback"
```

Installed laravel/socialite. Register the exact callback URL http://localhost:8023/login/google/callback with the Google OAuth web application. The button on login/register and the routes are disabled until ID, secret, and callback are configured. Socialite validates OAuth state; there is no stateless bypass. New verified Google identities can create accounts; returning identities log in using their Google ID. An email already attached to a password account is not silently linked: that account must use its password. Tokens are not stored.

Reference: https://laravel.com/docs/10.x/socialite

## Mailgun

```dotenv
MAILGUN_DOMAIN=
MAILGUN_SECRET=
MAIL_MAILER=mailgun
MAIL_FROM_ADDRESS="noreply@total-author.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Installed symfony/mailgun-mailer and symfony/http-client. Set a verified Mailgun sending domain and its API secret before enabling delivery. MAILGUN_ENDPOINT may be configured for the appropriate Mailgun region. Use a sender approved for that domain. The config selects Mailgun only when both credentials are present; otherwise it uses an in-memory transport, and mail/notification listeners cancel sending. Forgot-password links and request routes are disabled when either credential is missing. No welcome email flow is currently created; future mail also passes through the same gates. Contact forms never send mail, even when Mailgun is configured.

## Contact and legal pages

The landing and auth footers share a contact form and links to /privacy and /terms. Contact submissions are validated, CSRF-protected, rate-limited to five per minute, and saved to contact_messages. No administrative inbox UI or email forwarding is included. The legal pages are generic starting text describing the present features; operator details and deployment-specific obligations should be reviewed before public launch.

## Verification

Automated checks cover disabled integrations, configured password resets with fake notifications, Google signup and identity/email safeguards, configurable branding, and database-only contact submissions. Live Google OAuth and Mailgun delivery require real credentials and have not been performed.
