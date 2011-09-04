<?php

	require_once(_XE_PATH_.'modules/socialxeserver/socialxeserver.view.php');

	class socialxeserverMobile extends socialxeserverView {

		function init(){
			// template path 지정
			if (!$this->module_info->mskin) $this->module_info->mskin = 'default';
			$tpl_path = sprintf('%sm.skins/%s', $this->module_path, $this->module_info->mskin);
			if(!is_dir($tpl_path)) $tpl_path = sprintf('%sm.skins/%s', $this->module_path, 'default');
			$this->setTemplatePath($tpl_path);
		}
	}
?>
