<?php
//https://testlink-ivs-qual.ingenico.com/testlink_180518/lib/ivs/reqKPI.php
//https://testlink-ivs-qual.ingenico.com/testlink_180518/lib/ivs/reqKPI.php?prefix=SMR&folder=XDL%20Refund&status=rework&kpi=N4
//https://testlink-ivs-qual.ingenico.com/testlink_180518/lib/ivs/reqKPI.php?prefix=SMR&folder=SSC%20FR&status=draft&kpi=N4&details=true
//https://testlink-ivs-qual.ingenico.com/testlink_180518/lib/ivs/reqKPI.php?prefix=SMR&folder=SSC%20FR&kpi=N4
//https://testlink-ivs-qual.ingenico.com/testlink_180518/lib/ivs/reqKPI.php?prefix=SMR&kpi=N4
//https://testlink-ivs-qual.ingenico.com/testlink_180518/lib/ivs/reqKPI.php?prefix=SMR&folder=GREP-1068&status=Draft&kpi=N3&details=true
//https://testlink-ivs-qual.ingenico.com/testlink_180518/lib/ivs/reqKPI.php?prefix=SMR&folder=GREP&status=Draft&kpi=N1
//prefix: Project prefix, mandatory
//folder: Requirement Specification Document ID or TestSuite name (for N4); if missing - then root folder
//status: requirement status (for N1-N3) or testcase status (for N4); if missing - any
//KPI: one of the following; if missing - N1
//N1: Number of REQS in a document 
//N2: Number of REQS in a given state in a document
//N3: Number of REQ linked to at least one Test case for a given Document 
//N4: Number of test cases linked to at least one REQ for a given test suite

define('DBG',0);

require_once('../../config.inc.php');
require_once('common.php');

$kpi_input = get_input();
html_dump($kpi_input,"kpi_input");

$prefix = $kpi_input['prefix'];
$folder = $kpi_input['folder'];
$kpi = $kpi_input['kpi'];
if(!isset($kpi)){
	$kpi = 'N1';
}
if($kpi == 'N4'){
	$status = encode_status_T($kpi_input['status']);
}else{
	$status = encode_status_R($kpi_input['status']);
}
html_dump($status,'status');
$details = $kpi_input['details'];
if(!isset($details)){
	$details = false;
}
html_dump($details,'details');

//function testlinkInitPage(&$db, $initProject = FALSE, $dontCheckSession = false, $userRightsCheckFunction = null, $onFailureGoToLogin = false)
testlinkInitPage($db,FALSE,TRUE);
//html_dump($db);

//$req_spec_mgr = new requirement_spec_mgr($db);
//$tplan_mgr = new testplan($db);

//function get_filtered_req_map(&$db, $testproject_id, &$testproject_mgr, $filters, $options) {
	
$testproject_id = get_project_id($db,$prefix); //1029737;
//$testproject_id = 1029737;
html_dump($testproject_id,"testproject_id");

$count = 0;
switch($kpi){
	case 'N1':
	case 'N2':
	case 'N3':
		list($count,$items) = reqs($db,$testproject_id,$folder,$status,$kpi);
		break;
	case 'N4':
		//$count = tcs($db,$testproject_id,$folder,$status);
		list($count,$items) = tcs($db,$testproject_id,$folder,$status);
		//html_dump($n4,'N4:');
		//html_dump($t4,'T4:');
		break;
	default:
		break;
}

html_dump($details,'details');
if( $details == true){
	details($prefix,$items,$kpi);
}else{
	echo $count;
}

function details($prefix,$d,$kpi){
	echo '<html>';
	echo '  <TABLE BORDER="1">';
	echo '    <TR>';
	echo '      <TH>ID</TH>';
	echo '      <TH>Title</TH>';
	echo '    </TR>';
	foreach($d as $r){
		switch($kpi){
			case 'N1':
			case 'N2':
			case 'N3':
				//'req_doc_id' => 'GREP-731',
				//'title' => 'Add mapping between provisioning  Axis Address  and Transactional Axis Address in Axis Services',				
				$id = $r['req_doc_id'];
				$name = $r['title'];
				break;
			case 'N4':
				$id = $r['external_id'];
				$name = $r['name'];
				break;
			default:
				break;
		}
		
		echo '    <TR>';
		echo '      <TD><a target="_blank" href="' . tcURL($prefix,$id,$kpi) . '">' . $id . '</a></TD>';
		echo '      <TD>' . $name . '</TD>';
		echo '    </TR>';
	}
	echo '  </TABLE>';
	echo '</html>';
}

