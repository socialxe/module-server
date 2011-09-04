<?php

	class socialxeserverView extends ModuleObject {

		/**
		* @brief 초기화
		**/
		function init() {
			// template path 지정
			if (!$this->module_info->skin) $this->module_info->skin = 'default';
			$tpl_path = sprintf('%sskins/%s', $this->module_path, $this->module_info->skin);
			if(!is_dir($tpl_path)) $tpl_path = sprintf('%sskins/%s', $this->module_path, 'default');
			$this->setTemplatePath($tpl_path);

			// 서비스 모듈 정보
			$oSocialxeserverModel = &getModel('socialxeserver');
			Context::set('service_module_info', $oSocialxeserverModel->getServiceModuleInfo());
		}

		// 클라이언트 목록
		function dispSocialxeserverClientList(){
			$logged_info = Context::get('logged_info');
			if (!$logged_info->member_srl) return $this->stop('msg_not_permitted');


			// 클라이언트 목록
			$args->member_srl = $logged_info->member_srl;
			$args->page = Context::get('page');
			$args->domain = Context::get('domain');
			$output = executeQuery('socialxeserver.getClientList', $args);
			if (!$output->toBool()) return $output;

			// 템플릿에 쓰기 위해서 comment_model::getTotalCommentList() 의 return object에 있는 값들을 세팅
			Context::set('total_count', $output->total_count);
			Context::set('total_page', $output->total_page);
			Context::set('page', $output->page);
			Context::set('client_list', $output->data);
			Context::set('page_navigation', $output->page_navigation);

			// 목록이 없으면 초기 등록 화면을
			if (!$output->data && !Context::get('domain'))
				$this->setTemplateFile('empty');
			else
				$this->setTemplateFile('client_list');
		}

		// 클라이언트 등록
		function dispSocialxeserverInsertClient(){
			if(!$this->grant->register) return $this->stop('msg_not_permitted');

			$this->setTemplateFile('insert_client');
		}

		// 클라이언트 수정
		function dispSocialxeserverModifyClient(){
			if(!$this->grant->register) return $this->stop('msg_not_permitted');

			$client_srl = Context::get('client_srl');
			if (!$client_srl) return $this->stop('msg_invalid_request');

			// 클라이언트 정보 얻기
			$args->client_srl = $client_srl;
			$output = executeQuery('socialxeserver.getClient', $args);
			if (!$output->toBool()) return $output;
			if (!$output->data) return $this->stop('msg_invalid_request');

			// 본인의 클라이언트인지 다시 한번 더 확인
			$logged_info = Context::get('logged_info');
			if ($output->data->member_srl != $logged_info->member_srl) return $this->stop('msg_not_permitted');

			// 정보 가공
			$client_info = $output->data;
			$domain_array = explode(',', $client_info->domain);
			foreach($domain_array as $name => $val){
				$domain_array[$name] = trim($val);
			}

			// 템플릿에 사용하기 위해 셋
			Context::set('client_info', $client_info);
			Context::set('domain_list', $domain_array);

			$this->setTemplateFile('modify_client');
		}

	}
?>
