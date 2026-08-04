<?php
use PHPUnit\Framework\TestCase;

// Regression tests for two 1.0 audit findings, driven over real HTTP against the cmshttp fixture:
// a model with a custom primary key (tagpk, idColumn 'code') must work through every CMS.API route,
// and lifecycle hooks must fire exactly once per mutation, with mutations made in beforeCreate
// persisted (the ORM used to commit the original args and CMS used to fire the hooks a second time).
final class CmsPkHooksTest extends TestCase {

	private static string $appDir = __DIR__.'/fixtures/cmshttp/';
	private static string $entry  = __DIR__.'/fixtures/cmshttp/www/app.php';
	private static $server = null;
	private static int $port = 0;
	private static string $cookie = '';
	private static string $token = '';

	private static function enginePath():string {
		return rtrim(getenv('PHLO_ENGINE_PATH') ?: '/srv/control/phlo', '/').'/';
	}

	private static function cli(string ...$args):array {
		$proc = proc_open([PHP_BINARY, self::$entry, ...$args], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		$out = (string)stream_get_contents($pipes[1]);
		$err = (string)stream_get_contents($pipes[2]);
		return [proc_close($proc), $out, $err];
	}

	private static function http(string $method, string $path, array $headers = [], ?string $body = null):array {
		if (self::$cookie !== '') $headers[] = 'Cookie: '.self::$cookie;
		if ($body !== null) $headers[] = 'Content-Type: application/x-www-form-urlencoded';
		$ctx = stream_context_create(['http' => [
			'method'        => $method,
			'header'        => implode("\r\n", $headers),
			'content'       => $body ?? '',
			'timeout'       => 5,
			'ignore_errors' => true,
		]]);
		$resp   = (string)file_get_contents('http://127.0.0.1:'.self::$port.$path, false, $ctx);
		$status = 0;
		foreach ($http_response_header ?? [] as $h){
			if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) $status = (int)$m[1];
			if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $h, $m)) self::$cookie = $m[1];
		}
		return [$status, $resp];
	}

	private static function form(array $data):string { return http_build_query($data); }

	public static function setUpBeforeClass():void {
		if (!is_file(self::enginePath().'phlo.php')) self::markTestSkipped('CMS HTTP tests need the Phlo engine - set PHLO_ENGINE_PATH or check it out at /srv/control/phlo');
		[$code, $out, $err] = self::cli('build::run');
		self::assertSame(0, $code, "build::run failed:\n$out$err");
		[$code, $out, $err] = self::cli('app::setup');
		self::assertSame(0, $code, "app::setup failed:\n$out$err");

		self::$port   = 8420 + (getmypid() % 1000);
		self::$server = proc_open(
			[PHP_BINARY, '-S', '127.0.0.1:'.self::$port, self::$entry],
			[1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
			$pipes,
			self::$appDir.'www'
		);
		self::assertIsResource(self::$server, 'php -S did not start');
		$up = false;
		for ($i = 0; $i < 50 && !$up; ++$i){
			usleep(100_000);
			$sock = @fsockopen('127.0.0.1', self::$port, $e, $s, 0.2);
			if ($sock){ fclose($sock); $up = true; }
		}
		self::assertTrue($up, 'php -S did not come up on port '.self::$port);

		[$status, $body] = self::http('GET', '/csrf');
		self::assertSame(200, $status, "GET /csrf returned $status: $body");
		self::assertSame(1, preg_match('/name="csrf" content="([a-z0-9]+)"/i', $body, $m), "no CSRF meta in: $body");
		self::$token = $m[1];
	}

	public static function tearDownAfterClass():void {
		if (self::$server){ proc_terminate(self::$server); proc_close(self::$server); }
	}

	private function evalValue(string $expr) {
		[, $out] = self::cli('phlo_eval', "return $expr");
		return json_decode(trim($out), true);
	}

	private function headers():array {
		return ['X-Requested-With: phlo', 'X-CSRF-Token: '.self::$token];
	}

	public function testCustomPrimaryKeyCrudLifecycle():void {
		$h = $this->headers();

		[, $body] = self::http('POST', '/api/tagpk', $h, self::form(['code' => 'blue', 'label' => 'Blue']));
		$response = json_decode($body, true);
		$this->assertSame('tagpk/blue', $response['path'] ?? null, 'a create on a custom-PK model returns the path keyed by that PK');
		$this->assertArrayHasKey('main', $response, 'a custom-PK create renders the record in the same response');

		[, $body] = self::http('PUT', '/api/tagpk/red', $h, self::form(['code' => 'red', 'label' => 'Bright red']));
		$response = json_decode($body, true);
		$this->assertSame('tagpk/red', $response['path'] ?? null, 'an update addressed by the custom PK returns the record path');
		$this->assertArrayHasKey('main', $response, 'a custom-PK update renders the record in the same response');
		$this->assertSame('Bright red', $this->evalValue("tagpk::record(code: 'red')?->label"), 'the update persists through the custom PK');

		self::http('DELETE', '/api/tagpk/red', $h);
		$this->assertNull($this->evalValue("tagpk::record(code: 'red')?->label"), 'the delete removes the row addressed by the custom PK');
		$this->assertSame('Blue', $this->evalValue("tagpk::record(code: 'blue')?->label"), 'the delete only removes the addressed record');
	}

	public function testLifecycleHooksFireExactlyOnceAndMutationsPersist():void {
		$h = $this->headers();

		[, $body] = self::http('POST', '/api/hooked', $h, self::form(['title' => 'Hello World']));
		$response = json_decode($body, true);
		$this->assertSame('hooked/1', $response['path'] ?? null, 'the create returns the record path');
		$this->assertArrayHasKey('main', $response, 'the create renders the record in the same response');
		$counts = $this->evalValue("file_get_contents(data.'hooks.json')");
		$counts = is_string($counts) ? json_decode($counts, true) : $counts;
		$this->assertSame(1, $counts['beforeSave'] ?? 0, 'beforeSave fires exactly once on create');
		$this->assertSame(1, $counts['beforeCreate'] ?? 0, 'beforeCreate fires exactly once on create');
		$this->assertSame(1, $counts['afterCreate'] ?? 0, 'afterCreate fires exactly once on create');
		$this->assertSame(1, $counts['afterSave'] ?? 0, 'afterSave fires exactly once on create');
		$this->assertSame('hello world', $this->evalValue('hooked::record(id: 1)?->slug'), 'a mutation made in beforeCreate is persisted');

		self::http('DELETE', '/api/hooked/1', $h);
		$counts = $this->evalValue("file_get_contents(data.'hooks.json')");
		$counts = is_string($counts) ? json_decode($counts, true) : $counts;
		$this->assertSame(1, $counts['beforeDelete'] ?? 0, 'beforeDelete fires exactly once on delete');
		$this->assertSame(1, $counts['afterDelete'] ?? 0, 'afterDelete fires exactly once on delete');
		$this->assertNull($this->evalValue('hooked::record(id: 1)?->slug'), 'the delete removes the row');
	}
}
