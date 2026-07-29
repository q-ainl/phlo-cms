<?php
// source:  /srv/control/CMS/CMS.list.phlo
// phlo:    1.0
// extends: CMS
class CMS_list extends CMS {
	public static function BothGETListOptions($list, $options){
		if (($options && !str_contains($options, eq)) || !$model = CMS::uriListFind($list)) return false;
		if (!$model::canView()) return false;
		phlo('app')->active = $model;
		$CMS = CMS($model, 'list');
		$options === 'debug' && dx($model, $CMS);
		$options && static::listOptions($CMS, $options);
		if ($options && phlo('req')->async) return $CMS->page ? apply(...array_filter(arr(append: [$CMS->sectionSel.' table tbody' => $CMS->bodyRecords], outer: [$CMS->sectionSel.' #more' => $CMS->more], remove: (!$CMS->more) ? $CMS->sectionSel.' #more' : null, data: [$CMS->sectionSel => ['page' => $CMS->page]]))) : apply(...array_filter(arr(outer: [$CMS->sectionSel.' table' => $CMS->body, $CMS->sectionSel.' #more' => $CMS->more], remove: (!$CMS->more) ? $CMS->sectionSel.' #more' : null, data: [$CMS->sectionSel => ['page' => 0]], trans: true, path: $CMS->parent ? null : phlo('req')->path)));
		main($CMS, $CMS->title);
	}
	public static function listOptions(CMS $CMS, ?string $req = null){
		if ($CMS->parent) return;
		$opts = new obj;
		$pp = $CMS->model::$pp ?? $CMS->pp;
		$page = 0;
		if ($req){
			$req = obj(...create(array_filter(explode(slash, $req)), fn($s) => substr($s, 0, strpos($s, eq)), fn($s) => substr($s, strpos($s, eq) + 1)));
			if ($req->q){
				$fields = $CMS->searchFields;
				$CMS->q = $req->q;
				$like = $CMS->model::DB()->PDO->quote(perc.strtr($req->q, [space => perc]).perc);
				$opts->where = loop($fields, fn($f, $c) => "$c LIKE $like", ' OR ');
			}
			$filters = $CMS->filters;
			if ($req->f && ($filters[$req->f]['filter'] ?? null)) $opts->where = ($opts->where ? '('.$opts->where.') AND ' : void).$filters[$CMS->f = $req->f]['filter'];
			$sorts = $CMS->sorts;
			if ($req->o && ($sorts[$req->o]['order'] ?? null)) $opts->order = $sorts[$CMS->o = $req->o]['order'];
			if (isset($req->p)) $page = max(0, intval($req->p));
		}
		($order = $CMS->model::$order ?? (current($CMS->sorts)['order'] ?? null)) && $opts->order ??= $order;
		$opts->limit = ($page * $pp).comma.$pp;
		$CMS->options = $opts;
		$CMS->page = $page;
		$CMS->pp = $pp;
	}
	public $mode = 'list';
	protected function _pp(){
		return $this->model::$pp ?? 20;
	}
	protected function _title(){
		return $this->relationshipName ? $this->parent->model::fields()[$this->relationshipName]->title : $this->titleList;
	}
	protected function _options(){
		return new obj(limit: $this->pp);
	}
	protected function _records(){
		return $this->model::records(...$this->options);
	}
	protected function _fieldCount(){
		return count($this->fields);
	}
	protected function _searchFields(){
		return $this->parent ? [] : array_filter($this->model::fields(), fn($f) => $f->search);
	}
	protected function _filters(){
		return $this->parent ? [] : (method_exists($this->model, 'objFilters') || property_exists($this->model, 'objFilters') ? $this->model::objFilters() : []);
	}
	protected function _sorts(){
		return $this->parent ? [] : (method_exists($this->model, 'objSorts') || property_exists($this->model, 'objSorts') ? $this->model::objSorts() : []);
	}
	protected function _actions(){
		$actions = [];
		if (!$this->parent){
			$this->searchFields && $actions[] = $this->searchField;
			$this->filters && $actions[] = $this->filterField;
			$this->sorts && $actions[] = $this->sortField;
		}
		$this->model::canCreate() && $actions[] = tag('a', en('New'), href: '/create/'.$this->uri(true), class: 'async btn primary');
		return implode(lf, $actions);
	}
	protected function headerTitle():string {
		return "<span class=\"count\">".($this->hasData('records') ? count($this->records) : $this->model::recordCount())."</span> $this->DOMtitle";
	}
	protected function view():string {
		$_ = [];
		$_[] = "<section $this->section>";
		$_[] = "	".(indentView($this->header ))."";
		$_[] = "	".(indentView($this->body ))."";
		$_[] = "</section>";
		return implode(lf, $_);
	}
	protected function searchField():string {
		return "<div class=\"search\"><input type=\"search\" id=\"search\" value=\"".(esc($this->q ?? void))."\" placeholder=\"".(en('Search %s...', strtolower($this->titleRecord)))."\"><i class=\"icon zoom\"></i></div>";
	}
	protected function filterField():string {
		return "<select class=\"filter\" id=\"filter\"><option value=\"\"".($this->f ? void : ' selected').">⚐ ".(en('Filter:'))."".(loop($this->filters, fn($f, $key) => '<option value="'.$key.($this->f === $key ? '" selected>' : '">').'⚐ '.$f['title'], void))."</select>";
	}
	protected function sortField():string {
		return "<select class=\"sort\" id=\"sort\"><option value=\"\"".($this->o ? void : ' selected').">⚐ ".(en('Sort:'))."".(loop($this->sorts, fn($s, $key) => '<option value="'.$key.($this->o === $key ? '" selected>' : '">').'&#8645; '.$s['title'], void))."</select>";
	}
	protected function header():string {
		$_ = [];
		$_[] = "<header>";
		$_[] = "	<$this->titleTag>$this->headerTitle</$this->titleTag>";
		$_[] = "	".(indentView($this->actions ))."";
		$_[] = "</header>";
		return implode(lf, $_);
	}
	protected function body():string {
		$_ = [];
		$_[] = "<table class=\"body\">";
		$_[] = "	<thead>";
		$_[] = "		<tr>";
		foreach ($this->fields AS $column => $field){
			$_[] = "			<th>$field->title</th>";
		}
		$_[] = "		</tr>";
		$_[] = "	</thead>";
		$_[] = "	<tbody>";
		$_[] = "		".(indentView($this->bodyRecords , 2))."";
		$_[] = "	</tbody>";
		$_[] = "</table>";
		$_[] = "".($this->parent ? void : $this->more)."";
		return implode(lf, $_);
	}
	protected function bodyRecords():string {
		$_ = [];
		if ($records = $this->records){
			foreach ($this->records AS $id => $record){
				$_[] = "<tr data-id=\"$id\">";
				foreach ($this->fields AS $column => $field){
					$_[] = "	".(indentView($this->field($field, $record, 'td') ))."";
				}
				$_[] = "</tr>";
			}
		}
		else {
			$_[] = "".($this->noRecords)."";
		}
		return implode(lf, $_);
	}
	protected function more():string {
		return "".(count($this->records) === $this->pp ? tag('button', id: 'more', inner: en('Load more')) : void)."";
	}
	protected function noRecords():string {
		return "<tr><td class=\"noRecords\" colspan=\"$this->fieldCount\">".(en('No %s', strtolower($this->titleList)))."</td></tr>";
	}
}
