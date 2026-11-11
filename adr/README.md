# Architecture Decision Records

This folder records the design decisions behind the Contactgegevens Register, one decision per file, in the
[MADR](https://adr.github.io/madr/) format. Each record lists the options that were considered and why the rejected
ones were rejected.

## Conventions

- Files are named `NNNN-short-title.md` and numbered in order of creation. Numbers are never reused.
- `status` is one of `proposed`, `accepted`, `rejected`, `deprecated` or `superseded by NNNN`.
- An accepted record is not rewritten when a decision changes. Write a new record that supersedes it, and update the
  old record's status.
- Domain terms stay in Dutch ([0003](0003-own-model-and-api.md)).

## Records

| No. | Decision | Status |
|---|---|---|
| [0001](0001-dedicated-nextcloud-app.md) | Dedicated Nextcloud app with an OpenRegister-compatible format | accepted |
| [0002](0002-hmac-by-mijn-omgeving.md) | Mijn Omgeving computes the HMAC; contact details are stored in plain text | accepted |
| [0003](0003-own-model-and-api.md) | Own data model and API, with our own Dutch names | accepted |
| [0004](0004-hmac-on-identiteit.md) | One HMAC per identiteit, stored on the identiteit | accepted |
| [0005](0005-identifier-type-agnostic.md) | The register does not know the identifier type | accepted |
| [0006](0006-hmac-unique-by-index.md) | The HMAC is unique, enforced by a database index | accepted |
| [0007](0007-verification-derived-from-date.md) | Verification status derived from `geverifieerdOp`; forgetting is a delete | accepted |
| [0008](0008-audit-by-identiteit-uuid.md) | Audit by identiteit UUID instead of HMAC | accepted |
| [0009](0009-key-rotation-overlap-window.md) | Key rotation over an overlap window; stale identiteiten are purged by date | accepted |
| [0010](0010-action-endpoints-instead-of-patch.md) | One endpoint per transition instead of a general update | accepted |
| [0011](0011-identiteit-without-contactgegevens.md) | An identiteit may exist without contactgegevens | accepted |
