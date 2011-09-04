<?php

// 소셜XE에서 사용하는 세션을 관리하게 편하게 한데 모은 클래스
class socialxeServerSessionManager{

	// 생성자
	function socialxeServerSessionManager(){
		// 도메인 정보를 세팅
		$this->domain = $_SERVER['HTTP_HOST'];
	}

	// 인스턴스 얻기
	function &getInstance(){
		static $instance;
		if (!isset($instance)) $instance = new socialxeServerSessionManager();
		return $instance;
	}

	// 세션 세팅
	function setSession($name, $session){
		$_SESSION['socialxeserver'][$this->domain][$name] = $session;
	}

	// 세션 얻기
	function getSession($name){
		return $_SESSION['socialxeserver'][$this->domain][$name];
	}

	// 세션 지우기
	function clearSession($name){
		unset($_SESSION['socialxeserver'][$this->domain][$name]);
	}
}

?>