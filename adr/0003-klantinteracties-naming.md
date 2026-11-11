---
status: accepted
date: 2026-11-11
decision-makers: Marc Schwartz
---

# Klantinteracties naming 1:1, following version 0.9.2

## Context and Problem Statement

Field names should align with the API standards Klantinteracties and Contactmomenten where possible. The
register could also use Dutch names of its own, such as Identiteit, Contactgegeven, Voorkeur, Verificatie and
Auditregel. Klantinteracties (the VNG standard, implemented by Open Klant) already models parties, their identifiers
and their digital addresses. Which names does the register use for its entities, tables, fields, enum values and API
resources?

## Decision Drivers

- Municipal systems speak Klantinteracties, so matching names make integration and migration cheaper.
- Every deviation from the standard has to be explained to the next maintainer.
- The standard is still changing (0.9.2 at the time of writing), so the version followed must be recorded.

## Considered Options

1. Our own Dutch names (Identiteit, Contactgegeven, Voorkeur, …)
2. Klantinteracties names 1:1 wherever possible, with documented extensions
3. English technical names, with a Klantinteracties mapping layer in the API

## Decision Outcome

Chosen option: **2, Klantinteracties names 1:1**, following the Open Klant `klantinteracties/openapi.yaml`,
**version 0.9.2**. Our own names are only used where Klantinteracties has nothing, and each such extension is listed.

| Table | Klantinteracties |
|---|---|
| `partij` | `partij`, including the preferred channel |
| `partij_identificator` | `partijIdentificator` |
| `digitaal_adres` | `digitaalAdres` |
| `verificatie` | none (extension) |
| `audit` | none (extension) |

Extensions without a Klantinteracties counterpart:

| Field | On | Why |
|---|---|---|
| `pseudoniem`, `sleutelversie` | `partijIdentificator` | Replace `objectId` ([0004](0004-pseudonym-instead-of-objectid.md)) |
| `adresHash` | `digitaalAdres` | Duplicate check on encrypted values ([0002](0002-crypto-in-backend.md)) |
| `voorkeurskanaal` (`email` / `telefoonnummer` / `post`) | `partij` | `voorkeursDigitaalAdres` can't express "post" |
| `laatstGezien` | `partij` | Records when the partij was last seen |
| `verificatie` (entity) | — | Klantinteracties has no verification flow |
| `audit` (entity) | — | Klantinteracties has no audit log |

The version followed is recorded in the OpenAPI document as `info.x-klantinteracties: 0.9.2`.

### Consequences

- Good, because a later migration to Open Klant is mostly a straight copy.
- Good, because municipal developers recognise the model.
- Good, because Nextcloud's `Entity` maps `snake_case` columns to camelCase properties, so columns such as
  `soort_digitaal_adres` give the Klantinteracties names `soortDigitaalAdres` for free.
- Bad, because the model follows the standard's modelling choices. There is no separate preference table (the
  preference lives on `partij`) and no status column ([0007](0007-verification-derived-from-date.md)).
- Bad, because a new Klantinteracties version may rename fields. Upgrading is then a deliberate change, recorded in a
  new ADR.
- Neutral, because Klantinteracties fields the register doesn't use (`url`, `betrokkenen`, `rekeningnummers`, …) are
  left out, not returned empty. The API documentation lists them.

### Confirmation

Code review checks new fields against the specification. The API documentation contains a mapping table showing which
fields are 1:1, which are left out and which are extensions.

## Pros and Cons of the Options

### 1. Our own Dutch names

- Good, because the names could be chosen to fit this register exactly.
- Bad, because every integration with a Klantinteracties system would need a translation layer.

### 2. Klantinteracties 1:1

- Good, because the names come from a public standard with its own documentation.
- Bad, because the register has to follow the standard's modelling choices, also where a separate entity
  would be simpler.

### 3. English names with a mapping layer

- Good, because it matches the English used in code.
- Bad, because it adds a mapping layer and makes the database harder to read next to Klantinteracties.

## More Information

- Specification:
  <https://raw.githubusercontent.com/maykinmedia/open-klant/master/src/openklant/components/klantinteracties/openapi.yaml>
- Domain entities stay Dutch per the project rules; framework terms (Controller, Mapper, Service) stay English.
