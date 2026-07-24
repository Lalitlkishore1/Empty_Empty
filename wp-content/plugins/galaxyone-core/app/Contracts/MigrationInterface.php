<?php
/**
 * Migration contract.
 *
 * @package GalaxyOne\Core\Contracts
 */

namespace GalaxyOne\Core\Contracts;

interface MigrationInterface {

	/**
	 * Applies the migration.
	 *
	 * @return bool
	 */
	public static function up(): bool;

	/**
	 * Reverses the migration.
	 *
	 * @return void
	 */
	public static function down(): void;
}
