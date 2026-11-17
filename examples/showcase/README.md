# PAM Native UI showcase

Every PAM Native UI 2 component on one app: a validated sign-up form, every
input with its PHP state printed live, a 500-row virtualized list, alerts,
progress, skeletons, sheets, dialogs and snackbars, in light and dark.

```sh
cd examples/showcase
pam composer install
pam mobile dev            # Android device or emulator
```

`python3 ../../tools/e2e_android.py --serial <device>` drives it and checks
that every input reaches PHP; `python3 ../../tools/capture_showcase.py` takes
the light and dark screenshots in `docs/assets/showcase`.
