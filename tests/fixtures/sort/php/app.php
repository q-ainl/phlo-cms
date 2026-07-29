<?php
// source: /srv/control/CMS/tests/fixtures/sort/app.phlo
// phlo:   1.0
class app extends obj {
	public static function route():bool {
		return route('GET', '$section:files,images,thumbs $token.20 $filename', cb: 'CMS::GETSectionTokenFilename') ||
		route('GET', '$list $options=*', cb: 'CMS_list::BothGETListOptions') ||
		route('PUT', 'api sort $model', true, 'ids', cb: 'CMS_list_sort::AsyncPUTApiSortModelIds');
	}
	public $title = 'CMS sort test fixture';
	public $models = ['page', 'plain', 'coded', 'nosort'];
}
