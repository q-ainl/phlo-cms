<?php
// source:  /srv/control/CMS/tests/fixtures/sort/sortprobe.phlo
// phlo:    1.0
// summary: Sort test probe. Drives CMS_list_sort::sortApply straight against SQLite models and
class sortprobe extends obj {
	public static function run($fn){
		page::prepareTable();
		plain::prepareTable();
		coded::prepareTable();
		return $fn();
	}
	public static function cases(){
		$out = [];
	
		// A flat list renumbers as a whole and closes its gaps.
		$out['flat'] = static::run(function(){
			$error = CMS_list_sort::sortApply('plain', [3, 1, 2]);
			return ['error' => $error, 'positions' => plain::positions()];
		});
	
		// Reordering one group leaves the other exactly as it was.
		$out['group'] = static::run(function(){
			$error = CMS_list_sort::sortApply('page', [3, 2, 1]);
			return ['error' => $error, 'one' => page::positions(1), 'two' => page::positions(2)];
		});
	
		// Only part of a group in view, as with paging or a filter: the sent records swap among;
		// the slots they held, everything else keeps its place.
		$out['subset'] = static::run(function(){
			$error = CMS_list_sort::sortApply('page', [3, 2]);
			return ['error' => $error, 'one' => page::positions(1)];
		});
	
		// Records without a group are a group of their own.
		$out['null_group'] = static::run(function(){
			$error = CMS_list_sort::sortApply('page', [7, 6]);
			return ['error' => $error, 'loose' => page::positions(null), 'one' => page::positions(1)];
		});
	
		// A string primary key on a differently named column.
		$out['string_key'] = static::run(function(){
			$error = CMS_list_sort::sortApply('coded', ['card', 'cash', 'pin']);
			return ['error' => $error, 'positions' => coded::positions()];
		});
	
		// Refusals must leave the table untouched.
		$out['duplicate'] = static::run(function(){
			$error = CMS_list_sort::sortApply('plain', [1, 1]);
			return ['error' => $error, 'positions' => plain::positions()];
		});
	
		$out['foreign_group'] = static::run(function(){
			$error = CMS_list_sort::sortApply('page', [1, 4]);
			return ['error' => $error, 'one' => page::positions(1), 'two' => page::positions(2)];
		});
	
		$out['unknown'] = static::run(function(){
			$error = CMS_list_sort::sortApply('plain', [1, 999]);
			return ['error' => $error, 'positions' => plain::positions()];
		});
	
		// A model that never opted in cannot be reordered by a hand made request.
		$out['not_opted_in'] = static::run(function(){
			$error = CMS_list_sort::sortApply('nosort', [1, 2]);
			return ['error' => $error];
		});
	
		// Permission is checked over every record the renumber would write, not only over the;
		// ones that were sent: record 99 is untouchable and sits in the middle of the group.
		// The gaps matter: normalising closes them, so the untouchable record has to be written;
		// even though nobody dragged it, and that is exactly what must be refused.
		$out['permission'] = static::run(function(){
			page::DB()->query('UPDATE pages SET sort = 10 WHERE id = 1');
			page::DB()->query('INSERT INTO pages (id, menu, name, sort) VALUES (99, 1, ?, 20)', 'locked');
			page::DB()->query('UPDATE pages SET sort = 30 WHERE id = 2');
			page::DB()->query('UPDATE pages SET sort = 40 WHERE id = 3');
			$error = CMS_list_sort::sortApply('page', [3, 1]);
			return ['error' => $error, 'one' => page::positions(1)];
		});
	
		// The next free spot in a group, which is where a new record and a record moved into;
		// another group both belong.
		$out['next'] = static::run(function(){
			return ['one' => CMS_list_sort::sortNext('page', 1), 'two' => CMS_list_sort::sortNext('page', 2), 'loose' => CMS_list_sort::sortNext('page', null), 'flat' => CMS_list_sort::sortNext('plain', null)];
		});
	
		return $out;
	}
}