function tcURL($prefix,$item,$kpi){
	//https://testlink-ivs-qual.ingenico.com/testlink_180518/linkto.php?tprojectPrefix=SMR&item=testcase&id=SMR-3047
	//https://testlink-ivs-qual.ingenico.com/testlink_180518/linkto.php?tprojectPrefix=SMR&item=req&id=GREP-731	
	//$url = 'https://testlink-ivs-qual.ingenico.com/testlink_180518/';
	//$url = 'https://' . $_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']; 
	$url = $_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']; //testlink-ivs-qual.ingenico.com/testlink_180518/lib/ivs/reqKPI.php
	$n = strpos($url,'lib/ivs/');
	$url = substr($url,0,$n);
	$url = 'https://' . $url;
	html_dump($url,'url:');
	$url .= 'linkto.php?tprojectPrefix=' . $prefix;
	switch($kpi){
		case 'N1':
		case 'N2':
		case 'N3':
			$url .= '&item=req&id=' . $item;
			break;
		case 'N4':
			$url .= '&item=testcase&id=' . $prefix . '-' . $item;
			break;
		default:
			break;
	}
	return $url;
}

function tcs(&$db,$testproject_id,$folder,$status){
	$tproject_mgr = new testproject($db);
	$filters = init_filters_T($status);
	//html_dump($filters,'filters:');
	$tree = getTestSpecTree(
		$testproject_id,
		$tproject_mgr,
		$filters);	
	//html_dump($tree,'tree:');
	
	if(isset($folder)){
		$tree = scan_folder($tree,$folder);
	}
	//html_dump($tree,'tree:');

	$lst = scan_tree($tree);
	//html_dump($lst,'lst:');
	html_dump(count($lst),'count:');
	
	list($n4,$t4) = count_n4($db,$lst);
	//html_dump($n4,'N4:');
	//html_dump($t4,'T4:');
	return [$n4,$t4];
}

function count_n4(&$db,$lst){
	$req_mgr = new requirement_mgr($db);
	//$reqs = $req_mgr->get_all_for_tcase(1444362);
	//html_dump($reqs,"reqs");
	$n = 0;
	$t = array();
	foreach($lst as $node){
		$id = $node['id'];
		$reqs = $req_mgr->get_all_for_tcase($id);
		if(count($reqs) > 0){
			$n++;
			$t[] = $node;
		}
		//html_dump($reqs,'reqs ' . $id . '(' . $n . '):');
	}
	return [$n,$t];
}

function scan_folder($tree,$folder){
	if($tree['childNodes'] == null){		
		return null;
	}else{
		foreach($tree['childNodes'] as $node){
			if(isset($node['name']) and ($node['name'] == $folder)){
				return $node;
			}
			$ret = scan_folder($node,$folder);
			if(!is_null($ret)){
				return $ret;
			}
		}
	}
	return null;
}

function scan_tree($tree){
	$lst = array();
	if($tree['childNodes'] == null){
		//html_dump($tree,'leaf:');
		$lst[count($lst)] = $tree;
	}else{
		foreach($tree['childNodes'] as $node){
			$lst = array_merge($lst,scan_tree($node));
		}
	}
	//html_dump($lst,'lstZ:');
	return $lst;
}

function reqs(&$db,$testproject_id,$folder,$status,$kpi){
	$testproject_mgr = new testproject($db);	
	$filters = init_filters_R($status);
	$options = init_options_R();
	$map = get_filtered_req_map(
		$db,
		$testproject_id,
		$testproject_mgr,
		$filters,
		$options
	);
	//html_dump($map,'map:');
	html_dump(count($map),'count1:');
	if(isset($status)){
		$map = filter_status($db,$map,$status);
	}	
	html_dump(count($map),'count2:');
	html_dump($folder,'folder:');
	if(isset($folder)){
		$map = filter_folder($db,$map,$folder);
	}	
	//html_dump($map,'map:');
	html_dump(count($map),'count3:');

	//html_dump($kpi,'kpi:');
	if($kpi == 'N3'){
		$map = filter_coverage($db,$map);
	}	
	html_dump(count($map),'count4:');
	//html_dump($map,'map:');
	return [count($map),$map];
}

function filter_coverage(&$db,$map){
	$req_mgr = new requirement_mgr($db);
	$ret = array();
	foreach($map as $id => $req){
		//html_dump($req,'req:');
		$req_coverage = $req_mgr->get_coverage($id);
		//html_dump($req_coverage,'req_coverage ' . $id);
		if(isset($req_coverage)){
			$ret[$id] = $req;
		}
	}
	return $ret;
}

