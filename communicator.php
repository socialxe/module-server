<?php

// bit.ly 라이브러리 로드
if (!class_exists("bitly_SocialXE")){
	require_once(_XE_PATH_.'modules/socialxeserver/bitly.php');
}

// 클라이언트와 통신을 위한 클래스
class socialxeServerCommunicator {

	// 인스턴스
	function getInstance(&$sessionManager, &$config){
		static $instance;
		if (!isset($instance)) $instance = new socialxeServerCommunicator($sessionManager, $config);
		return $instance;
	}

	// 생성자
	function socialxeServerCommunicator(&$sessionManager, &$config){
		$this->providerManager = &socialxeServerProviderManager::getInstance($sessionManager, $config);
		$this->session = $sessionManager;
		$this->config = $config;

		// 소셜XE 계정 액세스 설정
		$this->access[twitter]->oauth_token = $config->twitter_access_token;
		$this->access[twitter]->oauth_token_secret = $config->twitter_access_token_secret;
		$this->access[me2day]->user_id = $config->me2day_user_id;
		$this->access[me2day]->user_key = $config->me2day_api_key;
	}

	// api 처리
	function procAPI($mode){
		$result = new Object();

		set_time_limit(0);

		// data decode
		$this->decodeData();

		switch($mode){
			// 요청토큰
			case 'request':
				return $this->request();
			break;

			// 로그인
			case 'login':
				return $this->login();
			break;

			// 자동로그인 키
			case 'autologinkey':
				return $this->autoLoginKey();
			break;

			// 로그인 세션 저장
			case 'setsession':
				return $this->setSession();
			break;

			// 로그인 세션 주기
			case 'getsession':
				return $this->getSession();
			break;

			// 콜백
			case 'callback':
				return $this->callback();
			break;

			// 액세스토큰
			case 'access':
				return $this->access();
			break;

			// 댓글 전송
			case 'send':
				return $this->send();
			break;

			default:
				Context::setRequestMethod('JSON');
				$result->setError(-1);
				$result->setMessage('invalid mode');
				return $result;
		}
	}

	// 요청토큰
	function request(){
		// 응답 형식은 JSON로
		Context::setRequestMethod('JSON');

		// 요청 토큰 테이블에서 5분이 지난 토큰은 삭제한다.
		$output = $this->_clearOldRequest();
		if (!$output->toBool()) return $output;

		// 클라이언트 버전이 0.9.1보다 낮으면 우선 요청 데이터 처리를 호출해준다.
		if ($this->client_ver < '0.9.1'){
			$name_list = array('client');
			$this->getRequestData($name_list);
		}

		// 클라이언트 토큰 확인
		unset($output);
		$output = $this->_getClientInfo($this->request_data['client']);

		if (!$output->toBool()) return $output;
		$client_info = $output->get('client_info');

		// 요청 토큰 생성
		unset($output);
		$output = $this->_insertRequest($client_info->client_srl);
		if (!$output->toBool()) return $output;
		$token = $output->get('token');

		$result = new Object();
		$result->add('request_token', $token);
		return $result;
	}

	// 로그인
	function login(){
		// 응답 형식은 HTML로
		Context::setRequestMethod('HTML');

		// 클라이언트 버전이 0.9.1보다 낮으면 우선 요청 데이터 처리를 호출해준다.
		if ($this->client_ver < '0.9.1'){
			$name_list = array('request_token', 'provider', 'xe');
			$this->getRequestData($name_list);
		}

		$request_token = $this->request_data['request_token'];
		$provider = $this->request_data['provider'];

		// 제공하는 서비스 인지 확인
		if (!$this->providerManager->inProvider($provider)) return $this->stop('msg_invalid_provider');

		// 요청 토큰 확인
		$output = $this->_checkRequest($request_token);
		if (!$output->toBool()) return $output;
		$request_info = $output->get('request_info');

		// 해당 클라이언트의 사용 도메인인지 확인
		$referer_info = parse_url($_SERVER['HTTP_REFERER']);
		$referer_domain = str_replace('www.', '', $referer_info['host']);

		$output = $this->_checkClientDomain($request_info->client_srl, $referer_domain);
		if (!$output->toBool()) return $output;

		// 요청이 온 사이트의 XE 위치를 세션에 저장한다.
		$callback = $referer_info['scheme'] . '://' . $referer_info['host'];
		if ( ($referer_info['scheme'] == 'https' && $referer_info['port'] && $referer_info['port'] != 443) ||
				($referer_info['scheme'] == 'http' && $referer_info['port'] && $referer_info['port'] != 80) ){
			$callback .= ':' . $referer_info['port'];
		}
		if ($this->request_data['xe']) $callback .= '/' . $this->request_data['xe'];
		$this->session->setSession('callback', $callback);

		// 로그인 URL을 얻는다.
		unset($output);
		$output = $this->providerManager->getLoginUrl($provider);
		if (!$output->toBool()) return $output;
		$url = $output->get('url');

		// 리다이렉트
		header('Location: ' . $url);
		Context::close();
		exit;
	}

