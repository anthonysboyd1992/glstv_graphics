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

**Broadcasts** are vMix PCs — GLSTV1, GLSTV2, GLSTV3 — not one-off race
nights. Each box has a stable identifier and data source URLs, its own
sections, cue stack, live text and defaults.

**Text keys** are grouped, and vMix sees them as `Group.key` —
`Rundown.now_racing`, `Break.brb_message`. Groups belong to a **layout**, so
every Dirt Track box shares the same fields and an Awards overlay does not
inherit `now_racing`. Live values and defaults stay per box.

A new broadcast copies a **layout** — image slots and caption groups. Dirt
Track is the starter; add others for studio, awards, or a different overlay
package. Duplicate an existing box if you want that shape and the cue stack
too.

**Assets** are graphics stored once and addressed by content hash. Re-uploading
the same file collapses onto the existing record, so a URL vMix has already
cached never breaks. Dimensions are read on upload. An asset whose shape does
not match a section is still assignable; the grid warns rather than hiding it.

**Cues** are saved picture states you author yourself, one per moment of the
night — "GLSS Hot Laps", "GLSS Heat 1", and so on. You write them in a
spreadsheet: a row per cue, a column per section. Every cell is three-state.
Blank leaves whatever is on air alone, so a cue that only changes the lower
third is a row with one filled cell. Clear empties that section.

Cues never carry text. Captions (`now_racing`, `next_event`, and anything else
you add) are typed live on the board and stay put when you Go Live, so a
caution message you just wrote does not disappear because Heat 2 came up.

Duplicating is the fast path, since Heat 2 is Heat 1 with a different picture.
A copy lands directly beneath its source and only needs renaming.

**The rundown** is those cues in order. Selecting one puts it on deck. Go Live
puts it to air and queues the next, so a night can be run from that one button.
The routing grid on the board is there for anything unplanned.

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

The seeder creates three demo boxes (GLSTV1–GLSTV3) covering the next Saturday,
with the dirt-track layout and a short cue stack. Layouts (reusable slot and
caption catalogs) live under Broadcast → Layouts; a new box copies whichever
one you pick.
To fill the board with obviously-fake graphics sized to each section:

```bash
php artisan broadcast:demo-assets
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
every text field is a column named `Group.key` (for example
`Rundown.now_racing`), and `UpdatedAt` changes whenever anything does.

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
the board's behavior around on-deck and go-live cues, manual overrides, and
upload deduplication.
