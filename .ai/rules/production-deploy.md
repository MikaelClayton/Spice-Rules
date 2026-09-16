# Production deploy

When the user says deploy (or ship to production), run `scripts/deploy-production.sh` from the repo root. Do not invent a one-off rsync. Do not commit unless they asked.

## Do

- Run tests that cover the change first, then `bash scripts/deploy-production.sh` (needs network / `all` permissions and `~/.ssh/id_rsa`).
- PHP-only: `SKIP_BUILD=1 bash scripts/deploy-production.sh`.
- Leave production `.env` alone. Uploaded files live on the VPS (`storage/app/public`); the script never rsyncs them.

## Do not

- `--delete` on rsync (wipes production uploads).
- Copy `public/storage`, `bootstrap/cache`, `.env`, `vendor`, `test.json`, or Firebase keys.
- Leave `public/hot` on the VPS (phones then request CSS from `localhost:5173`). The script deletes it after rsync.
- Skip `chown www-data` after rsync (Mac UIDs make PHP unable to write logos).
- Run `fit-ish:sync --force` as part of a normal deploy (extra Lionheart calls). Missing workout logos are backfilled from saved CDN URLs only.
