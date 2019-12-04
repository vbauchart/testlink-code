<?php

require_once('../../config.inc.php');
require_once('common.php');

$kpi_input = get_input();
$kpi_output = process_kpi($kpi_input);
display_kpi($kpi_input,$kpi_output);

function display_kpi(&$kpi_input,&$kpi_output){
	echo '<html>';
	echo '<body>';
	echo '<h1 class="title">KPI</h1>';

	echo '<TABLE BORDER="1"> ';
	echo '  <CAPTION>TestLink KPI</CAPTION> ';

	echo '  <TR> ';
	echo ' <TH>Project</TH> ';
	echo ' <TH>Test Suite</TH> ';
	echo ' <TH>Filter</TH> ';
	echo ' <TH>Result</TH> ';
	echo '  </TR> ';
	
	foreach($kpi_output as $row){
		echo '  <TR> ';
		echo ' <TD>' . $row['project'] . '</TD> ';
		echo ' <TD>' . $row['suite'] . '</TD> ';
		echo ' <TD>';
		print_r($row['filters']);
		echo '</TD> ';
		echo ' <TD>' . $row['counter'] . '</TD> ';
		echo '  </TR> ';
	}
	
	echo '</TABLE> ';

	echo '</body>';
	echo '</html>';
}

function process_kpi(&$kpi_input){
	testlinkInitPage($db,FALSE,TRUE);
	$kpi_output = $kpi_input;
	foreach($kpi_input as $i => $row){
		$args = get_args($db,$row);
		$r = generateTestSpecTree($db,$args['tproject_id'], $args['tproject_name'],$args['linkto'],$args['filters'],$args['options']);
		$kpi_output[$i]['counter'] = get_counter($r,$row['suite']);
	}
	return $kpi_output;
}

function get_counter(&$result,$name){
	$r = find_node($result['tree'],$name);
	return $r;
}

function find_node($tree,$name){
	if($tree['node_type_id'] > 2){
		return 0;
	}
	if($tree['name'] == $name){
		return $tree['testcase_count'];
	}
	foreach($tree['childNodes'] as $child){
		if($child['name'] == $name){
			return $child['testcase_count'];
		}
		/*
		$r = find_node($child,$name);
		if($r != NULL){
			return $r;
		}
		*/
	}
	return 0;
}

function get_input(){
	$url = $_SERVER["REQUEST_URI"];
	$str = parse_url($url, PHP_URL_QUERY);
	parse_str($str, $params);

	$str= $params['input'];
	$str = str_replace("\\", '', $str); 
	eval('$kpi_input = ' . $str . ';');
	foreach($kpi_input as $i => $row){
		if(!isset($row['project']) || $row['project'] == NULL || $row['project'] == ''){
			$kpi_input[$i]['project'] = $kpi_input[$i-1]['project'];
		}
		if(!isset($row['suite']) || $row['suite'] == NULL || $row['suite'] == ''){
			$kpi_input[$i]['suite'] = $kpi_input[$i-1]['suite'];
		}
	}
	return $kpi_input;
}

function get_args(&$db,$row){
	//generateTestSpecTree($db,$tproject_id, $tproject_name,$linkto,$filters);
	$args = array();
	$project_id = get_project_id($db, $row['project']);
	$args['tproject_id'] = $project_id;
	$args['tproject_name'] = $row['project'];
	$args['linkto'] = '';
	initFiltersOptions($args['filters'],$args['options']);
	foreach($row['filters'] as $k => $v){
		if($k == 'keywords'){
			foreach($v as $i => $w){
				$v[$i] = get_keyword_id($db,$project_id,$w);
			}
		}
		if($k == 'custom_fields'){
			$c = array();
			foreach($v as $i => $w){
				$j = get_custom_field_id($db,$i);
				$c[$j] = $w;
			}
			$v = $c;
		}
		$args['filters']['filter_' . $k] = decodeValue($k,$v);
	}
	/* test suites filtering is done in REVERSE way, like this:
	    [filter_toplevel_testsuite] => Array
        (
            [178924] => exclude_me
            [187411] => exclude_me
            [310455] => exclude_me
            [580398] => exclude_me
            [618276] => exclude_me
            [635472] => exclude_me
        )

	if($row['suite'] != ''){ 
		$args['filters']['filter_toplevel_testsuite'] = array($row['suite']);
	}
	*/
	return $args;
}

function initFiltersOptions(&$filters,&$options){
	$filters = array(
		'filter_keywords' => null,
		'filter_keywords_filter_type' => null
	);
	$options = array(
		'ignore_inactive_testcases' => 0,
		'ignore_active_testcases' => 0
	);
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
	return $db->fetchOneValue($sql);
}

function get_keyword_id(&$db,$prj,$keyword){
	$sql = "SELECT id FROM keywords WHERE keyword = '" . $keyword . "' AND testproject_id = " . $prj . ";";
	return $db->fetchOneValue($sql);
}

function get_project_id(&$db,$prj){
	$sql = "SELECT id FROM nodes_hierarchy WHERE node_type_id = 1 AND name = '" . $prj . "';";
	return $db->fetchOneValue($sql);
}

function html_dump($var){
	echo '<pre>' . var_export($var, true) . '</pre>';	
}
?>
