<?php
use PHPUnit\Framework\TestCase;

// Real-HTTP route tests for the CMS write API (CMS.API). The CMS CI otherwise only covers the BI
// query builder; this serves a fixture CMS app with PHP's built-in server and drives the actual
// POST/PUT/DELETE /api routes over HTTP, asserting the security contract every one of them enforces:
// the rotating CSRF token (a mutation without the X-CSRF-Token header is rejected), the per-model
// authz (canCreate/canChange/canDelete), and that a valid request really performs the CRUD. The Phlo
// engine is external (PHLO_ENGINE_PATH, default /srv/control/phlo).
final class CmsHttpTest extends TestCase {

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

	// Minimal HTTP client that carries the session cookie forward and returns [status, body].
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

		self::$port   = 8920 + (getmypid() % 1000);
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

		// One GET seeds the session and exposes the rotating CSRF token from the page <meta>.
		[$status, $body] = self::http('GET', '/csrf');
		self::assertSame(200, $status, "GET /csrf returned $status: $body");
		self::assertSame(1, preg_match('/name="csrf" content="([a-z0-9]+)"/i', $body, $m), "no CSRF meta in: $body");
		self::$token = $m[1];
	}

	public static function tearDownAfterClass():void {
		if (self::$server){ proc_terminate(self::$server); proc_close(self::$server); }
	}

	// Reads a column straight from the fixture DB via the app's CLI, to assert persistence
	// independently of the API response.
	private function dbValue(string $model, int $id, string $column) {
		[, $out] = self::cli('phlo_eval', "return $model::record(id: $id)?->$column");
		return json_decode(trim($out), true);
	}

	public function testWriteWithoutCsrfTokenIsRejected():void {
		[, $body] = self::http('POST', '/api/post', ['X-Requested-With: phlo'], self::form(['title' => 'x', 'body' => 'y']));
		$this->assertSame('Invalid CSRF token', (json_decode($body, true)['error'] ?? null), 'a write without the X-CSRF-Token header is refused before touching the model');
		$this->assertNull($this->dbValue('post', 1, 'title'), 'the rejected write created no row');
	}

	public function testCsrfProtectedCrudLifecycle():void {
		$h = ['X-Requested-With: phlo', 'X-CSRF-Token: '.self::$token];

		[, $body] = self::http('POST', '/api/post', $h, self::form(['title' => 'first', 'body' => 'hello']));
		$this->assertSame('/post/1', (json_decode($body, true)['location'] ?? null), 'a valid create returns the new record location');
		$this->assertSame('first', $this->dbValue('post', 1, 'title'), 'the create persists the posted title');

		[, $body] = self::http('PUT', '/api/post/1', $h, self::form(['title' => 'edited', 'body' => 'hello']));
		$this->assertSame('/post/1', (json_decode($body, true)['location'] ?? null), 'a valid update returns the record location');
		$this->assertSame('edited', $this->dbValue('post', 1, 'title'), 'the update persists the new title');

		[, $body] = self::http('DELETE', '/api/post/1', $h);
		$this->assertNull($this->dbValue('post', 1, 'title'), 'the delete removes the row');
	}

	public function testModelAuthzRefusesForbiddenDelete():void {
		$h = ['X-Requested-With: phlo', 'X-CSRF-Token: '.self::$token];
		[, $body] = self::http('DELETE', '/api/locked/1', $h);
		$this->assertSame('Not allowed', (json_decode($body, true)['error'] ?? null), 'a model that sets canDelete=false refuses the delete even with a valid token');
		$this->assertSame('permanent', $this->dbValue('locked', 1, 'title'), 'the forbidden delete left the row intact');
	}
}
