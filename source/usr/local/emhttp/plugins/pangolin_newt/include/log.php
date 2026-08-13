<?php
/* Pangolin Newt - shared log helpers
 *
 * Used by the settings page (inline recent-log tail) and by logwin.php
 * (the full-log popup). Reads across the current log plus any rotated
 * files (pangolin_newt.log, .log.1, .log.2 ...) so the view stays
 * populated right after a logrotate copytruncate, and colour-codes lines
 * (connect / disconnect / warning / error) consistently in both places.
 */

if (!defined('NEWT_LOG')) {
    define('NEWT_LOG', '/var/log/pangolin_newt.log');
}

/* Current + rotated log files, oldest first (chronological reading order).
 * Compressed rotations (.gz) are skipped - rotation is configured without
 * compression so every file is readable plain text. */
function newt_log_files(): array {
    $files = array_filter(glob(NEWT_LOG . '*') ?: [], fn($f) => !str_ends_with($f, '.gz'));
    usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b));
    return array_values($files);
}

/* CSS class for a log line. Connect/disconnect markers are matched first so
 * they win over the error/warning rules. Newt logs Go-style line-leading
 * level tokens ("ERROR: 2026/07/12 15:33:06 msg" - see logger/writer.go
 * upstream), so those are the primary signal; a short keyword fallback catches
 * messages relayed from elsewhere. Bare 4xx/5xx numbers and routine words
 * (retry, timeout, cannot) are deliberately not matched - they flagged byte
 * counts and normal reconnect chatter as errors.
 *
 * Two kinds of line get the connect highlight: the "==== ... ====" banners
 * rc.newt writes around start/stop, and Newt's own success message, which is
 * the moment the tunnel actually starts passing traffic. */
function newt_log_class(string $line): string {
    /* Markers first: they are ours and unambiguous. */
    if (preg_match('/====\s*newt (connect|start|self-updated)|tunnel connection to server established/i', $line)) {
        return 'newt-connect';
    }
    if (preg_match('/====\s*newt (disconnect|stop)/i', $line)) {
        return 'newt-disconnect';
    }
    /* Then the level token, which is authoritative and must beat any keyword
     * in the message body. Newt logs "WARN: ... Ping attempt 2 failed: ..."
     * on every transient reconnect; matching the word "failed" first would
     * paint routine chatter red, which is exactly the false-positive problem
     * the keyword list below was already trimmed to avoid. */
    if (preg_match('/^(erro|error|fatal|panic):?\s/i', $line))  return 'error';
    if (preg_match('/^(warn|warning):?\s/i', $line))            return 'warn';
    if (preg_match('/^(info|debug|trace):?\s/i', $line))        return 'text';
    /* No level token: a line relayed from elsewhere, or one of our own plain
     * messages. Fall back to keywords. Bare 4xx/5xx numbers and routine words
     * (retry, timeout, cannot) are deliberately not matched - they flagged
     * byte counts and normal reconnect chatter as errors. */
    if (preg_match('/\b(error|fatal|panic|failed|failure|refused|denied|unauthorized|forbidden)\b/i', $line)) {
        return 'error';
    }
    if (preg_match('/\b(warn(ing)?|deprecated)\b/i', $line)) {
        return 'warn';
    }
    return 'text';
}

/* Render lines as colour-coded <span> blocks for a <pre>. */
function newt_log_render(array $lines): string {
    $out = '';
    foreach ($lines as $line) {
        $line = rtrim($line, "\r\n");
        $cls  = newt_log_class($line);
        $out .= '<span class="' . $cls . '">' . htmlspecialchars($line === '' ? ' ' : $line) . "</span>\n";
    }
    return $out;
}

/* Last $n lines of one file, reading backwards in 8 KB chunks so a large
 * (multi-MB verbose) log never gets slurped whole. Line semantics match
 * file(FILE_IGNORE_NEW_LINES): no trailing newlines in elements, a final
 * unterminated line is included. */
function newt_tail_file(string $f, int $n): array {
    if ($n <= 0) {
        return [];
    }
    $fh = @fopen($f, 'rb');
    if ($fh === false) {
        return [];
    }
    fseek($fh, 0, SEEK_END);
    $pos = ftell($fh);
    $buf = '';
    while ($pos > 0) {
        $read = min(8192, $pos);
        $pos -= $read;
        fseek($fh, $pos, SEEK_SET);
        $buf = fread($fh, $read) . $buf;
        /* >$n newlines guarantees the last $n lines are complete even if the
         * chunk boundary split the first line in $buf. */
        if (substr_count($buf, "\n") > $n) {
            break;
        }
    }
    fclose($fh);
    if ($buf === '') {
        return [];
    }
    $lines = explode("\n", $buf);
    if (end($lines) === '') {
        array_pop($lines);   // trailing newline, not an empty last line
    }
    return array_slice($lines, -$n);
}

/* Last $n lines across current + rotated files (newest content kept). */
function newt_log_tail(int $n): array {
    $buf = [];
    foreach (array_reverse(newt_log_files()) as $f) {
        $need = $n - count($buf);
        if ($need <= 0) {
            break;
        }
        $buf = array_merge(newt_tail_file($f, $need), $buf);
    }
    return $buf;
}

/* Tail across all files, capped to the last $max lines (default 5000).
 * Non-positive $max means the default, never unlimited - the popup must not
 * load an unbounded amount of log into memory. */
function newt_log_all(int $max = 5000): array {
    return newt_log_tail($max > 0 ? $max : 5000);
}
