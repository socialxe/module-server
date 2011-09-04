<?php

if (!class_exists("Facebook")){
	require_once(_XE_PATH_.'modules/socialxeserver/facebook/facebook.php');
}

// 페이스북 클래스를 상속 받아 SocialXE에 적합하게 바꾼다.
class FacebookSocialXE extends Facebook {

	// 생성자
	public function __construct($config){
		parent::__construct($config);

		$CURL_OPTS['CURLOPT_CONNECTTIMEOUT'] = 30;
		$CURL_OPTS['CURLOPT_RETURNTRANSFER'] = true;
		$CURL_OPTS['CURLOPT_TIMEOUT'] = 60;
		$CURL_OPTS['CURLOPT_USERAGENT'] = 'SocialXE Communicator';
		$CURL_OPTS['CURLOPT_SSL_VERIFYPEER'] = false;
		$CURL_OPTS['CURLOPT_SSL_VERIFYHOST'] = 2;
	}

	// 로그인 주소
	public function getLoginUrl($params=array()){
		$_SESSION['state'] = md5(uniqid(rand(), TRUE)); //CSRF protection

		return $this->getUrl(
			'www',
			'dialog/oauth',
			array_merge(array(
				'client_id' => $this->getAppId(),
				'state' => $_SESSION['state']
			), $params)
		);
	}

	// 세션 얻기
	public function getSession($redirect_uri = null){
		$session = parent::getSession();
		if ($session) return $session;

		// oAuth 콜백 처리
		if (!isset($_REQUEST['code'])) return null;

		// state 검사
		if ($_SESSION['state'] != $_REQUEST['state']) return null;

		// 액세스 토큰 요청
		$response = $this->makeRequest(
			$this->getUrl('graph', 'oauth/access_token'),
			array(
				'client_id' => $this->getAppId(),
				'client_secret' => $this->getApiSecret(),
				'code' => $_REQUEST['code'],
				'redirect_uri' => $redirect_uri
			)
		);
		parse_str($response, $params);

		$this->setSession($params, true);

		return $params;
	}

	// validateSessionObject는 아무 것도 하지 않는 것으로 변경한다...
	// uid, sig 등 값이 oAuth 2.0 프로세스에서는 존재하지 않음.
	protected function validateSessionObject($session) {
		return $session;
	}
}
