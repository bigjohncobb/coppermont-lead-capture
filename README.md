# Coppermont Lead Capture

Coppermont Lead Capture is a lightweight WordPress plugin for lead generation using a bottom slide-in infobar and shortcode embeds.

## Features

- Bottom infobar email capture module with slide-in animation.
- Trigger support:
  - Scroll percentage trigger.
  - Time-delay trigger.
  - Exit-intent trigger.
- Targeting support:
  - Page-level include/exclude targeting.
  - Referral detection (allow-list domains).
  - Time scheduler window (start/end).
- Repetition control:
  - Max views per visitor.
  - Cooldown after dismiss.
- Analytics:
  - Impression count (times shown).
  - Submission count.
- Shortcode support:
  - `[coppermont_infobar]`

## Installation

1. Upload the plugin folder to `wp-content/plugins/coppermont-lead-capture`.
2. Activate **Coppermont Lead Capture** from **Plugins**.
3. Configure options in **Settings > Lead Capture**.

## Shortcode

```text
[coppermont_infobar]
[coppermont_infobar headline="Get updates" body="Join our list" button="Subscribe"]
```

## Security Notes

- Direct file access is blocked in PHP entry files with `ABSPATH` checks.
- AJAX handlers enforce nonce verification.
- User input is sanitized server-side before persistence.
- Admin settings rendering is protected by capability checks (`manage_options`).


## Contributor Security Expectations

When adding or updating admin-facing endpoints (AJAX, `admin-post.php`, or page actions), contributors must:

- Require `manage_options` authorization using `CMLC_Admin_Security::require_manage_options()`.
- Validate request intent with `CMLC_Admin_Security::require_admin_referer()` using an action-specific nonce.
- Return a hard deny on auth failure:
  - `wp_send_json_error(..., 403)` for JSON/AJAX handlers.
  - `wp_die(..., 403)` for direct admin handlers.
- Avoid mutating settings or analytics before both checks pass.

### Manual Security Verification Checklist

Run this checklist for any new admin action (dashboard filters, exports, bulk deletes, reset actions):

1. As a non-admin user, hit each endpoint directly and confirm a 403 response (JSON or wp_die).
2. As an admin user with an invalid/missing nonce, hit each endpoint and confirm a 403 response.
3. As an admin user with a valid nonce, confirm expected behavior succeeds.
4. Verify no state changes occur for failed checks (no export action, no deletes, no analytics reset).

## Uninstall Behavior

The plugin includes `uninstall.php` and removes the `cmlc_settings` option when uninstalled via WordPress.

## Extensibility

On successful lead submission, the plugin fires:

- `cmlc_lead_submitted( string $email, array $settings )`

Use this action to forward leads to CRM or email marketing tools.
