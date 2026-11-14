<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Co-Optimize
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Helpdesk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Helpdesk 1.0: the requests people send to their IT provider. A request is
 * stored before it is emailed, so a mail problem never loses it.
 */
class Version010000Date20261112230000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('helpdesk_requests')) {
			$table = $schema->createTable('helpdesk_requests');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			// where the provider's answer goes, and the name it is addressed to
			$table->addColumn('reply_email', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('reply_name', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('subject', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('body', Types::TEXT, ['notnull' => true]);
			$table->addColumn('urgent', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
			// JSON list of {name, size, mime, file}; the files live in the app's data
			$table->addColumn('files', Types::TEXT, ['notnull' => false]);
			// the receiver when the request was written: it is sent there even if the settings change
			$table->addColumn('provider_name', Types::STRING, ['notnull' => true, 'length' => 255]);
			$table->addColumn('provider_email', Types::STRING, ['notnull' => true, 'length' => 255]);
			// pending | sent | failed (pending again on the next try)
			$table->addColumn('state', Types::STRING, ['notnull' => true, 'length' => 16]);
			$table->addColumn('attempts', Types::INTEGER, ['notnull' => true, 'default' => 0, 'unsigned' => true]);
			$table->addColumn('last_error', Types::STRING, ['notnull' => false, 'length' => 500]);
			$table->addColumn('next_try_at', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
			$table->addColumn('admins_told', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('sent_at', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['uid', 'created_at'], 'hdr_requests_uid');
			$table->addIndex(['state', 'next_try_at'], 'hdr_requests_due');
		}
		return $schema;
	}
}
