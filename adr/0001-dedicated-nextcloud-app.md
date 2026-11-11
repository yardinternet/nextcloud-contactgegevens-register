---
status: accepted
date: 2026-11-10
decision-makers: Marc Schwartz
---

# Dedicated Nextcloud app with an OpenRegister-compatible format

## Context and Problem Statement

The Mijn Omgeving needs a shielded register that links a pseudonymised party (a citizen via DigiD, or an organisation
via eHerkenning) to verified, encrypted email addresses and phone numbers and a preferred channel. Nextcloud is a given
platform. Can this be built entirely with OpenRegister schemas and its object encryption, or is a dedicated Nextcloud
app needed for field-level encryption and unique indexes?

The result must be transferable to other parties, so it has to be small, stable and documented.

## Decision Drivers

- Uniqueness of pseudonyms and addresses must hold under concurrent requests.
- No administrator of the Nextcloud instance may be able to decrypt contact details.
- Access must be denied by default.
- The codebase must be small and stable enough to hand over and maintain.
- A later move to OpenRegister or to a Klantinteracties API must stay cheap.

## Considered Options

1. OpenRegister schemas with OpenRegister's object encryption
2. A dedicated app that rebuilds OpenRegister's generic schema engine
3. A dedicated app with ordinary Nextcloud `Entity`/`QBMapper` classes and migrations, using OpenRegister's
   configuration and export format
4. A service outside Nextcloud

## Decision Outcome

Chosen option: **3, a dedicated app with plain `Entity`/`QBMapper` classes, using OpenRegister's format.** It is the
only option that meets the uniqueness, encryption and access requirements while staying small. Following
OpenRegister's format (an OpenAPI 3 configuration file, UUIDs, and a JSON export with `@self` metadata) keeps a later
migration to OpenRegister a plain import.

### Consequences

- Good, because uniqueness is enforced by database indexes instead of PHP checks.
- Good, because the app contains only what this register needs, which keeps it reviewable and transferable.
- Good, because access control is written for this register, deny by default.
- Good, because the OpenRegister-compatible export keeps the migration path open.
- Bad, because the app has to build its own REST layer, token authentication and export, which OpenRegister would
  have provided.
- Bad, because compatibility with OpenRegister's format has to be tested, since OpenRegister may change it.

### Confirmation

A CI job imports the configuration file and an export into a throwaway OpenRegister instance and compares object
counts and a sample of values.

## Pros and Cons of the Options

### 1. OpenRegister schemas and object encryption

- Good, because no new app is needed and the generic engine already exists.
- Bad, because its encryption uses the Nextcloud instance secret, so an instance administrator can decrypt
  everything.
- Bad, because it checks uniqueness in PHP, which allows duplicates under concurrent inserts.
- Bad, because its authorization is open by default.
- Bad, because the codebase is too large and changes too fast to hand over: about 584,000 lines and about 900 commits
  a month in November 2026.

### 2. Dedicated app that rebuilds OpenRegister's generic engine

- Good, because it could fix the encryption, uniqueness and authorization problems.
- Bad, because a generic schema engine is far more code than one fixed data model needs, and would recreate the
  handover problem of option 1.

### 3. Dedicated app with plain Nextcloud entities and OpenRegister's format

- Good, because the data model is fixed, so plain entities and migrations are enough.
- Good, because database constraints give race-free uniqueness.
- Neutral, because it uses only the public Nextcloud API (`OCP\`), which ties it to Nextcloud but keeps upgrades
  predictable.
- Bad, because the REST layer, authentication and export are custom code.

### 4. Service outside Nextcloud

- Good, because it would be free of Nextcloud's constraints.
- Bad, because Nextcloud is a given platform.

## More Information

- Related: [0002](0002-crypto-in-backend.md) moves hashing and encryption out of the register entirely.
- The OpenRegister-compatible configuration file and export are specified in the OpenAPI document of this repository.
