# PAM Mobile UI

`pushinbr/pam-mobile-ui` is the official accessible component library for PAM Native.
It gives PHP applications a declarative tag API, typed fluent components, and
native Android behavior without adding a JavaScript runtime.

The catalog tracks all 61 gluestack-ui technical modules, 404 upstream exports,
and 326 component/icon facades at the reference commit recorded in
[`resources/parity.json`](resources/parity.json).
Compatibility means equivalent component anatomy, variants, state transitions,
accessibility semantics, and user-visible behavior adapted to Android rather
than a byte-for-byte port of React code.

## Install

```bash
composer require pushinbr/pam-mobile-ui:^0.1
pam mobile codegen
```

Composer autolinking registers the PHP provider and Android host. No manual
Gradle edit or JavaScript runtime is required.

## APIs

Tags remain terse:

```xml
<VStack class="gap-4 p-4">
    <Heading size="xl">Create account</Heading>
    <FormControl required="true">
        <FormControlLabel>
            <FormControlLabelText>Email</FormControlLabelText>
        </FormControlLabel>
        <Input variant="outline" size="md">
            <InputField
                value="$email"
                keyboard="email"
                on:change="setEmail"
            />
        </Input>
    </FormControl>
    <Button variant="default" size="lg" on:press="submit">
        <ButtonText>Continue</ButtonText>
    </Button>
</VStack>
```

PHP classes provide discoverability and strict types:

```php
use Pam\MobileUi\Component\Button;
use Pam\MobileUi\Enum\ButtonVariant;
use Pam\MobileUi\Enum\ComponentSize;

$button = Button::make('Continue')
    ->variant(ButtonVariant::Default)
    ->size(ComponentSize::Large)
    ->onPress($submit);
```

Applications are never forced into either form. Raw `Pam\Native\UI` elements,
custom components, `.pam` tags, fluent classes, and native plugins can be mixed
in the same tree.

Inputs preserve the complete compound anatomy while Android owns transient
editing state:

```xml
<FormControl required="true" invalid="{{ $passwordInvalid }}">
    <FormControlLabel>
        <FormControlLabelText>Password</FormControlLabelText>
    </FormControlLabel>
    <Input type="password" on:change="setPassword">
        <InputField
            value="{{ $password }}"
            sync="native"
        />
        <InputSlot slotAction="clear"><CloseIcon /></InputSlot>
        <InputSlot slotAction="toggle-password"><EyeIcon /></InputSlot>
    </Input>
    <FormControlHelper>
        <FormControlHelperText>Use at least 12 characters.</FormControlHelperText>
    </FormControlHelper>
    <FormControlError>
        <FormControlErrorText>Password is too short.</FormControlErrorText>
    </FormControlError>
</FormControl>
```

The callback may live on `Input` or `InputField`. `sync="native"` keeps every
keystroke and cursor update inside `EditText` and sends the value on blur;
`blur`, `submit`, `debounced` (48 ms by default) and `immediate` are also
available. `InputSlot` defaults to focusing the field and supports the
integer-backed `focus`, `clear`, `toggle-password` and `none` actions through
`Pam\MobileUi\Enum\InputSlotAction`.

Focus/invalid outlines, disabled/read-only editing policy, clear/password
actions and selection remain on the Android UI thread. `FormControl` links its
label, helper and error to the native field, injects the required asterisk,
hides inactive feedback, makes the label focus the field and exposes required,
read-only and invalid/error state to TalkBack. `Textarea` uses the same
controller with a multiline native field.

Compound selection uses one handler at the group root; item values and
controlled checked/selected state are inherited through arbitrary layout
wrappers:

```xml
<RadioGroup value="{{ $plan }}" on:change="choosePlan">
    <HStack space="md">
        <Radio value="starter"><RadioLabel>Starter</RadioLabel></Radio>
        <Radio value="pro"><RadioLabel>Pro</RadioLabel></Radio>
    </HStack>
</RadioGroup>
```

`Checkbox`, `CheckboxGroup`, `Radio` and `RadioGroup` keep selection on the
Android UI thread. Uncontrolled `defaultValue` and `defaultIsChecked`,
controlled values, read-only/disabled/invalid states, checkbox `indeterminate`
state and authored or forced indicator icons are supported. A radio group
unchecks its previous item locally and sends PHP exactly one semantic event
for the newly selected value. Each row exposes native checkable and collection
semantics to TalkBack, including keyboard activation and error text.

