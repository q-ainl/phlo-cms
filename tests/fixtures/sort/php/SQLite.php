<?php
// source:   /srv/control/phlo/resources/DB/SQLite.phlo
// phlo:     1.0.1
// version:  1.0
// creator:  q-ai.nl
// summary:  SQLite resource
// advice:   One file, one database, given as a path: %SQLite('/path/db.sqlite'). It writes with a lock over the whole file, so it fits a single site or a worker but not a set of processes writing at once. Perfect where you want the ORM without a server.
// extends:  DB
// package:  database
// frontend: false
// backend:  true
// requires: @DB php-ext:pdo php-ext:pdo_sqlite
// tags:     sqlite pdo database sql
class SQLite extends DB {
	public static function __handle(string $file){
		return "SQLite/$file";
	}
	public function __construct(private string $file){}
	protected function _PDO():\PDO {
		return new PDO('sqlite:'.$this->file);
	}
	public string $insertIgnore = ' OR IGNORE';
}
