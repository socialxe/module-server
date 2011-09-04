<?php

// 미투데이 라이브러리 로드
require_once(_XE_PATH_.'modules/socialxeserver/me2day/me2day.php');

// 미투데이를 위한 클래스
class socialxeServerProviderMe2day extends socialxeServerProvider{

// 인스턴스
function getInstance(&$sessionManager, $application_key){
	static $instance;
	if (!isset($instance)) $instance = new socialxeServerProviderMe2day($sessionManager, $application_key);
	return $instance;
}

// 생성자
function socialxeServerProviderMe2day(&$sessionManager, $application_key){
	parent::socialxeServerProvider('me2day', $sessionManager);
	$this->application_key = $application_key;
}

// 로그인 url을 얻는다.
function getLoginUrl(){
	// 미투데이 객체 생성
	$me2day = new me2day($this->application_key);

	// 요청 토큰을 생성
	$request_token = $me2day->getAuthUrl();

	// 요청 성공 체크
	if (!$request_token) return $this->stop('msg_notconnect_me2day');
	if ($request_token->code) return $this->stop(sprintf(Context::getLang('msg_error_me2day'), $request_token->description));
	if (!$request_token->token) return $this->stop(sprintf(Context::getLang('msg_error_me2day'), Context::getLang('msg_error_token')));

	// 요청 토큰을 세션에 저장한다.
	$session['token'] = $request_token->token;
	$this->session->setSession('me2day', $session);

	$url = $request_token->url;

	$result = new Object();
	$result->add('url', $url);
	return $result;
}

// 콜백 처리
function callback(){
	$token = Context::get('token');
	$user_id = Context::get('user_id');
	$user_key = Context::get('user_key');
	$result = Context::get('result');

	// 로그인 취소했으면 이전 페이지로 돌아간다.
	if ($result == "false"){
	$this->session->clearSession('me2day');
	return new Object();
	}

	// 이전 토큰과 일치하는지 확인
	$session = $this->session->getSession('me2day');
	if ($token != $session['token']){
	// 일치하지 않으면 세션 말소
	$this->session->clearSession('me2day');
	return $this->stop('msg_old_token');
	}

	// 미투데이 객체 생성
	$me2day = new me2day($this->application_key);

	// 사용자 정보를 받아서 저장해 놓는다.
	$person = $me2day->getPerson($user_id);

	// 요청 성공 체크
	if (!$person) return $this->stop('msg_notconnect_me2day');
	if ($person->code) return $this->stop(sprintf(Context::getLang('msg_error_me2day'), $person->description));
	if (!$person->id) return $this->stop('msg_notconnect_me2day');

	// 요청 토큰은 이제 필요없다.
	$this->session->clearSession('me2day');

	// 사용자 키와 사용자 정보를 묶는다.
	$info['account'] = $person;
	$info['access']['user_key'] = $user_key;
	$info['access']['user_id'] = $user_id;

	$result = new Object();
	$result->add('info', $info);
	return $result;
}

// 댓글 전송
function send($comment, $access, $uselang = 'en', $use_socialxe = false){
	$result = new Object();
	$max_length = 150;

	// 머리글
	$lang->comment = $this->lang->comment[$uselang];
	if (!$lang->comment) $lang->comment = $this->lang->comment['en'];
	$lang->notify = $this->lang->notify[$uselang];
	if (!$lang->notify) $lang->notify = $this->lang->notify['en'];

	// 태그 준비. 태그는 그냥 150자 넘으면 자른다
	$tag = $this->cut_str($comment->hashtag, $max_length-3, '...');

	// 내용 준비
		$before = array('"');
		$after = array('\"');
		$comment->content_title = str_replace($before, $after, $comment->content_title);
		$comment->content_title = preg_replace("/\\\\$/", '\\ ', $comment->content_title);
	if ($comment->content_title){
	$title = $comment->content_title;
	}else if ($use_socialxe){
	$title = $lang->notify;
	}else{
	$title = $lang->comment;
	}
	$content = $title . '」 ' . $comment->content;

	// 150자 체크
		$content = $this->cut_str($content, $max_length-3, '...');

	// URL 삽입
	$temp = explode('」', $content);
	if (count($temp) > 1){
	$temp[0] = '「"' . $this->cut_str($temp[0], 149, '') . '":' . $comment->content_link . ' ';
	$content = implode('」', $temp);
	}

	// 부모 댓글이 미투데이면 댓글 처리
	if ($comment->parent && $comment->parent->provider == 'me2day'){
	$output = $this->_sendComment($content, $comment->parent->comment_id, $access);
	}else{
	$output = $this->_sendPost($content, $tag, $access);
	}

	if ($output->code){
	$output->error = $output->description;
	}

	$result->add('result', $output);
	return $result;
}

// 포스트 등록
function _sendPost($content, $tag, $access){
	// 미투데이 객체 생성
	$me2day = new me2day($this->application_key);

	// 포스트 등록
	$output = $me2day->createPost($content, $tag, $access->user_id, $access->user_key);

	// 포스트 아이디 세팅
	$output->id = $output->post_id;

	return $output;
}

// 댓글 등록
function _sendComment($content, $post_id, $access){
	// 미투데이 객체 생성
	$me2day = new me2day($this->application_key);

	// 포스트 등록
	$output = $me2day->createComment($content, $post_id, $access->user_id, $access->user_key);

	return $output;
}
}

?>