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
