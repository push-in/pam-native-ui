# Native capabilities in PAM Mobile UI

`NativeCapabilities` composes the capability-backed primitives shipped by
`pushinbr/pam-native` without adding a second renderer or lifecycle.

```php
use Pam\MobileUi\Product\NativeCapabilities;
use Pam\Native\UI\Text;

$sheet = NativeCapabilities::bottomSheet(
    Text::make('Filters'),
    [0.4, 0.75, 1.0],
);

$video = NativeCapabilities::video(
    'https://cdn.example.com/demo.mp4',
    autoPlay: false,
);

$entrance = NativeCapabilities::entrance(Text::make('Ready'));
```

The `video()` and `audio()` factories provide the two native media modes. All
factories provide product-safe defaults for rounded sheets, native media,
WebView, context-menu regions and entrance motion. Lower-level properties and
events remain available on each returned primitive.

System capabilities such as files, capture, SQLite, notifications, background
tasks and sensors are called directly through their typed `Pam\Native\System`
or `Pam\Native\Database` APIs; they do not belong to the visual component
renderer.

For typed permission states, push receive/open events, continuous sensor/device
observation, lifecycle recovery and production security limits, follow Pam
Native's `docs/production-capabilities.md`. These APIs stay below the visual
component layer, so PAM Mobile UI does not duplicate their lifecycle.
