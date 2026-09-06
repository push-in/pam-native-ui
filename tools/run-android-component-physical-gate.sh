#!/usr/bin/env bash
set -euo pipefail

root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)

if [[ $# -ne 6 ]]; then
  echo "usage: $0 COMPONENT SERIAL APK PACKAGE HARNESS OUTPUT_DIRECTORY" >&2
  exit 64
fi

component=$1
serial=$2
apk=$(realpath "$3")
package=$4
harness=$(realpath "$5")
output=$(realpath -m "$6")

[[ ${component} =~ ^p-[a-z0-9-]+$ ]] || {
  echo "invalid component tag: ${component}" >&2
  exit 64
}
[[ ${package} =~ ^[A-Za-z][A-Za-z0-9_.]+$ ]] || {
  echo "invalid Android package: ${package}" >&2
  exit 64
}
[[ -f ${apk} ]] || {
  echo "APK does not exist: ${apk}" >&2
  exit 66
}
[[ -f ${harness} && ${harness} == "${root}/tools/"audit-*-android.py ]] || {
  echo "harness must be an audit-*-android.py file under tools/: ${harness}" >&2
  exit 66
}
if [[ -e ${output} || -L ${output} ]]; then
  echo "refusing to replace existing physical evidence: ${output}" >&2
  exit 65
fi

command -v adb >/dev/null || {
  echo "adb is required" >&2
  exit 69
}
command -v jq >/dev/null || {
  echo "jq is required" >&2
  exit 69
}

aapt_bin=$(command -v aapt || true)
if [[ -z ${aapt_bin} ]]; then
  sdk_root=${ANDROID_SDK_ROOT:-${ANDROID_HOME:-}}
  if [[ -n ${sdk_root} && -d ${sdk_root}/build-tools ]]; then
    aapt_bin=$(find "${sdk_root}/build-tools" -mindepth 2 -maxdepth 2 -type f -name aapt -print |
      sort -V |
      tail -n 1)
  fi
fi
[[ -n ${aapt_bin} && -x ${aapt_bin} ]] || {
  echo "Android aapt is required" >&2
  exit 69
}

state=$(adb -s "${serial}" get-state 2>/dev/null || true)
[[ ${state} == device ]] || {
  echo "Android device is not authorized and online: ${serial}" >&2
  exit 69
}
qemu=$(adb -s "${serial}" shell getprop ro.kernel.qemu | tr -d '\r')
manufacturer=$(adb -s "${serial}" shell getprop ro.product.manufacturer | tr -d '\r')
model=$(adb -s "${serial}" shell getprop ro.product.model | tr -d '\r')
identity=$(printf '%s %s' "${manufacturer}" "${model}" | tr '[:upper:]' '[:lower:]')
if [[ ${qemu} == 1 || ${serial} == emulator-* || ${identity} =~ emulator|sdk_gphone|generic ]]; then
  echo "physical approval gate refuses emulator identity: ${manufacturer} ${model}" >&2
  exit 69
fi

apk_badging=$("${aapt_bin}" dump badging "${apk}")
apk_package=$(sed -n "s/^package: name='\([^']*\)'.*/\1/p" <<<"${apk_badging}" | head -n 1)
[[ ${apk_package} == "${package}" ]] || {
  echo "APK package ${apk_package} does not match requested ${package}" >&2
  exit 65
}
device_abi=$(adb -s "${serial}" shell getprop ro.product.cpu.abi | tr -d '\r')
native_code=$(sed -n "s/^native-code: //p" <<<"${apk_badging}")
[[ ${native_code} == *"'${device_abi}'"* ]] || {
  echo "APK native code ${native_code:-none} does not support ${device_abi}" >&2
  exit 65
}

temporary=$(mktemp -d)
cleanup() {
  rm -r -- "${temporary}"
}
trap cleanup EXIT
mkdir -p "${output}"

input_apk_sha=$(sha256sum "${apk}" | awk '{print $1}')
adb -s "${serial}" install -r "${apk}"
installed_path=$(adb -s "${serial}" shell pm path "${package}" |
  sed -n 's/^package://p' |
  tr -d '\r' |
  head -n 1)
[[ -n ${installed_path} ]] || {
  echo "installed package path is unavailable for ${package}" >&2
  exit 70
}
adb -s "${serial}" pull "${installed_path}" "${temporary}/installed.apk" >/dev/null
installed_apk_sha=$(sha256sum "${temporary}/installed.apk" | awk '{print $1}')
[[ ${installed_apk_sha} == "${input_apk_sha}" ]] || {
  echo "installed APK hash differs from the approved input artifact" >&2
  exit 70
}

for pass in 1 2; do
  pass_output="${output}/pass-${pass}"
  python3 "${harness}" \
    --serial "${serial}" \
    --package "${package}" \
    --output "${pass_output}"
  jq -e \
    --arg component "${component}" \
    --arg package "${package}" \
    --arg serial "${serial}" \
    '.schemaVersion == 2
      and .component == $component
      and .package == $package
      and .device == $serial
      and .resultStatus == 1
      and ([.checks[]] | all)' \
    "${pass_output}/report.json" >/dev/null
done

first_report_sha=$(sha256sum "${output}/pass-1/report.json" | awk '{print $1}')
second_report_sha=$(sha256sum "${output}/pass-2/report.json" | awk '{print $1}')
[[ ${first_report_sha} != "${second_report_sha}" ]] || {
  echo "physical passes unexpectedly produced the same raw report" >&2
  exit 70
}

android_version=$(adb -s "${serial}" shell getprop ro.build.version.release | tr -d '\r')
android_api=$(adb -s "${serial}" shell getprop ro.build.version.sdk | tr -d '\r')
physical_size=$(adb -s "${serial}" shell wm size |
  sed -n 's/.*Physical size: //p' |
  tr -d '\r' |
  tail -n 1)
physical_density=$(adb -s "${serial}" shell wm density |
  sed -n 's/.*Physical density: //p' |
  tr -d '\r' |
  tail -n 1)

jq -n \
  --arg component "${component}" \
  --arg serial "${serial}" \
  --arg manufacturer "${manufacturer}" \
  --arg model "${model}" \
  --arg androidVersion "${android_version}" \
  --argjson androidApi "${android_api}" \
  --arg viewport "${physical_size}@${physical_density}dpi" \
  --arg package "${package}" \
  --arg buildSha256 "${input_apk_sha}" \
  --arg harness "${harness#"${root}/"}" \
  --arg firstReportSha256 "${first_report_sha}" \
  --arg secondReportSha256 "${second_report_sha}" \
  '{
    schemaVersion: 1,
    component: $component,
    resultStatus: 1,
    device: {
      serial: $serial,
      manufacturer: $manufacturer,
      model: $model,
      androidVersion: $androidVersion,
      api: $androidApi,
      viewport: $viewport
    },
    package: $package,
    buildSha256: $buildSha256,
    harness: $harness,
    passes: [
      {index: 1, resultStatus: 1, rawReportSha256: $firstReportSha256},
      {index: 2, resultStatus: 1, rawReportSha256: $secondReportSha256}
    ],
    checks: {
      physicalDevice: true,
      installedApkMatchesInput: true,
      twoIndependentPasses: true
    },
    approvalPending: [
      "manualVisualReview",
      "realInteractionRecording"
    ]
  }' >"${output}/two-pass-gate.json"

echo "PASS ${component} two-pass physical gate; approval still requires visual review and recording"
echo "Evidence: ${output}"