Accordion state also stays local to Android between controlled updates:

```xml
<Accordion type="multiple" value="{{ $openSections }}" on:change="setSections">
    <AccordionItem value="performance">
        <AccordionHeader>
            <AccordionTrigger>
                <AccordionTitleText>Performance</AccordionTitleText>
                <AccordionIcon><ChevronDownIcon /></AccordionIcon>
            </AccordionTrigger>
        </AccordionHeader>
        <AccordionContent>
            <AccordionContentText>
                Animation and visibility run on the native UI thread.
            </AccordionContentText>
        </AccordionContent>
    </AccordionItem>
</Accordion>
```

Only the trigger owns the toggle gesture. Collapsed content is removed from
layout and TalkBack, the icon rotates with the system animation setting, and
PHP receives one final boolean semantic event per item interaction.

Tabs use the complete upstream anatomy without a JavaScript/Reanimated layer:

```xml
<Tabs
    value="{{ $tab }}"
    orientation="horizontal"
    activationMode="automatic"
    on:change="selectTab"
>
    <TabsList>
        <TabsTrigger value="account"><TabsTriggerText>Account</TabsTriggerText></TabsTrigger>
        <TabsTrigger value="security"><TabsTriggerText>Security</TabsTriggerText></TabsTrigger>
        <TabsIndicator />
    </TabsList>
    <TabsContentWrapper>
        <TabsContent value="account"><Text>Account settings</Text></TabsContent>
        <TabsContent value="security"><Text>Security settings</Text></TabsContent>
    </TabsContentWrapper>
</Tabs>
```

Controlled `value` and uncontrolled `defaultValue`, horizontal/vertical
orientation, disabled triggers, `forceMount`, automatic/manual activation and
wrapping arrow/Home/End keyboard navigation are supported. Android moves the
indicator, swaps content and animates content height on the UI thread; PHP
receives only the selected scalar value. TalkBack sees a single-selection tab
collection with selected item position. The fluent API uses
`Pam\MobileUi\Enum\TabsActivationMode`.

Bottom sheets, action sheets and select portals share one native controller:

```xml
<BottomSheet
    isOpen="{{ $filtersOpen }}"
    snapPoints="25,50,90"
    snapToIndex="1"
    on:change="rememberSheetPosition"
    on:event="closeFilters"
>
    <BottomSheetPortal>
        <BottomSheetBackdrop pressBehavior="collapse" />
        <BottomSheetContent>
            <BottomSheetDragIndicator />
            <BottomSheetItem closeOnPress="false">
                <BottomSheetItemText>Keep editing</BottomSheetItemText>
            </BottomSheetItem>
        </BottomSheetContent>
    </BottomSheetPortal>
</BottomSheet>
```

The tag API accepts comma-separated percentages; the fluent API also accepts
`[25, 50, 90]`. Drag position, velocity prediction, backdrop opacity and snap
selection stay on Android's UI thread. PHP receives one final snap index through
`on:change`, and dismissals through `on:event`; no movement frame crosses the
bridge. `pressBehavior` supports `close`, `collapse` and `none` (or
`Pam\MobileUi\Enum\BackdropPressBehavior`), while `closeOnPress` and
`closeOnSelect` control item dismissal. Android Back, TalkBack scroll actions,
focus trapping, reduced motion and a 48 dp drag target are supported.

Anchored overlays use the same compact tag convention. The first child is the
trigger; Popover/Tooltip content or Menu items follow:

```xml
<Menu
    selectionMode="multiple"
    selectedKeys="{{ $selectedActions }}"
    placement="bottom-start"
    on:change="selectActions"
>
    <Button variant="outline"><ButtonText>Actions</ButtonText></Button>
    <MenuItem key="share"><MenuItemLabel>Share</MenuItemLabel></MenuItem>
    <MenuItem key="archive"><MenuItemLabel>Archive</MenuItemLabel></MenuItem>
</Menu>

<Tooltip openDelay="400" closeDelay="100" placement="top">
    <Button accessibilityLabel="Help"><InfoIcon /></Button>
    <TooltipContent><TooltipText>Open documentation</TooltipText></TooltipContent>
</Tooltip>
```

