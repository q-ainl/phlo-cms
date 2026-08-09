<?php
// source:   /srv/control/phlo/resources/fields/text.phlo
// phlo:     1.0.1
// extends:  field
// class:    field_text
// version:  1.0
// creator:  q-ai.nl
// summary:  Text field
// advice:   length is both the limit and the choice of input: over 250 characters the field renders a textarea instead of a single line.
// package:  fields
// frontend: false
// backend:  true
// tags:     field text
class field_text extends field {
	public $length = 100;
	protected function _multiline():bool {
		return $this->length > 250;
	}
	protected function label($record):string {
		return ($value = $record->{$this->name}) === null ? dash : strtr(esc(strlen($value) > 100 ? substr($value, 0, 80).'...' : $value), [lf => br]);
	}
	protected function input($record):string {
		return $this->multiline ? $this->inputMulti($record) : $this->inputField($record);
	}
	protected function inputField($record):string {
		return input(type: $this->type, name: $this->name, value: ($value = $record->{$this->name}) ? esc($value) : $this->default, maxlength: $this->length, placeholder: $this->placeholder, class: 'field');
	}
	protected function inputMulti($record):string {
		return textarea(name: $this->name, inner: ($value = $record->{$this->name}) ? esc($value) : $this->default ?? void, placeholder: $this->placeholder, class: 'field');
	}
	protected function _objColumns():array {
		return [$this->name];
	}
}
