# UI/UX report — v1.5.10

The contract template page is now a document workspace with an editor canvas,
searchable variable browser and preview mode. Raw HTML is deliberately
secondary. Contract tabs use scoped, keyboard-accessible state to avoid content
mixing across components.

The editor follows the shared spacing tokens and retains an RTL document
surface rather than placing legal text into a giant raw textarea. The variable
panel remains beside the canvas on wide desktop screens, becomes a full-width
section on tablet and does not force a horizontally overflowing layout on
mobile. A4 preview is a separate mode on constrained screens, avoiding an
unusable compressed two-column layout.

The visual editor is not an alternate financial renderer: official preview and
print remain server-calculated and use the same contract document model. This
separation prevents a browser state, stale amount or placeholder from becoming
an authoritative contract value.
