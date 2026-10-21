# Contract document rendering

`ContractTemplateService` owns versions, validation and the variable catalog.
`ContractTemplateRenderer` is the canonical renderer and server-side HTML
allowlist sanitizer. `ContractDocument::viewModel()` remains the only document
data model used by generated documents, preview and print.

The template editor never computes financial data and never turns a browser
preview into a production document. It serializes a draft source string only.
The separate A4 preview is sandboxed and is for layout feedback; the official
preview and final print route render the same server model and replacement map.
