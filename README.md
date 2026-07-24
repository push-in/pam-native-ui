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

The upstream provider API is available without a JavaScript context layer:

```xml
<GluestackUIProvider mode="system">
    <App />
</GluestackUIProvider>
```

`light`, `dark` and `system` scope token resolution to the provider subtree and
do not leak into adjacent renders. The provider itself is the full-size app
root. Context-only `BlankProvider`, `BlankContext` and `PromptInputProvider`
return their single child directly; multiple children use a reconciler node
that PAM's Android renderer flattens before native layout.

Image facades accept both PAM URI strings and React Native-compatible
`source="{{ ['uri' => $url] }}"` values (including source candidate lists).
`alt` becomes the native accessibility label, `resizeMode` stays on the image
primitive, and `AvatarFallback` is the same uppercase text alias exposed by
gluestack/shadcn. Scalar content passed through the fluent class API also
becomes a native text child for items, triggers and action buttons, so
`BottomSheetItem::make('Save')` and `PromptInputButton::make('Attach')` render
the same content as their tag equivalents.

React Native-compatible primitive props stay available on both APIs. Explicit
`pointerEvents` and `collapsable` values are applied after recipes; pressables
accept scalar or per-side `hitSlop`, `android_ripple.color`/`rippleColor` and
`pressedOpacity`; inputs accept `placeholderTextColor`, `selectionColor` and
`returnKeyType`. Colors support `#RGB[A]`, `#RRGGBB[AA]`, `rgb()`, `rgba()` and
`transparent`. PAM normalizes these once in PHP into compact integer properties,
then Android owns hit testing, ripple, selection and IME behavior on the UI
thread.

`role` (preferred, matching current React Native) and `accessibilityRole` accept
the full mobile role vocabulary. `role` wins when both are present. Components
also infer their semantic role: headings, links, alerts, checkboxes, radio
groups, combo-box triggers, menus, progress, sliders, tabs, grids and recycled
lists reach TalkBack with native class/heading/checkable metadata. You can
override the inference or use `none`/`presentation` without changing the visual
component.

Accessibility state follows the same React Native names: `accessible`,
`accessibilityState`, `accessibilityValue`, `accessibilityLiveRegion`,
`importantForAccessibility` and their `aria*` aliases. Disabled, selected,
checked/`mixed`, busy and expanded state compile to fixed protocol keys. Native
TalkBack nodes own checkable state, expand/collapse actions, live announcements
and numeric ranges; state changes emit an Android accessibility-content event,
not a PHP render callback.

Responsive grids keep the same class vocabulary used by gluestack:

```xml
<Grid class="grid-cols-2 sm:grid-cols-3 gap-x-3 gap-y-4">
    <GridItem class="col-span-1 sm:col-span-2">
        <Card><Text>Primary metric</Text></Card>
    </GridItem>
    <GridItem class="col-span-1">
        <Card><Text>Secondary metric</Text></Card>
    </GridItem>
</Grid>
```

`columns`/`numColumns`, `colSpan`, `gap`, `columnGap`, `rowGap` and
`flexDirection` are available as explicit PHP props too. Six bounded responsive
slots are encoded once; Android chooses the active breakpoint, wraps spans,
mirrors RTL and exposes collection metadata without asking PHP to re-render.

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

Core layout wrappers forward their platform controls without adding a PHP
interaction hop. For example, `SafeAreaView` accepts `edges="top,bottom"` and
`mode="padding|margin"`, while `KeyboardAvoidingView` accepts
`behavior="height|position|padding"`, `keyboardVerticalOffset` and `enabled`.
Android calculates system-bar and IME insets and updates the view on the UI
thread.

`RefreshControl` accepts React Native-compatible `colors`, `enabled`,
`progressBackgroundColor`, `progressViewOffset`, `size` and `tintColor`
fallback props. Pull detection, drag feedback and indicator animation are
native; the bridge carries only the semantic `onRefresh` event.

Text facades forward `selectable`, `selectionColor`, `numberOfLines`,
`ellipsizeMode`, font scaling/fitting, `textBreakStrategy`,
`android_hyphenationFrequency` and `dataDetectorType`. Selection, measurement,
line breaking and detector spans remain inside Android `TextView`.