	// 자동 로그인 키
	function autoLoginKey(){
		// 응답 형식은 JSON로
		Context::setRequestMethod('JSON');

		$auto_login_key = $_COOKIE['socialxe_ak'];
		if (!$auto_login_key){
			$auto_login_key = $token = md5(uniqid(rand(), TRUE));
		}
		setCookie('socialxe_ak',$auto_login_key, time()+60*60*24*365, '/');

		$result = new Object();
		$result->add('auto_login_key', $auto_login_key);

		require_once(_XE_PATH_.'classes/display/JSONDisplayHandler.php');
		$json = JSONDisplayHandler::toDoc($result);
		echo $_GET['callback'] . '(' . $json . ');';
		Context::close();
		exit;
	}

	// 세션 저장
	function setSession(){
		// 응답 형식은 JSON로
		Context::setRequestMethod('JSON');

		// 클라이언트 버전이 0.9.1보다 낮으면 우선 요청 데이터 처리를 호출해준다.
		if ($this->client_ver < '0.9.1'){
			$name_list = array('auto_login_key', 'session');
			$this->getRequestData($name_list);
		}

		$auto_login_key = $this->request_data['auto_login_key'];
		if (!$auto_login_key) return new Object();

		if ($this->client_ver < '0.9.1')
			$session = urldecode($this->request_data['session']); // 세션 정보
		else
			$session = serialize($this->request_data['session']); // 세션 정보

		// 기존 세션 정보 삭제
		$args->auto_login_key = $auto_login_key;
		executeQuery('socialxeserver.deleteSession', $args);

		// 세션 정보 저장
		$args->session = $session;
		executeQuery('socialxeserver.insertSession', $args);

		return new Object();
	}

	// 세션 주기
	function getSession(){
		// 응답 형식은 JSON로
		Context::setRequestMethod('JSON');

		// 클라이언트 버전이 0.9.1보다 낮으면 우선 요청 데이터 처리를 호출해준다.
		if ($this->client_ver < '0.9.1'){
			$name_list = array('auto_login_key');
			$this->getRequestData($name_list);
		}

		$auto_login_key = $this->request_data['auto_login_key'];
		if (!$auto_login_key) return new Object();

		$args->auto_login_key = $auto_login_key;
		$output = executeQuery('socialxeserver.getSession', $args);
		if (!$output->data) return new Object();

		$session = unserialize($output->data->session);
		$result = new Object();
		$result->add('session', $session);
		return $result;
	}

	// 콜백 처리
	function callback(){
		$provider = Context::get('provider');

		$output = $this->providerManager->callback($provider);
		if (!$output->toBool()) return $output;
		$info = $output->get('info');

		if (!$info){
			$this->_returnPage(null);
			return;
		}

		// 액세스 토큰 저장
		$verifier = md5(uniqid(rand(), TRUE));
		$args->verifier = $verifier;
		$args->access_token = serialize($info);
		$output = executeQuery('socialxeserver.insertVerifier', $args);
		if (!$output->toBool()) return $this->stop('error');

		// 원래 페이지로 돌아간다.
		$this->_returnPage($verifier);
	}

	// 액세스 토큰
	function access(){
		// 응답 형식은 JSON로
		Context::setRequestMethod('JSON');

		// 액세스 토큰 테이블에서 5분이 지난 토큰은 삭제한다.
		$args->regdate = date('YmdHis', time() - 5*60);
		$output = executeQuery('socialxeserver.deleteVerifier', $args);
		if (!$output->toBool()) return $this->stop('error');

		// 클라이언트 버전이 0.9.1보다 낮으면 우선 요청 데이터 처리를 호출해준다.
		if ($this->client_ver < '0.9.1'){
			$name_list = array('verifier');
			$this->getRequestData($name_list);
		}

		$verifier = $this->request_data['verifier'];

		// 액세스 토큰 얻기
		unset($args);
		$args->verifier = $verifier;
		$output = executeQuery('socialxeserver.getVerifier', $args);
		if (!$output->toBool()) return $this->stop('error');
		$access_token = unserialize($output->data->access_token);

		// 액세스 토큰 삭제
		$output = executeQuery('socialxeserver.deleteVerifier', $args);

		$result = new Object();
		$result->add('access_token', $access_token);
		return $result;
	}

