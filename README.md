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
- Multi-campaign architecture via the `cmlc_campaign` custom post type.
- Conflict resolution for overlapping campaigns using campaign priority.
- Analytics:
  - Impression count (times shown) per campaign.
  - Submission count per campaign.
- Shortcode support:
  - `[coppermont_infobar id="123"]`

## Installation

1. Upload the plugin folder to `wp-content/plugins/coppermont-lead-capture`.
2. Activate **Coppermont Lead Capture** from **Plugins**.
3. Create and configure campaigns in **Infobar Campaigns**.

## Shortcode

```text
[coppermont_infobar id="123"]
[coppermont_infobar id="123" headline="Get updates" body="Join our list" button="Subscribe"]
```

## Security Notes

- Direct file access is blocked in PHP entry files with `ABSPATH` checks.
- AJAX handlers enforce nonce verification.
- User input is sanitized server-side before persistence.
- Admin settings rendering is protected by capability checks (`manage_options`).

## Uninstall Behavior

The plugin includes `uninstall.php` and removes the `cmlc_settings` option when uninstalled via WordPress.

## Extensibility

On successful lead submission, the plugin fires:

- `cmlc_lead_submitted( string $email, array $settings )`

Use this action to forward leads to CRM or email marketing tools.
