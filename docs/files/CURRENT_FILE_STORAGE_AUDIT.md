# Current File Storage Audit - V1.3.8

| Source | Uploader | Previous storage | Validation | Registry and relationship after V1.3.8 |
| --- | --- | --- | --- | --- |
| Identity documents | customer/user | `storage/secure_uploads/identity` | image MIME, 10MB | `files` + identity-document and user relations |
| Card-to-card receipt | customer | `storage/secure_uploads/payment_receipts` | image MIME, 10MB | `files` + receipt, payment and contract relations |
| Chat attachment | authenticated user | `storage/secure_uploads/chat` | image MIME, 10MB | `files` + chat attachment/message relations |
| Legal log attachment | admin/lawyer | `storage/secure_uploads/legal_case_logs` | document MIME, 10MB | `files` + legal-log, contract and case relations |
| Product image | admin | `storage/uploads/products` | image MIME, 10MB | `files` public-asset record; product relation is pending model-level association |
| Logos/favicon | admin | `storage/uploads/logos` | image/SVG MIME, 10MB | `files` public-asset record |
| Manual file manager upload | admin | isolated public folder | extension only | replaced by secure storage, MIME checks, ZIP inspection, metadata, versions and audit |
| Update package | admin | `storage/updates` | dedicated signed-package workflow | intentionally outside user-file registry; it is operational deployment input |

Sensitive content is served through authorized module controllers. The file-management interface never displays physical server paths. Existing files can be ingested in batches through `file-manager/sync`.