function filter_status(&$db,$map,$status){
	$ret = array();
	foreach($map as $id => $req){
		if(has_status($db,$id,$status)){
			$ret[$id] = $req;
		}
	}
	return $ret;
}

function has_status(&$db,$id,$status){
	$sql = "SELECT id,version,revision,status FROM req_versions WHERE id  IN (SELECT id FROM nodes_hierarchy WHERE parent_id=${id} AND node_type_id = 8) ORDER BY version DESC";
	//html_dump($sql,'sql:');
	$v = $db->fetchFirstRow($sql);
	//html_dump($v,'v:');
	if($v['status'] == $status){
		return true;
	}
	return false;
}

function filter_folder(&$db,$map,$folder){
	$ret = array();
	foreach($map as $id => $req){
		if(is_in_folder($db,$id,$folder)){
			$ret[$id] = $req;
		}
	}
	return $ret;
}

function is_in_folder(&$db,$id,$folder){
	while(true){
		$parent_id = fetch_req_parent($db,$id);
		$node_type_id = fetch_node_type_id($db,$parent_id);
		if($node_type_id == 1){
			break;
		}
		if($node_type_id == 6){ //6 = requirement_spec
			$doc_id = fetch_req_spec_doc_id($db,$parent_id);
			if($doc_id == $folder){
				//html_dump('YES!');
				return true;
			}
		}
		$id = $parent_id;
	}
	//html_dump('NO!');
	return false;	
}

function fetch_req_parent(&$db,$id){
	$sql = "SELECT parent_id FROM nodes_hierarchy WHERE id = " . $id;
	//html_dump($sql,'sql:');
	$parent_id = $db->fetchOneValue($sql);
	//html_dump($parent_id,'parent_id:');
	return $parent_id;
}

function fetch_node_type_id(&$db,$id){
	$sql = "SELECT node_type_id FROM nodes_hierarchy WHERE id = " . $id;
	//html_dump($sql,'sql:');
	$node_type_id = $db->fetchOneValue($sql);
	//html_dump($node_type_id,'node_type_id:');
	return $node_type_id;
}

function fetch_req_spec_doc_id(&$db,$id){
	$sql = "SELECT doc_id FROM req_specs WHERE id = " . $id;
	//html_dump($sql,'sql:');
	$doc_id = $db->fetchOneValue($sql);
	//html_dump($doc_id,'doc_id:');
	return $doc_id;
}

function init_filters_T($status){
	return array(
		'filter_keywords_filter_type' => null,
		'filter_result_result' => null,
		'filter_result_method' => null,
		'filter_result_build' => null,
		'filter_assigned_user_include_unassigned' => null,
		'filter_tc_id' => null,
		'filter_testcase_name' => null,
		'filter_toplevel_testsuite' => array(),
		'filter_keywords' => null,
		'filter_workflow_status' => $status,
		'filter_importance' => null,
		'filter_priority' => null,
		'filter_execution_type' => null, 
		'filter_assigned_user' => null,
		'filter_custom_fields' => null,
		'filter_result' => null,
		'filter_bugs' => null,
		'setting_testplan' => null,
		'setting_build' => null,
		'setting_platform' => null,
		'setting_refresh_tree_on_action' => 1
	);
}

function init_filters_R($status){
	return array(
		'exclude_node_types' =>  array(
			'testplan' => 'exclude me',
			'testsuite' => 'exclude me',
			'testcase' => 'exclude me',
			'requirement_spec_revision' => 'exclude me'
		),
		'exclude_children_of' => array(
			'testcase' => 'exclude my children',
			'requirement' => 'exclude my children',
			'testsuite' => 'exclude my children'
		),
		'filter_doc_id' => null,
		'filter_title' => null,
		'filter_status' => $status,
		'filter_type' => null,
		'filter_spec_type' => null,
		'filter_coverage' => null,
		'filter_relation' => null,
		'filter_tc_id' => null,
		'filter_custom_fields' => null
	);
}


function init_options_R(){
	return array(
		'for_printing' => 0,
		'exclude_branches' => null,
		'recursive' => true,
		'order_cfg' => array(
			'type' => 'spec_order'
		)
	);
}

function get_project_id(&$db,$prefix){
	$sql = "SELECT id FROM testprojects WHERE prefix = '" . $prefix . "';";
	//html_dump($sql,"sql");
	return $db->fetchOneValue($sql);
}