`StatusBar` forwards `animated`, `backgroundColor`, `barStyle`, `hidden` and
`translucent`. Mounted instances merge in order and restore the prior native
window configuration; Android 15+ retains its mandatory edge-to-edge behavior.

Every `ScrollView`, including horizontal attachment rails, resolves to the
same PAM core primitive:

```xml
<ScrollView
    horizontal="true"
    contentOffset="{{ ['x' => 120, 'y' => 0] }}"
    pagingEnabled="true"
    snapToInterval="320"
    decelerationRate="fast"
    nestedScrollEnabled="true"
    overScrollMode="never"
    keyboardDismissMode="on-drag"
    on:scroll="rememberOffset"
>
    <HStack><Cards /></HStack>
</ScrollView>
```

Drag, fling, snap, fading edges, persistent scrollbars and IME dismissal remain
on Android. The bridge is silent without `on:scroll`; when observed, the
active-axis offset is coalesced to one scalar per VSYNC. `Spinner` additionally
forwards `animating`, `hidesWhenStopped`, `color` and small/large or numeric
`size` to the core `ActivityIndicator`.

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
    on:toggle="setFiltersOpen"
    on:change="rememberSheetPosition"
    on:event="closeFilters"
>
    <BottomSheetTrigger>
        <Button><ButtonText>Open filters</ButtonText></Button>
    </BottomSheetTrigger>
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
focus trapping, reduced motion and a 48 dp drag target are supported. With no
open prop the sheet starts closed at snap index zero. A root `on:toggle`
receives `true` from `BottomSheetTrigger` and `false` from native dismissal.

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
open state. Omitting `open`/`isOpen` keeps the gallery closed and
`initialIndex` defaults to zero, as in the upstream component.

Chat interfaces keep their high-frequency interaction state native while
remaining ordinary compound tags:

```xml
<Conversation>
    <ConversationContent>
        <Message role="assistant">
            <MessageContent>
                <MessageResponse on:event="openLink">
                    ## Choose an implementation

                    Use **PAM primitives** or a [native plugin](https://pam.dev).
                </MessageResponse>
            </MessageContent>
        </Message>
    </ConversationContent>
    <ConversationScrollButton><ArrowDownIcon /></ConversationScrollButton>
</Conversation>

<MessageBranch
    currentBranch="{{ $branch }}"
    loop="true"
    on:change="selectBranch"
>
    <MessageBranchContent>
        <MessageContent><Text>Typed PHP components</Text></MessageContent>
        <MessageContent><Text>Raw PAM elements</Text></MessageContent>
    </MessageBranchContent>
    <MessageBranchSelector>
        <MessageBranchPrevious />
        <MessageBranchPage />
        <MessageBranchNext />
    </MessageBranchSelector>
</MessageBranch>

<PromptInputProvider files="$files">
    <PromptInput on:submit="sendPrompt">
        <PromptInputBody>
            <PromptInputTextarea sync="native" placeholder="Ask PAM anything" />
        </PromptInputBody>
        <PromptInputSubmit><ArrowUpIcon /></PromptInputSubmit>
    </PromptInput>
</PromptInputProvider>

<ModelSelector
    open="{{ $modelSelectorOpen }}"
    value="{{ $model }}"
    size="sm"
    on:toggle="setModelSelectorOpen"
    on:change="selectModel"
>
    <ModelSelectorTrigger asChild="true">
        <Button><ButtonText>Choose model</ButtonText></Button>
    </ModelSelectorTrigger>
    <ModelSelectorContent title="Choose a model">
        <ModelSelectorInput sync="native" />
        <ModelSelectorList>
            <ModelSelectorGroup heading="PAM">
                <ModelSelectorItem value="pam-fast">
                    <ModelSelectorLogo provider="pam" />
                    <ModelSelectorName>PAM Fast</ModelSelectorName>
                </ModelSelectorItem>
            </ModelSelectorGroup>
        </ModelSelectorList>
    </ModelSelectorContent>
</ModelSelector>
```

