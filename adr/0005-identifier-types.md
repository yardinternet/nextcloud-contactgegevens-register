---
status: accepted
date: 2026-11-11
decision-makers: Marc Schwartz
---

# Identifier types, including `eherkenning_pseudo` via `anderePartijIdentificator`

## Context and Problem Statement

Parties log in to the Mijn Omgeving with DigiD (a BSN) or eHerkenning. Depending on the broker, service and
authorisation scenario, eHerkenning can supply a KvK number, an RSIN, a BSN, a vestigingsnummer, or a pseudonym
(`Pseudo` / `PseudoID`). It also distinguishes the person who logs in (`ActingSubjectID`) from the legal entity they
represent (`LegalSubjectID`). How are these identifiers typed in the register?

## Decision Drivers

- The type is part of the HMAC input ([0006](0006-pseudonym-unique-on-its-own.md)), so type codes must be stable and
  unambiguous.
- Naming follows Klantinteracties ([0003](0003-klantinteracties-naming.md)).
- An eHerkenning pseudonym is not a BSN, RSIN, KvK number or vestigingsnummer.

## Considered Options

1. Klantinteracties codes for the four registry identifiers, and `anderePartijIdentificator = eherkenning_pseudo`
   for eHerkenning pseudonyms
2. Klantinteracties codes only, mapping an eHerkenning pseudonym onto the closest of the four
3. Our own coarser types (`bsn` / `kvk-rsin` / `kvk-vestiging`)

## Decision Outcome

Chosen option: **1.** A `partij_identificator` uses Klantinteracties' `partijIdentificator` group
(`codeObjecttype`, `codeSoortObjectId`, `codeRegister`) for the four registry identifiers. For an eHerkenning
pseudonym it uses Klantinteracties' own free-text field `anderePartijIdentificator`.

| Identifier | Source | `codeObjecttype` | `codeSoortObjectId` | `codeRegister` | `anderePartijIdentificator` |
|---|---|---|---|---|---|
| BSN | DigiD, or eHerkenning `EntityConcernedID:BSN` | `natuurlijk_persoon` | `bsn` | `brp` | |
| RSIN | eHerkenning `EntityConcernedID:RSIN` | `niet_natuurlijk_persoon` | `rsin` | `hr` | |
| KvK number | eHerkenning `EntityConcernedID:KvKnr` | `niet_natuurlijk_persoon` | `kvk_nummer` | `hr` | |
| Vestigingsnummer | eHerkenning, attribute name to be confirmed | `vestiging` | `vestigingsnummer` | `hr` | |
| eHerkenning pseudonym | eHerkenning `Pseudo` / `PseudoID` | | | | `eherkenning_pseudo` |

Rules, checked in PHP:

- Exactly one of `codeSoortObjectId` and `anderePartijIdentificator` is set.
- The three `code*` fields are set together.
- A vestigingsnummer has `subIdentificatorVan`, pointing to the `partij_identificator` of its KvK number, as in
  Klantinteracties. Other identifiers leave it empty.
- An eHerkenning pseudonym is never mapped onto one of the four codes.
- The register stores the represented legal entity, not the person who logs in. Contact details belong to the entity.
- `soortPartij` is `persoon` for a BSN and `organisatie` otherwise. Klantinteracties' `contactpersoon` is not used.

### Consequences

- Good, because each type has one stable code, which the HMAC input can rely on.
- Good, because pseudonyms are kept apart from real registry identifiers.
- Bad, because the backend must map SAML attributes to these codes based on what the broker really sends.
- Bad, because the type codes are frozen: changing one changes every pseudonym computed with it.

### Confirmation

Unit tests cover the PHP rules above, including the `subIdentificatorVan` rule for vestigingsnummers.

## Pros and Cons of the Options

### 1. Klantinteracties codes plus `anderePartijIdentificator`

- Good, because it uses the standard's own escape hatch for identifiers outside its code lists.
- Bad, because `anderePartijIdentificator` is free text, so the allowed value is enforced by this app, not by the
  standard.

### 2. Map pseudonyms onto the four codes

- Good, because every identifier would use the same fields.
- Bad, because it would misrepresent a pseudonym as a registry number.

### 3. Our own coarser types

- Good, because there are fewer types to handle.
- Bad, because they combine KvK number and RSIN into one type, and don't follow Klantinteracties.

## More Information

Still open with the backend team and the broker:

- Which SAML attributes actually arrive, and whether the broker transforms them. Anonymised sample assertions are
  requested.
- The attribute name of the vestigingsnummer.
- If one login delivers both a KvK number and an RSIN, whether both are attached to one partij.
- Whether a vestiging is its own partij or an identifier of the KvK number's partij.
