<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Service;

use DateTimeZone;
use OCA\ContactgegevensRegister\Db\Identiteit;
use OCA\ContactgegevensRegister\Db\IdentiteitMapper;
use OCA\ContactgegevensRegister\Exception\OngeldigeInvoerException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\TTransactional;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\IDBConnection;
use Symfony\Component\Uid\Uuid;

class IdentiteitService {
	use TTransactional;

	/** HMAC-SHA256 as lowercase hex. D: `$` must not match before a trailing newline. */
	private const string HMAC_PATTERN = '/^[0-9a-f]{64}$/D';

	/** @psalm-suppress PossiblyUnusedMethod Instantiated by the DI container */
	public function __construct(
		private readonly IdentiteitMapper $mapper,
		private readonly IDBConnection $db,
		private readonly ITimeFactory $timeFactory,
	) {
	}

	/**
	 * Finds the identiteit of an HMAC or creates it. During a key rotation, the identiteit that only has
	 * the previous HMAC is re-keyed (ADR 0009).
	 *
	 * @return array{identiteit: Identiteit, aangemaakt: bool}
	 * @throws OngeldigeInvoerException
	 * @throws Exception
	 */
	public function findOrCreate(string $hmac, ?string $vorigeHmac = null): array {
		$this->validateHmac('hmac', $hmac);
		if ($vorigeHmac !== null) {
			$this->validateHmac('vorigeHmac', $vorigeHmac);
			if ($vorigeHmac === $hmac) {
				throw new OngeldigeInvoerException('vorigeHmac must differ from hmac');
			}
		}

		try {
			return $this->atomic(fn (): array => $this->findOrCreateInTransaction($hmac, $vorigeHmac), $this->db);
		} catch (Exception $e) {
			if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
			// Another request created or re-keyed an identiteit with this hmac at the same moment: return that one.
			return ['identiteit' => $this->mapper->findByHmac($hmac), 'aangemaakt' => false];
		}
	}

	/**
	 * @return array{identiteit: Identiteit, aangemaakt: bool}
	 * @throws Exception
	 */
	private function findOrCreateInTransaction(string $hmac, ?string $vorigeHmac): array {
		$identiteit = $this->findByHmac($hmac);
		if ($identiteit !== null) {
			// Also when an identiteit with vorigeHmac exists: that one is left for the purge.
			return ['identiteit' => $identiteit, 'aangemaakt' => false];
		}

		$vorige = $vorigeHmac === null ? null : $this->findByHmac($vorigeHmac);
		if ($vorige !== null) {
			$vorige->setHmac($hmac);
			$vorige->setHmacGezetOp($this->now());
			return ['identiteit' => $this->mapper->update($vorige), 'aangemaakt' => false];
		}

		$identiteit = new Identiteit();
		$identiteit->setUuid(Uuid::v4()->toRfc4122());
		$identiteit->setHmac($hmac);
		$identiteit->setHmacGezetOp($this->now());
		return ['identiteit' => $this->mapper->insert($identiteit), 'aangemaakt' => true];
	}

	/**
	 * @throws Exception
	 */
	private function findByHmac(string $hmac): ?Identiteit {
		try {
			return $this->mapper->findByHmac($hmac);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * @throws OngeldigeInvoerException
	 */
	private function validateHmac(string $field, string $value): void {
		if (preg_match(self::HMAC_PATTERN, $value) !== 1) {
			throw new OngeldigeInvoerException($field . ' must be 64 lowercase hexadecimal characters');
		}
	}

	private function now(): \DateTimeImmutable {
		return $this->timeFactory->now()->setTimezone(new DateTimeZone('UTC'));
	}
}
