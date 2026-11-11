---
status: accepted
date: 2026-11-23
decision-makers: Marc Schwartz
---

# Own data model and API, with our own Dutch names

## Context and Problem Statement

The register links a party's HMAC to its email addresses ([0002](0002-hmac-by-mijn-omgeving.md)). Klantinteracties
(the VNG standard, implemented by Open Klant) already models parties, their identifiers and their digital addresses,
and municipal systems speak it. Does the register follow the Klantinteracties data model and API, or does it get a
model and API of its own?

The register can't be a real Klantinteracties API. It holds no raw identifiers and no identifier types
([0005](0005-identifier-type-agnostic.md)), so fields that Klantinteracties requires would be missing or would hold a
different kind of value.

## Decision Drivers

- The Mijn Omgeving is the only client. No Klantinteracties client will ever call the register.
- The register must stay small enough to hand over.
- Every difference from a standard the register claims to follow has to be documented and kept up to date.
- The data must stay easy to convert to OpenRegister ([0001](0001-dedicated-nextcloud-app.md)).
- Domain names are Dutch.

## Considered Options

1. Klantinteracties names and API shape 1:1, with documented deviations
2. A model and API of our own, using Klantinteracties words where they fit
3. A model and API of our own, with our own Dutch names (Identiteit, Contactgegeven, …)

## Decision Outcome

Chosen option: **3, a model and API of our own, with our own Dutch names.** The register doesn't follow the
Klantinteracties model or API, borrows none of its names and claims no compatibility with it. The names say what a
record is in this register: an `identiteit` is one pseudonymised login identifier
([0004](0004-hmac-on-identiteit.md)), not a party, and a `contactgegeven` is one contact detail linked to it.

The model:

| Table | Column | Type |
|---|---|---|
| `identiteit` | `id` | integer |
| | `uuid` | string(36) |
| | `hmac` | string(64) ([0004](0004-hmac-on-identiteit.md), [0006](0006-hmac-unique-by-index.md)) |
| | `hmac_gezet_op` | datetime, UTC ([0009](0009-key-rotation-overlap-window.md)) |
| `contactgegeven` | `id` | integer |
| | `uuid` | string(36) |
| | `identiteit_id` | integer |
| | `soort` | string |
| | `waarde` | string |
| | `geverifieerd_op` | date, nullable ([0007](0007-verification-derived-from-date.md)) |
| | `is_primair` | boolean |
| `audit` | `id` | integer |
| | `tijdstip` | datetime |
| | `actie` | string |
| | `identiteit_uuid` | string(36), nullable ([0008](0008-audit-by-identiteit-uuid.md)) |
| | `bron` | string |

Rules for the model:

- The tables are prefixed with `cgr_` in the database (`oc_cgr_identiteit`, `oc_cgr_contactgegeven`), so their names
  can't clash with those of other apps.
- A foreign key column is named after the table it points to, with an `_id` suffix. It holds the internal integer
  `id`.
- `contactgegeven.identiteit_id` is a foreign key with `ON DELETE CASCADE`: deleting an identiteit deletes its
  contactgegevens in the same statement.
- An identiteit can't hold the same `waarde` twice for one `soort`. A unique index on `(identiteit_id, soort, waarde)`
  rules out exact duplicates, also under concurrent requests. On top of that the register compares a new `waarde`
  case-insensitively with the existing ones, so `Jan@example.nl` and `jan@example.nl` count as the same address. A
  `waarde` is stored as it was received.
- `audit.identiteit_uuid` is deliberately not a foreign key, and its name says that it holds the UUID
  ([0008](0008-audit-by-identiteit-uuid.md)).

The API:

| Endpoint | Purpose |
|---|---|
| `POST /identiteiten` | Find or create the identiteit for the `hmac` in the request body and return its `uuid`; during a key rotation, re-key it ([0009](0009-key-rotation-overlap-window.md)) |
| `DELETE /identiteiten/{uuid}` | Delete the identiteit and its contactgegevens |
| `GET /identiteiten/{uuid}/contactgegevens` | List the contactgegevens of an identiteit, optionally with `isGeverifieerd=true` |
| `POST /identiteiten/{uuid}/contactgegevens` | Add a contactgegeven (not verified) |
| `POST /contactgegevens/{uuid}/verifieer` | Mark a contactgegeven as verified ([0007](0007-verification-derived-from-date.md)) |
| `POST /contactgegevens/{uuid}/primair` | Make a contactgegeven the primary one ([0010](0010-action-endpoints-instead-of-patch.md)) |
| `DELETE /contactgegevens/{uuid}` | Forget a contactgegeven ([0007](0007-verification-derived-from-date.md)) |

