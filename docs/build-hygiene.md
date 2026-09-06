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

## Local development storage contract

Run the project cleanup after every local native build or audit:

```bash
scripts/cleanup-build-artifacts.sh
```

The allowlist includes the kitchen-sink `.pam-native` trees, including the
Composer-installed self-copy used by package-validation builds. These trees
contain Gradle intermediates and expanded runtime payloads; they are never
release deliverables. The signed APK in `examples/kitchen-sink/dist` and the
curated evidence under `docs/assets` are deliberately retained.

Local development must follow these limits:

- do not keep more than one PAM audit AVD; wipe or remove superseded PAM AVDs;
- do not keep emulator snapshots after an audit unless they are required to
  reproduce an open defect;
- treat `$HOME/.gradle/caches` as disposable and prune it when it exceeds 8 GB;
- retain release files in `dist`, never an entire `.pam-native` build tree;
- check `df -h /` before a release build and stop if less than 10 GB is free.

Build commands must run as the regular development user. Running Composer,
PHPStan, Gradle or PAM Native with `sudo` creates non-writable caches that the
bounded cleanup intentionally refuses to traverse. If cleanup reports an
ownership error, repair that exact reported build directory before continuing;
never solve it by running the whole development workflow as root.

CI invokes the same cleanup in unconditional final steps. Any new build root
must be added to the fixed allowlist in `scripts/cleanup-build-artifacts.sh` in
the same change that introduces it.
