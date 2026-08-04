<?php
// source:   /srv/control/phlo/resources/session.phlo
// phlo:     1.0.1
// version:  1.0
// creator:  q-ai.nl
// summary:  Session data object
// package:  web
// frontend: false
// backend:  true
// tags:     session web state
class session extends obj {
	protected function controller(){
		session_start();
		$this->objData = $_SESSION;
	}
	public function __set($key, $value){
		return $_SESSION[$key] = $this->objData[$key] = $value;
	}
	public function __unset($key){
		unset($this->objData[$key], $_SESSION[$key]);
	}
	public function __isset($key){
		return isset($this->objData[$key]);
	}
	public function objRegenerateId($deleteOld = true){
		session_regenerate_id($deleteOld);
		$this->objData = $_SESSION;
	}
}
