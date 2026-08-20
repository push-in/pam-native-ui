#!/usr/bin/env bash
set -euo pipefail

root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
output=${1:-}
if [[ -z "${output}" ]]; then
  echo "usage: tools/capture-android-device-evidence.sh OUTPUT.json" >&2
  exit 64
fi
if [[ -e "${output}" || -L "${output}" ]]; then
  echo "refusing to replace existing evidence: ${output}" >&2
  exit 65
fi
if [[ -n "$(git -C "${root}" status --porcelain)" ]]; then
  echo "physical evidence requires a clean committed worktree" >&2
  exit 66
fi

native_root=${PAM_NATIVE_ROOT:-"${root}/../pam-native"}
gradlew="${native_root}/android/gradlew"
if [[ ! -x "${gradlew}" ]]; then
  echo "PAM Native Gradle wrapper is unavailable at ${gradlew}" >&2
  exit 69
fi
command -v adb >/dev/null || {
  echo "adb is required" >&2
  exit 69
}

mapfile -t devices < <(adb devices | awk 'NR > 1 && $2 == "device" { print $1 }')
if [[ ${#devices[@]} -ne 1 ]]; then
  echo "exactly one authorized Android device is required" >&2
  exit 69
fi

temporary=$(mktemp -d)
capture_state="${root}/.pam-device-capture"
cleanup() {
  for target in "${temporary}" "${capture_state}" "${root}/android/build"; do
    if [[ -e "${target}" || -L "${target}" ]]; then
      rm -r -- "${target}"
    fi
  done
}
trap cleanup EXIT
mkdir -p "${capture_state}/gradle-home" "${capture_state}/project-cache"

serial=${devices[0]}
adb -s "${serial}" logcat -c
GRADLE_USER_HOME="${capture_state}/gradle-home" "${gradlew}" \
  --project-cache-dir "${capture_state}/project-cache" \
  -p "${root}/android" \
  -PpamNativeRoot="${native_root}" \
  clean connectedDebugAndroidTest
adb -s "${serial}" logcat -d -v brief PamMobileUiBench:I '*:S' > "${temporary}/benchmark.log"

python3 "${root}/benchmarks/capture-device-evidence.py" \
  --logcat "${temporary}/benchmark.log" \
  --junit "${root}/android/build/outputs/androidTest-results/connected" \
  --source-revision "$(git -C "${root}" rev-parse HEAD)" \
  --output "${output}"
python3 "${root}/benchmarks/verify-device-evidence.py" "${output}"
