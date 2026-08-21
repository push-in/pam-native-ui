#!/usr/bin/env bash

set -euo pipefail

repository_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
temporary_directory=$(mktemp -d)
trap 'find "${temporary_directory}" -depth -delete' EXIT

expect_failure() {
    if "$@" >/dev/null 2>&1; then
        printf 'Expected command to fail: %s\n' "$*" >&2
        exit 1
    fi
}

for copy in one two; do
    "${repository_root}/tools/reproducible-archive.sh" \
        tar.gz HEAD . pam-native-ui-test/ \
        "${temporary_directory}/php-${copy}.tar.gz"
    "${repository_root}/tools/reproducible-archive.sh" \
        zip HEAD ios ios/ \
        "${temporary_directory}/ios-${copy}.zip"
done

cmp "${temporary_directory}/php-one.tar.gz" "${temporary_directory}/php-two.tar.gz"
cmp "${temporary_directory}/ios-one.zip" "${temporary_directory}/ios-two.zip"
tar -tzf "${temporary_directory}/php-one.tar.gz" >"${temporary_directory}/php-files.txt"
unzip -Z1 "${temporary_directory}/ios-one.zip" >"${temporary_directory}/ios-files.txt"
grep -Fqx 'pam-native-ui-test/composer.json' "${temporary_directory}/php-files.txt"
grep -Fqx 'ios/Sources/PamMobileUi/PamMobileUiHost.swift' "${temporary_directory}/ios-files.txt"

expect_failure "${repository_root}/tools/reproducible-archive.sh" \
    zip HEAD ../ios ios/ "${temporary_directory}/escaped.zip"
expect_failure "${repository_root}/tools/reproducible-archive.sh" \
    zip HEAD ios ../ios/ "${temporary_directory}/escaped-prefix.zip"
expect_failure "${repository_root}/tools/reproducible-archive.sh" \
    zip HEAD ios ios/ "${temporary_directory}/ios-one.zip"

printf 'Verified reproducible PHP and iOS source archives.\n'