	// 댓글 전송
	function send(){
		// 응답 형식은 JSON로
		Context::setRequestMethod('JSON');

		// 클라이언트 버전이 0.9.1보다 낮으면 우선 요청 데이터 처리를 호출해준다.
		if ($this->client_ver < '0.9.1'){
			$name_list = array('comment', 'master_provider', 'logged_provider_list', 'reply_provider_list', 'client', 'uselang', 'twitter', 'me2day', 'facebook', 'yozm');
			$this->getRequestData($name_list);
		}

		if ($this->client_ver < '0.9.1'){
			$comment = unserialize(urldecode($this->request_data['comment'])); // 댓글 정보
			$master_provider = $this->request_data['master_provider']; // 대표 계정
			$logged_provider_list = unserialize($this->request_data['logged_provider_list']); // 로그인한 서비스 목록
			$reply_provider_list = unserialize($this->request_data['reply_provider_list']); // 리플 서비스 목록
			$client_token = $this->request_data['client']; // 클라이언트 키
			$uselang = $this->request_data['uselang']; // 언어
		}else{
			$comment = $this->request_data['comment']; // 댓글 정보
			$master_provider = $this->request_data['master_provider']; // 대표 계정
			$logged_provider_list = $this->request_data['logged_provider_list']; // 로그인한 서비스 목록
			$reply_provider_list = $this->request_data['reply_provider_list']; // 리플 서비스 목록
			$client_token = $this->request_data['client']; // 클라이언트 키
			$uselang = $this->request_data['uselang']; // 언어
		}

		// 원본 주소를 저장해 둔다.
		$content_link = $comment->content_link;

		// 소셜 서비스 액세스 정보 준비
		foreach($logged_provider_list as $provider){
			if ($this->client_ver < '0.9.1')
				$access[$provider] = unserialize($this->request_data[$provider]);
			else
				$access[$provider] = $this->request_data[$provider];
		}

		// 해당 클라이언트의 사용 도메인인지 확인
		$referer_info = parse_url($comment->content_link);
		$referer_domain = str_replace('www.', '', $referer_info['host']);

		$output = $this->_checkClientDomain($request_info->client_srl, $referer_domain);
		if (!$output->toBool()) return $output;

		// 태그 준비
		if (!$comment->hashtag) $comment->hashtag = 'socialxe';

		// 로그인한 서비스에 댓글을 전송한다.
		unset($output);
		$send_result = array();
		$reply = false;

		// 링크 주소
		if ($comment->short_link){
			$comment->content_link = $comment->short_link;
		}else{
			$bitly = new bitly_SocialXE($this->config->bitly_username, $this->config->bitly_api_key);
			$comment->content_link = $bitly->shorten(urlencode($this->_getCommentUrl($content_link, $comment->parent->comment_srl)));
		}

		// 댓글이면 모두 등록
		if (!$comment->parent){
			foreach($logged_provider_list as $provider){
				$output = $this->providerManager->send($provider, $comment, $access[$provider], $uselang);
				$send_result[$provider] = $output->get('result');
			}
		}

		// 대댓글이면 원래 글의 서비스와 동일한 서비스 로그인 중이면 등록
		else if (in_array($comment->parent->provider, $logged_provider_list)){
			// 근데 페이스북은 제외
			if ($comment->parent->provider != 'facebook'){
				$output = $this->providerManager->send($comment->parent->provider, $comment, $access[$comment->parent->provider], $uselang);
				$send_result[$comment->parent->provider] = $output->get('result');
				$reply = true;
			}
		}

		// 대댓글인데 리플 처리가 안 됐으면 소셜XE 계정을 이용하여 리플을 보낸다.
		if ($comment->parent && !$reply){
			$output = $this->providerManager->send($comment->parent->provider, $comment, $this->access[$comment->parent->provider], $uselang, true);
			$send_result[$comment->parent->provider] = $output->get('result');
		}

		// 리플 리스트 중 보내지 않은 서비스가 있으면 보낸다.
		if (!is_array($send_result)) $send_result = Array();
		$sended_provider_list = array_keys($send_result);

		foreach($reply_provider_list as $provider){
			if (in_array($provider, $sended_provider_list)) continue;

			// 먼저 사용자의 계정으로 등록을 시도
			if (in_array($provider, $logged_provider_list)){
				$output = $this->providerManager->send($provider, $comment, $access[$provider], $uselang);
				$send_result[$comment->parent->provider] = $output->get('result');
			}

			// 사용자 계정이 없으면 소셜 XE 계정으로 시도
			else{
				$output = $this->providerManager->send($provider, $comment, $this->access[$provider], $uselang, true);
				$send_result[$comment->parent->provider] = $output->get('result');
			}
		}

		$result = new Object();
		$result->add('result', $send_result);
		return $result;
	}

