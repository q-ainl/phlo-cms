<?php
// source:  /srv/control/CMS/CMS.list.sort.phlo
// phlo:    1.0
// extends: CMS_list
class CMS_list_sort extends CMS_list {
	public static function sortColumn($model){
		return property_exists($model, 'sortColumn') ? $model::$sortColumn : 'sort';
	}
	public static function sortGroup($model){
		return property_exists($model, 'sortGroup') ? $model::$sortGroup : null;
	}
	public static function sortIds($model, array $ids){
		return array_values(array_map(fn($id) => $model::$idType === 'int' ? (int)$id : (string)$id, $ids));
	}
	// A record loaded from the database carries its raw key in objData, while the property
	// itself hands back the related object. A record built from a payload has only the property,
	// and that already holds the raw key.
	public static function sortGroupOf($record, $group){
		return $record->objData[$group] ?? $record->$group;
	}
	// An empty group is a group of its own: records without a category belong together and can
	// be ordered among themselves. That needs IS NULL, because an equality bind on null matches
	// nothing in SQL and would silently hand back an empty group.
	public static function sortGroupRecords($model, $column, $group, $value){
		if ($group === null) return $model::records(order: $column.' ASC');
		if ($value === null) return $model::records(where: $group.' IS NULL', order: $column.' ASC');
		return $model::records(...[$group => $value], order: $column.' ASC');
	}
	// The next free position within a group, so a record lands at the end instead of keeping a
	// number that means nothing there. The group value is passed in rather than read from the
	// record, because a record being saved holds its new group on the property and its old one
	// in objData.
	// Deliberately not locked: two creates in the very same moment can pick the same number.
	// That is a back office, not a checkout, and the first drag afterwards normalises the group
	// anyway, so the reading costs less than a lock on every insert.
	public static function sortNext($model, $value):int {
		$column = static::sortColumn($model);
		$rows = static::sortGroupRecords($model, $column, static::sortGroup($model), $value);
		$last = 0;
		foreach ($rows AS $row) $last = max($last, (int)$row->$column);
		return $last + 1;
	}
	// The reorder itself, apart from the request so it can be exercised directly. Returns an
	// error string, or null when the group was rewritten.
	public static function sortApply($model, array $rawIds):?string {
		if ((property_exists($model, 'listView') ? $model::$listView : null) !== 'sort') return 'Model does not sort by hand';
		$column = static::sortColumn($model);
		$fields = $model::fields();
		if (!isset($fields[$column])) return 'Model has no sort column';
		$group = static::sortGroup($model);
		if ($group !== null && !isset($fields[$group])) return 'Model has no sort group';
		$key = $model::idColumn();
		$ids = static::sortIds($model, $rawIds);
		if (!$ids) return 'Nothing to sort';
		if (count($ids) !== count(array_unique($ids, SORT_REGULAR))) return 'Duplicate records';
		$first = $model::record(...[$key => $ids[0]]);
		if (!$first) return 'Unknown record';
		$all = static::sortGroupRecords($model, $column, $group, $group === null ? null : static::sortGroupOf($first, $group));
		foreach ($ids AS $id) if (!isset($all[$id])) return 'Record outside this group';
		$order = array_keys($all);
		$slots = [];
		foreach ($order AS $index => $id) if (in_array($id, $ids, true)) $slots[] = $index;
		foreach ($slots AS $slot => $index) $order[$index] = $ids[$slot];
		// Renumbering normalises the whole group, so it writes records the browser never sent.
		// Permission is therefore checked over everything that actually changes, not over what;
		// was submitted: a record the user may not change must not be moved by dragging its;
		// neighbour either.
		$writes = [];
		foreach ($order AS $index => $id) if ((int)$all[$id]->$column !== $index + 1) $writes[$id] = $index + 1;
		foreach ($writes AS $id => $position) if (!$model::canChange($all[$id])) return 'Not allowed';
		$model::transaction(function() use ($model, $writes, $column, $key){
			foreach ($writes AS $id => $position) $model::change($key.'=?', $id, ...[$column => $position]);
		});
		return null;
	}
	public static function AsyncPUTApiSortModelIds($model){
		if (!phlo('CSRF')->verify) return apply(error: 'Invalid CSRF token');
		if (!in_array($model, (array)phlo('app')->models, true)) return apply(error: 'Unknown model');
		if ($error = static::sortApply($model, (array)phlo('payload')->ids)) return apply(error: $error);
		return apply(state: false);
	}
	// The whole difference with an ordinary list: one column in front, holding the handle and the
	// group the row belongs to. The table itself comes from CMS_list, so a change to the standard
	// list does not have to be made twice.
	public $rowExtra = 1;
	protected function sortGroupValue($record){
		return ($group = static::sortGroup($this->model)) ? (string)static::sortGroupOf($record, $group) : void;
	}
	protected function rowHead(){
		return '<th></th>';
	}
	protected function rowLead($record){
		$group = ' data-sort-group="'.esc((string)$this->sortGroupValue($record)).'"';
		if ($this->o) return '<td class="sort sort--off"'.$group.'><span class="sort__grip" title="'.esc(en('Switch back to the manual order to drag')).'">⬍</span></td>';
		return '<td class="sort"'.$group.'><button type="button" class="sort__grip" aria-label="'.esc(en('Move this row: drag it, or use the arrow keys')).'" title="'.esc(en('Drag to reorder')).'">⬍</button></td>';
	}
	protected function controller(){
		$this->type = 'default';
	}
}
