=== Coppermont Lead Capture ===
Contributors: coppermont
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 0.1.1

Lead generation infobar and popup-style trigger engine with scheduling, targeting, repetition control, analytics, and Turnstile verification.

== Description ==
Coppermont Lead Capture provides a mobile-responsive lead capture infobar with conversion-oriented trigger controls and analytics.

Key capabilities:
- Slide-in bottom infobar with email capture.
- Scroll, time delay, and exit-intent triggers.
- Referral detection and page-level targeting.
- Scheduler windows and repetition control.
- Analytics counters for impressions (times shown) and submissions.
- Cloudflare Turnstile verification for infobar and shortcode forms.
- Strict mode to fail closed when verification service is unavailable.

== Turnstile Setup ==
1. Create a Turnstile widget in Cloudflare.
2. Copy Site Key and Secret Key.
3. Go to Settings > Lead Capture and enable Turnstile.
4. Save Site Key and Secret Key.
5. Keep Turnstile Strict Mode enabled to block submissions on verification outage/timeouts.

Troubleshooting:
- If you see "Captcha verification is required," ensure Turnstile JS is loading and the widget appears.
- If verification fails, check that your domain is configured in Turnstile and action is `cmlc_submit`.
- If strict mode blocks submissions, verify server connectivity to `https://challenges.cloudflare.com/turnstile/v0/siteverify`.

== Shortcodes ==
Use `[coppermont_infobar]` to embed a lead capture form inline.

Optional attributes:
- `headline`
- `body`
- `button`

== Security ==
- AJAX endpoints require a nonce.
- User inputs are sanitized before saving.
- Admin settings are protected by `manage_options` capability checks.
- Turnstile verification is enforced server-side before accepting a submission when enabled.
- Honeypot field remains active as a secondary anti-spam measure.

== Uninstall ==
On uninstall, the plugin uses the same centralized data manager logic to delete all plugin data.


== Admin Navigation ==
After activation, manage the plugin from the top-level **Lead Capture** menu:
- Dashboard
- Campaigns
- Leads
- Analytics
- Settings
