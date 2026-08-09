<?php
// source:   /srv/control/phlo/resources/session.phlo
// phlo:     1.0.1
// version:  1.0
// creator:  q-ai.nl
// summary:  Session data object
// advice:   The session as an object: read a key, write a key, done. Call objRegenerateId() the moment someone logs in or changes role, so a token from before cannot be reused. Sessions are per browser, so anything that must survive a device belongs in a model. Override options to steer the cookie: a flow that returns to you with a cross-site POST, such as an OIDC provider posting its callback, needs cookie_samesite None with cookie_secure true, because a Lax cookie is not sent on that request and the state you stored is then unreadable.
// package:  web
// frontend: false
// backend:  true
// tags:     session web state
class session extends obj {
	public static function options():array {
		return [];
	}
	protected function controller(){
		session_start(static::options());
		$this->objData = $_SESSION;
	}
	public function __set($key, $value){
		return $_SESSION[$key] = $this->objData[$key] = $value;
	}
	public function __unset($key){
		unset($this->objData[$key], $_SESSION[$key]);
	}
	public function __isset($key):bool {
		return isset($this->objData[$key]);
	}
	public function objRegenerateId($deleteOld = true):void {
		session_regenerate_id($deleteOld);
		$this->objData = $_SESSION;
	}
}
