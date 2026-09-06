#!/usr/bin/env bash
set -euo pipefail

root=${1:-.}
root=$(cd "${root}" && pwd -P)
cleanup_failures=0
paths=(
  .build
  android/.gradle
  android/build
  examples/kitchen-sink/.pam-native
  examples/kitchen-sink/vendor/pushinbr/pam-native-ui/examples/kitchen-sink/.pam-native
  pam-native/target
  pam-native/android/.gradle
  pam-native/android/build
  pam-native/android/app/build
  pam-native/android/plugin-api/build
  pam-native/ios/.build
  pam-cli/target
  pam-native-candidate/target
)
for relative in "${paths[@]}"; do
  path=${root}/${relative}
  [[ ${path} == "${root}/"* ]] || { printf 'refusing cleanup outside %s: %s\n' "${root}" "${path}" >&2; exit 1; }
  if [[ -e ${path} || -L ${path} ]]; then
    [[ ! -L ${path} ]] || { printf 'refusing symlinked build artifact: %s\n' "${path}" >&2; exit 1; }
    blocked=$(find "${path}" -type d ! -writable -print -quit)
    if [[ -n ${blocked} ]]; then
      printf 'cannot clean %s: non-writable build directory %s\n' "${relative}" "${blocked}" >&2
      cleanup_failures=$((cleanup_failures + 1))
      continue
    fi
    if find "${path}" -depth -delete; then
      printf 'cleaned %s\n' "${relative}"
    else
      cleanup_failures=$((cleanup_failures + 1))
    fi
  fi
done

if (( cleanup_failures > 0 )); then
  printf '%d build artifact root(s) require ownership repair before cleanup\n' "${cleanup_failures}" >&2
  exit 1
fi
