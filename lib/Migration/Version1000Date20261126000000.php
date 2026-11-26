<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\Attributes\CreateTable;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/** @psalm-suppress UnusedClass Loaded by Nextcloud's migration service */
#[CreateTable(table: 'cgr_identiteit', columns: ['id', 'uuid', 'hmac', 'hmac_gezet_op'], description: 'Pseudonymised login identifiers')]
#[CreateTable(table: 'cgr_contactgegeven', columns: ['id', 'uuid', 'identiteit_id', 'soort', 'waarde', 'geverifieerd_op', 'is_primair'], description: 'Contact details of an identiteit')]
final class Version1000Date20261126000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('cgr_identiteit')) {
			$identiteit = $schema->createTable('cgr_identiteit');
			$identiteit->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$identiteit->addColumn('uuid', Types::STRING, ['length' => 36, 'notnull' => true]);
			$identiteit->addColumn('hmac', Types::STRING, ['length' => 64, 'notnull' => true]);
			$identiteit->addColumn('hmac_gezet_op', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
			$identiteit->setPrimaryKey(['id']);
			$identiteit->addUniqueIndex(['uuid'], 'cgr_ident_uuid');
			$identiteit->addUniqueIndex(['hmac'], 'cgr_ident_hmac');
			$identiteit->addIndex(['hmac_gezet_op'], 'cgr_ident_hmac_gezet_op');
		}

		if (!$schema->hasTable('cgr_contactgegeven')) {
			$contactgegeven = $schema->createTable('cgr_contactgegeven');
			$contactgegeven->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$contactgegeven->addColumn('uuid', Types::STRING, ['length' => 36, 'notnull' => true]);
			$contactgegeven->addColumn('identiteit_id', Types::BIGINT, ['notnull' => true]);
			$contactgegeven->addColumn('soort', Types::STRING, ['length' => 32, 'notnull' => true]);
			$contactgegeven->addColumn('waarde', Types::STRING, ['length' => 254, 'notnull' => true]);
			$contactgegeven->addColumn('geverifieerd_op', Types::DATE_IMMUTABLE, ['notnull' => false]);
			// Boolean columns can't be NOT NULL in Nextcloud (Oracle stores them as a nullable number).
			$contactgegeven->addColumn('is_primair', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
			$contactgegeven->setPrimaryKey(['id']);
			$contactgegeven->addUniqueIndex(['uuid'], 'cgr_contact_uuid');
			$contactgegeven->addUniqueIndex(['identiteit_id', 'soort', 'waarde'], 'cgr_contact_ident_waarde');
			$contactgegeven->addForeignKeyConstraint(
				$schema->getTable('cgr_identiteit')->getName(),
				['identiteit_id'],
				['id'],
				['onDelete' => 'CASCADE'],
				'cgr_contact_ident_fk',
			);
		}

		return $schema;
	}
}
