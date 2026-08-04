<?php
// source:  /srv/control/CMS/tests/fixtures/sort/coded.phlo
// phlo:    1.0.1
// class:   coded
// extends: model
class coded extends model {
	// A string primary key on a column that is not called id, so the reorder cannot lean on
	// either assumption.
	public static $table = 'codeds';
	public static $order = 'weight ASC';
	public static $listView = 'sort';
	public static $sortColumn = 'weight';
	public static $idColumn = 'code';
	public static $idType = 'string';
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
			code: field(type: 'text', title: 'Code'),
			name: field(type: 'text', title: 'Name'),
			weight: field(type: 'number', title: 'Weight'),
		);
	}
	public static function prepareTable(){
		static::DB()->query('CREATE TABLE IF NOT EXISTS codeds (code TEXT PRIMARY KEY, name TEXT, weight INTEGER)');
		static::DB()->query('DELETE FROM codeds');
		static::DB()->query('INSERT INTO codeds (code, name, weight) VALUES (?, ?, 1), (?, ?, 2), (?, ?, 3)', 'cash', 'Cash', 'pin', 'Pin', 'card', 'Card');
	}
	public static function positions(){
		$out = [];
		foreach (static::DB()->query('SELECT code, weight FROM codeds ORDER BY weight')->fetchAll() AS $row) $out[(string)$row['code']] = (int)$row['weight'];
		return $out;
	}
}
