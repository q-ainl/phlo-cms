<?php
// source:  /srv/control/CMS/tests/fixtures/sort/plain.phlo
// phlo:    1.0.1
// class:   plain
// extends: model
class plain extends model {
	// No group at all: the whole table is one order, the case of a short flat list.
	public static $table = 'plains';
	public static $order = 'sort ASC';
	public static $listView = 'sort';
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
			name: field(type: 'text', title: 'Name'),
			sort: field(type: 'number', title: 'Sort'),
		);
	}
	public static function prepareTable(){
		static::DB()->query('CREATE TABLE IF NOT EXISTS plains (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, sort INTEGER)');
		static::DB()->query('DELETE FROM plains');
		// Gaps on purpose: a normalising renumber has to close them.
		static::DB()->query('INSERT INTO plains (id, name, sort) VALUES (1, ?, 5), (2, ?, 9), (3, ?, 40)', 'a', 'b', 'c');
	}
	public static function positions(){
		$out = [];
		foreach (static::DB()->query('SELECT id, sort FROM plains ORDER BY sort')->fetchAll() AS $row) $out[(int)$row['id']] = (int)$row['sort'];
		return $out;
	}
}
