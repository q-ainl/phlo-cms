<?php
// The Phlo engine is an external dependency; its location is configurable via PHLO_ENGINE_PATH
// (default /srv/control/phlo) so this test runs from any checkout. The CMS sources under test are
// this repo's root, which the build picks up via paths.resources in the generated app.json.
$engine = rtrim(getenv('PHLO_ENGINE_PATH') ?: '/srv/control/phlo', '/').'/';
$root   = dirname(__DIR__, 4).'/';
$data   = dirname(__DIR__).'/data/';
file_put_contents($data.'app.json', str_replace('__CMS_ROOT__', $root, file_get_contents($data.'app.json.dist')));

require $engine.'phlo.php';

phlo_app(
	id:    'CMSBITEST',
	host:  'localhost',
	build: true,
	debug: false,
	app:   dirname(__DIR__).'/',
);
