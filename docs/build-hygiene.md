# Mandatory build hygiene

PAM Native UI retains only explicit release deliverables and bounded evidence.
Every local, CI and release build must remove regenerable Gradle, Xcode, SwiftPM
and Rust outputs after success or failure.

The repository uses `scripts/cleanup-build-artifacts.sh` in unconditional final
workflow steps. The script is project-scoped, follows a fixed allowlist, refuses
symlinked artifact roots, and never removes source, Composer dependencies,
screenshots, evidence or `dist`.

Applications receive the same behavior from PAM Runtime and PAM Native: final
APK/AAB/IPA/app deliverables are copied to `dist` before intermediate build
trees are cleaned.
