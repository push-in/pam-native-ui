#!/usr/bin/env bash

set -euo pipefail

fail() {
    printf 'reproducible-archive: %s\n' "$*" >&2
    exit 1
}

[[ $# -eq 5 ]] ||
    fail 'usage: reproducible-archive.sh <zip|tar.gz> <git-ref> <subtree|.> <prefix> <output>'

format=$1
git_ref=$2
subtree=$3
prefix=$4
output=$5

[[ ${format} == zip || ${format} == tar.gz ]] || fail 'unsupported archive format'
[[ ${git_ref} =~ ^[A-Za-z0-9._/-]{1,160}$ ]] || fail 'git ref is not a bounded safe value'
[[ ${subtree} == . || ${subtree} =~ ^[A-Za-z0-9._/-]{1,160}$ ]] ||
    fail 'subtree is not a bounded safe path'
[[ ${subtree} != /* && ${subtree} != .. && ${subtree} != ../* &&
    ${subtree} != */../* && ${subtree} != */.. ]] || fail 'subtree escapes its root'
[[ ${prefix} =~ ^[A-Za-z0-9._/-]{1,160}/$ ]] ||
    fail 'archive prefix must be a bounded relative directory ending in /'
[[ ${prefix} != /* && ${prefix} != *'../'* ]] || fail 'archive prefix escapes its root'
[[ ! -e ${output} && ! -L ${output} ]] || fail 'output already exists'

repository_root=$(git rev-parse --show-toplevel)
commit=$(git -C "${repository_root}" rev-parse --verify "${git_ref}^{commit}")
treeish=${commit}
if [[ ${subtree} != . ]]; then
    treeish=${commit}:${subtree}
    [[ $(git -C "${repository_root}" cat-file -t "${treeish}") == tree ]] ||
        fail 'subtree does not resolve to a Git tree'
fi

output_parent=$(dirname "${output}")
mkdir -p "${output_parent}"
temporary=$(mktemp "${output_parent}/.pam-archive.XXXXXX")
trap 'find "${temporary}" -depth -delete 2>/dev/null || true' EXIT

case ${format} in
    zip)
        git -C "${repository_root}" archive \
            --format=zip \
            --prefix="${prefix}" \
            --output="${temporary}" \
            "${treeish}"
        ;;
    tar.gz)
        git -C "${repository_root}" archive \
            --format=tar \
            --prefix="${prefix}" \
            "${treeish}" |
            gzip -n -9 >"${temporary}"
        ;;
esac

[[ -s ${temporary} ]] || fail 'archive is empty'
mv "${temporary}" "${output}"
trap - EXIT
