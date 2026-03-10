=== CF7 Anti-Spam Shield ===
Contributors: supplesolutions
Donate link: https://supple.com.au
Tags: contact form 7, anti-spam, spam protection, honeypot, rate limiting
Requires at least: 5.5
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight anti-spam protection for Contact Form 7 — no external APIs, no CAPTCHAs, no user friction.

== Description ==

CF7 Anti-Spam Shield adds multiple layers of invisible spam protection to your Contact Form 7 forms. It works silently in the background without adding CAPTCHAs, puzzles, or any other user-facing elements that hurt conversions.

**How it works — 5 layers of protection:**

1. **Honeypot trap** — Adds a hidden field that bots fill in but humans never see. JavaScript clears it on page load, so only automated submissions get caught.
2. **Time-based check** — Rejects submissions that happen faster than a configurable threshold (default: 3 seconds). Bots submit forms instantly; humans don't.
3. **Rate limiting** — Blocks IP addresses that exceed a configurable number of submissions per hour (default: 5). Stops brute-force spam attacks.
4. **URL limiting** — Rejects messages containing more than a configurable number of URLs (default: 2). Most spam contains multiple links.
5. **Disallowed words** — Blocks submissions containing known spam phrases (viagra, casino, etc.) plus your own custom word list.

**Additional features:**

* Optional Cyrillic character blocking for sites that don't expect non-Latin submissions
* Spam log with statistics — see what's being blocked and why
* Settings page under Settings > CF7 Anti-Spam
* Quick-access settings link on the Plugins page
* Clean uninstall — removes all data when deleted
* Developer-friendly with filters and actions for extending functionality
* Works alongside existing spam solutions (reCAPTCHA, Akismet, etc.)

**No external dependencies.** No API keys. No third-party services. Everything runs locally on your server.

== Installation ==

1. Upload the `cf7-antispam-shield` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu in WordPress.
3. Go to Settings > CF7 Anti-Spam to configure (optional — sensible defaults are applied automatically).

**Requirements:**

* WordPress 5.5 or higher
* PHP 7.4 or higher
* Contact Form 7 plugin (must be installed and active)

== Frequently Asked Questions ==

= Does this replace reCAPTCHA or Akismet? =

It can, but it also works alongside them. CF7 Anti-Spam Shield provides invisible protection that doesn't require user interaction, while reCAPTCHA and Akismet use different detection methods. Using them together gives you the strongest protection.

= Will legitimate users ever be blocked? =

The default settings are conservative and should not block legitimate users. If a real user is somehow blocked, they'll see a generic "could not be sent" message and can try again. You can fine-tune all thresholds in the settings.

= Does it slow down my site? =

No. The plugin adds minimal overhead — a tiny JavaScript file (under 1 KB) and a few milliseconds of server-side validation on form submission. There are no external API calls.

= Can I add my own spam checks? =

Yes. Developers can use the `cf7as_spam_checks` filter to add custom check functions, the `cf7as_disallowed_words` filter to modify the word list, and the `cf7as_spam_blocked` action to trigger custom behavior when spam is detected.

= Does it work with caching plugins? =

Yes. The honeypot and timestamp fields are set via JavaScript, so they work correctly with all page caching solutions.

= What happens to blocked submissions? =

They are rejected with a generic error message. If logging is enabled (default), blocked attempts are recorded in the Spam Log tab with the reason, IP address, and timestamp.

== Screenshots ==

1. Settings page — configure all protection layers
2. Spam Log — view blocked submissions with reasons and IP addresses

== Changelog ==

= 1.0.0 =
* Initial release
* Honeypot hidden field trap
* Time-based submission check
* IP rate limiting
* URL count limiting
* Disallowed word/phrase blocking
* Optional Cyrillic character blocking
* Spam log with statistics
* Settings page with tabbed interface
* Developer hooks and filters

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== Developer Documentation ==

**Available filters:**

* `cf7as_spam_checks` — Add, remove, or reorder spam check functions
* `cf7as_disallowed_words` — Modify the disallowed words list at runtime
* `cf7as_hidden_fields` — Customize the hidden form fields HTML
* `cf7as_error_message` — Change the error message shown to blocked users
* `cf7as_log_entry` — Modify or suppress individual log entries

**Available actions:**

* `cf7as_loaded` — Fires after the plugin is fully initialized
* `cf7as_spam_blocked` — Fires when a submission is blocked (receives the reason string)

**Example — Add a custom spam check:**

`
add_filter( 'cf7as_spam_checks', function( $checks ) {
    $checks[] = function() {
        // Your custom logic here.
        // Return a string (reason) to block, or false to pass.
        return false;
    };
    return $checks;
} );
`
