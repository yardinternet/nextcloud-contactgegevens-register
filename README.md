# Contactgegevens Register

Afgeschermd register dat een gepseudonimiseerde identiteit (BSN via DigiD, of een bedrijfsidentificatie via
eHerkenning) koppelt aan geverifieerde, versleutelde e-mailadressen en telefoonnummers en een voorkeurskanaal.

De app is bedoeld voor gebruik met de Mijn Omgeving van een gemeente. Daar is na inloggen een BSN of KvK-nummer
bekend, maar geen contactgegevens. De backend van de Mijn Omgeving pseudonimiseert en versleutelt; het register ziet
nooit een BSN, KvK-nummer, e-mailadres of telefoonnummer in platte tekst. Veldnamen volgen de API-standaard
Klantinteracties.

## Vereisten

- Nextcloud 34 of 35
- PHP 8.3 of hoger
- MySQL 8.0+, PostgreSQL 10+ of SQLite (Oracle wordt niet ondersteund)

## Development

Installeer de dependencies (inclusief de tools onder `vendor-bin/`):

```bash
composer install
```

Beschikbare scripts:

| Commando                 | Omschrijving                                        |
|--------------------------|-----------------------------------------------------|
| `composer lint`          | PHP syntax check                                    |
| `composer cs:check`      | Code style controleren (php-cs-fixer)               |
| `composer cs:fix`        | Code style automatisch herstellen                   |
| `composer psalm`         | Statische analyse                                   |
| `composer test:unit`     | Unit tests (draaien binnen een Nextcloud-installatie) |
| `composer openapi`       | `openapi.json` opnieuw genereren                    |
| `composer rector`        | Rector refactorings toepassen                       |

Commits volgen de [Conventional Commits](https://www.conventionalcommits.org/) conventie; dit wordt in CI
gecontroleerd voor pull requests.

## Licentie

[AGPL-3.0-or-later](LICENSE)
