# Project & Impact Graph Engine

Node types:
Page, Feature, Component, Hook, Service, API, Schema, Style, Test, Decision.

Edge types:
uses, renders, imports, calls, styles, tests, depends_on, supersedes.

Before a modification:
- traverse direct dependencies
- traverse indirect consumers to configured depth
- identify affected tests
- identify cross-cutting CSS/tokens
- identify accessibility, SEO and performance-sensitive consumers
- compute risk from fan-out, public API change, critical-path position and test coverage

Output must conform to `contracts/impact.schema.json`.
