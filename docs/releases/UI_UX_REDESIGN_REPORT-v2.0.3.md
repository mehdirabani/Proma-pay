# Mobile layout correction — 2.0.3

This patch addresses the mobile defects reported from the 2.0.2 installation rather than claiming a broader visual redesign.

## Fixes

- Sidebar scrolling now belongs to the actual V2 navigation menu. The legacy outer sidebar remains clipped by the shell, so relying on its old scroll rule did not make the newly rendered navigation menu scrollable. The brand and role header remain fixed while the menu scrolls independently with touch momentum and bottom safe-area padding.
- Mobile table cards now constrain grid tracks, rows, and cells to the viewport. Long customer names wrap at word boundaries instead of being squeezed to one glyph per line.
- Penalty details, legal-comparison descriptions, and badges can wrap within installment cards. Previously inherited `white-space: nowrap` rules on comparison text forced a mobile table wider than its card and caused content to be cut off.
- Customer installment cards retain their existing meaning and actions while using the full available width; the patch does not change any financial calculation or payment behavior.

## Responsive verification scope

The browser test covers customer installments and dashboard plus administrator dashboard, contract list and contract details at 320/360/375/390/430 CSS pixels. It asserts no document/card horizontal overflow, usable customer-name width, no vertical-letter wrapping, and reachable bottom sidebar navigation. This is targeted regression coverage, not an accessibility certification or a redesign audit of every application route.