`ConversationContent` is a real Android scroll container. The latest-message
button observes scroll position and moves locally, while a newly appended user
message follows the upstream auto-scroll rule. Message branches switch mounted
responses, counter and accessibility state on the UI thread, emitting one final
index. Prompt text enables/disables Submit locally; submit trims and optionally
clears the field, then invokes PHP with the upstream shape
`['text' => string, 'files' => list]`. With `sync="native"`, typing itself does
not cross the bridge. Attachment data stays in PHP provider state; Android
receives only its integer count, so files and data URIs are never serialized
back through the event bridge. Attachment `type` uses the sequential
`Pam\MobileUi\Enum\AttachmentType` values (`File=1`,
`SourceDocument=2`).

`ModelSelector` preserves the upstream controlled contract. Its root is a
flattened provider node, `asChild` keeps the authored trigger primitive, and
only the content owns a PAM native modal. Header, screen-reader title, close
control, bounded scroll body, group headings, selected item state and
`xs`/`sm`/`md`/`lg`/`full` sizing are composed automatically. Trigger,
selection, close button and Android Back each emit one semantic event; input
editing can stay entirely native with `sync="native"`.

`MessageResponse` supports headings, ordered/unordered lists, quotes, bold,
italic, strike-through, inline/fenced code and safe links in one intrinsic
Android text view. It also accepts gluestack-style `message.parts`: text parts
become native Markdown and file parts become PAM images. A safe link emits its
URI through `on:event`, leaving navigation policy in the application.

File trees use the same compound syntax with native expansion and selection:

```xml
<FileTree
    defaultExpanded="$expandedFolders"
    selectedPath="{{ $selectedFile }}"
    on:change="selectFile"
    on:event="folderExpansionChanged"
>
    <FileTreeFolder path="/src" name="src">
        <FileTreeFile path="/src/App.php" name="App.php" />
        <FileTreeFolder path="/src/Components" name="Components">
            <FileTreeFile path="/src/Components/Card.php" name="Card.php" />
        </FileTreeFolder>
    </FileTreeFolder>
</FileTree>
```

Folder content and chevron motion remain on Android. File/folder selection emits
one path through `on:change`; a folder additionally emits one bounded
`on:event` map with integer action `1`, `path` and `expanded`. The root supports
controlled `expanded`/`expandedPaths` or local `defaultExpanded`, and exposes a
hierarchical single-selection collection to TalkBack.

Large scalar data sets use PAM's recycled Android list primitives directly:

```xml
<FlatList
    items="$packages"
    rowHeight="48"
    numColumns="2"
    prefetch="8"
    initialScrollIndex="20"
    removeClippedSubviews="true"
    showsVerticalScrollIndicator="false"
    onEndReachedThreshold="0.25"
    on:scroll="rememberOffset"
    on:endReached="loadMore"
/>

<SectionList
    sections="$groupedPackages"
    itemHeight="52"
    inverted="true"
/>
```

`FlatList` and `VirtualizedList` pack scalar rows into a compact payload and
mount them through AndroidX `RecyclerView`; `SectionList` does the same for
titled groups. `rowHeight`/`itemHeight`, `horizontal`, `numColumns`, `inverted`,
`initialScrollIndex`, `removeClippedSubviews`, `prefetch`,
`maxToRenderPerBatch`, `initialNumToRender`, scrolling, indicators and the
end-reached threshold map to the core native list instead of creating a PHP
component per row. RecyclerView owns recycling, its GapWorker owns prefetch, and
PAM coalesces `on:scroll` to one event per VSYNC.
Arbitrary rich row trees remain possible through ordinary PAM composition or a
specialized native plugin; the scalar fast path deliberately does not invoke a
PHP `renderItem` callback while scrolling.

The aliases `ActionsheetFlatList`, `ActionsheetVirtualizedList`,
`ActionsheetSectionList`, `BottomSheetFlatList`, `BottomSheetSectionList`,
`SelectFlatList`, `SelectVirtualizedList` and `SelectSectionList` use those
same recycled native nodes inside their overlay anatomy.

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
