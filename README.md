# unraid-pangolin_newt

An Unraid plugin that installs [Newt](https://github.com/fosrl/newt) and adds a settings page to register your server with a [Pangolin](https://pangolin.net) instance as a **site**. This lets you publish resources running on your Unraid host (Docker containers, web UIs, services) through Pangolin.

> **Site or client?** A *site* (this plugin) exposes this server's resources *to* Pangolin. A *client* (the [Pangolin CLI plugin](https://github.com/Joly0/unraid-pangolin_cli)) gives this server access *to* private resources elsewhere. They are complements, not alternatives, and can be installed side by side.

## Install

Add the following URL in **Plugins > Install Plugin** (or via Community Apps):

```
https://github.com/Joly0/unraid-pangolin_newt/raw/main/pangolin_newt.plg
```

## Usage

1. In your Pangolin dashboard, create a **site**, choose **Newt** as the connection method, and copy its **Newt ID** and **Newt secret**.
2. On Unraid, open **Settings > Pangolin Newt**.
3. Enter your **endpoint URL**, **Newt ID** and **Newt secret**.
4. Click **Apply** to connect. Optionally enable **Start automatically on boot**.

The `newt` binary is also available from the Unraid terminal.

## Shared Settings entry

When the [Pangolin CLI plugin](https://github.com/Joly0/unraid-pangolin_cli) is installed alongside this one, the two collapse into a single **Settings > Pangolin** entry with a **CLI** and a **Newt** tab, rather than taking two slots in Network Services. Install either one on its own and it keeps its own entry.

This works through Unraid's native page mechanism, with no coordination at install time: each plugin ships three `.page` files (a standalone entry, a tab, and an identical copy of the shared group container), and each one's `Cond=` tests whether the *other* plugin's tab page is present. Removing either plugin restores the other's standalone entry automatically. Testing for the tab file rather than the plugin directory means an older, pre-grouping version of the other plugin is also handled: both simply keep their own entry.

## How it works

- The webGui files (settings page + `/etc/rc.d/rc.newt` service script) ship in a Slackware `.txz` package under `packages/`, reinstalled on every boot.
- The `newt` binary is downloaded from the [fosrl/newt](https://github.com/fosrl/newt) releases to the flash drive (`/boot/config/plugins/pangolin_newt/`) and restored to `/usr/local/bin/newt` on each boot (Unraid's root filesystem is volatile).
- Credentials are passed to Newt **through the environment** (`PANGOLIN_ENDPOINT`, `NEWT_ID`, `NEWT_SECRET`), so the secret does not appear in the process list.
- Newt runs fully in user space (netstack): no network interface is created, `/etc/resolv.conf` is untouched, and no firewall rules are needed. A TUN device is only set up if you pass `--native` yourself.
- **Connected** state comes from Newt's own health file (`--health-file`), which it writes once its tunnel ping succeeds and removes when the connection drops — a live process alone is not treated as a working tunnel.
- **Connecting retries, and a watchdog keeps it up.** Newt's registration handshake with the server fails intermittently (it logs `SendMessageInterval timed out ... newt/wg/register`), and when it does it keeps the process alive *without ever retrying*, so the site stays down indefinitely. `Connect` therefore relaunches Newt until the tunnel reports healthy, and a cron watchdog (every 5 minutes) restarts it if it drops or never came up. The watchdog only acts while the service is meant to be connected, so pressing **Disconnect** is never undone.
- Connection settings are stored in `/boot/config/plugins/pangolin_newt/pangolin_newt.cfg`.

### Self-update

Newt can update its own binary from your Pangolin server. Because `/usr/local/bin` is RAM on Unraid, that update would be lost on reboot, so the plugin defaults **Allow Newt to update itself** to **No** (via Newt's documented `NEWT_SYSTEM_SUBSTRATE=CONTAINER` opt-out) and ships new versions as plugin updates instead.

Set it to **Yes** and a cron job (`rc.newt sync-binary`, every 10 minutes) copies any self-updated binary back to the flash drive so it survives a reboot. The `.plg` version check only re-downloads when the flash copy is *older* than the bundled version, so a self-updated binary is never dragged back down.

## Development

Edit files under `source/`, then rebuild the package and refresh the `.plg` MD5:

```bash
./build.sh
```

Bump `<!ENTITY version>` (and `newtVersion` when updating Newt) in `pangolin_newt.plg` before building a release. Commit the regenerated `packages/*.txz` and the updated `.plg`.

Tests:

```bash
tests/smoke-test.sh <path-to-newt-binary>    # Newt still has the flags/env vars the plugin uses
tests/package-test.sh packages/<file>.txz    # package is installable (exec bits, ownership, MD5)
```

## Repository layout

```
pangolin_newt.plg                                 installer
build.sh                                          packages source/ -> packages/*.txz
packages/                                         built .txz (committed, served via raw)
source/
  etc/rc.d/rc.newt                                service control (start/stop/status)
  etc/cron.d/pangolin_newt                        flash sync for self-updates
  etc/logrotate.d/pangolin_newt                   log rotation
  install/slack-desc                              Slackware package metadata
  usr/local/emhttp/plugins/pangolin_newt/
    Pangolin.page                                 shared "Pangolin" group (both plugins ship this)
    PangolinNewt.page                             standalone Settings entry
    PangolinNewtTab.page                          tab inside the shared group
    include/settings.php                          the settings form, rendered by both of the above
    include/log.php                               log tail + colour-coding helpers
    include/logwin.php                            full-log popup
    scripts/rc.newt                               webGui wrapper for /update.php
    README.md                                     plugin description
```

## Trademarks & attribution

This is an **unofficial, community-maintained** plugin. It is **not affiliated with,
sponsored by, or endorsed by Fossorial, Inc.**

"Pangolin" and the Pangolin logo are trademarks of Fossorial, Inc. The Pangolin
logo images bundled with this plugin
(`source/usr/local/emhttp/plugins/pangolin_newt/pangolin_newt.png` and
`pangolin.png`) are the property of Fossorial, Inc., taken from the
[fosrl/pangolin](https://github.com/fosrl/pangolin) repository, and are used
solely to identify the Pangolin software this plugin integrates with.

The Newt binary installed by this plugin is published by Fossorial, Inc. via
[fosrl/newt](https://github.com/fosrl/newt) and is downloaded from its official
releases at install time; it is licensed under its own terms (AGPL-3.0, with
commercial licensing available from Fossorial, Inc.).

See [NOTICE](NOTICE) for details.
