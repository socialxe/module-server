<?php

if (!class_exists("Services_JSON_SocialXE")){
	require_once (_XE_PATH_.'modules/socialxeserver/JSON.php');
}


class me2day{

	var $api_url = 'http://me2day.net/api/'; // 미투데이 api 경로

	var $useragent = 'SocialXE Communicator';

	function me2day($api_key){
		$this->api_key = $api_key;
	}

	// 인증토큰 주소 요청
	function getAuthUrl(){
		$url = $this->api_url . 'get_auth_url.json?akey=' . $this->api_key;

		$headers = array('User-Agent' => $this->useragent);
		$content = $this->getRemoteResource($url, null, 30, 'GET', null, $headers);

		// XML 파싱
		$json = new Services_JSON_SocialXE();
		$output = $json->decode($content);

		return $output;
	}

	// 사용자 정보
	function getPerson($id){
		$url = $this->api_url . 'get_person/' . $id . '.json';

		$headers = array('User-Agent' => $this->useragent);
		$content = $this->getRemoteResource($url, null, 30, 'GET', null, $headers);

		// XML 파싱
		$json = new Services_JSON_SocialXE();
		$output = $json->decode($content);

		return $output;
	}

	// 글 등록
	function createPost($post, $tag, $id, $user_key){
		$url = $this->api_url . 'create_post/' . $id . '.json';

		$post_data['post[body]'] = $post;
		$post_data['post[tags]'] = $tag;
		$post_data['akey'] = $this->api_key;
		$post_data['ukey'] = $this->createUserKey($user_key);
		$post_data['uid'] = $id;

		foreach($post_data as $name => $val){
			$body .= '&' . $name . '=' . urlencode($val);
		}
		$body = substr($body, 1);

		$headers = array('User-Agent' => $this->useragent);
		$content = $this->getRemoteResource($url, $body, 30, 'POST', 'application/x-www-form-urlencoded', $headers);

		// JSON 디코딩
		$json = new Services_JSON_SocialXE();
		$output = $json->decode($content);

		if ($output){
			return $output;
		}else{
			$result->error = $content;
			return $result;
		}

	}

	// 댓글 등록
	function createComment($comment, $post_id, $id, $user_key){
		$url = $this->api_url . 'create_comment.json';

		$post_data['post_id'] = $post_id;
		$post_data['body'] = $comment;
		$post_data['akey'] = $this->api_key;
		$post_data['ukey'] = $this->createUserKey($user_key);
		$post_data['uid'] = $id;

		foreach($post_data as $name => $val){
			$body .= '&' . $name . '=' . urlencode($val);
		}
		$body = substr($body, 1);

		$headers = array('User-Agent' => $this->useragent);
		$content = $this->getRemoteResource($url, $body, 3, 'POST', 'application/x-www-form-urlencoded', $headers);

		// JSON 디코딩
		$json = new Services_JSON_SocialXE();
		$output = $json->decode($content);

		if ($output){
			return $output;
		}else{
			$result->error = $content;
			return $result;
		}

	}

	// 유저키 생성
	function createUserKey($user_key){
		$rand_str = substr(uniqid(), 0, 8);
		return $rand_str . md5($rand_str . $user_key);
	}

	function getRemoteResource($url, $body = null, $timeout = 3, $method = 'GET', $content_type = null, $headers = array(), $cookies = array(), $post_data = array()) {
		requirePear();
		require_once('HTTP/Request.php');

		$parsed_url = parse_url(__PROXY_SERVER__);
		if($parsed_url["host"]) {
			$oRequest = new HTTP_Request(__PROXY_SERVER__);
			$oRequest->setMethod('POST');
			$oRequest->_timeout = $timeout;
			$oRequest->addPostData('arg', serialize(array('Destination'=>$url, 'method'=>$method, 'body'=>$body, 'content_type'=>$content_type, "headers"=>$headers, "post_data"=>$post_data)));
		} else {
			$oRequest = new HTTP_Request($url);
			if(count($headers)) {
				foreach($headers as $key => $val) {
					$oRequest->addHeader($key, $val);
				}
			}
			if($cookies[$host]) {
				foreach($cookies[$host] as $key => $val) {
					$oRequest->addCookie($key, $val);
				}
			}
			if(count($post_data)) {
				foreach($post_data as $key => $val) {
					$oRequest->addPostData($key, $val);
				}
			}
			if(!$content_type) $oRequest->addHeader('Content-Type', 'text/html');
			else $oRequest->addHeader('Content-Type', $content_type);
			$oRequest->setMethod($method);
			if($body) $oRequest->setBody($body);

			$oRequest->_timeout = $timeout;
		}

		$oResponse = $oRequest->sendRequest();

		$code = $oRequest->getResponseCode();
		$header = $oRequest->getResponseHeader();
		$response = $oRequest->getResponseBody();
		if($c = $oRequest->getResponseCookies()) {
			foreach($c as $k => $v) {
				$cookies[$host][$v['name']] = $v['value'];
			}
		}

		if($code > 300 && $code < 399 && $header['location']) {
			return FileHandler::getRemoteResource($header['location'], $body, $timeout, $method, $content_type, $headers, $cookies, $post_data);
		}

		return $response;
	}
}

?>