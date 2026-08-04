<?php
// source:  /srv/control/CMS/tests/fixtures/sort/nosort.phlo
// phlo:    1.0.1
// class:   nosort
// extends: model
class nosort extends model {
	// Has a column called sort but never selected the sort view. Reordering it must be refused,
	// otherwise opting in means nothing.
	public static $table = 'nosorts';
	public static $order = 'sort ASC';
	public static function canView(){
		return true;
	}
	public static function canChange(){
		return true;
	}
	public static function canDelete(){
		return true;
	}
	public static function schema(){
		return arr(
			id: field(type: 'number', title: 'ID'),
			sort: field(type: 'number', title: 'Sort'),
		);
	}
}
