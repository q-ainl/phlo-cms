<?php
// source:   /srv/control/phlo/resources/fields/field.phlo
// phlo:     1.0.1
// type:     abstract class
// version:  1.0
// creator:  q-ai.nl
// summary:  Base ORM field
// package:  fields
// frontend: false
// backend:  true
// tags:     field orm
abstract class field extends obj {
	public static function __handle(...$data){
		return null;
	}
	protected function _title(){
		return ucfirst($this->name);
	}
	protected function input($record){
		return input(type: $this->type, name: $this->name, value: $record->{$this->name} ?? $this->default, maxlength: $this->length, placeholder: $this->placeholder, class: 'field');
	}
	protected function label($record){
		return $record->{$this->name};
	}
	protected function _objColumns(){
		return [];
	}
	public function objValidate($value){
		if (($value === null || $value === void) && $this->required) return $this->title.' is required';
		if ($value === null || $value === void) return null;
		if ($this->length && is_string($value) && mb_strlen($value) > $this->length) return $this->title.' is too long (max '.$this->length.')';
		if ($this->pattern && is_string($value) && !preg_match('/'.str_replace('/', '\\/', $this->pattern).'/', $value)) return $this->title.' has invalid format';
		if ($this->enum && !in_array($value, (array)$this->enum, true)) return $this->title.' must be one of: '.implode(', ', (array)$this->enum);
		return null;
	}
}
