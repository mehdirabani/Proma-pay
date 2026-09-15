# UI/UX Regression Report - Proma Pay V1.5.11

Focused area: Settings > Contracts > Template.

Changes verified by static regression checks:

- Route-scoped assets are selected after URL decoding.
- The visual editor shell is server-rendered before JavaScript enhancement.
- Advanced HTML is not the initial visible editing mode.
- Preview mode has independent active state.
- The editing area uses a document-like canvas with consistent spacing.

Limitations:

- Live browser certification was not claimed because no configured QA base URL
  was available in this execution environment.
