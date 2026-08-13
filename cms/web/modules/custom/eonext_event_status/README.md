# EO Next Event Status

Standalone Drupal module for custom event ribbons (badges) on DPL CMS.

Editors can set ribbon text and colour on event series and instances. The ribbon
is shown on event pages and list teasers, and exposed on the events REST API.

Does **not** require `eonext_mobilesearch` or theme changes.

## Requirements

- DPL CMS with `dpl_event`, `recurring_events`, and `color_field` enabled.

## Installation

1. Extract the zip so the module lives at
   `web/modules/custom/eonext_event_status/` (folder name must match the machine
   name).

2. From the CMS project root (`cms/`), enable the module:

   ```bash
   ./vendor/bin/drush en eonext_event_status -y
   ./vendor/bin/drush cr
   ```

   Enabling the module imports its configuration; see below.

   If the ribbon fields are missing after enable, run
   `./vendor/bin/drush cim -y` once and clear caches. That applies the
   module's field configuration when the automatic import did not run.

## Configuration management

This module follows the [DPL webmaster module configuration
pattern](https://github.com/danskernesdigitalebibliotek/dpl-web/blob/develop/docs/cms/webmaster-modules.md).
The ribbon field storages and instances live in `config/sync/`, and
`OverlayConfigEventSubscriber` adds them to the storage DPL CMS imports on every
release. Without that, a platform release deletes them along with the ribbon
text and colour editors have entered.

`eonext_event_status_update_config()` runs the import, called from
`hook_modules_installed()` on a fresh install and from `hook_post_update_NAME()`
on an existing site. Update hooks must not import: Drupal runs all of them
before any post update hook, so importing early would snapshot the site while a
sibling module still holds DPL configuration pinned.

The ribbon widgets are **not** stored in DPL's event form displays. They are
placed by `hook_entity_form_display_alter()` when the form is built, so DPL
keeps ownership of those displays and can keep reworking them.

> Because of this, the ribbon fields do not appear on
> **Manage form display** and cannot be reordered or grouped there. Do not add
> them by hand: saving a DPL display pins it in `config_ignore_auto`, which
> stops the platform from updating it.

## Form fields

The ribbon fields appear at the bottom of the event edit forms:

- **Event series** — Ribbon Text, Ribbon Color, Apply to all event instances
- **Event instance** — Ribbon Text, Ribbon Color

There is no **Apply to all event instances** field on instances — that setting
exists only on the event series.

## Usage

On an **event series**:

- **Ribbon Text** — label shown on the ribbon
- **Ribbon Color** — hex colour (colour picker)
- **Apply to all event instances** — when enabled, the series ribbon is used on
  all instances unless an instance has its own ribbon text

On an **event instance**, set **Ribbon Text** and **Ribbon Color** to override
the series ribbon for that occurrence.

## REST API

Ribbon data is added to the existing DPL endpoint:

`GET /api/v1/events`

When configured, each event may include:

```json
"ribbon": {
  "text": "Udsolgt",
  "color": "#c0392b"
}
```

Events without a ribbon omit the `ribbon` key.
