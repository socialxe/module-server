<?php

// 서비스 관리 클래스
class socialxeServerProviderManager{

	// 인스턴스
	function getInstance(&$sessionManager, &$config){
		static $instance;
		if (!isset($instance)) $instance = new socialxeServerProviderManager($sessionManager, $config);
		return $instance;
	}

	// 생성자
	function socialxeServerProviderManager(&$sessionManager, &$config){
		// 세션 관리자 저장
		$this->session = $sessionManager;

		// 환경설정
		$this->config = $config;

		// 제공하는 서비스
		$this->provider_list = array('twitter', 'me2day', 'facebook', 'yozm');

		// 각 서비스 클래스
		$this->provider['twitter'] = &socialxeServerProviderTwitter::getInstance($this->session, $config->twitter_consumer_key, $config->twitter_consumer_key_secret);
		$this->provider['me2day'] = &socialxeServerProviderMe2day::getInstance($this->session, $config->me2day_application_key);
		$this->provider['facebook'] = &socialxeServerProviderFacebook::getInstance($this->session, $config->facebook_app_id, $config->facebook_app_secret);
		$this->provider['yozm'] = &socialxeServerProviderYozm::getInstance($this->session, $config->yozm_consumer_key, $config->yozm_consumer_key_secret);
	}

	// 제공하는 서비스 여부 확인
	function inProvider($provider){
		return in_array($provider, $this->provider_list);
	}

	// 로그인 URL을 얻는다.
	function getLoginUrl($provider){
		if (!$this->inProvider($provider)) return new Object(-1, 'msg_invalid_provider');

		return $this->provider[$provider]->getLoginUrl();
	}

	// 콜백 처리
	function callback($provider){
		if (!$this->inProvider($provider)) return new Object(-1, 'msg_invalid_provider');

		return $this->provider[$provider]->callback();
	}

	// 댓글 전송
	function send($provider, $comment, $access, $uselang = 'en', $use_socialxe = false){
		if (!$this->inProvider($provider)) return new Object(-1, 'msg_invalid_provider');

		return $this->provider[$provider]->send($comment, $access, $uselang, $use_socialxe);
	}
}

?>
