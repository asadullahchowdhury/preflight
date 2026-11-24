#!/usr/bin/env bash
# Fails the job when the report reaches the fail-on severity.
set -euo pipefail

fail_on="${PREFLIGHT_FAIL_ON:-danger}"
severity="${PREFLIGHT_SEVERITY:-}"

if [ -z "$severity" ]; then
  echo "::error title=Preflight::No result: the check step did not finish."
  exit 1
fi

case "$fail_on" in
  danger) [ "$severity" = "danger" ] && failed=1 || failed=0 ;;
  warn) [ "$severity" != "info" ] && failed=1 || failed=0 ;;
  never) failed=0 ;;
  *) echo "::error title=Preflight::fail-on must be danger, warn or never, got '$fail_on'."; exit 1 ;;
esac

if [ "$failed" = 1 ]; then
  echo "::error title=Preflight::Migrations are rated '$severity' (fail-on: $fail_on). See the PR comment or job summary for safer alternatives."
  exit 1
fi
echo "Preflight: $severity (fail-on: $fail_on)."
