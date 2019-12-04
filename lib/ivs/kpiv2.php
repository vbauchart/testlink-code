<?php
define('DBG',0);

require_once('../../config.inc.php');
require_once('common.php');

$kpi_input = get_input();
html_dump($kpi_input,"kpi_input");
$kpi_output = process_kpi($kpi_input);
html_dump($kpi_output,"kpi_output");
//echo $kpi_output['counter'];

echo json_encode($kpi_output);

function process_kpi(&$kpi_input){
	testlinkInitPage($db,FALSE,TRUE);
	$kpi_output = $kpi_input;
	$args = get_args($db,$kpi_input);
	html_dump($args,"args");
	$r = generateTestSpecTree($db,$args['tproject_id'], $args['tproject_name'],$args['linkto'],$args['filters'],$args['options']);
	$kpi_output['counter'] = get_counter($r,$kpi_input['suite']);
	return $kpi_output;
}

function get_counter(&$result,$name){
	//echo '<br/>get_counter:';
	//html_dump($result);
	//html_dump($result['name']);
	//html_dump($result['tree']['name']);
	//html_dump($result['tree']['childNodes'][0]['name']);
	//html_dump($result['tree']['childNodes'][0]['testlink_node_name']);
	//html_dump($result['tree']['childNodes'][0]['testcase_count']);
	//html_dump($result['tree']['childNodes'][0]);
	//if($result['tree']['name'] == $name) return $result['tree']['testcase_count'];
	$r = find_node($result['tree'],$name);
	//echo '<br/>counter result:';
	//html_dump($r);
	return $r;
}

function find_node($tree,$name){
	html_dump($tree['name'],"find_node tree['name']");
	html_dump($tree['node_type_id'],"find_node tree['node_type_id']");
	if($tree['node_type_id'] > 2){
		return 0;
	}
	if($name == ''){
		return $tree['testcase_count'];
	}
	foreach($tree['childNodes'] as $child){
	html_dump($child['name'],"find_node child['name']");
		if($child['name'] == $name){
			return $child['testcase_count'];
		}
	}
	return 0;
}

function get_args(&$db,$input){
	//generateTestSpecTree($db,$tproject_id, $tproject_name,$linkto,$filters);
	$args = array();
	$project_id = get_project_id($db, $input['project']);
	$args['tproject_id'] = $project_id;
	$args['tproject_name'] = $input['project'];
	$args['linkto'] = '';
	initFiltersOptions($args['filters'],$args['options']);
	foreach($input['filters'] as $k => $v){
		html_dump($k,'k==filter');
		if($k == 'keywords'){
			html_dump($v,'1k==keywords');
			foreach($v as $i => $w){
				//echo '<br/>keyword: ' . $i . ' ' . $w;
				$v[$i] = get_keyword_id($db,$project_id,$w);
				//echo '<br/>keyword: ' . $i . ' ' . $w;
			}
			html_dump($v,'2k==keywords');
		}
		if($k == 'custom_fields'){
			html_dump($v,'1k==custom_fields');
			$c = array();
			foreach($v as $i => $w){
				html_dump($w,'custom_field');
				$j = get_custom_field_id($db,$i);
				$c[$j] = $w;
				//html_dump($c);
			}
			$v = $c;
			html_dump($v,'2k==custom_fields');
		}
		$args['filters']['filter_' . $k] = decodeValue($k,$v);
	}
	return $args;
}

function decodeValue($k,$v){
	switch ($k) {
		case "importance":
			switch ($v) {
//$tlCfg->importance_levels = array(HIGH => 3,MEDIUM => 2,LOW => 1);
				case "High": $v = 3; break;
				case "Medium": $v = 2; break;
				case "Low": $v = 1; break;
				default: break;
			}
			break;
		case "workflow_status":
//$tlCfg->testCaseStatus = array( 'draft' => 1, 'readyForReview' => 2, 'reviewInProgress' => 3, 'rework' => 4, 'obsolete' => 5, 'future' => 6, 'final' => 7 );   			
			switch ($v) {
				case "Draft": $v = 1; break;
				case "Ready for review": $v = 2; break;
				case "Review in progress": $v = 3; break;
				case "Rework": $v = 4; break;
				case "Obsolete": $v = 5; break;
				case "Future": $v = 6; break;
				case "Final": $v = 7; break;
				default: break;
			}
			break;
		case "execution_type":
//define('TESTCASE_EXECUTION_TYPE_MANUAL', 1);
//define('TESTCASE_EXECUTION_TYPE_AUTO', 2);
			switch ($v) {
				case "Manual": $v = 1; break;
				case "Automated": $v = 2; break;
				default: break;
			}
		default: break;
	}
	
	return $v;
}

function get_custom_field_id(&$db,$name){
	$sql = "SELECT id FROM custom_fields WHERE name = '" . $name . "';";
	return $db->fetchOneValue($sql,'get_custom_field_id sql');
}

function get_keyword_id(&$db,$prj,$keyword){
	//return 239;
	$sql = "SELECT id FROM keywords WHERE keyword = '" . $keyword . "' AND testproject_id = " . $prj . ";";
	//echo '<br/>' . $sql;
	return $db->fetchOneValue($sql);
}

function initFiltersOptions(&$filters,&$options){
	//$filters = array();
	$filters = array(
		'filter_keywords' => null,
		'filter_keywords_filter_type' => null
	);
	$options = array(
		'ignore_inactive_testcases' => 0,
		'ignore_active_testcases' => 0
	);
}

function get_project_id(&$db,$prj){
	//return 178912;
	$sql = "SELECT id FROM nodes_hierarchy WHERE node_type_id = 1 AND name = '" . $prj . "';";
	//$sql = "SELECT COUNT(*) FROM testprojects";
	html_dump($sql,"sql");
	return $db->fetchOneValue($sql);
}

function get_input(){
	$url = $_SERVER["REQUEST_URI"];
	html_dump($url,"url");
	$str = parse_url($url, PHP_URL_QUERY);
	html_dump($str,"1str");
	$str = str_replace(array("%22","%20",'%7B','%7D'),array('"',' ','{','}'),$str);
	html_dump($str,"2str");
	$params = json_decode($str,true);
	//html_dump($params,"json_decode");
	return $params;
}

function html_dump($var,$str=''){
	if(DBG == 1){
		echo $str . ':<pre>' . var_export($var, true) . '</pre>';
	}
}
?>
