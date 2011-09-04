<?php

	class socialxeserverModel extends ModuleObject {

		/**
		* @brief 초기화
		**/
		function init() {
		}

		// 환경설정
		function getConfig(){
			// 설정 정보를 받아옴 (module model 객체를 이용)
			$oModuleModel = &getModel('module');
			return $config = $oModuleModel->getModuleConfig('socialxeserver');
		}

		// 이미 존재하는 도메인인지 확인
		function isExsistDomain($domain, $client_srl = null){
			$result = new Object();
			$domain = str_replace(array('http://', 'www.'), '', $domain);

			// DB에서 검색
			$args->domain = $domain;
			$args->client_srl = $client_srl;
			$output = executeQueryArray('socialxeserver.getClientByDomain', $args);
			if (!$output->toBool()) return $output;

			// 검색 결과가 없으면 false
			if (!$output->data){
				$result->add('result', false);
				return $result;
			}

			// DB 검색 결과에서 도메인을 하나씩 체크
			foreach($output->data as $client){
				$domain_array = explode(',', $client->domain);
				foreach($domain_array as $val){
					// 정확히 일치하는지를 검사
					if (trim($val) == $domain){
						$result->add('result', true);
						return $result;
					}
				}
			}

			// 일치하는 도메인이 없으면 false
			$result->add('result', false);
			return $result;
		}

		// 서비스 모듈 정보
		function getServiceModuleInfo(){
			$oModuleModel = &getModel('module');

			$output = executeQuery('socialxeserver.getServiceModule');
			if(!$output->data->module_srl) return;

			$module_info = $oModuleModel->getModuleInfoByModuleSrl($output->data->module_srl);
			return $module_info;
		}
	}
?>
