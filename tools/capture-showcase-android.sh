#!/usr/bin/env bash

set -euo pipefail

root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
output=${1:-"${root}/docs/assets/android/components"}
package=${PAM_SHOWCASE_PACKAGE:-dev.pam.mobileui.catalog.debug}
activity=${PAM_SHOWCASE_ACTIVITY:-dev.pam.nativeapp.PamActivity}
serial=${ANDROID_SERIAL:-}
settle_seconds=${PAM_SHOWCASE_SETTLE_SECONDS:-1}

command -v adb >/dev/null || {
  echo 'adb is required to capture the Android showcase.' >&2
  exit 69
}
command -v xmllint >/dev/null || {
  echo 'xmllint is required to validate the captured screen.' >&2
  exit 69
}
command -v timeout >/dev/null || {
  echo 'timeout is required to bound Android capture operations.' >&2
  exit 69
}

if [[ -z ${serial} ]]; then
  mapfile -t devices < <(adb devices | awk 'NR > 1 && $2 == "device" { print $1 }')
  [[ ${#devices[@]} -eq 1 ]] || {
    echo 'Connect exactly one Android device or set ANDROID_SERIAL.' >&2
    exit 64
  }
  serial=${devices[0]}
fi
adb=(adb -s "${serial}")
timeout 10s "${adb[@]}" shell pm path "${package}" >/dev/null || {
  echo "Package ${package} is not installed on ${serial}." >&2
  exit 66
}

mkdir -p "${output}"
temporary=$(mktemp -d)
hierarchies="${temporary}/hierarchies"
mkdir -p "${hierarchies}"
window_scale=$(timeout 5s "${adb[@]}" shell settings get global window_animation_scale | tr -d '\r')
transition_scale=$(timeout 5s "${adb[@]}" shell settings get global transition_animation_scale | tr -d '\r')
animator_scale=$(timeout 5s "${adb[@]}" shell settings get global animator_duration_scale | tr -d '\r')
restore_demo() {
  timeout 5s "${adb[@]}" shell am broadcast \
    -a com.android.systemui.demo -e command exit >/dev/null 2>&1 || true
  timeout 5s "${adb[@]}" shell settings put global window_animation_scale \
    "${window_scale}" >/dev/null 2>&1 || true
  timeout 5s "${adb[@]}" shell settings put global transition_animation_scale \
    "${transition_scale}" >/dev/null 2>&1 || true
  timeout 5s "${adb[@]}" shell settings put global animator_duration_scale \
    "${animator_scale}" >/dev/null 2>&1 || true
  rm -rf "${temporary}"
}
trap restore_demo EXIT

timeout 5s "${adb[@]}" shell settings put global window_animation_scale 0
timeout 5s "${adb[@]}" shell settings put global transition_animation_scale 0
timeout 5s "${adb[@]}" shell settings put global animator_duration_scale 0
timeout 5s "${adb[@]}" shell settings put global sysui_demo_allowed 1
timeout 5s "${adb[@]}" shell am broadcast \
  -a com.android.systemui.demo -e command enter >/dev/null
timeout 5s "${adb[@]}" shell am broadcast \
  -a com.android.systemui.demo -e command clock -e hhmm 1010 >/dev/null
timeout 5s "${adb[@]}" shell am broadcast \
  -a com.android.systemui.demo -e command battery -e level 100 -e plugged false >/dev/null
timeout 5s "${adb[@]}" shell am broadcast \
  -a com.android.systemui.demo -e command network -e wifi show -e level 4 >/dev/null

mapfile -t tags < <(
  rg -o '<p-[a-z0-9-]+' "${root}/docs/catalog.md" |
    sed 's/^<//' |
    sort -u
)
[[ ${#tags[@]} -eq 84 ]] || {
  echo "Expected 84 catalog tags, found ${#tags[@]}." >&2
  exit 65
}

capture() {
  local name=$1
  local url=$2
  local expected=$3
  local route_tag=${4:-}
  local remote="/sdcard/pam-showcase-${name}.png"
  local remote_hierarchy="/sdcard/pam-showcase-${name}.xml"
  local hierarchy="${hierarchies}/${name}.xml"
  local dump_output=''
  local dumped=false

  for _launch_attempt in 1 2 3; do
    if ! timeout 20s "${adb[@]}" shell am start -W -S \
      -n "${package}/${activity}" -d "${url}" >/dev/null; then
      dump_output="route launch timed out for ${url}"
      continue
    fi
    sleep "${settle_seconds}"
    for _dump_attempt in 1 2 3; do
      "${adb[@]}" shell rm -f "${remote_hierarchy}"
      dump_output=$(timeout 15s "${adb[@]}" shell uiautomator dump --compressed \
        "${remote_hierarchy}" 2>&1 | tr -d '\r') || true
      if [[ ${dump_output} == *'dumped to'* ]] && \
        "${adb[@]}" shell test -f "${remote_hierarchy}" && \
        timeout 10s "${adb[@]}" pull "${remote_hierarchy}" "${hierarchy}" >/dev/null && \
        xmllint --xpath \
          "//*[contains(@text, '${expected}') or contains(@content-desc, '${expected}')]" \
          "${hierarchy}" >/dev/null 2>&1; then
        if [[ -z ${route_tag} ]] || \
          xmllint --xpath "//*[@text='${route_tag}']" \
            "${hierarchy}" >/dev/null 2>&1; then
          dumped=true
          break 2
        fi
      fi
      sleep 1
    done
  done
  [[ ${dumped} == true ]] || {
    echo "Could not capture the expected rendered state for ${url}: ${dump_output}" >&2
    exit 1
  }
  timeout 15s "${adb[@]}" shell screencap -p "${remote}" >/dev/null
  timeout 10s "${adb[@]}" pull "${remote}" "${output}/${name}.png" >/dev/null
}

capture overview pam-showcase://screen/overview 'PAM Studio'
for screen in actions forms data overlays all; do
  label=$(tr '[:lower:]' '[:upper:]' <<<"${screen:0:1}")${screen:1}
  [[ ${screen} == all ]] && label='Components'
  capture "screen-${screen}" "pam-showcase://screen/${screen}" "${label}"
done

declare -A component_parent=(
  [p-app-bar-nav-icon]=p-app-bar
  [p-banner-actions]=p-banner
  [p-calendar-day]=p-calendar
  [p-card-actions]=p-card
  [p-carousel-item]=p-carousel
  [p-expansion-panel-text]=p-expansion-panel
  [p-expansion-panel-title]=p-expansion-panel
  [p-item]=p-item-group
  [p-slide-group-item]=p-slide-group
  [p-stepper-actions]=p-stepper
  [p-stepper-header]=p-stepper
  [p-stepper-item]=p-stepper
  [p-stepper-vertical-actions]=p-stepper-vertical
  [p-stepper-vertical-item]=p-stepper-vertical
  [p-stepper-window]=p-stepper
  [p-stepper-window-item]=p-stepper
  [p-timeline-item]=p-timeline
  [p-treeview-item]=p-treeview
)
for tag in "${tags[@]}"; do
  route_tag=${component_parent[${tag}]:-${tag}}
  capture "${tag}" "pam-showcase://component/${tag}" 'Variations' "${route_tag}"
done

density=$("${adb[@]}" shell wm density | awk '/Physical density/ { print $3 }')
python3 "${root}/tools/validate-android-screenshots.py" \
  "${output}" "${hierarchies}" \
  --density "${density}" \
  --output "${output}/visual-audit.json"

{
  printf '{\n'
  printf '  "device": "%s",\n' "$(${adb[@]} shell getprop ro.product.model | tr -d '\r')"
  printf '  "api": %s,\n' "$(${adb[@]} shell getprop ro.build.version.sdk | tr -d '\r')"
  printf '  "package": "%s",\n' "${package}"
  printf '  "componentCount": %d,\n' "${#tags[@]}"
  printf '  "capturedAt": "%s"\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf '}\n'
} >"${output}/manifest.json"

echo "Captured ${#tags[@]} component states and 6 showcase screens from ${serial}."
