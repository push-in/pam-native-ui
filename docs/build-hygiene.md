# Mandatory build hygiene

PAM Native UI retains only explicit release deliverables and bounded evidence.
Every local, CI and release build must remove regenerable Gradle, Xcode, SwiftPM
and Rust project build outputs after success or failure. The bounded shared
Gradle cache described below is the exception, not an application deliverable.

The repository uses `scripts/cleanup-build-artifacts.sh` in unconditional final
workflow steps. The script is project-scoped, follows a fixed allowlist, refuses
symlinked artifact roots, and never removes source, Composer dependencies,
screenshots, evidence or `dist`.

Applications receive the same behavior from PAM Runtime and PAM Native: final
APK/AAB/IPA/app deliverables are copied to `dist` before intermediate build
trees are cleaned.

## Local development storage contract

### Composer path packages in showcase builds

Build the kitchen-sink application in a temporary directory outside the UI
repository when installing the UI through a Composer path repository. Composer
rejects installing a package inside its own source tree. Point the temporary
application's path repositories at the Native PHP package and a clean export
of the UI commit being validated. A clean export avoids copying Gradle caches,
old example dependencies and build outputs into the package.

Check both `composer.lock` and `vendor/composer/installed.json` before building:
an updated lock alone does not prove the installed PHP files are current.
Run Composer with the PHP version required by the application, complete the
dependency preflight, and verify the installed source before capturing device
evidence. Keep the temporary application's lock with its build evidence.

After the audit, remove that exact temporary application and source export,
retaining only the intended APK and evidence. Do not use a broad `/tmp` cleanup.

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

### Reuse one bounded Gradle cache during iterative device work

For repeated local showcase builds, prefer the existing user-owned Gradle cache
via `PAM_NATIVE_GRADLE_HOME="$HOME/.gradle"`, after checking it is below the 8 GB
limit. Check its size again after the build. Do not create another shared cache
or disable application-artifact cleanup to obtain faster builds.

PAM Native's `android_gradle_user_home` honors this explicit override. Its
mandatory cleanup still removes the generated application's `app/build` and
project-local Gradle intermediates; it does not recursively delete the shared
user cache. This avoids repopulating an isolated dependency/transform cache for
every small UI candidate. It does not imply that all compilation is cached or
promise a particular build time. If the shared cache exceeds the limit, stop
and prune only verified disposable entries with no active build using them.

Build commands must run as the regular development user. Running Composer,
PHPStan, Gradle or PAM Native with `sudo` creates non-writable caches that the
bounded cleanup intentionally refuses to traverse. If cleanup reports an
ownership error, repair that exact reported build directory before continuing;
never solve it by running the whole development workflow as root.

CI invokes the same cleanup in unconditional final steps. Any new build root
must be added to the fixed allowlist in `scripts/cleanup-build-artifacts.sh` in
the same change that introduces it.
