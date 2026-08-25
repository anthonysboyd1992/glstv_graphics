# Dirt Broadcast

A control surface for running dirt track broadcasts in vMix. You point graphics
at sections from a routing grid, vMix polls a per-show data source, and the
picture changes.

Nothing is ever pushed to vMix. The application owns the state and publishes it
at a URL; vMix pulls on its own refresh interval. That one decision shapes
everything else here: there is no command that can be lost, a vMix restart
recovers by itself, and a broadcast machine can be rebuilt mid-show without
touching this app.

## How it fits together

**Show templates** describe the shape of a broadcast. A template owns sections
(`ScoreBug`, `LowerThird`, …), each with an expected pixel size; text keys
(`now_racing`, `brb_message`, …); and asset roles for the graphics that swap
between events.

**Assets** are graphics stored once and addressed by content hash. Re-uploading
the same file collapses onto the existing record, so a URL vMix has already
cached never breaks. Dimensions are read on upload and used to keep a 1920x180
score bug out of a 500x500 slot.

**Asset packs** map roles to concrete assets. A pack per track or per series
lets you swap a night's worth of sponsor and class graphics in one move instead
of reassigning every section.

**Looks** are saved cue states: a set of section assignments and text values.
Each item either sets a section, clears it, or leaves it alone, so a look can
change the lower third without disturbing the score bug.

**The rundown** is looks in order. Generate a night from a race program and you
get a cue per class and phase, with `now_racing` and `next_event` already
written. Then it's Next, Next, Next all night, with the routing grid there for
anything unplanned.

## Requirements

- PHP 8.4+ (developed against 8.5)
- Node 20+ (developed against 24)
- SQLite for local work, PostgreSQL or MySQL in production
- An S3 bucket for assets in production

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
```

The seeder creates a dirt track template, race classes, and a demo show with a
full rundown. To fill the board with obviously-fake graphics sized to each
section:

```bash
php artisan broadcast:demo-assets --fill-pack
```

If you use Herd, isolate the site to a supported PHP version, since the default
may be older than the project requires:

```bash
herd isolate 8.5
```

Blade files written after the last build will be missing their Tailwind classes
until you re-run `npm run build`. If a screen renders unstyled or with the wrong
layout, that is almost always why.

## Wiring up vMix

Each show has a UUID, and its data sources hang off it. Open a show's board and
click **Data source URLs** to copy them.

```
https://your-host/ds/{uuid}/live.json?token={token}
https://your-host/ds/{uuid}/live.xml?token={token}
https://your-host/ds/{uuid}/rundown.json?token={token}
https://your-host/ds/{uuid}/rundown.xml?token={token}
```

The live feed is a single row. Every section is a column holding an image URL,
every text key is a column holding a string, and `UpdatedAt` changes whenever
anything does.

In vMix, open **Settings → Data Sources → Add**, choose JSON or XML depending on
which your title expects, paste the URL, and set the refresh interval. One or
two seconds is usually right; the payload is small and the endpoints are cheap.
Then map the title fields: image fields to section columns, text fields to text
key columns.

Both JSON and XML are published because vMix titles vary in what they accept.
Use whichever binds cleanly, and don't be surprised if you end up with both on
different inputs.

The token is per show and lives in the query string. Rotating it invalidates
every URL for that show, so re-copy them into vMix afterward.

## Deploying to EC2

Run the app on its own instance and let the vMix boxes reach it over the VPC.
Each vMix instance keeps its Elastic IP for you to RDP into; the app doesn't
need to know about any of them, because traffic only flows the other way.

Assets belong in S3. Add a **VPC gateway endpoint for S3** so image pulls stay
on the AWS backbone instead of routing out through a NAT gateway — cheaper, and
one less thing between vMix and a graphic mid-race.

The usual production steps apply:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm run build
```

Put the app behind HTTPS. vMix will happily poll either, but the URLs carry
tokens.

A note on scale: the data source endpoints are stateless and read a single show
row. If you're running several broadcasts off one app instance, they will not be
what breaks.

## Testing

```bash
php artisan test
```

Feature tests cover every screen, the data source endpoints in both formats, and
the board's behavior around applying looks, stepping the rundown, manual
overrides, role resolution, and upload deduplication.
