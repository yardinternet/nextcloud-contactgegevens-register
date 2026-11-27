<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Db;

use DateTimeImmutable;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;
use Override;

/**
 * A pseudonymised login identifier. The hmac is never exposed by the API.
 *
 * @method string getUuid()
 * @method void setUuid(string $uuid)
 * @method string getHmac()
 * @method void setHmac(string $hmac)
 * @method DateTimeImmutable getHmacGezetOp()
 * @method void setHmacGezetOp(DateTimeImmutable $hmacGezetOp)
 *
 * The properties are read and written through Entity's magic accessors, and $id is set by the mapper.
 * @psalm-suppress PossiblyUnusedProperty
 * @psalm-suppress PropertyNotSetInConstructor
 * @psalm-suppress UnusedClass Used by the API and the purge, which are not built yet.
 */
final class Identiteit extends Entity implements JsonSerializable {
	protected string $uuid = '';
	protected string $hmac = '';
	protected ?DateTimeImmutable $hmacGezetOp = null;

	public function __construct() {
		$this->addType('uuid', Types::STRING);
		$this->addType('hmac', Types::STRING);
		$this->addType('hmacGezetOp', Types::DATETIME_IMMUTABLE);
	}

	/**
	 * @return array{uuid: string}
	 */
	#[Override]
	public function jsonSerialize(): array {
		return [
			'uuid' => $this->uuid,
		];
	}
}
