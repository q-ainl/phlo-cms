<?php
// source:   /srv/control/phlo/resources/fields/number.phlo
// phlo:     1.0.1
// extends:  field
// class:    field_number
// version:  1.0
// creator:  q-ai.nl
// summary:  Number field
// package:  fields
// frontend: true
// backend:  true
// tags:     field number
class field_number extends field {
	public $decimals = 0;
	public $length = 5;
	public $min = 0;
	protected function label($record){
		return ($value = $record->{$this->name}) === null || $value === void ? dash : number_format($value, $this->decimals, comma, dot);
	}
	protected function input($record){
		return input(type: 'number', name: $this->name, value: $record->{$this->name} ?? $this->default, step: $this->decimals ? dot.str_repeat('0', $this->decimals - 1).'1' : null, min: $this->min, class: 'field');
	}
	protected function parse($record){
		$name = $this->name;
		if (!phlo('payload')->hasData($name)) return;
		$value = phlo('payload')->$name;
		$record->$name = ($value === null || (is_string($value) && trim($value) === void)) ? ($this->default ?? 0) : $value;
	}
	protected function _objColumns(){
		return [$this->name];
	}
}
