<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Tests\Integration\Db;

use DateTimeImmutable;
use DateTimeZone;
use OCA\ContactgegevensRegister\Db\Contactgegeven;
use OCA\ContactgegevensRegister\Db\ContactgegevenMapper;
use OCA\ContactgegevensRegister\Db\Identiteit;
use OCA\ContactgegevensRegister\Db\IdentiteitMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Uid\Uuid;
use Test\TestCase;

#[Group('DB')]
final class IdentiteitMapperTest extends TestCase {
	private IdentiteitMapper $mapper;
	private ContactgegevenMapper $contactgegevenMapper;
	/** @var list<int> */
	private array $createdIds = [];

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
		$this->mapper = Server::get(IdentiteitMapper::class);
		$this->contactgegevenMapper = Server::get(ContactgegevenMapper::class);
	}

	#[\Override]
	protected function tearDown(): void {
		$this->mapper->deleteByIds($this->createdIds);
		parent::tearDown();
	}

	public function testFindsByIdByUuidAndByHmac(): void {
		$identiteit = $this->createIdentiteit(new DateTimeImmutable('2026-11-26 12:34:56', new DateTimeZone('UTC')));

		$byId = $this->mapper->findById($identiteit->getId());
		$byUuid = $this->mapper->findByUuid($identiteit->getUuid());
		$byHmac = $this->mapper->findByHmac($identiteit->getHmac());

		$this->assertSame($identiteit->getUuid(), $byId->getUuid());
		$this->assertSame($identiteit->getId(), $byUuid->getId());
		$this->assertSame($identiteit->getId(), $byHmac->getId());
		$this->assertSame('2026-11-26 12:34:56', $byUuid->getHmacGezetOp()->format('Y-m-d H:i:s'));
		$this->assertSame(['uuid' => $identiteit->getUuid()], $byUuid->jsonSerialize());
	}

	public function testFindByUuidThrowsForUnknownUuid(): void {
		$this->expectException(DoesNotExistException::class);
		$this->mapper->findByUuid(Uuid::v4()->toRfc4122());
	}

	public function testHmacIsUnique(): void {
		$first = $this->createIdentiteit();

		try {
			$this->createIdentiteit(hmac: $first->getHmac());
			$this->fail('A second identiteit with the same hmac was inserted');
		} catch (Exception $e) {
			$this->assertSame(Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION, $e->getReason());
		}
	}

	public function testDeletingAnIdentiteitDeletesItsContactgegevens(): void {
		$identiteit = $this->createIdentiteit();
		$contactgegeven = new Contactgegeven();
		$contactgegeven->setUuid(Uuid::v4()->toRfc4122());
		$contactgegeven->setIdentiteitId($identiteit->getId());
		$contactgegeven->setSoort('email');
		$contactgegeven->setWaarde('a@example.org');
		$contactgegeven->setIsPrimair(true);
		$this->contactgegevenMapper->insert($contactgegeven);

		$this->assertSame(1, $this->mapper->deleteByIds([$identiteit->getId()]));

		$this->assertSame([], $this->contactgegevenMapper->findByIdentiteit($identiteit->getId()));
		$this->expectException(DoesNotExistException::class);
		$this->contactgegevenMapper->findByUuid($contactgegeven->getUuid());
	}

	public function testFindsIdsWithHmacGezetOpBefore(): void {
		$before = $this->createIdentiteit(new DateTimeImmutable('2000-01-01 23:59:59', new DateTimeZone('UTC')));
		$at = $this->createIdentiteit(new DateTimeImmutable('2000-01-02 00:00:00', new DateTimeZone('UTC')));

		$ids = $this->mapper->findIdsWithHmacGezetOpBefore(new DateTimeImmutable('2000-01-02 00:00:00', new DateTimeZone('UTC')));

		$this->assertContains($before->getId(), $ids);
		$this->assertNotContains($at->getId(), $ids);
	}

	public function testFindsIdsWithoutContactgegevensBefore(): void {
		$old = new DateTimeImmutable('2000-01-01 00:00:00', new DateTimeZone('UTC'));
		$empty = $this->createIdentiteit($old);
		$withContactgegeven = $this->createIdentiteit($old);
		$recent = $this->createIdentiteit(new DateTimeImmutable('2000-01-03 00:00:00', new DateTimeZone('UTC')));

		$contactgegeven = new Contactgegeven();
		$contactgegeven->setUuid(Uuid::v4()->toRfc4122());
		$contactgegeven->setIdentiteitId($withContactgegeven->getId());
		$contactgegeven->setSoort('email');
		$contactgegeven->setWaarde('b@example.org');
		$this->contactgegevenMapper->insert($contactgegeven);

		$ids = $this->mapper->findIdsWithoutContactgegevensBefore(new DateTimeImmutable('2000-01-02 00:00:00', new DateTimeZone('UTC')));

		$this->assertContains($empty->getId(), $ids);
		$this->assertNotContains($withContactgegeven->getId(), $ids);
		$this->assertNotContains($recent->getId(), $ids);
	}

	public function testDeleteByIdsReturnsTheNumberOfDeletedRows(): void {
		$first = $this->createIdentiteit();
		$second = $this->createIdentiteit();

		$this->assertSame(2, $this->mapper->deleteByIds([$first->getId(), $second->getId()]));
		$this->assertSame(0, $this->mapper->deleteByIds([]));
	}

	private function createIdentiteit(?DateTimeImmutable $hmacGezetOp = null, ?string $hmac = null): Identiteit {
		$identiteit = new Identiteit();
		$identiteit->setUuid(Uuid::v4()->toRfc4122());
		$identiteit->setHmac($hmac ?? bin2hex(random_bytes(32)));
		$identiteit->setHmacGezetOp($hmacGezetOp ?? new DateTimeImmutable('now', new DateTimeZone('UTC')));
		$this->mapper->insert($identiteit);
		$this->createdIds[] = $identiteit->getId();

		return $identiteit;
	}
}
