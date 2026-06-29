<?php
// The Phlo engine is an external dependency; PHLO_ENGINE_PATH (default /srv/control/phlo) makes this
// run from any checkout. The CMS sources under test are this repo's root, injected into the generated
// app.json via the __CMS_ROOT__ placeholder so paths.resources finds them.
$engine = rtrim(getenv('PHLO_ENGINE_PATH') ?: '/srv/control/phlo', '/').'/';
$root   = dirname(__DIR__, 4).'/';
$data   = dirname(__DIR__).'/data/';
file_put_contents($data.'app.json', str_replace('__CMS_ROOT__', $root, file_get_contents($data.'app.json.dist')));

require $engine.'phlo.php';

phlo_app(
	id:    'CMSHTTPTEST',
	host:  'localhost',
	build: true,
	debug: false,
	app:   dirname(__DIR__).'/',
);
