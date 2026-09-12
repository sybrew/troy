---
name: playground
description: >-
  Launches a local WordPress Playground site with Troy Client, Server, Daemon,
  and/or Installer mounted, intercepts repo.deploytroy.org with a faux API, and
  drives admin/REST A/B via Playwright MCP. Use when testing Troy
  implementations, edge cases, update/info/ZIP/ping flows, Site Health, install
  paths, admin UI, or before calling output-affecting work done.
---
# Playground regression

Do not write PHPUnit. Do not use Local WP, Docker, or MySQL. The engine is wp-plugin-regression.

If launch, harness, permalinks, official intercept, or Playwright login looks wrong, decide whether the cause is a Troy package or the regression plumbing (Playground, engine, mounts, cookies, rewrites, mu-plugins, the harness). When the plumbing is at fault, say so in the chat. Fix or report that system. Do not work around it in plugin code. Never change Troy Server, Client, Daemon, or Installer so they fit a broken harness.

When **not** to boot: wrap, trailing-comma, minify-only, docs-only.

When to run:

- Admin UI, update API, endpoints, headers, install, Site Health
- Support reproduction
- Before calling that work done

PHP edits on the working-tree mount apply on the next request. Launch takes the next free port in `9001`–`9099` (shared with other consumers of this engine). Read the URL from launch output or `~/.wordpress-playground/tests/runs.json`. Do not assume port `9410`. Restart after `--wp` / `--php`, a different `--site`, or a different `--package` set. Do not reuse persist across majors or `latest` vs `trunk`.

Captures are logged-out. Launch does not pass `--login`.

Do not `flush_rewrite_rules()` in a blueprint `runPHP` step. That writes incomplete rules and post permalinks fall through to the homepage. Set `permalink_structure` and `delete_option( 'rewrite_rules' )` so Playground can flush on a later `init`.

Playground 301s `/sitemap.xml` via a VFS mu-plugin (`sitemap-redirect.php`). The engine overwrites that file with a no-op until https://github.com/WordPress/wordpress-playground/issues/4325 is patched. Drop the overwrite when that issue lands. If every request 500s with a parse error in that file, the no-op was invalid PHP. Fix `lib/launch.js` in wp-plugin-regression; do not change a Troy package.

If `~/.wordpress-playground/tests/runs.json` lists a live pid for that port, reuse it.

`waitReady` waits for the Playground CLI line `Ready! WordPress is running` (blueprint finished) plus HTTP, not the first mid-install 200. A blueprint error in `server.log` fails launch even if the pid is still up.

Do not pass `--plugin=wporg`. Troy packages are not on wordpress.org. Do not use `launch --pair`.

## Permission

Read `.local/playground/permission.txt` with an explicit path before cloning the engine. Grep/Glob cannot see `.local/`.

Never invent `True` or `False`. Never write this file unless the user has replied with the flag.

Format is always one line (`True` or `False`, never guessed):

```
PLAYGROUND=True|False
```

- If the file does not exist, prompt. If you cannot prompt and no permissions exist, skip Playground altogether and ask at the end of the chat.
- After the user replies with the flag, create the file from that reply.
- If the flag is `False`, do not prompt again, and do not clone or launch.
- If the flag is `True`, proceed. After a permitted import, keep that flag `True`.

The playground script reads this file. A missing file is a hard stop. A `False` flag skips even if the engine is already cloned.

## Engine

Public source of truth:

- https://github.com/theseoframework/wp-plugin-regression → `.local/playground/wp-plugin-regression`

If permission is `True` and `run.js` is missing, clone that repo and run `npm install` in it. Do not copy the engine from elsewhere; clone the public repo above.

Override the path with `WP_PLUGIN_REGRESSION_DIR` only when the user sets it (local engine clone, e.g. `C:\GitHub\wp-plugin-regression`).

`--root` is this monorepo. Package folders are `dir` values in `packages.json`.

## Official repo

Playground networking is on. Client and Server ship `Troy: repo.deploytroy.org`. Daemon, Installer, and Embed hardcode `https://repo.deploytroy.org/plugin/get/zip/troy-client/`. Each new site mints `troy_client_site_unique_id` and would POST stats to production.

