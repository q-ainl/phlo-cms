<?php
use PHPUnit\Framework\TestCase;

// Integration tests for hand sorting, run against the Phlo engine. A fixture drives
// CMS_list_sort::sortApply straight against SQLite models, because the interesting behaviour is
// what ends up in the table: a reorder must renumber the whole group it touches and no other,
// must survive being handed only part of a group (paging, filtering, searching), must treat an
// empty group as a group of its own, must not assume an integer primary key called id, and must
// refuse anything it cannot do without leaving half a group rewritten behind.
final class CmsSortTest extends TestCase {

	private static string $entry = __DIR__.'/fixtures/sort/www/app.php';
	private static array $cases = [];

	private static function enginePath():string {
		return rtrim(getenv('PHLO_ENGINE_PATH') ?: '/srv/control/phlo', '/').'/';
	}

	private static function cli(string ...$args):array {
		$proc = proc_open([PHP_BINARY, self::$entry, ...$args], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		$out  = (string)stream_get_contents($pipes[1]);
		$err  = (string)stream_get_contents($pipes[2]);
		return [proc_close($proc), $out, $err];
	}

	public static function setUpBeforeClass():void {
		if (!is_file(self::enginePath().'phlo.php')) self::markTestSkipped('these CMS tests need the Phlo engine - set PHLO_ENGINE_PATH or check it out at /srv/control/phlo');
		[$code, $out, $err] = self::cli('build::run');
		self::assertSame(0, $code, "build::run failed:\n$out$err");
		[$code, $out, $err] = self::cli('sortprobe::cases');
		self::assertSame(0, $code, "sortprobe::cases failed:\n$out$err");
		$cases = json_decode(trim($out), true);
		self::assertIsArray($cases, "no JSON from the probe: $out");
		self::$cases = $cases;
	}

	private function case(string $name):array {
		$this->assertArrayHasKey($name, self::$cases, "the probe did not report case $name");
		return self::$cases[$name];
	}

	public function testFlatListRenumbersAsAWholeAndClosesItsGaps():void {
		$c = $this->case('flat');
		$this->assertNull($c['error']);
		$this->assertSame(['3' => 1, '1' => 2, '2' => 3], $c['positions']);
	}

	public function testReorderingOneGroupLeavesTheOtherAlone():void {
		$c = $this->case('group');
		$this->assertNull($c['error']);
		$this->assertSame(['3' => 1, '2' => 2, '1' => 3], $c['one']);
		$this->assertSame(['4' => 1, '5' => 2], $c['two'], 'the untouched group must keep its own order');
	}

	// The case paging and filtering rest on: only the records that were sent move, and they
	// move among the slots they already held.
	public function testPartOfAGroupSwapsWithinItsOwnSlots():void {
		$c = $this->case('subset');
		$this->assertNull($c['error']);
		$this->assertSame(['1' => 1, '3' => 2, '2' => 3], $c['one']);
	}

	public function testRecordsWithoutAGroupSortAmongThemselves():void {
		$c = $this->case('null_group');
		$this->assertNull($c['error'], 'an empty group value is a group, not a reason to refuse');
		$this->assertSame(['7' => 1, '6' => 2], $c['loose']);
		$this->assertSame(['1' => 1, '2' => 2, '3' => 3], $c['one'], 'a grouped record must not be dragged along');
	}

	public function testStringPrimaryKeyOnItsOwnColumn():void {
		$c = $this->case('string_key');
		$this->assertNull($c['error']);
		$this->assertSame(['card' => 1, 'cash' => 2, 'pin' => 3], $c['positions']);
	}

	public function testDuplicateIdsAreRefusedAndNothingIsWritten():void {
		$c = $this->case('duplicate');
		$this->assertSame('Duplicate records', $c['error']);
		$this->assertSame(['1' => 5, '2' => 9, '3' => 40], $c['positions']);
	}

	public function testAnIdFromAnotherGroupIsRefusedAndNothingIsWritten():void {
		$c = $this->case('foreign_group');
		$this->assertSame('Record outside this group', $c['error']);
		$this->assertSame(['1' => 1, '2' => 2, '3' => 3], $c['one']);
		$this->assertSame(['4' => 1, '5' => 2], $c['two']);
	}

	public function testAnUnknownIdIsRefusedAndNothingIsWritten():void {
		$c = $this->case('unknown');
		$this->assertNotNull($c['error']);
		$this->assertSame(['1' => 5, '2' => 9, '3' => 40], $c['positions']);
	}

	// Sorting is opt in: without it a hand made request could reshuffle any model that happens
	// to have a column called sort.
	public function testAModelThatDidNotOptInCannotBeReordered():void {
		$this->assertSame('Model does not sort by hand', $this->case('not_opted_in')['error']);
	}

	// Renumbering normalises the whole group, so it writes records nobody dragged. Permission
	// has to cover those too, or a locked record moves when its neighbour is dragged.
	public function testPermissionCoversEveryRecordTheRenumberWouldWrite():void {
		$c = $this->case('permission');
		$this->assertSame('Not allowed', $c['error']);
		$this->assertSame(['1' => 10, '99' => 20, '2' => 30, '3' => 40], $c['one'], 'a refused reorder must not leave half a group rewritten');
	}

	// Where a new record, and a record moved into another group, both belong.
	public function testNextPositionIsTheEndOfTheRightGroup():void {
		$c = $this->case('next');
		$this->assertSame(4, $c['one']);
		$this->assertSame(3, $c['two']);
		$this->assertSame(3, $c['loose'], 'the empty group counts as its own group here too');
		$this->assertSame(41, $c['flat'], 'a flat list continues after its highest number, gaps and all');
	}
}