function encode_status_T($status){ //$tlCfg->testCaseStatus	
	switch($status){
		case 'draft': $status = 1; break;
		case 'readyForReview': $status = 2; break;
		case 'reviewInProgress': $status = 3; break;
		case 'rework': $status = 4; break;
		case 'obsolete': $status = 5; break;
		case 'future': $status = 6; break;
		case 'final': $status = 7; break;
		case 'any': $status = null; break;
		default: $status = null; break;
	}
	return $status;
}
								
function encode_status_R($status){
	switch($status){
		case 'Valid': $status = TL_REQ_STATUS_VALID; break;
		case 'Not testable': $status = TL_REQ_STATUS_NOT_TESTABLE; break;
		case 'Draft': $status = TL_REQ_STATUS_DRAFT; break;
		case 'Review': $status = TL_REQ_STATUS_REVIEW; break;
		case 'Rework': $status = TL_REQ_STATUS_REWORK; break;
		case 'Finish': $status = TL_REQ_STATUS_FINISH; break;
		case 'Implemented': $status = TL_REQ_STATUS_IMPLEMENTED; break;
		case 'Obsolete': $status = TL_REQ_STATUS_OBSOLETE; break;
		case 'Any': $status = null; break;
		default: $status = null; break;
	}
	return $status;
}


function get_input(){
	$url = $_SERVER["REQUEST_URI"];
	html_dump($url,"url");
	$str = parse_url($url, PHP_URL_QUERY);
	html_dump($str,"1str");
	if(is_null($str)){
		help();
		exit();
	}
	parse_str($str, $params); //https://stackoverflow.com/questions/8643938/php-fastest-method-to-parse-url-params-into-variables
	html_dump($params,"params");
	return $params;
}

function help(){
	$url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; 

	echo 'Automated check for REQ - TC links - Help screen<br/>';	
	echo '<p>Arguments:';
	echo '<UL>';
	echo '<LI>kpi: KPI to calculate</LI>';
	echo '<UL>';
	echo '<LI>N1 - Number of REQS in a document</LI>';
	echo '<LI>N2 - Number of REQS in a given state in a document</LI>';
	echo '<LI>N3 - Number of REQ linked to at least one Test case for a given Document</LI>';
	echo '<LI>N4 - Number of test cases linked to at least one REQ for a given test suite</LI>';
	echo '</UL>';
	echo '<LI>prefix: prefix of testlink project</LI>';
	echo '<LI>folder: requirement specification or test suite</LI>';
	echo '<UL>';
	echo '<LI>for N1,N2,N3 - Document ID of requirement specification</LI>';
	echo '<LI>for N4 - Test suite name</LI>';
	echo '</UL>';
	echo '<LI>status: status of a requirement or test case to filter</LI>';
	echo '<UL>';
	echo '<LI>for N1,N2,N3 - one of (Valid,Not testable,Draft,Review,Rework,Finish,Implemented,Obsolete,Any)</LI>';
	echo '<LI>for N4 - on of(draft,readyForReview,reviewInProgress,rework,obsolete,future,final,any)</LI>';
	echo '</UL>';
	echo '<LI>details: if true provide all records; otherwise (default) - return count only</LI>';
	echo '</UL>';
	echo '</p>';

	echo '<p>Examples:';
	echo '<UL>';
	echo '<LI>' . ahref($url,'') . ' - this help screen></LI>';
	echo '<LI>' . ahref($url,'?prefix=SMR&folder=GREP&kpi=N1') . ' - Number of requirements in GREP specification of SMR project></LI>';
	echo '<LI>' . ahref($url,'?prefix=SMR&folder=GREP-1068&status=Draft&kpi=N2') . ' - Number of requirements in GREP-1068 folder with status Draft of SMR project></LI>';
	echo '<LI>' . ahref($url,'?prefix=SMR&folder=GREP&status=Draft&kpi=N3') . ' - Number of requirements in GREP with status Draft of SMR project linked to at least one Test case></LI>';
	echo '<LI>' . ahref($url,'?prefix=SMR&folder=XDL+Refund&status=rework&kpi=N4') . ' - Number of test cases linked to at least one requiremnet in XDL Refund test suite of SMR project with status refund></LI>';
	echo '<LI>' . ahref($url,'?prefix=SMR&folder=GREP&kpi=N1&details=true') . ' - List all the requirements in GREP specification of SMR project></LI>';
	echo '</UL>';
	echo '</p>';
	
	exit();
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

