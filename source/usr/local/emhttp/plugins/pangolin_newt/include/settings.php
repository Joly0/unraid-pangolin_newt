<?php
/* Pangolin Newt - settings form (shared body)
 *
 * Not a page in its own right: PangolinNewt.page (standalone entry) and
 * PangolinNewtTab.page (tab inside the shared "Pangolin" group, used when the
 * Pangolin CLI plugin is also installed) both render this file, so the form
 * exists once no matter which of the two is showing. It is Markdown + PHP,
 * evaluated in that order by the wrappers, exactly as Unraid does for a
 * normal .page.
 *
 * Registers this Unraid server with a Pangolin instance as a *site*, so that
 * resources running on this host can be published through Pangolin. Settings
 * are saved to /boot/config/plugins/pangolin_newt/pangolin_newt.cfg via
 * /update.php and the service is controlled through /etc/rc.d/rc.newt.
 */
$plugin = "pangolin_newt";
$cfg    = parse_plugin_cfg($plugin, true);
$bin    = "/usr/local/bin/newt";
$rc     = "/etc/rc.d/rc.newt";

$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/plugins/$plugin/include/log.php";

$installed = is_file($bin);
/* "newt --version" prints "Newt version 1.15.0"; keep just the number so the
 * status line reads the same way the CLI plugin's does. */
$newtVer   = $installed
           ? trim(preg_replace('/^Newt version\s*/i', '', (string)shell_exec("$bin --version 2>/dev/null | head -n1")))
           : _("not installed");

/* rc.newt reports three states. "running" means the process is up but Newt's
 * health file is absent, i.e. the tunnel is not (yet) passing its ping - which
 * is what a wrong secret or an already-connected site looks like. */
$state     = trim((string)shell_exec("$rc status 2>/dev/null | tail -n1"));
$running   = ($state == "running" || $state == "connected");
$connected = ($state == "connected");

$logLines  = newt_log_tail(50);
$logHtml   = newt_log_render($logLines);

$ENDPOINT   = htmlspecialchars($cfg['PANGOLIN_ENDPOINT'] ?? '');
$NEWT_ID    = htmlspecialchars($cfg['NEWT_ID']           ?? '');
$NEWT_SECRET= htmlspecialchars($cfg['NEWT_SECRET']       ?? '');
$AUTOSTART  = ($cfg['AUTOSTART']          ?? 'no');
$DOCKER     = ($cfg['DOCKER_INTEGRATION'] ?? 'yes');
$LOG_LEVEL  = ($cfg['LOG_LEVEL']          ?? 'INFO');
$SELF_UPDATE= ($cfg['SELF_UPDATE']        ?? 'no');
$EXTRA_ARGS = htmlspecialchars($cfg['EXTRA_ARGS']        ?? '');

if ($connected) {
  $stateColor = 'green-text';
  $stateStyle = '';
  $stateText  = _('Connected');
} elseif ($running) {
  // The theme has no yellow-text class; use its yellow variable directly.
  $stateColor = '';
  $stateStyle = 'color:var(--yellow-500);';
  $stateText  = _('Starting / not connected');
} else {
  $stateColor = 'orange-text';
  $stateStyle = '';
  $stateText  = _('Disconnected');
}
$fieldStyle = 'width:360px;max-width:100%;';
$levels     = ['DEBUG','INFO','WARN','ERROR','FATAL'];
?>

<form markdown="1" name="newt_settings" method="POST" action="/update.php" target="progressFrame">
<input type="hidden" name="#file"    value="pangolin_newt/pangolin_newt.cfg">
<input type="hidden" name="#command" value="plugins/pangolin_newt/scripts/rc.newt">
<?php /* The buttons below set this field via this.form, NOT via an element id.
     When both Pangolin plugins are installed their two settings forms render
     into the SAME document as tabs of the shared "Pangolin" group, so any id
     here would be duplicated and document.getElementById() would return only
     the first one - every button on the second tab would then silently submit
     the other form's default action. Scoping to this.form keeps each tab
     driving its own form. */ ?>
<input type="hidden" name="#arg[1]" value="apply">

_(Status)_:
: <span class="<?=$stateColor?>" style="font-weight:bold;<?=$stateStyle?>"><i class="fa fa-circle"></i> <?=$stateText?></span> &nbsp; <span style="opacity:.7;"><?=_('Version')?>: <?=$newtVer?></span>

_(Pangolin endpoint URL)_:
: <input type="text" name="PANGOLIN_ENDPOINT" style="<?=$fieldStyle?>" value="<?=$ENDPOINT?>" placeholder="https://pangolin.example.com">

> The base URL of your Pangolin instance — the same address you open the dashboard with. For Pangolin Cloud use `https://app.pangolin.net`. Include `https://` and no trailing path. <a href="https://docs.pangolin.net/manage/sites/understanding-sites" target="_blank">Site docs ↗</a>

_(Newt ID)_:
: <input type="text" name="NEWT_ID" style="<?=$fieldStyle?>" value="<?=$NEWT_ID?>" placeholder="newt id">

> The public identifier of a **site**. In the Pangolin dashboard go to **Sites → Add Site** and choose **Newt** as the connection method. Copy the generated **Newt ID** here. A site is the reverse of a client: it *publishes* this server's resources through Pangolin, rather than giving this server access to other resources. <a href="https://docs.pangolin.net/manage/sites/understanding-sites" target="_blank">Site docs ↗</a>