Uncontrolled Menu, Popover and Tooltip triggers open locally, so displaying an
overlay does not require a PHP render. Controlled `isOpen` remains available.
Android resolves all 12 placements against the visible window, applies
`offset`/`crossOffset`, flips to the opposite side only when it reduces
overflow, clamps to an 8 dp screen gutter and aligns `PopoverArrow` after the
final placement. Entrance/exit motion uses only opacity and scale.

Menu items are 48 dp native hosts with ripple feedback, single/multiple/none
selection (`Pam\MobileUi\Enum\SelectionMode`), disabled keys, Home/End, wrapping
arrow navigation, type-ahead search and TalkBack collection semantics. Item
activation emits one semantic event; `closeOnSelect` dismisses locally for an
uncontrolled menu. Popover close buttons, backdrop, Android Back and
accessibility dismiss share one bounded lifecycle event.

Gesture-heavy components keep transient work on Android's UI thread. The
calendar supports single, multiple and range selection, disabled/min/max dates,
localized month navigation, first-day-of-week and fixed-week layouts:

```xml
<Calendar
    mode="single"
    value="{{ $selectedDate }}"
    year="2026"
    month="7"
    firstDayOfWeek="1"
    fixedWeeks="true"
    on:change="selectDate"
>
    <CalendarHeader>
        <CalendarHeaderPrevButton><ChevronLeftIcon /></CalendarHeaderPrevButton>
        <CalendarHeaderTitle />
        <CalendarHeaderNextButton><ChevronRightIcon /></CalendarHeaderNextButton>
    </CalendarHeader>
    <CalendarBody><CalendarGrid /></CalendarBody>
</Calendar>
```

Month changes, day hit-testing, drawing, range highlighting and disabled-date
checks happen natively. PHP receives one semantic event after selection; list
and range modes are decoded into typed arrays before the application handler is
called. `Pam\MobileUi\Enum\ComponentMode` provides the equivalent fluent API.

`DateTimePicker` uses Android's system date/time dialogs, including the native
two-step flow for `datetime`. It supports `minimumDate`, `maximumDate`,
`locale`, `timeZoneOffsetInMinutes`, `is24Hour`, `placeholder` and the same
`YYYY`, `MM`, `DD`, `HH`, `mm`, `ss` display-format tokens as the reference
component. A child trigger can contain any PAM layout; the native host owns the
tap so no PHP event is needed merely to open the picker.

Range controls preserve their authored component anatomy while Android updates
it locally:

```xml
<Progress value="{{ $progress }}">
    <ProgressFilledTrack class="bg-emerald-600" />
</Progress>
<Slider
    value="{{ $progress }}"
    minValue="0"
    maxValue="100"
    step="5"
    on:change="updateProgress"
    on:event="finishProgress"
>
    <SliderTrack class="h-2">
        <SliderFilledTrack class="bg-emerald-600" />
    </SliderTrack>
    <SliderThumb class="h-5 w-5" />
</Slider>
```

`SliderTrack`, `SliderFilledTrack`, `SliderThumb` and
`ProgressFilledTrack` retain their utility classes and custom content.
Dragging, snapping, reversal, horizontal/vertical positioning and filled-track
scaling happen on the UI thread. `on:change` receives continuous values
coalesced to at most one callback per display frame; `on:event` receives the
final value once the gesture ends. The fluent component API exposes the same
pair as `->onChange(...)` and `->onChangeEnd(...)`, both decoded as `float`.
`Switch` likewise uses the optimized PAM host with `sm`, `md` and `lg` sizes,
controlled or default state, custom off/on track colors, thumb colors,
keyboard activation and native switch semantics.

Feedback components preserve their upstream state behavior:

```xml
<Skeleton isLoaded="{{ $profileLoaded }}" speed="fast">
    <ProfileCard />
</Skeleton>

<SkeletonText _lines="3" gap="2" speed="very-fast" />

<Toast action="error" duration="5000" on:event="toastDismissed">
    <ToastTitle>Upload failed</ToastTitle>
    <ToastDescription>Check the connection and try again.</ToastDescription>
</Toast>
```

Loaded skeletons return their authored content without leaving a wrapper.
Loading text skeletons render the requested line count under one native pulse
animator. Toast duration is scheduled once per identity rather than restarted
by unrelated renders; transient hide/exit motion and TalkBack live-region
announcement stay on Android, with one final dismiss event for PHP.

