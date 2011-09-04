<?php

	class socialxeserverAdminController extends ModuleObject {

		/**
		* @brief 초기화
		**/
		function init() {
			// 서비스 모듈 정보를 얻는다.
			$oSocialxeserverModel = &getModel('socialxeserver');
			$this->service_module_info = $oSocialxeserverModel->getServiceModuleInfo();
			Context::set('service_module_info', $this->service_module_info);
		}

		/**
		* @brief 설정
		**/
		function procSocialxeserverAdminInsertConfig() {
			// 기본 정보를 받음
			$args = Context::getRequestVars();

			// module Controller 객체 생성하여 입력
			$oModuleController = &getController('module');
			$output = $oModuleController->insertModuleConfig('socialxeserver',$args);
			return $output;
		}

		// 클라이언트 추가/수정
		function procSocialxeserverAdminInsertClient(){
			$oSocialxeserverController = &getController('socialxeserver');

			return $oSocialxeserverController->_insertClient();
		}

		// 클라이언트 선택 삭제
		function procSocialxeserverAdminDeleteCheckedClient(){
			// 선택된 글이 없으면 오류 표시
			$cart = Context::get('cart');
			if(!$cart) return $this->stop('msg_invalid_request');
			$client_srl_list= explode('|@|', $cart);
			$client_count = count($client_srl_list);
			if(!$client_count) return $this->stop('msg_invalid_request');

			$args->client_srls = implode(',', $client_srl_list);
			return executeQuery('socialxeserver.deleteClient', $args);
		}

		// 서비스 모듈 생성
		function procSocialxeserverAdminInsertServiceModule(){
			if($this->service_module_info->module_srl) return new Object(-1,'msg_invalid_request');

			$oModuleController = &getController('module');

			$args->module = 'socialxeserver';
			$args->mid = Context::get('service_id');
			$args->site_srl = 0;
			$args->skin = 'default';
			$args->browser_title = 'SocialXE Server';
			$output = $oModuleController->insertModule($args);
			if(!$output->toBool()) return $output;

			$this->setRedirectUrl(getUrl('','module','admin','act','dispSocialxeserverAdminServiceConfig'));
		}

		// 서비스 모듈 설정 업데이트
		function procSocialxeserverAdminUpdateServiceModule(){
			if(!$this->service_module_info->module_srl) return new Object(-1,'msg_invalid_request');

			// module 모듈의 model/controller 객체 생성
			$oModuleController = &getController('module');
			$oModuleModel = &getModel('module');

			// 모듈의 정보 설정
			$args = Context::getRequestVars();
			$args->module = 'socialxeserver';
			$args->mid = $args->service_id;
			unset($args->service_id);

			$args->module_srl = $this->service_module_info->module_srl;
			$output = $oModuleController->updateModule($args);
			if(!$output->toBool()) return $output;
		}

	}
?>
