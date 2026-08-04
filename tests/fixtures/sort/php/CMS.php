<?php
// source: /srv/control/CMS/CMS.phlo
// phlo:   1.0.1
// type:   abstract class
abstract class CMS extends obj {
	public static function GETSectionTokenFilename($section, $token, $filename){
		return output(file: ($exists = file_exists($file = constant($section).substr($token, 0, 2).slash.substr($token, 2).dot.pathinfo($filename, PATHINFO_EXTENSION))) ? $file : www.'notFound.webp', attachment: $exists ? null : false);
	}
	public static function _uriLists(){
		return create(phlo('app')->models, fn($model) => $model::$uriList ?? $model::$table);
	}
	public static function uriListFind($uri){
		return static::uriLists()[$uri] ?? false;
	}
	public static function _uriRecords(){
		return create(phlo('app')->models, fn($model) => $model::$uriRecord ?? $model);
	}
	public static function uriRecordFind($uri){
		return static::uriRecords()[$uri] ?? false;
	}
	public function __construct(public string $model, ...$args){
		return $args && $this->objImport(...$args);
	}
	protected function _fields(){
		return array_filter($this->model::fields(), fn($field) => $field->{$this->mode} !== false && $field->name !== $this->foreignKey);
	}
	protected function _record(){
		return $this->model::record(...[$this->model::idColumn() => $this->id]);
	}
	protected function _recordId(){
		$pk = $this->model::idColumn();
		return $this->record->$pk;
	}
	protected function _DOMtitle(){
		return $this->hideBody || ($this->parent && !$this->parent->childField) ? tag('a', $this->title, href: slash.$this->uri, class: 'async') : $this->title;
	}
	protected function _titleList(){
		return $this->model::$titleList ?? ucfirst($this->model::$table);
	}
	protected function _titleRecord(){
		return $this->model::$titleRecord ?? ucfirst($this->model);
	}
	protected function _uriList(){
		return $this->model::$uriList ?? $this->model::$table;
	}
	protected function _uriRecord(){
		return $this->model::$uriRecord ?? $this->model;
	}
	protected function _recordMode(){
		return $this->model::$recordMode ?? phlo('app')->recordMode ?? 'view';
	}
	protected function _section(){
		return "class=\"$this->selector\" data-model=\"$this->model\" data-list=\"$this->uriList\" data-record=\"".($this->relationshipName ?? $this->uriRecord)."\"".($this->parent ? ' data-parent="'.$this->parent->uriRecord.slash.$this->parent->id.'"' : void).($this->recordMode === 'edit' ? ' data-open="edit"' : void);
	}
	protected function _sectionSel(){
		return 'section[data-model="'.$this->model.'"][data-record="'.($this->relationshipName ?? $this->uriRecord).'"]'.($this->parent ? '[data-parent="'.$this->parent->uriRecord.'/'.$this->parent->id.'"]' : '');
	}
	protected function _selector(){
		return implode(space, array_filter([$this->isMain ? 'main' : null, $this->mode, $this->type]));
	}
	protected function _titleTag(){
		return $this->isMain ? 'h1' : 'h2';
	}
	protected function _uri($useRecord = false){
		$parentPath = $this->parent ? $this->parent->uriRecord.'/'.$this->parent->id.'/' : '';
		$selfPath = $this->relationshipName ?? ($this->mode === 'list' && !$useRecord ? $this->uriList : $this->uriRecord);
		$idPath = ($this->mode === 'record' || $this->mode === 'change') && $this->id ? '/'.$this->id : '';
		return $parentPath.$selfPath.$idPath;
	}
	protected function event(){
		return $this->record?->hasMethod($method = 'view'.ucfirst($this->mode)) && is_string($output = $this->record->$method($this)) ? "$output\n" : void;
	}
	protected function field($field, $record, $tag){
		$mode = $this->mode;
		$views = ['list' => 'label', 'record' => 'label', 'create' => 'input', 'change' => 'input'];
		$view = $field->$mode === true || $field->$mode === null ? $views[$mode] : $field->$mode;
		$view === 'list' && $mode === 'record' && ($view = 'count');
		$classes = [$view, $field->type];
		$body = $field->$view($record);
		$field->prefix && $body = $field->prefix.$body;
		$field->suffix && $body .= $field->suffix;
		if (!str_contains($body, '<a') && !str_contains($body, '<input') && !str_contains($body, '<select') && !str_contains($body, '<textarea')) $classes[] = 'link';
		$class = ' class="'.implode(space, $classes).'"';
		return "<$tag$class>$body</$tag>";
	}
	protected function view():string {
		$_ = [];
		$_[] = "$this->event<section $this->section>";
		$_[] = "	".(indentView($this->header ))."";
		$_[] = "	".(indentView($this->body ))."";
		$_[] = "</section>";
		return implode(lf, $_);
	}
}
