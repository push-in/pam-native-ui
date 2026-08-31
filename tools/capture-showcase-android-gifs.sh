#!/usr/bin/env bash

set -euo pipefail

root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
output=${1:-"${root}/docs/assets/android/gifs"}
package=${PAM_SHOWCASE_PACKAGE:-dev.pam.mobileui.catalog.debug}
activity=${PAM_SHOWCASE_ACTIVITY:-dev.pam.nativeapp.PamActivity}
serial=${ANDROID_SERIAL:-}

command -v adb >/dev/null || {
  echo 'adb is required to record the Android showcase.' >&2
  exit 69
}
command -v ffmpeg >/dev/null || {
  echo 'ffmpeg is required to encode the Android showcase GIFs.' >&2
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
"${adb[@]}" shell pm path "${package}" >/dev/null || {
  echo "Package ${package} is not installed on ${serial}." >&2
  exit 66
}

mkdir -p "${output}"
temporary=$(mktemp -d)
show_touches=$("${adb[@]}" shell settings get system show_touches | tr -d '\r')
restore_device() {
  "${adb[@]}" shell settings put system show_touches \
    "${show_touches:-0}" >/dev/null 2>&1 || true
  rm -rf "${temporary}"
}
trap restore_device EXIT
"${adb[@]}" shell settings put system show_touches 1

tap_text() {
  local label=$1
  local occurrence=${2:-1}
  local remote=/sdcard/pam-showcase-interaction.xml
  local hierarchy=${temporary}/interaction.xml
  "${adb[@]}" shell uiautomator dump --compressed "${remote}" >/dev/null
  "${adb[@]}" pull "${remote}" "${hierarchy}" >/dev/null
  local coordinates
  coordinates=$(python3 - "${hierarchy}" "${label}" "${occurrence}" <<'PY'
import re
import sys
import xml.etree.ElementTree as ET

path, label, occurrence = sys.argv[1], sys.argv[2], int(sys.argv[3])
matches = [
    node for node in ET.parse(path).iter('node')
    if node.attrib.get('text') == label
    or node.attrib.get('content-desc') == label
]
if len(matches) < occurrence:
    raise SystemExit(f"Could not find occurrence {occurrence} of {label!r}")
bounds = [int(value) for value in re.findall(r'\d+', matches[occurrence - 1].attrib['bounds'])]
print((bounds[0] + bounds[2]) // 2, (bounds[1] + bounds[3]) // 2)
PY
  )
  read -r x y <<<"${coordinates}"
  "${adb[@]}" shell input tap "${x}" "${y}"
}

swipe_first_slider() {
  local remote=/sdcard/pam-showcase-slider.xml
  local hierarchy=${temporary}/slider.xml
  "${adb[@]}" shell uiautomator dump --compressed "${remote}" >/dev/null
  "${adb[@]}" pull "${remote}" "${hierarchy}" >/dev/null
  local coordinates
  coordinates=$(python3 - "${hierarchy}" <<'PY'
import re
import sys
import xml.etree.ElementTree as ET

node = next(
    candidate for candidate in ET.parse(sys.argv[1]).iter('node')
    if candidate.attrib.get('class') == 'android.widget.SeekBar'
)
left, top, right, bottom = [int(value) for value in re.findall(r'\d+', node.attrib['bounds'])]
y = (top + bottom) // 2
print(left + (right - left) // 4, y, left + (right - left) * 3 // 4, y)
PY
  )
  read -r start_x y end_x _ <<<"${coordinates}"
  "${adb[@]}" shell input swipe "${start_x}" "${y}" "${end_x}" "${y}" 900
}

interact() {
  case $1 in
    navigation)
      tap_text 'Open component workbench'
      sleep 2
      tap_text 'Forms'
      ;;
    expansion-panels)
      tap_text 'Delivery'
      sleep 2
      tap_text 'Support'
      ;;
    dialog)
      tap_text 'Open dialog'
      sleep 2
      "${adb[@]}" shell input keyevent BACK
      ;;
    menu)
      tap_text 'Open menu'
      sleep 2
      "${adb[@]}" shell input tap 1000 1200
      ;;
    tabs)
      tap_text 'Activity'
      sleep 2
      tap_text 'Details'
      ;;
    slider)
      swipe_first_slider
      ;;
    time-picker)
      tap_text '14:35'
      sleep 2
      "${adb[@]}" shell input keyevent BACK
      ;;
    *)
      echo "Unknown interaction $1" >&2
      return 64
      ;;
  esac
}

record() {
  local name=$1
  local url=$2
  local remote=/sdcard/pam-showcase-${name}.mp4
  local video=${temporary}/${name}.mp4

  "${adb[@]}" shell am start -W -S \
    -n "${package}/${activity}" -d "${url}" >/dev/null
  sleep 1
  "${adb[@]}" shell rm -f "${remote}"
  "${adb[@]}" shell screenrecord \
    --size 540x1200 --bit-rate 4000000 --time-limit 7 "${remote}" &
  local recorder=$!
  sleep 1
  interact "${name}"
  wait "${recorder}" || true
  "${adb[@]}" pull "${remote}" "${video}" >/dev/null
  ffmpeg -hide_banner -loglevel error -y -i "${video}" \
    -filter_complex \
    'fps=12,scale=360:-1:flags=lanczos,split[a][b];[a]palettegen=max_colors=128[p];[b][p]paletteuse=dither=bayer' \
    "${output}/${name}.gif"
}

captures=${PAM_SHOWCASE_GIFS:-'navigation expansion-panels dialog menu tabs slider time-picker'}
captured=0
for name in ${captures}; do
  case ${name} in
    navigation) url=pam-showcase://screen/overview ;;
    *) url="pam-showcase://component/p-${name}" ;;
  esac
  record "${name}" "${url}"
  captured=$((captured + 1))
done

echo "Captured ${captured} Android interaction GIFs from ${serial}."