	// comment_srl이 붙은 주소를 만든다.
	function _getCommentUrl($content_link, $comment_srl){
		if (!$comment_srl) return $content_link;

		$url_info = parse_url($content_link);
		$url = $url_info[scheme] . '://' . $url_info[host];

		if ($url_info[path])
			$url .= $url_info[path];
		else
			$url .= '/';

		if ($comment_srl){
			if ($url_info[query])
				$url .= '?' . $url_info[query] . '&comment_srl=' . $comment_srl;
			else
				$url .= '?comment_srl=' . $comment_srl;

			if ($url_info['fragment'])
				$url .= '#' . $url_info['fragment'];
			else
				$url .= '#socialxe_comment';
		}else{
			if ($url_info[query])
				$url .= '?' . $url_info[query];
		}
		return $url;
	}

	// 5분이 지난 요청 토큰을 지운다.
	function _clearOldRequest(){
		$args->regdate = date('YmdHis', time() - 5*60);
		$output = executeQuery('socialxeserver.deleteRequestToken', $args);
		if (!$output->toBool()) return $this->stop('error');
		return new Object();
	}

	// 요청 토큰 저장
	function _insertRequest($client_srl){
		$token = md5(uniqid(rand(), TRUE));

		$args->client_srl = $client_srl;
		$args->request_token = $token;
		$output = executeQuery('socialxeserver.insertRequestToken', $args);
		if (!$output->toBool()) return $this->stop('error');

		$result = new Object();
		$result->add('token', $token);
		return $result;
	}

	// 요청 토큰 확인
	function _checkRequest($request_token){
		$args->request_token = $request_token;
		$output = executeQuery('socialxeserver.getRequest', $args);
		if (!$output->toBool()) return $this->stop('error');
		if (!$output->data->client_srl) return $this->stop('msg_invalid_request_token');

		$result = new Object();
		$result->add('request_info', $output->data);
		return $result;
	}

	// 클라이언트 토큰 확인
	function _getClientInfo($client){
		$args->client_token = $client; // 클라이언트 토큰
		$output = executeQuery('socialxeserver.getClient', $args);
		if (!$output->toBool()) return $this->stop('error');
		if (!$output->data->client_srl) return $this->stop('invalid client token');
		if ($output->data->client_token != $args->client_token) return $this->stop('invalid client token');

		$result = new Object();
		$result->add('client_info', $output->data);
		return $result;
	}

	// 클라이언트 확인
	function _getClientInfoBySrl($client_srl){
		$args->client_srl = $client_srl;
		$output = executeQueryArray('socialxeserver.getClient', $args);
		if (!$output->toBool()) return $this->stop('error');
		if (!$output->data) return $this->stop('msg_invalid_request_client');

		$result = new Object();
		$result->add('client_info', $output->data);
		return $result;
	}

	// 클라이언트의 사용 도메인인지 확인
	function _checkClientDomain($client_srl, $domain){
		// 해당 클라이언트 정보 가져오기
		$output = $this->_getClientInfoBySrl($client_srl);
		if (!$output->toBool()) return $output;
		$client_info = $output->get('client_info');

		// 해당 클라이언트의 사용 도메인인지 확인
		$is_valid = false;
		foreach($client_info as $val){
			$domain_array = explode(',', $val->domain);
			foreach($domain_array as $val2){
				if (trim($val2) == $domain){
					$is_valid = true;
				}
			}
		}
		if (!$is_valid) return $this->stop('msg_invalid_request_domain');

		return new Object();
	}

	// 이전 페이지로 돌아간다.
	function _returnPage($verifier){
		// 관리자 화면의 요즘 액세스 얻기 인지 확인한다.
		if ($this->session->getSession('yozmgetaccess')){
			$url = './?module=socialxeserver&act=dispSocialxeserverAdminCallback&verifier=' . $verifier;
			$this->session->clearSession('yozmgetaccess');
		}else{
			$url = $this->session->getSession('callback') . '?module=socialxe&act=dispSocialxeCallback&provider=' . Context::get('provider') . '&verifier=' . $verifier;
			$this->session->clearSession('callback');
		}

		header('Location: ' . $url);
		Context::close();
		exit;
	}

	// 요청 데이터 처리
	function decodeData(){
		$this->request_data = unserialize(urldecode(base64_decode(Context::get('data'))));

		if (is_array($this->request_data)){
			foreach($this->request_data as $name => $val){
				if (($tmp = @unserialize($val)) !== false)
					$this->request_data[$name] = $tmp;
			}
		}

		// 클라이언트 버전
		$this->client_ver = Context::get('ver');
	}

	// 클라이언트 0.9버전 호환을 위한 함수
	function getRequestData($name_list){
		if (!is_array($name_list)) return;

		foreach($name_list as $name){
			if (get_magic_quotes_gpc())
				$this->request_data[$name] = stripslashes(Context::get($name));
			else
				$this->request_data[$name] = Context::get($name);
		}
	}

	function stop($msg){
		$result = new Object();
		$result->setError(-1);
		$result->setMessage($msg);
		return $result;
	}
}

?>
