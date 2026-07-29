<?php
// source:  /srv/control/CMS/tests/fixtures/sort/page.phlo
// phlo:    1.0
// class:   page
// extends: model
class page extends model {
	// Hand sorted within a group, the shape most models use: pages inside a menu, buttons inside
	// a page. The group is nullable on purpose, because a record without one still has to sort.
	public static $table = 'pages';
	public static $order = 'sort ASC';
	public static $listView = 'sort';
	public static $sortGroup = 'menu';
	public static function canView(){
		return true;
	}
	public static function canDelete(){
		return true;
	}
	// Record 99 stands for anything the current user may not touch, so the reorder can be
	// checked against permission per record rather than per request.
	public static function canChange($record = null){
		return !$record || (int)$record->id !== 99;
	}
	public static function schema(){
		return arr(
			id: field(type: 'number', title: 'ID'),
			menu: field(type: 'number', title: 'Menu'),
			name: field(type: 'text', title: 'Name'),
			sort: field(type: 'number', title: 'Sort'),
		);
	}
	public static function prepareTable(){
		static::DB()->query('CREATE TABLE IF NOT EXISTS pages (id INTEGER PRIMARY KEY AUTOINCREMENT, menu INTEGER, name TEXT, sort INTEGER)');
		static::DB()->query('DELETE FROM pages');
		static::DB()->query('INSERT INTO pages (id, menu, name, sort) VALUES (1, 1, ?, 1), (2, 1, ?, 2), (3, 1, ?, 3), (4, 2, ?, 1), (5, 2, ?, 2)', 'a', 'b', 'c', 'x', 'y');
		static::DB()->query('INSERT INTO pages (id, menu, name, sort) VALUES (6, NULL, ?, 1), (7, NULL, ?, 2)', 'loose one', 'loose two');
	}
	public static function positions($menu = null){
		$sql = $menu === null ? 'SELECT id, sort FROM pages WHERE menu IS NULL ORDER BY sort' : 'SELECT id, sort FROM pages WHERE menu = '.(int)$menu.' ORDER BY sort';
		$out = [];
		foreach (static::DB()->query($sql)->fetchAll() AS $row) $out[(int)$row['id']] = (int)$row['sort'];
		return $out;
	}
}
