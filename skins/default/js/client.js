// 클라이언트 추가 후
function completeInsertClient(){
	location.href = current_url.setQuery('act','dispSocialxeserverClientList').setQuery('client_srl','');
}

// 도메인 추가 버튼 엔터
jQuery(document).ready(function(){
	jQuery("#insert_domain").bind('keypress', function(e){
		if (e.keyCode != 13) return true;

		doInsertDomain();
		return false;
	});
});

// 도메인 추가
function doInsertDomain() {
	var fo_obj = xGetElementById("fo_obj");
	var sel_obj = fo_obj._domain_list;
	var domain = fo_obj._domain.value;
	if(!domain) return;

	fo_obj._domain.value = '';
	var domain_array = domain.split(',');
	for(var i = 0; i < domain_array.length; i++){
		domain = jQuery.trim(domain_array[i]);

		var flag = false;
		for(var j=0;j<sel_obj.options.length;j++) {
			if (sel_obj.options[j].value == domain){
				flag = true;
			}
		}

		if (flag) continue;

		var opt = new Option(domain,domain,true,true);
		sel_obj.options[sel_obj.options.length] = opt;

		sel_obj.size = sel_obj.options.length;
		sel_obj.selectedIndex = -1;
	}

	var domains = new Array();
	for(var i=0;i<sel_obj.options.length;i++) {
		domains[domains.length] = sel_obj.options[i].value;

	}
	fo_obj.domain.value = domains.join(',');

	fo_obj._domain.focus();
}

// 도메인 삭제
function doDeleteDomain() {
	var fo_obj = xGetElementById("fo_obj");
	var sel_obj = fo_obj._domain_list;
	sel_obj.remove(sel_obj.selectedIndex);

	sel_obj.size = sel_obj.options.length;
	sel_obj.selectedIndex = -1;

	var domains = new Array();
	for(var i=0;i<sel_obj.options.length;i++) {
		domains[domains.length] = sel_obj.options[i].value;

	}
	fo_obj.domain.value = domains.join(',');
}

// 클라이언트 삭제
function doDeleteClient(client_srl){
	jQuery('input[name=cart]:checkbox').attr('checked', false);
	jQuery('input[name=cart][value=' + client_srl + ']:checkbox').attr('checked', true);
	jQuery('#fo_list').submit();
}