Rules for the API:

- An HMAC only travels in a request body, never in a path or query string. That keeps it out of access logs.
- Records are identified by a `uuid`. The internal `id` is never exposed: a contactgegeven refers to its identiteit
  with the property `identiteit`, which holds the UUID. A UUID is used instead of the `id` for three reasons:
  - It can't be guessed or enumerated, where an integer `id` reveals how many records exist and which one comes next.
  - It stays unique outside this installation, so records keep their identifier in an export and after a migration
    to OpenRegister ([0001](0001-dedicated-nextcloud-app.md)).
  - It is never handed out again after a delete, so a reference that the Mijn Omgeving or the audit log
    ([0008](0008-audit-by-identiteit-uuid.md)) still holds can't point at a different record.
- Requests and responses are plain JSON, without the OCS envelope that Nextcloud's OCS API wraps around its data.
- A list is a plain JSON array. An identiteit has a handful of contactgegevens, so there is no pagination.
- Adding a `waarde` that the identiteit already holds for that `soort` returns `409 Conflict`.
- There is no general update. Each supported change has its own endpoint, and a `waarde` can't be changed at all
  ([0010](0010-action-endpoints-instead-of-patch.md)).
- An identiteit may exist without contactgegevens ([0011](0011-identiteit-without-contactgegevens.md)).

How the flow of the Mijn Omgeving maps to the model:

| Flow | Register |
|---|---|
| Email address | `contactgegeven` with `soort` = `email` |
| Primary email address | `isPrimair` = `true`; at most one per identiteit and `soort`, and the first one added is primary automatically |
| Verified email address | `geverifieerdOp` is set ([0007](0007-verification-derived-from-date.md)) |

Version 1 handles email addresses only. Two things are left for later:

- Phone numbers. They fit the model without changes, as `soort` = `telefoonnummer`.
- The preferred channel (email, phone or post). It will be a field on `identiteit`, to be recorded in a new ADR.

### Consequences

- Good, because the register has two tables and an audit log, and seven endpoints.
- Good, because finding or creating an identiteit is one request that can be repeated safely.
- Good, because there is no mapping to a standard to document, and no version of that standard to keep up with.
- Good, because the names can't be mistaken for compatibility with Klantinteracties.
- Good, because Nextcloud's `Entity` maps `snake_case` columns to camelCase properties, so a column such as
  `geverifieerd_op` gives the property `geverifieerdOp` for free. The camelCase names, UUIDs and ISO dates carry over
  to OpenRegister properties unchanged.
- Bad, because a migration to a Klantinteracties API needs a mapping. It would also have to run through the Mijn
  Omgeving, because only the Mijn Omgeving has the raw identifiers and their types.
- Bad, because municipal developers have to learn the names from the API documentation instead of recognising them.

### Confirmation

The OpenAPI document of this repository describes the whole API and is checked in CI. No endpoint takes an HMAC in its
path or query string, and no endpoint returns the internal `id`.

## Pros and Cons of the Options

### 1. Klantinteracties 1:1

- Good, because the names and the API shape come from a public standard with its own documentation.
- Good, because a later migration to Open Klant would be mostly a copy.
- Bad, because the register would still not be compatible: `objectId` would hold an HMAC and the required type fields
  would be missing.
- Bad, because the standard's shape costs code that this register doesn't need: a separate identifier record, nested
  objects, a pagination envelope and filters that join three tables.
- Bad, because the standard is still changing, so the version followed would have to be tracked.

### 2. Own model and API, with Klantinteracties words

- Good, because the model and the API contain only what the Mijn Omgeving needs.
- Good, because the words are familiar in the municipal domain.
- Bad, because the API is custom and has to be documented in full.
- Bad, because the borrowed words suggest a compatibility that isn't there, and they don't fit: a `partij` here would
  be one login identifier, not a party ([0004](0004-hmac-on-identiteit.md)).

### 3. Own model and API, with our own Dutch names

- Good, because the model and the API contain only what the Mijn Omgeving needs.
- Good, because the names are chosen to fit this register exactly, and are shorter.
- Bad, because it invents new words where well-known ones exist.
- Bad, because the API is custom and has to be documented in full.

## More Information

- Klantinteracties specification, for comparison:
  <https://raw.githubusercontent.com/maykinmedia/open-klant/master/src/openklant/components/klantinteracties/openapi.yaml>
- Domain entities stay Dutch per the project rules; framework terms (Controller, Mapper, Service) stay English.
