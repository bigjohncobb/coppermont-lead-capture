=== Coppermont Lead Capture ===
Contributors: coppermont
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 0.1.1

Lead generation infobar and popup-style trigger engine with scheduling, targeting, repetition control, and analytics.

== Description ==
Coppermont Lead Capture provides a mobile-responsive lead capture infobar with conversion-oriented trigger controls and analytics.

Key capabilities:
- Admin-side data management controls for resetting analytics or purging plugin data.
- Slide-in bottom infobar with email capture.
- Scroll, time delay, and exit-intent triggers.
- Referral detection and page-level targeting.
- Scheduler windows and repetition control.
- Analytics counters for impressions (times shown) and submissions.

== Shortcodes ==
Use `[coppermont_infobar]` to embed a lead capture form inline.

Optional attributes:
- `headline`
- `body`
- `button`


== Data Management ==
In the plugin's top-level **Lead Capture** admin page, users with `manage_options` get deliberate cleanup tools:
- **Reset analytics only** (nonce-protected).
- **Delete all plugin data (irreversible)** (nonce-protected + requires typing `DELETE ALL DATA`).

Use the full purge only when intentional permanent deletion is required.

== Security ==
- AJAX endpoints require a nonce.
- User inputs are sanitized before saving.
- Admin settings are protected by `manage_options` capability checks.

== Uninstall ==
On uninstall, plugin data is removed via `uninstall.php` using the same centralized deletion service as the admin purge action.
