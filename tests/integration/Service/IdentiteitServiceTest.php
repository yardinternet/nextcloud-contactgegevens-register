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
use OCA\ContactgegevensRegister\Exception\OngeldigeInvoerException;
use OCA\ContactgegevensRegister\Service\IdentiteitService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Uid\Uuid;
use Test\TestCase;

#[Group('DB')]
final class IdentiteitServiceTest extends TestCase {
	private IdentiteitService $service;
	private IdentiteitMapper $mapper;
	/** @var list<int> */
	private array $createdIds = [];

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
		$this->service = Server::get(IdentiteitService::class);
		$this->mapper = Server::get(IdentiteitMapper::class);
	}

	#[\Override]
	protected function tearDown(): void {
		$this->mapper->deleteByIds($this->createdIds);
		parent::tearDown();
	}

	public function testCreatesAnIdentiteitForANewHmac(): void {
		$hmac = $this->hmac();

		$result = $this->findOrCreate($hmac);

		$this->assertTrue($result['aangemaakt']);
		$this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $result['identiteit']->getUuid());
		$stored = $this->mapper->findByHmac($hmac);
		$this->assertSame($result['identiteit']->getUuid(), $stored->getUuid());
		$this->assertEqualsWithDelta(time(), $stored->getHmacGezetOp()->getTimestamp(), 60);
	}

	public function testReturnsTheExistingIdentiteitForAKnownHmac(): void {
		$hmac = $this->hmac();
		$first = $this->findOrCreate($hmac);

		$second = $this->findOrCreate($hmac);

		$this->assertFalse($second['aangemaakt']);
		$this->assertSame($first['identiteit']->getUuid(), $second['identiteit']->getUuid());
	}

	public function testReKeysAnIdentiteitThatOnlyHasTheVorigeHmac(): void {
		$vorige = $this->insert($this->hmac(), new DateTimeImmutable('2000-01-01', new DateTimeZone('UTC')));
		$hmac = $this->hmac();

		$result = $this->findOrCreate($hmac, $vorige->getHmac());

		$this->assertFalse($result['aangemaakt']);
		$this->assertSame($vorige->getUuid(), $result['identiteit']->getUuid());
		$stored = $this->mapper->findByUuid($vorige->getUuid());
		$this->assertSame($hmac, $stored->getHmac());
		$this->assertGreaterThan(new DateTimeImmutable('2000-01-02'), $stored->getHmacGezetOp());
	}

	public function testCreatesAnIdentiteitWhenNeitherHmacExists(): void {
		$result = $this->findOrCreate($this->hmac(), $this->hmac());

		$this->assertTrue($result['aangemaakt']);
	}

	public function testReturnsTheIdentiteitWithHmacWhenBothExist(): void {
		$oud = new DateTimeImmutable('2000-01-01', new DateTimeZone('UTC'));
		$vorige = $this->insert($this->hmac(), $oud);
		$huidige = $this->insert($this->hmac(), $oud);

		$result = $this->findOrCreate($huidige->getHmac(), $vorige->getHmac());

		$this->assertFalse($result['aangemaakt']);
		$this->assertSame($huidige->getUuid(), $result['identiteit']->getUuid());
		// The other one is left untouched, for the purge.
		$this->assertSame($vorige->getHmac(), $this->mapper->findByUuid($vorige->getUuid())->getHmac());
		$this->assertSame('2000-01-01', $this->mapper->findByUuid($huidige->getUuid())->getHmacGezetOp()->format('Y-m-d'));
	}

	/**
	 * @return array<string, array{string, ?string}>
	 */
	public static function invalidInput(): array {
		$valid = str_repeat('a', 64);
		return [
			'empty hmac' => ['', null],
			'plain identifier' => ['123456789', null],
			'uppercase hmac' => [str_repeat('A', 64), null],
			'hmac too long' => [str_repeat('a', 65), null],
			'hmac with trailing newline' => [$valid . "\n", null],
			'invalid vorigeHmac' => [$valid, 'xyz'],
			'empty vorigeHmac' => [$valid, ''],
			'vorigeHmac equals hmac' => [$valid, $valid],
		];
	}

	#[DataProvider('invalidInput')]
	public function testRejectsInvalidInput(string $hmac, ?string $vorigeHmac): void {
		$this->expectException(OngeldigeInvoerException::class);
		$this->service->findOrCreate($hmac, $vorigeHmac);
	}

	public function testDeleteRemovesTheIdentiteitAndItsContactgegevens(): void {
		$identiteit = $this->insert($this->hmac(), new DateTimeImmutable('now', new DateTimeZone('UTC')));
		$contactgegevenMapper = Server::get(ContactgegevenMapper::class);
		$contactgegeven = new Contactgegeven();
		$contactgegeven->setUuid(Uuid::v4()->toRfc4122());
		$contactgegeven->setIdentiteitId($identiteit->getId());
		$contactgegeven->setSoort('email');
		$contactgegeven->setWaarde('jan@example.org');
		$contactgegevenMapper->insert($contactgegeven);

		$this->service->delete($identiteit->getUuid());

		$this->assertSame([], $contactgegevenMapper->findByIdentiteit($identiteit->getId()));
		$this->expectException(DoesNotExistException::class);
		$this->mapper->findByUuid($identiteit->getUuid());
	}

	public function testDeleteThrowsForUnknownUuid(): void {
		$this->expectException(NietGevondenException::class);
		$this->service->delete(Uuid::v4()->toRfc4122());
	}

	/**
	 * @return array{identiteit: Identiteit, aangemaakt: bool}
	 */
	private function findOrCreate(string $hmac, ?string $vorigeHmac = null): array {
		$result = $this->service->findOrCreate($hmac, $vorigeHmac);
		$this->createdIds[] = $result['identiteit']->getId();
		return $result;
	}

	private function insert(string $hmac, DateTimeImmutable $hmacGezetOp): Identiteit {
		$identiteit = new Identiteit();
		$identiteit->setUuid(Uuid::v4()->toRfc4122());
		$identiteit->setHmac($hmac);
		$identiteit->setHmacGezetOp($hmacGezetOp);
		$this->mapper->insert($identiteit);
		$this->createdIds[] = $identiteit->getId();
		return $identiteit;
	}

	private function hmac(): string {
		return bin2hex(random_bytes(32));
	}
}
