# CMS maintenance scripts

Run from the Drupal root (`cms/`).

## Delete all Kulturnat events and submissions

Removes every `eventinstance`, `kultur_event_submission`, and `eventseries`. Safe for Drush (disables DPL event redirects during delete).

```bash
cd ~/kulturnat-eonext.stg.inlead.dev/public_html/cms

./vendor/bin/drush --uri=https://kulturnat-eonext.stg.inlead.dev php:script scripts/delete-kulturnat-events.php
```

Replace `--uri` with the site’s canonical URL if different.

Afterwards:

```bash
./vendor/bin/drush cr
./vendor/bin/drush search-api:reset-tracker events
./vendor/bin/drush search-api:index events
```

**Warning:** irreversible. Use on staging/local unless you intend to wipe production events.
