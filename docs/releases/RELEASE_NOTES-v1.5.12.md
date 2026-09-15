# Release Notes - Proma Pay V1.5.12

## Fixed

- Clean full installs no longer ship with a thin subset of the RTL template
  assets. Runtime CSS, JavaScript, fonts and images from `html/RTL/assets` are
  included in the full Core package.
- The contract professional settings editor no longer opens to a blank canvas
  if Quill or the page adapter fails to initialize.

## Changed

- The advanced source editor remains available as a graceful fallback, while
  the visual editor is still the enhanced default when JavaScript is healthy.