_(Newt secret)_:
: <input type="password" name="NEWT_SECRET" style="<?=$fieldStyle?>" value="<?=$NEWT_SECRET?>" placeholder="newt secret">

> The secret generated together with the Newt ID when the site is created. It is shown only once in the dashboard, so copy it immediately — treat it like a password. It is passed to Newt through the environment, so unlike a command-line flag it does **not** appear in the server's process list.

_(Start automatically on boot)_:
: <select name="AUTOSTART" size="1" style="<?=$fieldStyle?>">
  <option value="yes" <?=$AUTOSTART=='yes'?'selected':''?>><?=_('Yes')?></option>
  <option value="no"  <?=$AUTOSTART=='no' ?'selected':''?>><?=_('No')?></option>
  </select>

> When set to **Yes**, the site connects automatically every time the server boots. When **No**, connect manually with the buttons below.

_(Expose Docker containers to Pangolin)_:
: <select name="DOCKER_INTEGRATION" size="1" style="<?=$fieldStyle?>">
  <option value="yes" <?=$DOCKER=='yes'?'selected':''?>><?=_('Yes')?></option>
  <option value="no"  <?=$DOCKER=='no' ?'selected':''?>><?=_('No')?></option>
  </select>

> Gives Newt read-only access to this server's Docker socket so the Pangolin dashboard can **list your containers** when you pick a target for a resource, instead of you typing IP addresses and ports by hand. Newt only reads the container list; it never starts, stops or changes anything. Set to **No** to keep the socket private. <a href="https://docs.pangolin.net/manage/sites/understanding-sites" target="_blank">Docs ↗</a>

_(Log level)_:
: <select name="LOG_LEVEL" size="1" style="<?=$fieldStyle?>">
  <?foreach ($levels as $l):?>
  <option value="<?=$l?>" <?=$LOG_LEVEL==$l?'selected':''?>><?=$l?></option>
  <?endforeach;?>
  </select>

> How much detail Newt writes to its log, shown below. **INFO** (default) records connects, disconnects and errors. Raise to **DEBUG** when troubleshooting a tunnel that will not come up — it is noisy, so lower it again afterwards. **WARN** and above keep the log quiet.

_(Allow Newt to update itself)_:
: <select name="SELF_UPDATE" size="1" style="<?=$fieldStyle?>">
  <option value="no"  <?=$SELF_UPDATE=='no' ?'selected':''?>><?=_('No')?></option>
  <option value="yes" <?=$SELF_UPDATE=='yes'?'selected':''?>><?=_('Yes')?></option>
  </select>

> **No** (default) means this plugin decides which Newt version runs, and new versions arrive as plugin updates that you can review first. **Yes** lets Newt fetch newer builds from your Pangolin server on its own (shortly after start, then every few hours) and restart into them; the plugin saves each new build to the flash drive so it survives a reboot. Choose **Yes** if you would rather track your Pangolin server's expected version automatically.

_(Additional arguments)_:
: <input type="text" name="EXTRA_ARGS" style="<?=$fieldStyle?>" value="<?=$EXTRA_ARGS?>" placeholder="--mtu 1280 --prefer-endpoint 203.0.113.10">

> Optional extra flags appended to the `newt` command. Run `newt --help` in an Unraid terminal for the full list. Common ones: `--mtu 1280` (tune packet size), `--prefer-endpoint <host>` (force a specific server endpoint), `--dns 1.1.1.1` (resolver Newt uses for targets), `--disable-clients` (do not accept Pangolin clients), `--native` (use a kernel WireGuard interface instead of user space).

&nbsp;
: <input type="submit" style="<?=$fieldStyle?>" value="_(Apply)_" onclick="this.form.elements['#arg[1]'].value='apply';"<?=$installed?'':' disabled'?>>
<?if ($running):?><input type="submit" style="<?=$fieldStyle?>" value="_(Disconnect)_" onclick="this.form.elements['#arg[1]'].value='stop';">
<?else:?><input type="submit" style="<?=$fieldStyle?>" value="_(Connect)_" onclick="this.form.elements['#arg[1]'].value='start';"<?=$installed?'':' disabled'?>>
<?endif;?>
<input type="button" style="<?=$fieldStyle?>" value="_(Done)_" onclick="done()">
</form>

<?if (!$installed):?>
<blockquote class="orange-text"><?=_('The Newt binary is not installed. Reinstall the plugin or check your internet connection.')?></blockquote>
<?endif;?>

<style>
  pre.newt-log{max-height:300px;overflow:auto;}
  pre.newt-log span{display:block;padding:0 4px;}
  pre.newt-log span.newt-connect{color:var(--green-800);background:var(--green-100);font-weight:bold;}
  pre.newt-log span.newt-disconnect{font-weight:bold;opacity:.85;}
</style>

<table class="tablesorter shift ups"><thead><tr><th><?=_('Recent log')?>
  &nbsp; <a href="/plugins/pangolin_newt/include/logwin.php" target="_blank"
    onclick="window.open(this.href,'newt_log','width=900,height=600,scrollbars=yes,resizable=yes');return false;"><?=_('View full log')?> &#8599;</a>
</th></tr></thead></table>
<?if ($logHtml):?>
<pre class="newt-log"><?=$logHtml?></pre>
<?else:?>
<pre class="newt-log"><span class="text" style="opacity:.7;"><?=_('No log entries yet.')?></span></pre>
<?endif;?>
