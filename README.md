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

All privileged admin mutations must use shared security helpers in `CMLC_Admin_Security`:

- Capability gate: `CMLC_Admin_Security::current_user_can_manage_options()`.
- Admin form nonce gate: `CMLC_Admin_Security::check_admin_referer_or_false()` through `enforce_admin_action_or_die()`.
- Authenticated AJAX nonce gate: `CMLC_Admin_Security::enforce_admin_ajax_or_json_error()`.

Current protected admin action endpoints for upcoming dashboard pages:

- `admin_post_cmlc_dashboard_filter`
- `admin_post_cmlc_export_data`
- `admin_post_cmlc_bulk_delete`
- `admin_post_cmlc_reset_analytics`
- `wp_ajax_cmlc_dashboard_filter`
- `wp_ajax_cmlc_export_data`
- `wp_ajax_cmlc_bulk_delete`
- `wp_ajax_cmlc_reset_analytics`

Unauthorized requests must fail closed:

- `admin_post_*` handlers terminate with `wp_die( ..., 403 )`.
- `wp_ajax_*` handlers return JSON error with HTTP `403`.

### Manual Security Checklist (Unauthorized Access)

Run these checks before merging admin endpoint changes:

1. Open each `admin_post_cmlc_*` URL without admin login and verify a `403`/die response.
2. Repeat while logged in as a non-admin user and verify a `403`/die response.
3. Open each `admin_post_cmlc_*` URL as admin but without valid nonce and verify nonce failure.
4. Call each `wp_ajax_cmlc_*` action without login and verify JSON `403`.
5. Call each `wp_ajax_cmlc_*` action as non-admin and verify JSON `403`.
6. Call each `wp_ajax_cmlc_*` action as admin with invalid nonce and verify JSON `403`.

## Uninstall Behavior

The plugin includes `uninstall.php` and removes the `cmlc_settings` option when uninstalled via WordPress.

## Extensibility

On successful lead submission, the plugin fires:

- `cmlc_lead_submitted( string $email, array $settings )`

Use this action to forward leads to CRM or email marketing tools.
