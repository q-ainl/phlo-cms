<?php
use PHPUnit\Framework\TestCase;

// Integration tests for the BI feature, the highest-risk part of the CMS, run against the Phlo
// engine. A fixture subclasses CMS_dashboard_bi to reach its protected buildQuery and drives it
// against a SQLite model: the query builder must bind every value as a parameter (defeating the
// UNION / ' OR '1'='1 injection that the structured rewrite replaced raw SQL with), reject any
// column or operator outside the model, keep the record's real primary key, and evict an
// unrunnable cached search so a deterministic token does not fail forever.
final class CmsBiTest extends TestCase {

	private static string $entry = __DIR__.'/fixtures/bi/www/app.php';

	// The Phlo engine is a separate repo; PHLO_ENGINE_PATH overrides its location (default
	// /srv/control/phlo), so this runs from any checkout. The fixture entry reads the same
	// variable, and the subprocess inherits it.
	private static function enginePath():string {
		return rtrim(getenv('PHLO_ENGINE_PATH') ?: '/srv/control/phlo', '/').'/';
	}

	private static function cli(string ...$args):array {
		$proc = proc_open([PHP_BINARY, self::$entry, ...$args], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		$out  = (string)stream_get_contents($pipes[1]);
		$err  = (string)stream_get_contents($pipes[2]);
		return [proc_close($proc), $out, $err];
	}

	private static function fetch(string $target):array {
		[$code, $out, $err] = self::cli($target);
		self::assertSame(0, $code, "$target failed:\n$out$err");
		$r = json_decode(trim($out), true);
		self::assertIsArray($r, "no JSON from $target: $out");
		return $r;
	}

	public static function setUpBeforeClass():void {
		if (!is_file(self::enginePath().'phlo.php')) self::markTestSkipped('these CMS tests need the Phlo engine - set PHLO_ENGINE_PATH or check it out at /srv/control/phlo');
		[$code, $out, $err] = self::cli('build::run');
		self::assertSame(0, $code, "build::run failed:\n$out$err");
	}

	public function testBiQueryResistsSqlInjection():void {
		$r = self::fetch('biprobe::sqliCases');
		$this->assertSame(1, $r['validRows'], 'a valid structured filter matches its row');
		$this->assertSame(0, $r['injectRows'], "a classic ' OR '1'='1 value is bound as data, matching nothing");
		$this->assertSame(0, $r['unionRows'], 'a UNION SELECT payload is bound as a value, never reaching the SQL string');
		$this->assertSame(2, $r['allRows'], 'no filters returns every row');
		$this->assertTrue($r['unknownColumn'], 'a column outside the model is rejected');
		$this->assertTrue($r['unknownOperator'], 'an operator outside the allowlist is rejected');
		$this->assertSame(2, $r['badOrderIgnored'], 'a malicious ORDER BY column is ignored, not injected');
		$this->assertSame([1, 2], $r['keys'], 'rows are keyed by their real primary key (FETCH_UNIQUE), not 0,1,2 - the CMS uses the array key as the record data-id');
		$this->assertSame(1, $r['firstId'], 'the primary key is selected as a throwaway _ so FETCH_UNIQUE keeps the real id on the record object, not consumed off table.*');
	}

	public function testInvalidBiStructureIsEvictedFromCache():void {
		$r = self::fetch('biprobe::cacheCases');
		$this->assertTrue($r['cachedBefore'], 'the structure is in the cache before the query runs');
		$this->assertTrue($r['evictedAfter'], 'a valid-model-but-bad-structure entry is dropped from the cache when buildQuery fails, so the deterministic token does not fail forever');
	}
}
