<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Contactgegeven>
 */
class ContactgegevenMapper extends QBMapper {
	public const string TABLE = 'cgr_contactgegeven';

	/** @psalm-suppress PossiblyUnusedMethod Instantiated by the DI container */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE, Contactgegeven::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function findByUuid(string $uuid): Contactgegeven {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('uuid', $qb->createNamedParameter($uuid)));

		return $this->findEntity($qb);
	}

	/**
	 * The contactgegevens of an identiteit, oldest first.
	 *
	 * @param bool|null $geverifieerd true: only verified ones, false: only unverified ones, null: all
	 * @return list<Contactgegeven>
	 * @throws Exception
	 */
	public function findByIdentiteit(int $identiteitId, ?bool $geverifieerd = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('identiteit_id', $qb->createNamedParameter($identiteitId, IQueryBuilder::PARAM_INT)))
			->orderBy('id');

		if ($geverifieerd === true) {
			$qb->andWhere($qb->expr()->isNotNull('geverifieerd_op'));
		} elseif ($geverifieerd === false) {
			$qb->andWhere($qb->expr()->isNull('geverifieerd_op'));
		}

		return $this->findEntities($qb);
	}

	/**
	 * Whether the identiteit already has this waarde for this soort, compared case-insensitively.
	 *
	 * @throws Exception
	 */
	public function existsWaarde(int $identiteitId, string $soort, string $waarde): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName())
			->where($qb->expr()->eq('identiteit_id', $qb->createNamedParameter($identiteitId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('soort', $qb->createNamedParameter($soort)))
			->andWhere($qb->expr()->eq(
				$qb->func()->lower('waarde'),
				$qb->func()->lower($qb->createNamedParameter($waarde)),
			))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$exists = $result->fetchOne() !== false;
		$result->closeCursor();

		return $exists;
	}

	/**
	 * The primary contactgegeven of an identiteit for a soort, if there is one.
	 *
	 * @throws Exception
	 */
	public function findPrimair(int $identiteitId, string $soort): ?Contactgegeven {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('identiteit_id', $qb->createNamedParameter($identiteitId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('soort', $qb->createNamedParameter($soort)))
			->andWhere($qb->expr()->eq('is_primair', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->setMaxResults(1);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}
}
