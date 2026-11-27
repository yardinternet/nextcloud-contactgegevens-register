---
status: accepted
date: 2026-11-23
decision-makers: Marc Schwartz
---

# The register does not know the identifier type

## Context and Problem Statement

Parties log in to the Mijn Omgeving with DigiD (a BSN) or eHerkenning (a KvK number, or depending on the broker an
RSIN, a vestigingsnummer or a pseudonym). The Mijn Omgeving turns whatever identifier it gets into an HMAC
([0002](0002-hmac-by-mijn-omgeving.md)). Does the register need to know which kind of identifier an HMAC came from, or
whether the party is a person or an organisation?

## Decision Drivers

- The register only has to link contact details to an HMAC.
- Mapping login attributes to identifier types is knowledge of the Mijn Omgeving, not of the register.
- The less the register stores about a party, the better.

## Considered Options

1. The register stores only the HMAC
2. The Mijn Omgeving sends the type, and the register stores it next to the HMAC

## Decision Outcome

Chosen option: **1, the register stores only the HMAC.** An `identiteit` has no field for the kind of identifier and no
field for the kind of party ([0003](0003-own-model-and-api.md)).

Which identifier is used for which login, and how it is normalised before hashing, is decided in the Mijn Omgeving.
Supporting a new kind of identifier there needs no change in the register.

### Consequences

- Good, because the register has no type rules to implement, test or keep in line with the login brokers.
- Good, because the register can't tell whether an HMAC belongs to a citizen or an organisation, which is one less
  thing a copy of the database reveals.
- Bad, because the register can't filter or report by identifier type.
- Bad, because a migration to a system that needs the type has to get it from the Mijn Omgeving, together with the
  raw identifier.

### Confirmation

The OpenAPI document has no type field on `identiteit`.

## Pros and Cons of the Options

### 1. Only the HMAC

- Good, because the register stays as small as its task.
- Bad, because the type can't be recovered from the register.

### 2. Type sent by the Mijn Omgeving and stored

- Good, because the register could report by type, and a migration could read the type from it.
- Bad, because the register would store and validate information it never uses.
- Bad, because the list of types would have to follow whatever the login brokers supply.
