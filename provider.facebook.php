<?php

// 페이스북 라이브러리 로드
require_once(_XE_PATH_.'modules/socialxeserver/facebook/facebook_socialxe.php');

// 페이스북을 위한 클래스
class socialxeServerProviderFacebook extends socialxeServerProvider{

	// 인스턴스
	function getInstance(&$sessionManager, $app_id, $app_secret){
		static $instance;
		if (!isset($instance)) $instance = new socialxeServerProviderFacebook($sessionManager, $app_id, $app_secret);
		return $instance;
	}

	// 생성자
	function socialxeServerProviderFacebook(&$sessionManager, $app_id, $app_secret){
		parent::socialxeServerProvider('facebook', $sessionManager);
		$this->app_id = $app_id;
		$this->app_secret = $app_secret;
		$this->callback = $this->getNotEncodedFullUrl('', 'module', 'socialxeserver', 'act', 'procSocialxeserverCallback', 'provider', 'facebook');
	}

	// 로그인 url을 얻는다.
	function getLoginUrl(){
		// 페이스북 객체 생성
		$fb = new FacebookSocialXE(array(
			"appId" => $this->app_id,
			"secret" => $this->app_secret,
			"cookie" => false
		));

		$display = 'popup';
		if(preg_match('/(iPod|iPhone|Android|BlackBerry|SymbianOS|SCH\-M[0-9]+)/',$_SERVER['HTTP_USER_AGENT']))
		{
			$display = 'touch';
		}

		// URL 생성
		try{
			$loginUrl = $fb->getLoginUrl(array(
				"scope" => "publish_stream,offline_access,email",
				"display" => $display,
				"redirect_uri" => $this->callback
			));
		}catch(FacebookApiException $e){
			return new Object(-1, $e->__toString());
		}

		$result = new Object();
		$result->add('url', $loginUrl);

		return $result;
	}

	// 콜백 처리
	function callback(){
		// 페이스북 객체 생성
		$fb = new FacebookSocialXE(array(
			"appId" => $this->app_id,
			"secret" => $this->app_secret,
			"cookie" => false
		));

		try{
			$session = $fb->getSession($this->callback);
		}catch(FacebookApiException $e){
			return new Object(-1, $e->__toString());
		}

		// 로그인 취소했으면 이전 페이지로 돌아간다.
		if (!$session){
			$this->session->clearSession('facebook');
			return new Object();
		}

		// 사용자 정보도 받아서 저장해 놓는다.
		$account = $fb->api('me');

		// 액세스 토큰과 사용자 정보를 묶는다.
		$info['access'] = $session;
		$info['account'] = $account;

		$result = new Object();
		$result->add('info', $info);
		return $result;
	}

	// 댓글 전송
	function send($comment, $access, $uselang = 'en', $use_socialxe = false){
		$result = new Object();

		// 머리글
		$lang->comment = $this->lang->comment[$uselang];
		if (!$lang->comment) $lang->comment = $this->lang->comment['en'];
		$lang->notify = $this->lang->notify[$uselang];
		if (!$lang->notify) $lang->notify = $this->lang->notify['en'];

		// 페이스북 객체 생성
		$fb = new FacebookSocialXE(array(
			"appId" => $this->app_id,
			"secret" => $this->app_secret,
			"cookie" => false
		));

		// 세션 세팅
		if (is_object($access)){
			$fb->setSession((array)$access, false);
		}

		// 얼마만큼의 길이를 사용할 수 있는지 확인
		$max_length = 420;

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
		$content = $this->cut_str($content2, $max_length-3, '...');

		// 썸네일이 제공되면 그것을 사용
		if ($comment->content_thumbnail){
			$image = $comment->content_thumbnail;
		}

		// 썸네일 없으면 1x1 투명 gif 파일...
		else{
			$image = Context::getRequestUri() . 'modules/socialxeserver/tpl/images/blank.gif';
		}

		// 부모 댓글이 페이스북이면 메일로 댓글 알림
		if ($comment->parent && $comment->parent->provider == 'facebook'){
			$reply_id = $comment->parent->comment_id;

			try{
				$output = $fb->api(array('method' => 'notifications.sendEmail', 'recipients' => $comment->parent->id, 'subject' => $title, 'text' => $content . ' ' . $comment->content_link));
			}catch(FacebookApiException $e){
				$output->error = $e->__toString();
			}

		}

		// 댓글 전송
		else{
			try{
				$output = $fb->api($fb->getUser() . '/feed', 'POST', array('message' => $content, 'link' => $comment->content_link, 'picture' => $image));
			}catch(FacebookApiException $e){
				$output->error = $e->__toString();
			}
		}

		$result->add('result', $output);
		return $result;
	}
}

?>