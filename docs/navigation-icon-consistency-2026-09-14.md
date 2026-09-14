# Navigation icon consistency

PAM Native UI's generated drawer destinations now honor `items[].icon`, retaining
the previous positional defaults when omitted. Selection uses the same scalar
comparison as Navigation Bar/Rail, preventing a numeric value represented as text
from losing its selected state. Regression coverage checks the actual icon host
identifier and suppresses repeated selection callbacks.

The first Android candidate (`44de1610ad177c3f94621bec47d4d69c26344d88feb29fe11f2878efa2e7d295`)
was built on API 36 in 12 seconds. `/tmp/pam-drawer-custom-icons.png` was viewed:
the requested star, globe and settings glyphs render. This exposed another issue:
the selected star remained dark despite a white foreground style. Icon host
properties carry their own color, so style-only tint was insufficient here.

The compositions now explicitly pass their semantic foreground to the icon host:
Primary/MutedForeground for Navigation Bar/Rail and
SecondaryForeground/MutedForeground for Drawer. This is UI composition, not a
new native implementation. The matrix passes after that correction. The final
color correction is not yet installed or visually verified; the earlier screenshot
must not be used as approval of its contrast. No publication or full navigation
approval is claimed. A tap on Explore closed the drawer; selected-state retention
still needs checking after reopening rather than inspecting the closed hierarchy.

## Final color candidate

API 36 APK `609907c97fa30768287ee1be65183f10f52d1152dd14ccf157770ff83d55e21c`
includes the color correction. The build completed in 11 seconds and cleaned
96.7 MiB of regenerable artifacts.

- `/tmp/pam-navigation-colors-20260914/report.json`: Bar and Rail selected the
  requested destination and rejected disabled destinations at font scale 1.0.
  Selected screenshots were viewed: active icons use primary green and inactive
  icons use muted foreground, matching their semantic state.
- `/tmp/pam-drawer-colors-20260914/report.json`: long Drawer rejected disabled
  selection, scrolled to its final destination and closed on selection.
- `/tmp/pam-drawer-final.png` was viewed: the selected custom star is now white,
  matching the selected label on its purple surface; inactive icons are muted.
- A subsequent tap on Explore closed the front drawer. Reopening through its
  labeled trigger exposed Home selected=false and Explore selected=true, asserted
  from `/tmp/pam-drawer-reopened.xml`.

This closes the earlier color and front-drawer retention checks for this emulator
candidate. Large fonts, dark theme, full navigation/back-stack integration,
TalkBack, Samsung, iOS and performance are not established by this scoped batch.

## Shared icon style propagation

Reviewing other compositions found the same style-only tint pattern in Search
and additional built-in icon slots. The shared icon rendering path now transfers
color into the custom host payload before construction. Precedence is explicit
style tint, explicit style text color, explicit color/action prop, resolved root
tint/text color, then theme foreground. This preserves explicit component colors
while allowing actual style overrides to win.

Regression cases cover named native UI icons, generic Icon, PIcon, competing
tint/text overrides, and an explicit packed color without a style override. The
first implementation incorrectly let the default root text color override the
explicit color prop; the regression caught this and precedence was corrected.
The full material matrix passes. This shared follow-up has not yet been installed
on Android: the APK and screenshots above prove the earlier navigation correction,
not the newly generalized style path. No native platform code was duplicated.
