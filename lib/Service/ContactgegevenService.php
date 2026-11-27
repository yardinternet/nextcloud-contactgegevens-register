<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Service;

use DateTimeZone;
use OCA\ContactgegevensRegister\Db\Contactgegeven;
use OCA\ContactgegevensRegister\Db\ContactgegevenMapper;
use OCA\ContactgegevensRegister\Db\Identiteit;
use OCA\ContactgegevensRegister\Db\IdentiteitMapper;
use OCA\ContactgegevensRegister\Exception\DubbelContactgegevenException;
use OCA\ContactgegevensRegister\Exception\NietGevondenException;
use OCA\ContactgegevensRegister\Exception\OngeldigeInvoerException;
use OCA\ContactgegevensRegister\ResponseDefinitions;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\TTransactional;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\IDBConnection;
use Symfony\Component\Uid\Uuid;

/**
 * @psalm-import-type ContactgegevensRegisterContactgegeven from ResponseDefinitions
 */
class ContactgegevenService {
	use TTransactional;

	/** Version 1 only stores email addresses; telefoonnummer follows later. */
	private const array SOORTEN = ['email'];

	/** @psalm-suppress PossiblyUnusedMethod Instantiated by the DI container */
	public function __construct(
		private readonly ContactgegevenMapper $mapper,
		private readonly IdentiteitMapper $identiteitMapper,
		private readonly IDBConnection $db,
		private readonly ITimeFactory $timeFactory,
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
	 * Adds a contactgegeven, not verified. It becomes primary when the identiteit has no other contactgegeven
	 * of that soort (ADR 0010).
	 *
	 * @return ContactgegevensRegisterContactgegeven
	 * @throws NietGevondenException
	 * @throws OngeldigeInvoerException
	 * @throws DubbelContactgegevenException
	 * @throws Exception
	 */
	public function add(string $identiteitUuid, string $soort, string $waarde): array {
		$this->validate($soort, $waarde);
		$identiteit = $this->findIdentiteit($identiteitUuid);

		try {
			$contactgegeven = $this->atomic(function () use ($identiteit, $soort, $waarde): Contactgegeven {
				if ($this->mapper->existsWaarde($identiteit->getId(), $soort, $waarde)) {
					throw new DubbelContactgegevenException('The identiteit already has this ' . $soort);
				}

				$contactgegeven = new Contactgegeven();
				$contactgegeven->setUuid(Uuid::v4()->toRfc4122());
				$contactgegeven->setIdentiteitId($identiteit->getId());
				$contactgegeven->setSoort($soort);
				$contactgegeven->setWaarde($waarde);
				$contactgegeven->setIsPrimair(!$this->hasSoort($identiteit, $soort));
				return $this->mapper->insert($contactgegeven);
			}, $this->db);
		} catch (Exception $e) {
			if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
			// Another request added the same waarde at the same moment.
			throw new DubbelContactgegevenException('The identiteit already has this ' . $soort, 0, $e);
		}

		return $contactgegeven->toApiData($identiteitUuid);
	}

	/**
	 * Marks a contactgegeven as verified today (UTC). Repeating it keeps the original date (ADR 0007, 0010).
	 *
	 * @return ContactgegevensRegisterContactgegeven
	 * @throws NietGevondenException
	 * @throws Exception
	 */
	public function verify(string $uuid): array {
		$contactgegeven = $this->findContactgegeven($uuid);
		if ($contactgegeven->getGeverifieerdOp() === null) {
			$contactgegeven->setGeverifieerdOp($this->timeFactory->now()->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0));
			$contactgegeven = $this->mapper->update($contactgegeven);
		}

		return $this->toApiData($contactgegeven);
	}

	/**
	 * Makes a contactgegeven the primary one of its identiteit and soort, and clears the flag on the current
	 * primary one in the same transaction (ADR 0010). It doesn't have to be verified.
	 *
	 * @return ContactgegevensRegisterContactgegeven
	 * @throws NietGevondenException
	 * @throws Exception
	 */
	public function makePrimair(string $uuid): array {
		$contactgegeven = $this->atomic(function () use ($uuid): Contactgegeven {
			$contactgegeven = $this->findContactgegeven($uuid);
			if ($contactgegeven->getIsPrimair()) {
				return $contactgegeven;
			}

			$huidige = $this->mapper->findPrimair($contactgegeven->getIdentiteitId(), $contactgegeven->getSoort());
			if ($huidige !== null) {
				$huidige->setIsPrimair(false);
				$this->mapper->update($huidige);
			}
			$contactgegeven->setIsPrimair(true);
			return $this->mapper->update($contactgegeven);
		}, $this->db);

		return $this->toApiData($contactgegeven);
	}

	/**
	 * Forgets a contactgegeven: a hard delete. When it was the primary one, the identiteit has none until the
	 * client makes another one primary (ADR 0007).
	 *
	 * @throws NietGevondenException
	 * @throws Exception
	 */
	public function delete(string $uuid): void {
		$this->mapper->delete($this->findContactgegeven($uuid));
	}

	/**
	 * @throws OngeldigeInvoerException
	 */
	private function validate(string $soort, string $waarde): void {
		if (!in_array($soort, self::SOORTEN, true)) {
			throw new OngeldigeInvoerException('soort must be one of: ' . implode(', ', self::SOORTEN));
		}
		// Also rejects addresses longer than 254 characters (RFC 5321), the length of the column.
		if (filter_var($waarde, FILTER_VALIDATE_EMAIL) === false) {
			throw new OngeldigeInvoerException('waarde must be a valid email address');
		}
	}

	/**
	 * @throws Exception
	 */
	private function hasSoort(Identiteit $identiteit, string $soort): bool {
		foreach ($this->mapper->findByIdentiteit($identiteit->getId()) as $contactgegeven) {
			if ($contactgegeven->getSoort() === $soort) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @throws NietGevondenException
	 * @throws Exception
	 */
	private function findContactgegeven(string $uuid): Contactgegeven {
		try {
			return $this->mapper->findByUuid($uuid);
		} catch (DoesNotExistException) {
			throw new NietGevondenException('Contactgegeven not found');
		}
	}

	/**
	 * @return ContactgegevensRegisterContactgegeven
	 * @throws Exception
	 */
	private function toApiData(Contactgegeven $contactgegeven): array {
		$identiteit = $this->identiteitMapper->findById($contactgegeven->getIdentiteitId());
		return $contactgegeven->toApiData($identiteit->getUuid());
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
