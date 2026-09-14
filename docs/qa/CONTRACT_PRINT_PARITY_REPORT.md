# Contract print parity report — v1.5.10

The local editor preview is intentionally non-authoritative. Official preview
and final print continue to call the server renderer with the contract document
view model. The v1.5.9 guarantor parity path is retained: the linked and manual
guarantor sources are combined by `ContractDocument::viewModel()`.
