<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Tests\Integration\Service;

use DateTimeImmutable;
use DateTimeZone;
use OCA\ContactgegevensRegister\Db\Contactgegeven;
use OCA\ContactgegevensRegister\Db\ContactgegevenMapper;
use OCA\ContactgegevensRegister\Db\Identiteit;
use OCA\ContactgegevensRegister\Db\IdentiteitMapper;
use OCA\ContactgegevensRegister\Exception\NietGevondenException;
use OCA\ContactgegevensRegister\Service\ContactgegevenService;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Uid\Uuid;
use Test\TestCase;

#[Group('DB')]
final class ContactgegevenServiceTest extends TestCase {
	private ContactgegevenService $service;
	private ContactgegevenMapper $mapper;
	private IdentiteitMapper $identiteitMapper;
	private Identiteit $identiteit;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
		$this->service = Server::get(ContactgegevenService::class);
		$this->mapper = Server::get(ContactgegevenMapper::class);
		$this->identiteitMapper = Server::get(IdentiteitMapper::class);

		$this->identiteit = new Identiteit();
		$this->identiteit->setUuid(Uuid::v4()->toRfc4122());
		$this->identiteit->setHmac(bin2hex(random_bytes(32)));
		$this->identiteit->setHmacGezetOp(new DateTimeImmutable('now', new DateTimeZone('UTC')));
		$this->identiteitMapper->insert($this->identiteit);
	}

	#[\Override]
	protected function tearDown(): void {
		// The foreign key removes the contactgegevens of the identiteit.
		$this->identiteitMapper->deleteByIds([$this->identiteit->getId()]);
		parent::tearDown();
	}

	public function testFindByIdentiteitReturnsAllContactgegevens(): void {
		$verified = $this->insert('a@example.org', new DateTimeImmutable('2026-11-26'), true);
		$unverified = $this->insert('b@example.org');

		$result = $this->service->findByIdentiteit($this->identiteit->getUuid());

		$this->assertSame([
			[
				'uuid' => $verified->getUuid(),
				'identiteit' => $this->identiteit->getUuid(),
				'soort' => 'email',
				'waarde' => 'a@example.org',
				'geverifieerdOp' => '2026-11-26',
				'isPrimair' => true,
			],
			[
				'uuid' => $unverified->getUuid(),
				'identiteit' => $this->identiteit->getUuid(),
				'soort' => 'email',
				'waarde' => 'b@example.org',
				'geverifieerdOp' => null,
				'isPrimair' => false,
			],
		], $result);
	}

	public function testFindByIdentiteitCanReturnOnlyVerifiedContactgegevens(): void {
		$verified = $this->insert('a@example.org', new DateTimeImmutable('2026-11-26'));
		$this->insert('b@example.org');

		$result = $this->service->findByIdentiteit($this->identiteit->getUuid(), true);

		$this->assertSame([$verified->getUuid()], array_column($result, 'uuid'));
	}

	public function testFindByIdentiteitReturnsAnEmptyListForAnIdentiteitWithoutContactgegevens(): void {
		$this->assertSame([], $this->service->findByIdentiteit($this->identiteit->getUuid()));
	}

	public function testFindByIdentiteitThrowsForUnknownIdentiteit(): void {
		$this->expectException(NietGevondenException::class);
		$this->service->findByIdentiteit(Uuid::v4()->toRfc4122());
	}

	private function insert(string $waarde, ?DateTimeImmutable $geverifieerdOp = null, bool $isPrimair = false): Contactgegeven {
		$contactgegeven = new Contactgegeven();
		$contactgegeven->setUuid(Uuid::v4()->toRfc4122());
		$contactgegeven->setIdentiteitId($this->identiteit->getId());
		$contactgegeven->setSoort('email');
		$contactgegeven->setWaarde($waarde);
		$contactgegeven->setGeverifieerdOp($geverifieerdOp);
		$contactgegeven->setIsPrimair($isPrimair);
		return $this->mapper->insert($contactgegeven);
	}
}
