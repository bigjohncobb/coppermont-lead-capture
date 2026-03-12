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
  - Impression/submission tracking per campaign.
  - Campaign conversion-rate rankings in admin.
- Campaign management:
  - Lead Campaign custom post type with design, trigger, targeting, schedule, dimensions, opacity, priority, and status settings.
  - Automatic migration of existing global settings into a default campaign.
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
[coppermont_infobar campaign_id="123"]
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
