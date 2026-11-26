<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Tests\Integration\Service;

use DateTimeImmutable;
use DateTimeZone;
use OCA\ContactgegevensRegister\Db\Contactgegeven;
use OCA\ContactgegevensRegister\Db\ContactgegevenMapper;
use OCA\ContactgegevensRegister\Db\Identiteit;
use OCA\ContactgegevensRegister\Db\IdentiteitMapper;
use OCA\ContactgegevensRegister\Exception\DubbelContactgegevenException;
use OCA\ContactgegevensRegister\Exception\NietGevondenException;
use OCA\ContactgegevensRegister\Exception\OngeldigeInvoerException;
use OCA\ContactgegevensRegister\Service\ContactgegevenService;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;
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

	public function testAddMakesTheFirstOfASoortPrimary(): void {
		$first = $this->service->add($this->identiteit->getUuid(), 'email', 'Jan@Example.org');
		$second = $this->service->add($this->identiteit->getUuid(), 'email', 'piet@example.org');

		$this->assertSame($this->identiteit->getUuid(), $first['identiteit']);
		$this->assertSame('email', $first['soort']);
		$this->assertSame('Jan@Example.org', $first['waarde'], 'Stored as received');
		$this->assertNull($first['geverifieerdOp']);
		$this->assertTrue($first['isPrimair']);
		$this->assertFalse($second['isPrimair']);
		$this->assertSame(
			[$first, $second],
			$this->service->findByIdentiteit($this->identiteit->getUuid()),
		);
	}

	public function testAddDoesNotMakeItPrimaryWhenOthersOfTheSoortExistWithoutAPrimary(): void {
		$this->insert('a@example.org');

		$added = $this->service->add($this->identiteit->getUuid(), 'email', 'b@example.org');

		$this->assertFalse($added['isPrimair']);
	}

	public function testAddRejectsADuplicateComparedCaseInsensitively(): void {
		$this->service->add($this->identiteit->getUuid(), 'email', 'jan@example.org');

		$this->expectException(DubbelContactgegevenException::class);
		$this->service->add($this->identiteit->getUuid(), 'email', 'JAN@example.org');
	}

	public function testAddThrowsForUnknownIdentiteit(): void {
		$this->expectException(NietGevondenException::class);
		$this->service->add(Uuid::v4()->toRfc4122(), 'email', 'jan@example.org');
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function invalidContactgegevens(): array {
		return [
			'unknown soort' => ['telefoonnummer', '0612345678'],
			'empty waarde' => ['email', ''],
			'not an email address' => ['email', 'jan'],
			'longer than 254 characters' => ['email', str_repeat('a', 64) . '@' . str_repeat('b', 63) . '.' . str_repeat('c', 63) . '.' . str_repeat('d', 60) . '.nl'],
		];
	}

	#[DataProvider('invalidContactgegevens')]
	public function testAddRejectsInvalidInput(string $soort, string $waarde): void {
		$this->expectException(OngeldigeInvoerException::class);
		$this->service->add($this->identiteit->getUuid(), $soort, $waarde);
	}

	public function testVerifySetsTodayAndKeepsTheFirstDate(): void {
		$contactgegeven = $this->insert('jan@example.org');

		$verified = $this->service->verify($contactgegeven->getUuid());

		$today = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');
		$this->assertSame($today, $verified['geverifieerdOp']);
		$this->assertSame($this->identiteit->getUuid(), $verified['identiteit']);
		$this->assertSame($today, $this->mapper->findByUuid($contactgegeven->getUuid())->getGeverifieerdOp()?->format('Y-m-d'));
	}

	public function testVerifyKeepsAnEarlierDate(): void {
		$contactgegeven = $this->insert('jan@example.org', new DateTimeImmutable('2026-01-02'));

		$this->assertSame('2026-01-02', $this->service->verify($contactgegeven->getUuid())['geverifieerdOp']);
		$this->assertSame('2026-01-02', $this->mapper->findByUuid($contactgegeven->getUuid())->getGeverifieerdOp()?->format('Y-m-d'));
	}

	public function testVerifyThrowsForUnknownContactgegeven(): void {
		$this->expectException(NietGevondenException::class);
		$this->service->verify(Uuid::v4()->toRfc4122());
	}

	public function testMakePrimairMovesTheFlagWithinTheSoort(): void {
		$huidige = $this->insert('a@example.org', isPrimair: true);
		$nieuwe = $this->insert('b@example.org');

		$result = $this->service->makePrimair($nieuwe->getUuid());

		$this->assertTrue($result['isPrimair']);
		$this->assertSame($this->identiteit->getUuid(), $result['identiteit']);
		$this->assertTrue($this->mapper->findByUuid($nieuwe->getUuid())->getIsPrimair());
		$this->assertFalse($this->mapper->findByUuid($huidige->getUuid())->getIsPrimair());
	}

	public function testMakePrimairWorksWithoutACurrentPrimaryAndIsIdempotent(): void {
		$contactgegeven = $this->insert('a@example.org');

		$this->assertTrue($this->service->makePrimair($contactgegeven->getUuid())['isPrimair']);
		$this->assertTrue($this->service->makePrimair($contactgegeven->getUuid())['isPrimair']);
		$this->assertSame($contactgegeven->getUuid(), $this->mapper->findPrimair($this->identiteit->getId(), 'email')?->getUuid());
	}

	public function testMakePrimairThrowsForUnknownContactgegeven(): void {
		$this->expectException(NietGevondenException::class);
		$this->service->makePrimair(Uuid::v4()->toRfc4122());
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
