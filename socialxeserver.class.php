<?php

	class socialxeserver extends ModuleObject {

		var $add_triggers = array(
			array('member.deleteMember', 'socialxeserver', 'controller', 'triggerDeleteMember', 'after')
		);

		function socialxeserver(){
			// 지원 환경 체크
			if (!$this->isSupported()) return;

			require_once(_XE_PATH_.'modules/socialxeserver/sessionManager.php');
			require_once(_XE_PATH_.'modules/socialxeserver/communicator.php');
			require_once(_XE_PATH_.'modules/socialxeserver/providerManager.php');
			require_once(_XE_PATH_.'modules/socialxeserver/provider.class.php');
			require_once(_XE_PATH_.'modules/socialxeserver/provider.twitter.php');
			require_once(_XE_PATH_.'modules/socialxeserver/provider.me2day.php');
			require_once(_XE_PATH_.'modules/socialxeserver/provider.facebook.php');
			require_once(_XE_PATH_.'modules/socialxeserver/provider.yozm.php');

			// 설정 정보를 받아옴 (module model 객체를 이용)
			$oModuleModel = &getModel('module');
			$this->config = $oModuleModel->getModuleConfig('socialxeserver');

			$this->session = &socialxeServerSessionManager::getInstance();
			$this->communicator = &socialxeServerCommunicator::getInstance($this->session, $this->config);
		}

		/**
		* @brief 설치시 추가 작업이 필요할시 구현
		**/
		function moduleInstall() {
			if (!$this->isSupported()) return new Object();

			$oModuleController = &getController('module');

			// $this->add_triggers 트리거 일괄 추가
			foreach($this->add_triggers as $trigger) {
				$oModuleController->insertTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
			}

			return new Object();
		}

		/**
		* @brief 설치가 이상이 없는지 체크하는 method
		**/
		function checkUpdate() {
			if (!$this->isSupported()) return false;

			$oModuleModel = &getModel('module');

			// $this->add_triggers 트리거 일괄 검사
			foreach($this->add_triggers as $trigger) {
				if(!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4])) return true;
			}

			return false;
		}

		/**
		* @brief 업데이트 실행
		**/
		function moduleUpdate() {
			if (!$this->isSupported()) return new Object();

			$oModuleModel = &getModel('module');
			$oModuleController = &getController('module');

			// $this->add_triggers 트리거 일괄 업데이트
			foreach($this->add_triggers as $trigger) {
				if(!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4])) {
					$oModuleController->insertTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
				}
			}

			return new Object(0, 'success_updated');
		}

		/**
		* @brief 캐시 파일 재생성
		**/
		function recompileCache() {
		}

		// 모듈 제거
		function moduleUninstall(){
			$oModuleController = &getController('module');

			foreach($this->add_triggers as $trigger) {
				$oModuleController->deleteTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
			}

			// 서비스 모듈 정보를 얻는다.
			$oSocialxeserverModel = &getModel('socialxeserver');
			$service_module_info = $oSocialxeserverModel->getServiceModuleInfo();
			if (!$service_module_info) return new Object();

			return $oModuleController->deleteModule($service_module_info->module_srl);
		}

		function isSupported(){
			if (version_compare(PHP_VERSION, '5.0.0', '<')) return false;
			if (!function_exists('curl_init')) return false;
			if (!function_exists('json_decode')) return false;
			return true;
		}

		function getNotEncodedFullUrl() {
			$num_args = func_num_args();
			$args_list = func_get_args();
			$request_uri = Context::getRequestUri();
			if(!$num_args) return $request_uri;

			$url = Context::getUrl($num_args, $args_list, null, false);
			if(!preg_match('/^http/i',$url)){
				preg_match('/^(http|https):\/\/([^\/]+)\//',$request_uri,$match);
				$url = Context::getUrl($num_args, $args_list, null, false);
				return substr($match[0],0,-1).$url;
			}
			return $url;
		}
	}
?>
