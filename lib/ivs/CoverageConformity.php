<?php

define('DBG',0);

require_once('../../config.inc.php');
require_once('common.php');
require_once('requirements.inc.php');
require_once('../requirements/reqExportCoverageConformity.php');

$kpi_input = get_input();
html_dump($kpi_input,"kpi_input");
//function testlinkInitPage(&$db, $initProject = FALSE, $dontCheckSession = false, $userRightsCheckFunction = null, $onFailureGoToLogin = false)
testlinkInitPage($db,FALSE,TRUE);
//html_dump($db);

$req_spec_mgr = new requirement_spec_mgr($db);
$req_mgr = new requirement_mgr($db);
$tplan_mgr = new testplan($db);

$project_id = get_project_id($db,$kpi_input['project']);
html_dump($project_id,'project_id:');

$req_spec_id = get_reqspec_id($db,$kpi_input['req_spec']);
html_dump($req_spec_id,'req_spec_id:');

$tplan_name = $kpi_input['testplan'];
$tplan_id = get_tplan_id($db,$project_id,$tplan_name);
html_dump($tplan_id,'tplan_id:');

$stat_map = setUpReqStatusCfg();

$content = '';
if(isset($kpi_input['format'])){
	$format = $kpi_input['format'];
}else{
	$format = 'json';
}
if($format == 'csv'){
	$content = exportReqCovConfCsv($req_spec_mgr,$req_mgr,$tplan_mgr,$req_spec_id,$tplan_id,$tplan_name,$stat_map);
	echo '<pre>' . $content . '</pre>';
}else if($format == 'json'){
	$content = exportReqCovConfJson($req_spec_mgr,$req_mgr,$tplan_mgr,$req_spec_id,$tplan_id,$tplan_name,$stat_map);
	echo $content;
}
html_dump($content);

function get_tplan_id(&$db,$prj,$tplan){
	$sql = "SELECT p.id FROM testplans p,nodes_hierarchy h WHERE p.id = h.id AND p.testproject_id = " . $prj . " AND h.name = '" . $tplan . "';";
	html_dump($sql,"sql");
	return $db->fetchOneValue($sql);
}

function get_reqspec_id(&$db,$doc_id){
	$sql = "SELECT id FROM req_specs WHERE doc_id = '" . $doc_id . "';";
	html_dump($sql,"sql");
	return $db->fetchOneValue($sql);
}

function get_project_id(&$db,$prefix){
	$sql = "SELECT id FROM testprojects WHERE prefix = '" . $prefix . "';";
	html_dump($sql,"sql");
	return $db->fetchOneValue($sql);
}

function get_input(){
	$url = $_SERVER["REQUEST_URI"];
	html_dump($url,"url");
	$str = parse_url($url, PHP_URL_QUERY);
	html_dump($str,"1str");
	//$str = str_replace(array("%22","%20",'%7B','%7D'),array('"',' ','{','}'),$str);
	//html_dump($str,"2str");
	if(is_null($str)){
		help();
		exit();
	}
	parse_str($str, $params);
	html_dump($params,"params");
	//$params = json_decode($str,true);
	//html_dump($params,"json_decode");
	return $params;
}

function help(){
	$url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; 
	
	echo 'Coverage/Conformity report - Help screen<br/>';	
	echo '<p>Arguments:';
	echo '<UL>';
	echo '<LI>project: testlink project prefix</LI>';
	echo '<LI>req_spec: requirement specification folder</LI>';
	echo '<LI>testplan: Testlink test plan name</LI>';
	echo '<LI>format: output format (csv,json)</LI>';
	echo '</UL>';
	echo '</p>';

	echo '<p>Examples:';
	echo '<UL>';
	echo '<LI>' . ahref($url,'') . ' - this help screen></LI>';
	echo '<LI>' . ahref($url,'?project=SMR&req_spec=GREP-399&testplan=Release_2018_NG07_System&format=json') . ' - Get Coverage/Conformity data for all requirements of GREP-399 folder of SMR project related to testplan=Release_2017_NG3_System test plan></LI> in json format';
	echo '</UL>';
	echo '</p>';
}

function ahref($u,$h){
	return '<a target="_blank" href="' . $u . $h . '">' . $u . $h . '</a>';	
}

function html_dump($var,$str=''){
	if(DBG == 1){
		echo $str . ':<pre>' . var_export($var, true) . '</pre>';
	}
}
?>

