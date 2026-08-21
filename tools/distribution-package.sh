#!/usr/bin/env bash

set -euo pipefail

repository_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
package_name=pushinbr/pam-native-ui

fail() {
    printf 'distribution-package: %s\n' "$*" >&2
    exit 1
}

validate_tag() {
    local release_tag=$1
    local version
    version=$(<"${repository_root}/VERSION")

    [[ ${release_tag} =~ ^v([0-9]+)\.([0-9]+)\.([0-9]+)$ ]] ||
        fail "release tag must use stable SemVer with a v prefix"
    [[ v${version} == "${release_tag}" ]] ||
        fail "${release_tag} does not match VERSION ${version}"
    grep -Fqx "## ${version}" "${repository_root}/CHANGELOG.md" ||
        fail "CHANGELOG.md does not contain ${version}"
}

create_distribution_commit() {
    local source_ref=$1
    local release_tag=$2
    local source_commit
    local source_tree
    local source_date

    source_commit=$(git -C "${repository_root}" rev-parse "${source_ref}^{commit}")
    source_tree=$(git -C "${repository_root}" rev-parse "${source_ref}^{tree}")
    source_date=$(git -C "${repository_root}" show -s --format=%aI "${source_commit}")

    printf 'PAM Mobile UI distribution %s\n' "${release_tag}" |
        GIT_AUTHOR_NAME='PAM Release Automation' \
        GIT_AUTHOR_EMAIL='release@pam.dev' \
        GIT_AUTHOR_DATE="${source_date}" \
        GIT_COMMITTER_NAME='PAM Release Automation' \
        GIT_COMMITTER_EMAIL='release@pam.dev' \
        GIT_COMMITTER_DATE="${source_date}" \
        git -C "${repository_root}" commit-tree "${source_tree}"
}

verify_distribution() (
    local distribution_ref=$1
    local temporary_directory
    temporary_directory=$(mktemp -d)
    trap 'find "${temporary_directory}" -depth -delete' EXIT

    git -C "${repository_root}" archive "${distribution_ref}" |
        tar -x -C "${temporary_directory}"

    [[ -f ${temporary_directory}/composer.json ]] ||
        fail "distribution is missing composer.json"
    [[ -f ${temporary_directory}/pam-native.plugin.json ]] ||
        fail "distribution is missing pam-native.plugin.json"
    [[ -f ${temporary_directory}/LICENSE ]] ||
        fail "distribution is missing LICENSE"
    [[ $(jq -er '.name' "${temporary_directory}/composer.json") == "${package_name}" ]] ||
        fail "distribution declares the wrong Composer package"

    composer validate \
        --strict \
        --no-check-lock \
        --no-interaction \
        "${temporary_directory}/composer.json" >/dev/null

    if find "${temporary_directory}" \
        \( -name vendor -o -name .env -o -name local.properties -o -name '*.jks' \) \
        -print -quit |
        grep -q .; then
        fail "distribution contains generated or private files"
    fi
)

case "${1:-}" in
    validate-tag)
        [[ $# -eq 2 ]] || fail "usage: $0 validate-tag <vX.Y.Z>"
        validate_tag "$2"
        ;;
    create)
        [[ $# -eq 3 ]] || fail "usage: $0 create <git-ref> <vX.Y.Z>"
        validate_tag "$3"
        create_distribution_commit "$2" "$3"
        ;;
    verify)
        [[ $# -eq 2 ]] || fail "usage: $0 verify <git-ref>"
        verify_distribution "$2"
        ;;
    *)
        fail "usage: $0 {validate-tag|create|verify}"
        ;;
esac
