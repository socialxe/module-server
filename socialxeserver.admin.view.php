<?php

	class socialxeserverAdminView extends socialxeserver {

		/**
		* @brief 초기화
		**/
		function init() {
			if (!$this->isSupported()) return $this->stop('msg_check_support');

			// 서비스 모듈 정보를 얻는다.
			$oSocialxeserverModel = &getModel('socialxeserver');
			$this->service_module_info = $oSocialxeserverModel->getServiceModuleInfo();
			Context::set('service_module_info', $this->service_module_info);
		}

		/**
		* @brief 설정
		**/
		function dispSocialxeserverAdminConfig() {
			// 설정 정보를 받아옴 (module model 객체를 이용)
			$oModuleModel = &getModel('module');
			$config = $oModuleModel->getModuleConfig('socialxeserver');
			Context::set('config',$config);

			// 템플릿 파일 지정
			$this->setTemplatePath($this->module_path.'tpl');
			$this->setTemplateFile('index');
		}

		// 클라이언트 목록
		function dispSocialxeserverAdminClient(){
			// 클라이언트 목록
			$args->page = Context::get('page');
			$args->domain = Context::get('domain');
			$output = executeQuery('socialxeserver.getAdminClientList', $args);
			if (!$output->toBool()) return $output;

			// 템플릿에 쓰기 위해서 comment_model::getTotalCommentList() 의 return object에 있는 값들을 세팅
			Context::set('total_count', $output->total_count);
			Context::set('total_page', $output->total_page);
			Context::set('page', $output->page);
			Context::set('client_list', $output->data);
			Context::set('page_navigation', $output->page_navigation);

			// 템플릿 파일 지정
			$this->setTemplatePath($this->module_path.'tpl');
			$this->setTemplateFile('client');
		}

		// 클라이언트 추가
		function dispSocialxeserverAdminInsertClient(){
			// 템플릿 파일 지정
			$this->setTemplatePath($this->module_path.'tpl');
			$this->setTemplateFile('insert_client');
		}

		// 클라이언트 수정
		function dispSocialxeserverAdminModifyClient(){
			$client_srl = Context::get('client_srl');
			if (!$client_srl) return $this->stop('msg_invalid_request');

			// 클라이언트 정보 얻기
			$args->client_srl = $client_srl;
			$output = executeQuery('socialxeserver.getClient', $args);
			if (!$output->toBool()) return $output;
			if (!$output->data) return $this->stop('msg_invalid_request');

			// 정보 가공
			$client_info = $output->data;
			$domain_array = explode(',', $client_info->domain);
			foreach($domain_array as $name => $val){
				$domain_array[$name] = trim($val);
			}

			// 템플릿에 사용하기 위해 셋
			Context::set('client_info', $client_info);
			Context::set('domain_list', $domain_array);

			// 템플릿 파일 지정
			$this->setTemplatePath($this->module_path.'tpl');
			$this->setTemplateFile('modify_client');
		}

		// 서비스 모듈 설정
		function dispSocialxeserverAdminServiceConfig(){
			// 서비스 모듈이 생성되지 않았으면 생성 화면으로
			$this->setTemplatePath($this->module_path.'tpl');
			if (!$this->service_module_info){
				$this->setTemplateFile('insert_service_module');
			}

			// 서비스 모듈이 설정되어 있으면 설정 화면으로
			else{
				$oModuleModel = &getModel('module');
				$skin_list = $oModuleModel->getSkins($this->module_path);
				Context::set('skin_list',$skin_list);

				$mskin_list = $oModuleModel->getSkins($this->module_path, "m.skins");
				Context::set('mskin_list', $mskin_list);

				$oLayoutModel = &getModel('layout');
				$layout_list = $oLayoutModel->getLayoutList();
				Context::set('layout_list', $layout_list);

				$mobile_layout_list = $oLayoutModel->getLayoutList(0,"M");
				Context::set('mlayout_list', $mobile_layout_list);

				$this->setTemplateFile('service_config');
			}
		}

		// 권한 설정
		function dispSocialxeserverAdminServiceGrant(){
			// 공통 모듈 권한 설정 페이지 호출
			$oModuleAdminModel = &getAdminModel('module');
			$grant_content = $oModuleAdminModel->getModuleGrantHTML($this->service_module_info->module_srl, $this->xml_info->grant);
			Context::set('grant_content', $grant_content);

			$this->setTemplatePath($this->module_path.'tpl');
			$this->setTemplateFile('grant_list');
		}

		// 요즘 액세스 토큰 얻기
		function dispSocialxeserverAdminGetYozmAccessToken(){
			// 세션 세팅
			$this->session->setSession('yozmgetaccess', true);

			// 로그인 URL을 얻는다.
			unset($output);
			$output = $this->communicator->providerManager->getLoginUrl('yozm');
			if (!$output->toBool()) return $output;
			$url = $output->get('url');

			// 리다이렉트
			header('Location: ' . $url);
			Context::close();
			exit;
		}

		// 콜백
		function dispSocialxeserverAdminCallback(){
			$output = $this->communicator->access();
			Context::set('access_token', $output->get('access_token'));

			// 템플릿 파일 지정
			$this->setTemplatePath($this->module_path.'tpl');
			$this->setTemplateFile('yozmgetaccess');

			// HTML 형식
			Context::setRequestMethod('HTML');
		}
	}
?>
