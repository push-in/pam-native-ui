#!/usr/bin/env bash
set -euo pipefail

root=${1:-.}
root=$(cd "${root}" && pwd -P)
paths=(.build android/.gradle android/build pam-native/target pam-native/android/.gradle pam-native/android/build pam-native/android/app/build pam-native/android/plugin-api/build pam-native/ios/.build)
for relative in "${paths[@]}"; do
  path=${root}/${relative}
  [[ ${path} == "${root}/"* ]] || { printf 'refusing cleanup outside %s: %s\n' "${root}" "${path}" >&2; exit 1; }
  if [[ -e ${path} || -L ${path} ]]; then
    [[ ! -L ${path} ]] || { printf 'refusing symlinked build artifact: %s\n' "${path}" >&2; exit 1; }
    find "${path}" -depth -delete
    printf 'cleaned %s\n' "${relative}"
  fi
done