`0-troy-playground-official.php` intercepts host `repo.deploytroy.org` on `pre_http_request` and returns faux ping / updates / info / stats / tags / zip. It never proxies. Swallow UUID and stats. Leave `deploytroy.org` docs links alone. ZIP is always built from a mounted plugin or `uploads/troy-playground-src/{slug}` so Daemon/Installer get the working tree, not a production release. An unmounted slug 404s. Do not fall through to official ZIP.

Playground WASM unhooks WordPress.org update cron and disables curl/streams. That does not make the faux optional. Site Health, `plugins_api`, Daemon ZIP, and anything that sets `update_plugins` still call `wp_remote_*`.

When the Server public API changes, update that mu-plugin in the same change.

Do not add a product `is_dev` handler for this harness.

## Packages

`--package=troy-client` (repeatable or comma-separated). Default `troy-client`. `all` mounts every catalog entry.

```
node .cursor/skills/playground/scripts/playground.js launch
node .cursor/skills/playground/scripts/playground.js launch --package=troy-server
node .cursor/skills/playground/scripts/playground.js launch --package=troy-client,troy-server
node .cursor/skills/playground/scripts/playground.js launch --package=troy-client-daemon
node .cursor/skills/playground/scripts/playground.js launch --package=all
```

- `troy-client` / `troy-server` / `troy-installer` — regular plugins, activated unless noted.
- `troy-client-daemon` — product file is mounted under `mu-plugins/troy-client-daemon/`. `1-troy-playground-daemon.php` loads it after `0-troy-playground-official.php`. It skips `wp_installing()` so `install.php` does not run Daemon before `wp_options` exists, and skips when Client's main file is in persist but `includes/api.php` is not. When Client and Daemon are both selected, `playground.js` hardlinks Client directories into persist via `extraMounts` (and drops those dirs from `plugin.mounts` so Playground does not double-mount).
- Daemon or Installer without Client: Client is mounted as a zip-source under `uploads/troy-playground-src/troy-client`, not under `plugins/`. The faux ZIP handler builds a package from that tree. Daemon then installs Client.
- Client + Daemon: Client is mounted and activated. Daemon becomes a no-op install.

Official, Playwright admin, and capture-frame shims always load. `3-troy-playground-frames.php` handles `wp_plugin_regression_frame` `blog` (posts on front) so `capture` does not throw `Frame blog failed`. Do not copy TSF SEO surfaces.

The first `--package` plugin is the persist slug (`troy-client` by default). Persist is `~/.wordpress-playground/tests/<slug>/<version>/<site>/`.

## Command

From the repo root:

```
node .cursor/skills/playground/scripts/playground.js launch
node .cursor/skills/playground/scripts/playground.js stop
node .cursor/skills/playground/scripts/playground.js stop --port=9001
node .cursor/skills/playground/scripts/playground.js capture --label before
node .cursor/skills/playground/scripts/playground.js capture --label after
node .cursor/skills/playground/scripts/playground.js compare --before before --after after
node .cursor/skills/playground/scripts/playground.js surfaces
node .cursor/skills/playground/scripts/playground.js harness --action ping
node .cursor/skills/playground/scripts/playground.js harness --json-file .local/playground/payload.json
```

Optional launch flags: `--wp=latest`, `--php=8.4`, `--site=default`, `--port`, `--keep`. Launch picks the next free port in `9001`–`9099`. Pin with `--port`. `--keep` reuses persist instead of wiping. Default PHP is 8.4 (Server). Client-only version-sensitive work may use `--php=7.4`.

## WordPress and PHP versions

`--wp` is a Playground **build slug**, not a path to Core. Do not reuse persist across majors or `latest` vs `trunk`.

Slugs the CLI accepts: `latest` (default), `beta`, `trunk` (`nightly` is the same), a hosted major/minor (`7.0`, `6.9`, `6.9.1`), a beta/RC (`6.8-RC1`), or a zip URL. PHP is `--php` (`7.4`–`8.5`). The wrapper defaults PHP to `8.4` when you omit `--php`.

`@wp-playground/wordpress` `resolveWordPressRelease()` turns the slug into `{ version, releaseUrl }` (`latest` → `7.1` plus the zip URL). Playground stores the zip as `~/.wordpress-playground/<version>.zip`. The engine unpacks a slim tree at `~/.wordpress-playground/wp/<version>/` (same version token), keeps `WP_DEFAULT_THEME`, strips other bundled Twenty* themes, and mounts with `install-from-existing-files`.

