#!/usr/bin/env bash
#
# smoke-test.sh - verify a Newt binary still satisfies the assumptions this
# plugin depends on, so an automated Newt bump can't silently ship a build that
# breaks rc.newt or the settings page.
#
# Usage: tests/smoke-test.sh <path-to-newt-binary>
#
# Exit status 0 = all required checks passed, non-zero = something the plugin
# relies on is gone (Newt likely changed in a breaking way).
#
# Note: unlike the Pangolin CLI, Newt has no subcommands - it is a single
# long-running process configured entirely by flags and environment variables.
# So this checks flags and env var names rather than verbs.
#
set -uo pipefail

BIN="${1:?usage: smoke-test.sh <path-to-newt-binary>}"
[ -f "$BIN" ] || { echo "FAIL: binary not found at $BIN"; exit 1; }
chmod +x "$BIN" 2>/dev/null || true

HELP="$("$BIN" --help 2>&1 || true)"

fail=0
pass() { printf 'PASS: %s\n' "$*"; }
bad()  { printf 'FAIL: %s\n' "$*"; fail=1; }
warn() { printf 'WARN: %s\n' "$*"; }

# Newt is a Go program, and Go's flag package lists flags single-dashed in its
# usage output ("  -health-file string") even though the double-dashed form
# works on the command line. So match the flag name itself, allowing either
# number of leading dashes, anchored to the start of a usage line so a flag
# merely mentioned in another flag's description text does not count.
has_flag() {
  local name="${1#--}"
  grep -qE "^[[:space:]]*--?${name}([[:space:]]|=|$)" <<<"$HELP"
}

# 1. The binary runs and reports a version. The settings page parses this, so
#    the "Newt version X.Y.Z" shape matters, not just the exit status.
if VER="$("$BIN" --version 2>&1 | head -n1)" && [ -n "$VER" ]; then
  pass "'newt --version' runs -> ${VER}"
  if grep -qiE '[0-9]+\.[0-9]+\.[0-9]+' <<<"$VER"; then
    pass "version output contains a parseable version number"
  else
    bad "version output has no X.Y.Z number - the settings page cannot parse it"
  fi
else
  bad "'newt --version' did not run"
fi

# 2. Flags rc.newt passes on every start. These are REQUIRED - if any is gone
#    the service script breaks.
for flag in --health-file --log-level --docker-socket; do
  if has_flag "$flag"; then
    pass "flag '${flag}' present"
  else
    bad "flag '${flag}' missing"
  fi
done

# 3. Credentials are passed through the environment, never the command line
#    (that is what keeps the secret out of `ps`). If Newt stopped reading these
#    names, rc.newt would start an unconfigured process that never connects.
#    --show-config reports where each value came from, so a value sourced from
#    "env" proves the variable is still honoured.
if has_flag --show-config; then
  pass "flag '--show-config' present (used to verify env var handling)"
  CFGOUT="$(PANGOLIN_ENDPOINT=https://smoke.test NEWT_ID=smoke-id NEWT_SECRET=smoke-secret \
            "$BIN" --show-config 2>&1 || true)"
  for want in "https://smoke.test" "smoke-id"; do
    if grep -qF "$want" <<<"$CFGOUT"; then
      pass "environment value '${want}' was picked up by newt"
    else
      bad "newt ignored an environment value ('${want}') - PANGOLIN_ENDPOINT/NEWT_ID may have been renamed"
    fi
  done
  # The secret must not be echoed back in full; if it ever is, the settings
  # page and any support paste of --show-config would leak it.
  if grep -qF "smoke-secret" <<<"$CFGOUT"; then
    warn "--show-config prints the secret in clear text (plugin does not expose this output, but be aware)"
  else
    pass "--show-config does not print the secret in clear text"
  fi
else
  warn "flag '--show-config' missing (optional) - cannot verify env var handling"
fi

# 4. Optional flags offered as examples in the settings page's "Additional
#    arguments" help. Missing ones only warn (they are never passed by default).
for flag in --mtu --dns --prefer-endpoint --disable-clients --native; do
  if has_flag "$flag"; then
    pass "flag '${flag}' present"
  else
    warn "flag '${flag}' missing (optional)"
  fi
done

echo
if [ "$fail" -ne 0 ]; then
  echo "RESULT: FAILED - Newt may have changed in a way that breaks the plugin."
  exit 1
fi
echo "RESULT: PASSED"
