<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Tests\Integration\Db;

use DateTimeImmutable;
use DateTimeZone;
use OCA\ContactgegevensRegister\Db\Contactgegeven;
use OCA\ContactgegevensRegister\Db\ContactgegevenMapper;
use OCA\ContactgegevensRegister\Db\Identiteit;
use OCA\ContactgegevensRegister\Db\IdentiteitMapper;
use OCP\DB\Exception;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Uid\Uuid;
use Test\TestCase;

#[Group('DB')]
final class ContactgegevenMapperTest extends TestCase {
	private ContactgegevenMapper $mapper;
	private IdentiteitMapper $identiteitMapper;
	private Identiteit $identiteit;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
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

	public function testStoresAndReadsAllFields(): void {
		$contactgegeven = $this->createContactgegeven('Jan@Example.org', new DateTimeImmutable('2026-11-26'), true);

		$found = $this->mapper->findByUuid($contactgegeven->getUuid());

		$this->assertSame([
			'uuid' => $contactgegeven->getUuid(),
			'identiteit' => $this->identiteit->getUuid(),
			'soort' => 'email',
			'waarde' => 'Jan@Example.org',
			'geverifieerdOp' => '2026-11-26',
			'isPrimair' => true,
		], $found->toApiData($this->identiteit->getUuid()));
		$this->assertSame($this->identiteit->getId(), $found->getIdentiteitId());
	}

	public function testUnverifiedAndNotPrimairAreReadBack(): void {
		$contactgegeven = $this->createContactgegeven('jan@example.org');

		$found = $this->mapper->findByUuid($contactgegeven->getUuid());

		$this->assertNull($found->getGeverifieerdOp());
		$this->assertFalse($found->getIsPrimair());
	}

	public function testWaardeIsUniquePerIdentiteitAndSoort(): void {
		$this->createContactgegeven('jan@example.org');

		try {
			$this->createContactgegeven('jan@example.org');
			$this->fail('A duplicate contactgegeven was inserted');
		} catch (Exception $e) {
			$this->assertSame(Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION, $e->getReason());
		}
	}

	public function testExistsWaardeComparesCaseInsensitively(): void {
		$this->createContactgegeven('Jan@Example.org');

		$this->assertTrue($this->mapper->existsWaarde($this->identiteit->getId(), 'email', 'jan@example.org'));
		$this->assertTrue($this->mapper->existsWaarde($this->identiteit->getId(), 'email', 'JAN@EXAMPLE.ORG'));
		$this->assertFalse($this->mapper->existsWaarde($this->identiteit->getId(), 'email', 'piet@example.org'));
		$this->assertFalse($this->mapper->existsWaarde($this->identiteit->getId(), 'telefoonnummer', 'jan@example.org'));
	}

	public function testFindByIdentiteitFiltersOnVerification(): void {
		$verified = $this->createContactgegeven('a@example.org', new DateTimeImmutable('2026-11-26'));
		$unverified = $this->createContactgegeven('b@example.org');

		$this->assertSame(
			[$verified->getUuid(), $unverified->getUuid()],
			$this->uuids($this->mapper->findByIdentiteit($this->identiteit->getId())),
		);
		$this->assertSame(
			[$verified->getUuid()],
			$this->uuids($this->mapper->findByIdentiteit($this->identiteit->getId(), true)),
		);
		$this->assertSame(
			[$unverified->getUuid()],
			$this->uuids($this->mapper->findByIdentiteit($this->identiteit->getId(), false)),
		);
	}

	public function testFindPrimair(): void {
		$this->assertNull($this->mapper->findPrimair($this->identiteit->getId(), 'email'));

		$this->createContactgegeven('a@example.org');
		$primair = $this->createContactgegeven('b@example.org', isPrimair: true);

		$this->assertSame($primair->getUuid(), $this->mapper->findPrimair($this->identiteit->getId(), 'email')?->getUuid());
		$this->assertNull($this->mapper->findPrimair($this->identiteit->getId(), 'telefoonnummer'));
	}

	private function createContactgegeven(string $waarde, ?DateTimeImmutable $geverifieerdOp = null, bool $isPrimair = false): Contactgegeven {
		$contactgegeven = new Contactgegeven();
		$contactgegeven->setUuid(Uuid::v4()->toRfc4122());
		$contactgegeven->setIdentiteitId($this->identiteit->getId());
		$contactgegeven->setSoort('email');
		$contactgegeven->setWaarde($waarde);
		$contactgegeven->setGeverifieerdOp($geverifieerdOp);
		$contactgegeven->setIsPrimair($isPrimair);

		return $this->mapper->insert($contactgegeven);
	}

	/**
	 * @param list<Contactgegeven> $contactgegevens
	 * @return list<string>
	 */
	private function uuids(array $contactgegevens): array {
		return array_map(static fn (Contactgegeven $c): string => $c->getUuid(), $contactgegevens);
	}
}
