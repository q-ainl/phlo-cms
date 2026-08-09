<?php
// source:   /srv/control/phlo/resources/fields/field.phlo
// phlo:     1.0.1
// type:     abstract class
// version:  1.0
// creator:  q-ai.nl
// summary:  Base ORM field
// advice:   Every field type extends this one and is made with field(type: 'x'), which resolves to the resource field_x; anything else you pass becomes a property, so title, required, length, pattern and enum need no declaration. Override input() for the form and label() for the list, and let objColumns name the columns the field owns, so a field that stores nothing returns an empty array.
// package:  fields
// frontend: false
// backend:  true
// tags:     field orm
abstract class field extends obj {
	public static function __handle(...$data){
		return null;
	}
	protected function _title():string {
		return ucfirst($this->name);
	}
	protected function input($record):string {
		return input(type: $this->type, name: $this->name, value: $record->{$this->name} ?? $this->default, maxlength: $this->length, placeholder: $this->placeholder, class: 'field');
	}
	protected function label($record){
		return $record->{$this->name};
	}
	protected function _objColumns():array {
		return [];
	}
	public function objValidate($value):?string {
		if (($value === null || $value === void) && $this->required) return $this->title.' is required';
		if ($value === null || $value === void) return null;
		if ($this->length && is_string($value) && mb_strlen($value) > $this->length) return $this->title.' is too long (max '.$this->length.')';
		if ($this->pattern && is_string($value) && !preg_match('/'.str_replace('/', '\\/', $this->pattern).'/', $value)) return $this->title.' has invalid format';
		if ($this->enum && !in_array($value, (array)$this->enum, true)) return $this->title.' must be one of: '.implode(', ', (array)$this->enum);
		return null;
	}
}
