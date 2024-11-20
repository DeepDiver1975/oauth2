<?php
namespace OCA\oauth2\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use OCP\Migration\ISchemaMigration;

class Version20220525140622 implements ISchemaMigration {
	public function changeSchema(Schema $schema, array $options) {
		$prefix = $options['tablePrefix'];
		$table = $schema->getTable("{$prefix}oauth2_clients");
		if (!$table->hasColumn('invalidate_on_logout')) {
			$table->addColumn('invalidate_on_logout', Types::BOOLEAN, [
				'notnull' => true,
				'default' => false,
			]);
		}
	}
}