Do not write sites under `.local/playground/sites` (that tree is inside the synced repo). Persist is `~/.wordpress-playground/tests/<slug>/<version>/<site>/`. Launch wipes that folder unless `--keep`. Captures stay in `.local/playground/captures/`. Old folders under `.local/playground/sites/` are unused; delete them locally if they are still syncing.

`--wp=trunk` is the prebuilt WordPress/WordPress nightly. It is not `wordpress-develop` and not `--wp=7.2`. `--wp=7.2` only works if Playground hosts a 7.2 release or beta zip. Do not mount `wordpress-develop/src` (or its `build/`) as `/wordpress`. That is not implemented.

Use `--wp` / `--php` only when the change can be version-sensitive. Not a full matrix on every edit.

## Server on SQLite

Troy Server schema is InnoDB + `ON DUPLICATE KEY UPDATE` + `SHOW TABLE STATUS`. Playground is SQLite. Mounting Server is allowed. If upgrade records a database block or public API 503s, the harness is at fault. Do not patch Server for SQLite.

`2-troy-playground-server-sqlite.php` loads when Server is selected. It seeds `$wpdb->dbh->errno` so `record_database_block()` does not call `PDO::errorInfo()` on Playground's uninitialized `WP_MySQL_On_SQLite` (that 255s `activatePlugin`). errno `0` records a block. It also writes blueprint fatals to persist `debug.log`.

Client, Daemon, and Installer are SQLite-safe.

## A/B previous vs current

Troy is mostly the Playwright path: Server admin, Client Site Health, update/REST, install. HTTP `capture` / `compare` is a short public catalog (`/`, `/sample-page/`). Do not copy TSF SEO surfaces.

The working tree is mounted live. PHP edits apply on the next request. There is no “previous plugin” left on disk after you edit. Pick one previous:

1. **This session, not yet edited.** `launch`, `capture --label before`, edit, `capture --label after`, `compare --before before --after after`.
2. **Already edited, or vs gold.** `capture --label after`, `compare --before baseline --after after`. Requires `.local/playground/captures/baseline.json` from the same catalog on `latest`. If that file is missing or the catalog changed, say so. Do not capture `before` from the dirty tree and call it previous.

Do not git checkout, stash, or mount another Core tree to fake previous. `--plugin=wporg` is rejected.

Admin UI, settings, and REST-from-the-browser A/B is Playwright MCP against the live Playground URL after `launch`. The engine does not drive a browser. `playwright.env` in Cursor settings is the Test extension, not the agent. Cursor’s built-in Browser Automation conflicts with Playwright MCP; use the MCP. Default captures stay logged-out.

Playwright MCP must load `.cursor/skills/playground/playwright.mcp.json`. This repo's `.cursor/mcp.json` sets that `--config` so a user-level `%USERPROFILE%\.cursor\mcp.json` still pointing at TSF does not win in this workspace. That file strips Chrome's `--disable-blink-features=AutomationControlled` flag and sends `X-Troy-Playground-Admin: 1`. `troy-playwright-admin.php` also accepts `X-TSF-Playground-Admin` until the MCP is restarted onto the Troy config. For admin/REST, navigate to the live origin from launch or `runs.json` (`http://127.0.0.1:<port>/wp-admin/` or `/wp-json/`). Do not fill `admin` / `password` — Cursor Auto-review blocks that. `troy-playwright-admin.php` calls `wp_set_auth_cookie()` when the header is present. That is the login. Do not add `.cursor/permissions.json`. Do not pass Playground `--login` or a blueprint `login` step (HTTP capture would share that session). Front-end HTML stays logged-out. Restart the Playwright MCP server after changing `mcp.json` or the config.

Mutate the live site by writing mu-plugins under `~/.wordpress-playground/tests/<slug>/<version>/<site>/mu-plugins/`, or `harness --json-file <path>`. Do not pass `--json "{...}"` from PowerShell; it strips the quotes. Do not add `eval`.

One capture writes `.local/playground/captures/<label>.json`. It is a local gold file, not a CI gate. Do not hand-edit it.

`stop` waits for the site SQLite file to unlock. `stop` with no `--port` stops every run for `--root`. Default launch wipes persist (`--keep` to reuse). If SQLite stays locked, retry stop, then launch.

If the script exits non-zero, fix the failure.
