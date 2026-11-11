# Architecture Decision Records

This folder records the design decisions behind the Contactgegevens Register, one decision per file, in the
[MADR](https://adr.github.io/madr/) format. Each record lists the options that were considered and why the rejected
ones were rejected.

## Conventions

- Files are named `NNNN-short-title.md` and numbered in order of creation. Numbers are never reused.
- `status` is one of `proposed`, `accepted`, `rejected`, `deprecated` or `superseded by NNNN`.
- An accepted record is not rewritten when a decision changes. Write a new record that supersedes it, and update the
  old record's status.
- Domain terms follow Klantinteracties ([0003](0003-klantinteracties-naming.md)) and stay in Dutch.

## Records

| No. | Decision | Status |
|---|---|---|
| [0001](0001-dedicated-nextcloud-app.md) | Dedicated Nextcloud app with an OpenRegister-compatible format | accepted |
| [0002](0002-crypto-in-backend.md) | Hashing and encryption in the backend, keys outside Nextcloud | accepted |
| [0003](0003-klantinteracties-naming.md) | Klantinteracties naming 1:1, following version 0.9.2 | accepted |
| [0004](0004-pseudonym-instead-of-objectid.md) | No raw identifiers: a pseudonym instead of `objectId` | accepted |
| [0005](0005-identifier-types.md) | Identifier types, including `eherkenning_pseudo` via `anderePartijIdentificator` | accepted |
| [0006](0006-pseudonym-unique-on-its-own.md) | Pseudonym unique on its own; the type is part of the HMAC input | accepted |
| [0007](0007-verification-derived-from-date.md) | Verification status derived from `verificatieDatum`; withdrawal is a delete | accepted |
| [0008](0008-audit-by-partij-uuid.md) | Audit by partij UUID instead of pseudonym | accepted |
