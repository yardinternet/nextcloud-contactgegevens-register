<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Service;

use OCA\ContactgegevensRegister\Db\Contactgegeven;
use OCA\ContactgegevensRegister\Db\ContactgegevenMapper;
use OCA\ContactgegevensRegister\Db\Identiteit;
use OCA\ContactgegevensRegister\Db\IdentiteitMapper;
use OCA\ContactgegevensRegister\Exception\NietGevondenException;
use OCA\ContactgegevensRegister\ResponseDefinitions;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;

/**
 * @psalm-import-type ContactgegevensRegisterContactgegeven from ResponseDefinitions
 */
class ContactgegevenService {
	/** @psalm-suppress PossiblyUnusedMethod Instantiated by the DI container */
	public function __construct(
		private readonly ContactgegevenMapper $mapper,
		private readonly IdentiteitMapper $identiteitMapper,
	) {
	}

	/**
	 * The contactgegevens of an identiteit, oldest first.
	 *
	 * @return list<ContactgegevensRegisterContactgegeven>
	 * @throws NietGevondenException
	 * @throws Exception
	 */
	public function findByIdentiteit(string $identiteitUuid, bool $alleenGeverifieerd = false): array {
		$identiteit = $this->findIdentiteit($identiteitUuid);
		$contactgegevens = $this->mapper->findByIdentiteit($identiteit->getId(), $alleenGeverifieerd ? true : null);

		return array_map(
			static fn (Contactgegeven $contactgegeven): array => $contactgegeven->toApiData($identiteitUuid),
			$contactgegevens,
		);
	}

	/**
	 * @throws NietGevondenException
	 * @throws Exception
	 */
	private function findIdentiteit(string $uuid): Identiteit {
		try {
			return $this->identiteitMapper->findByUuid($uuid);
		} catch (DoesNotExistException) {
			throw new NietGevondenException('Identiteit not found');
		}
	}
}