Image galleries accept ordinary PHP arrays without serializing that collection
through every child tag:

```php
$gallery = [
    ['url' => 'file:///mountain.jpg', 'alt' => 'Mountain at sunrise'],
    ['url' => 'file:///ocean.jpg', 'alt' => 'Ocean after sunset'],
];
```

```xml
<ImageViewer
    images="$gallery"
    open="{{ $galleryOpen }}"
    initialIndex="{{ $galleryIndex }}"
    loop="true"
    on:toggle="setGalleryOpen"
    on:change="selectGalleryImage"
>
    <ImageViewerTrigger>
        <Button><ButtonText>Open gallery</ButtonText></Button>
    </ImageViewerTrigger>
    <ImageViewerContent>
        <ImageViewerNavigation />
        <ImageViewerCounter />
        <ImageViewerCloseButton><CloseIcon /></ImageViewerCloseButton>
    </ImageViewerContent>
</ImageViewer>
```

PAM expands the bounded declarative array into native image children once.
Android then owns active-image visibility, previous/next controls, looping,
horizontal swipe, double-tap zoom, pan, counter updates and TalkBack collection
actions. Gesture progress emits no PHP callback; `on:change` receives one
zero-based index after a semantic selection and `on:toggle` receives the final
open state.

Large scalar data sets use PAM's recycled Android list primitives directly:

```xml
<FlatList
    items="$packages"
    rowHeight="48"
    showsVerticalScrollIndicator="false"
    onEndReachedThreshold="0.25"
    on:endReached="loadMore"
/>

<SectionList
    sections="$groupedPackages"
    itemHeight="52"
/>
```

`FlatList` and `VirtualizedList` pack scalar rows into a compact payload and
reuse Android row views. `SectionList` does the same for titled groups.
`rowHeight`, `itemHeight`, scrolling, indicators and the end-reached threshold
map to the core native list instead of creating a PHP component per row.
Arbitrary rich row trees remain possible through ordinary PAM composition or a
specialized native plugin; the scalar fast path deliberately does not invoke a
PHP `renderItem` callback while scrolling.

Tables keep their full declarative anatomy while Android exposes native
collection coordinates to TalkBack:

```xml
<Table>
    <TableHeader>
        <TableRow>
            <TableHead>Package</TableHead>
            <TableHead>Runtime</TableHead>
        </TableRow>
    </TableHeader>
    <TableBody>
        <TableRow>
            <TableData>pushinbr/pam-mobile-ui</TableData>
            <TableData>Android</TableData>
        </TableRow>
    </TableBody>
</Table>
```

Application utility classes use the same mobile compiler as the captured
component recipes:

```xml
<Card class="w-1/2 p-4 gap-2 justify-between">
    <Heading size="lg">Fast by construction</Heading>
    <Text class="ui-text-muted">Only packed numeric style properties cross.</Text>
</Card>
```

Plugins resolve these classes lazily while composing a changed tree. Unsupported
utilities fail explicitly; CSS or class strings are never sent to Android.

See the installable catalog in
[`examples/kitchen-sink`](examples/kitchen-sink) and the generated inventory in
[`docs/catalog.md`](docs/catalog.md).

## Architecture

- PHP resolves tokens, variants and static composition before encoding.
- The PAM binary tree carries integers/scalars, not JSON or CSS strings.
- Android owns focus, ripple, gestures, overlays, scrolling and local state.
- Modal, drawer and sheet content mounts in native Android windows while
  compound triggers remain in the main tree.
- `create`, `update`, typed event dispatch and release execute on the UI thread.
- Network, disk and expensive decoding stay away from the UI thread.
- Animations use native properties and respect the system animator scale.

See [`docs/architecture.md`](docs/architecture.md) and
[`docs/parity.md`](docs/parity.md). The reproducible PHP and physical-device
results, commands, limits and claim boundary are recorded in
[`docs/performance.md`](docs/performance.md).

## Status

The parity file is the release gate. PHP/tag generation, packed native hosts,
themes, recipe/state matrix expansion, PHPStan level 9, Android
compilation/lint, and the initial kitchen sink are implemented. Entries remain
explicitly `planned`, `implemented`, `verified`, or `not-applicable` per gate;
no unverified component is presented as finished.
