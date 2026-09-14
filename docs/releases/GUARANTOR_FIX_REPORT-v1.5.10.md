# Guarantor rendering regression — v1.5.10

No change was made to the v1.5.9 canonical guarantor view model. Existing
linked and manually entered guarantors continue to be combined by the server
renderer for preview and print parity.

The v1.5.10 editor stores structural variables without changing their source
form. In particular `{{guarantors_section}}` is displayed as a protected
editor token and serialized back exactly before draft save. The official
preview and print renderer still resolves that variable from the canonical
contract document view model, so a visual edit cannot reduce a multi-guarantor
contract to one guarantor or erase the generated section.

The prior static guarantor regression test passed during this release. A real
staging print should still be checked when a host upgrades, because local font
availability and browser print margins are host/browser concerns rather than
template-data concerns.
