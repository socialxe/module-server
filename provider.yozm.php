<?php

// 요즘 라이브러리 로드
require_once(_XE_PATH_.'modules/socialxeserver/yozm/yozm.php');

// 요즘을 위한 클래스
class socialxeServerProviderYozm extends socialxeServerProvider{

	// 인스턴스
	function getInstance(&$sessionManager, $consumer_key, $consumer_secret){
		static $instance;
		if (!isset($instance)) $instance = new socialxeServerProviderYozm($sessionManager, $consumer_key, $consumer_secret);
		return $instance;
	}

	// 생성자
	function socialxeServerProviderYozm(&$sessionManager, $consumer_key, $consumer_secret){
		parent::socialxeServerProvider('yozm', $sessionManager);
		$this->consumer_key = $consumer_key;
		$this->consumer_secret = $consumer_secret;
		$this->oauth_callback = $this->getNotEncodedFullUrl('', 'module', 'socialxeserver', 'act', 'procSocialxeserverCallback', 'provider', 'yozm');
	}

	// 로그인 url을 얻는다.
	function getLoginUrl(){
		// 요즘 객체 생성
		$connection = new yozm($this->consumer_key, $this->consumer_secret);

		// 요청 토큰을 생성
		$request_token = $connection->getRequestToken($this->oauth_callback);

		// 요청 토큰을 세션에 저장한다.
		$session['oauth_token'] = $token = $request_token['oauth_token'];
		$session['oauth_token_secret'] = $request_token['oauth_token_secret'];
		$this->session->setSession($this->name, $session);


		// 요청 실패 확인
		if ($connection->http_code == 200){
			$url = $connection->getAuthorizeURL($token);

			$result = new Object();
			$result->add('url', $url);

			return $result;
		}else{
			return $this->stop($request_token['oauth_problem']);
		}
	}

	// 콜백 처리
	function callback(){
		$oauth_token = Context::get('oauth_token');
		$oauth_verifier = Context::get('oauth_verifier');

		// 세션
		$session = $this->session->getSession($this->name);

		// 이전 토큰과 일치하는지 확인
		if ($oauth_token != $session['oauth_token']){
			// 일치하지 않으면 세션 말소
			$this->session->clearSession($this->name);
			return $this->stop('msg_old_token');
		}

		// 요즘 객체 생성
		$connection = new yozm($this->consumer_key, $this->consumer_secret, $session['oauth_token'], $session['oauth_token_secret']);

		// 액세스 토큰 요청
		$access_token = $connection->getAccessToken($oauth_verifier);

		// 요청 토큰은 이제 필요없다.
		$this->session->clearSession($this->name);

		// 요청 성공 체크
		if ($connection->http_code == 200){
			// 요즘 사용자인지 확인한다.
			$joined = $connection->get('user/joined');
			if (!$joined->joined){
				return $this->stop('msg_error_yozm_not_joined');
			}

			// 사용자 정보도 받아서 저장해 놓는다.
			$credentials = $connection->get('user/show');

			// 액세스 토큰과 사용자 정보를 묶는다.
			$info['access'] = $access_token;
			$info['account'] = $credentials;

			$result = new Object();
			$result->add('info', $info);
			return $result;
		}else{
			return $this->stop('msg_error_yozm');
		}
	}

	// 댓글 전송
	function send($comment, $access, $uselang = 'en', $use_socialxe = false){
		$result = new Object();

		// 머리글
		$lang->comment = $this->lang->comment[$uselang];
		if (!$lang->comment) $lang->comment = $this->lang->comment['en'];
		$lang->notify = $this->lang->notify[$uselang];
		if (!$lang->notify) $lang->notify = $this->lang->notify['en'];

		// 요즘 객체 생성
		$connection = new yozm($this->consumer_key, $this->consumer_secret, $access->oauth_token, $access->oauth_token_secret);

		// 내용 준비
		$content = ' ' . $comment->content_link;

		// 얼마만큼의 길이를 사용할 수 있는지 확인
		$max_length = 150 - $this->strlen($content);

		// 실제 내용을 준비
		if ($comment->content_title){
			$title = $comment->content_title;
		}else if ($use_socialxe){
			$title = $lang->notify;
		}else{
			$title = $lang->comment;
		}
		$content2 = '「' . $title . '」 ' . $comment->content;

		// 내용 길이가 최대 길이를 넘는지 확인
		$content = urlencode($this->cut_str($content2, $max_length-3, '...') . $content);

		// 댓글 전송

		// 부모 댓글이 요즘이면 반응글 처리
		if ($comment->parent && $comment->parent->provider == $this->name){
			$output = $connection->post('message/add', array('parent_msg_id' => $comment->parent->comment_id, 'message' => $content));
		}else{
			$output = $connection->post('message/add', array('message' => $content));
			$output->id = $output->message->msg_id;
		}

		if ($output->status != 200){
			$output->error = $output->result_msg;
		}

		$result->add('result', $output);
		return $result;
	}
}

?>