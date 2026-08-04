<?php
// source:   /srv/control/phlo/resources/fields/text.phlo
// phlo:     1.0.1
// extends:  field
// class:    field_text
// version:  1.0
// creator:  q-ai.nl
// summary:  Text field
// package:  fields
// frontend: false
// backend:  true
// tags:     field text
class field_text extends field {
	public $length = 100;
	protected function _multiline(){
		return $this->length > 250;
	}
	protected function label($record){
		return ($value = $record->{$this->name}) === null ? dash : strtr(esc(strlen($value) > 100 ? substr($value, 0, 80).'...' : $value), [lf => br]);
	}
	protected function input($record){
		return $this->multiline ? $this->inputMulti($record) : $this->inputField($record);
	}
	protected function inputField($record){
		return input(type: $this->type, name: $this->name, value: ($value = $record->{$this->name}) ? esc($value) : $this->default, maxlength: $this->length, placeholder: $this->placeholder, class: 'field');
	}
	protected function inputMulti($record){
		return textarea(name: $this->name, inner: ($value = $record->{$this->name}) ? esc($value) : $this->default ?? void, placeholder: $this->placeholder, class: 'field');
	}
	protected function _objColumns(){
		return [$this->name];
	}
}
