<?php

require_once(INCLUDE_PATH . '/class.DatabaseMigration.inc.php');

class Migration_20260723143705 extends DatabaseMigration
{
	// Roll forward
	public function up() {

		/*
		 * fromRoman() is used to sort structural units (titles, chapters, etc.) whose
		 * identifiers are Roman numerals. It ships in htdocs/admin/statedecoded.sql, but that
		 * file also relies on a `DELIMITER $$` directive that only the mysql CLI understands
		 * (PDO does not), so any database whose schema was loaded via
		 * ParserController::populate_db() -- or whose tables predate the function's addition
		 * to statedecoded.sql -- ends up missing it, even though its tables exist.
		 *
		 * Creating a DETERMINISTIC function (fromRoman genuinely is one: same input always
		 * yields the same output) requires the SUPER privilege while binary logging is on,
		 * which the application's database user is not expected to have. This migration will
		 * fail with MySQL error 1418 or 1419 unless the server has
		 * log_bin_trust_function_creators=1 set -- Docker's db service sets this (see
		 * deploy/docker-compose.yml). On a production server, an administrator must run, once,
		 * as a user with SUPER (or via my.cnf, to survive restarts):
		 *
		 *   SET GLOBAL log_bin_trust_function_creators = 1;
		 *
		 * before this migration -- or ParserController::populate_db() on a fresh install --
		 * can succeed.
		 *
		 * Originally published at <http://www.mysql.com/tools/tool.php?id=107>, a
		 * no-longer-extant page; the author is unknown.
		 */
		$this->queueRoutine('fromRoman',
			"CREATE FUNCTION `fromRoman`(inRoman varchar(15)) RETURNS int(11)
			DETERMINISTIC
			BEGIN
			DECLARE numeral CHAR(7) DEFAULT 'IVXLCDM'; DECLARE digit TINYINT; DECLARE previous INT DEFAULT 0; DECLARE current INT; DECLARE sum INT DEFAULT 0; SET inRoman = UPPER(inRoman); WHILE LENGTH(inRoman) > 0 DO SET digit := LOCATE(RIGHT(inRoman, 1), numeral) - 1; SET current := POW(10, FLOOR(digit / 2)) * POW(5, MOD(digit, 2)); SET sum := sum + POW(-1, current < previous) * current; SET previous := current; SET inRoman = LEFT(inRoman, LENGTH(inRoman) - 1); END WHILE; RETURN sum;
			END");

		$this->execute();
	}

	// Roll back
	public function down() {
		$this->queue('DROP FUNCTION IF EXISTS `fromRoman`');
		$this->execute();
	}
}
