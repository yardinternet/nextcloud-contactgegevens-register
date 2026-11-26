<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Db;

use DateTimeImmutable;
use OCA\ContactgegevensRegister\ResponseDefinitions;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * A contact detail of an identiteit. Verification is derived from geverifieerdOp.
 *
 * @method string getUuid()
 * @method void setUuid(string $uuid)
 * @method int getIdentiteitId()
 * @method void setIdentiteitId(int $identiteitId)
 * @method string getSoort()
 * @method void setSoort(string $soort)
 * @method string getWaarde()
 * @method void setWaarde(string $waarde)
 * @method DateTimeImmutable|null getGeverifieerdOp()
 * @method void setGeverifieerdOp(?DateTimeImmutable $geverifieerdOp)
 * @method bool getIsPrimair()
 * @method void setIsPrimair(bool $isPrimair)
 *
 * @psalm-import-type ContactgegevensRegisterContactgegeven from ResponseDefinitions
 *
 * The properties are read and written through Entity's magic accessors, and $id is set by the mapper.
 * @psalm-suppress PossiblyUnusedProperty
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class Contactgegeven extends Entity {
	protected string $uuid = '';
	protected int $identiteitId = 0;
	protected string $soort = '';
	protected string $waarde = '';
	protected ?DateTimeImmutable $geverifieerdOp = null;
	protected bool $isPrimair = false;

	public function __construct() {
		$this->addType('uuid', Types::STRING);
		$this->addType('identiteitId', Types::BIGINT);
		$this->addType('soort', Types::STRING);
		$this->addType('waarde', Types::STRING);
		$this->addType('geverifieerdOp', Types::DATE_IMMUTABLE);
		$this->addType('isPrimair', Types::BOOLEAN);
	}

	/**
	 * The API shape. The entity only knows the internal id of its identiteit, so the caller passes the UUID.
	 *
	 * @return ContactgegevensRegisterContactgegeven
	 */
	public function toApiData(string $identiteitUuid): array {
		return [
			'uuid' => $this->uuid,
			'identiteit' => $identiteitUuid,
			'soort' => $this->soort,
			'waarde' => $this->waarde,
			'geverifieerdOp' => $this->geverifieerdOp?->format('Y-m-d'),
			'isPrimair' => $this->isPrimair,
		];
	}
}
