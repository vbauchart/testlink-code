<?php
/** Coverage/Conformity Export as indicated in https://jira.techno.ingenico.com/browse/TOOLS-87
 *
**/
require_once("../../config.inc.php");
require_once("csv.inc.php");
require_once("xml.inc.php");
require_once("common.php");
require_once("requirements.inc.php");

// $coverage = (number of Test Cases linked to Requirement within the Test Plan) / (number of Test Cases linked to Requirement)
// 
// if at least one of Test Cases linked to Requirement within the Test Plan has status FAILED
//    $conformity = FAILED
// else if at least one of Test Cases linked to Requirement within the Test Plan has status BLOCKED
//    $conformity = BLOCKED
// else if ALL Test Cases linked to Requirement within the Test Plan has status NOT RUN
//    $conformity = NOT RUN
// else if ALL Test Cases linked to Requirement within the Test Plan has status PASSED
//    $conformity = PASSED
// else
//    $conformity = Number of passed Test Cases / Total number of Test Cases
function evaluate_req(&$status_code, $total_tcs, &$counters) {
    //$conformity = 'passed'; //passed|failed|blocked|not run|m/n
    if( !isset($counters[$status_code['not_run']]) ){
		$counters[$status_code['not_run']] = 0;
    }
    if( !isset($counters[$status_code['not_applicable']]) ){
		$counters[$status_code['not_applicable']] = 0;
    }
    if( !isset($counters[$status_code['failed']]) ){
		$counters[$status_code['failed']] = 0;
    }
    if( !isset($counters[$status_code['blocked']]) ){
		$counters[$status_code['blocked']] = 0;
    }
    if( !isset($counters[$status_code['passed']]) ){
		$counters[$status_code['passed']] = 0;
    }

    //$coverage = 'm/n';
    $resT = $counters['total'];
    $resB = $total_tcs;	
    $resP = $counters[$status_code['passed']];	
    $coverage = $resT . '/' . $resB;
            
    if ($counters['total'] == 0) { // Zero test cases linked => uncovered
		$conformity = 'uncovered';
    }else if($counters['total'] == $counters[$status_code['not_run']]){
		//$conformity = $status_code['not_run'];
		$conformity = 'not run';
    }else if($counters[$status_code['failed']] > 0){
		//$conformity = $status_code['failed'];
		$conformity = 'failed';
    }else if($counters[$status_code['blocked']] > 0){
		//$conformity = $status_code['blocked'];
		$conformity = 'blocked';
    }else if($counters[$status_code['passed']] + $counters[$status_code['not_applicable']] == $counters['total']){
		//$conformity = $status_code['passed'];
		$conformity = 'passed';
    }else{
        //$conformity = $counters[$status_code['passed']] . '/' . $counters['total'] . ' passed';
		$conformity = 'partially passed';
    }
    return array($coverage,$conformity,$resT,$resB,$resP);
}

function get_external_id(&$db,$prefix,$tc_id){
	$sql = "SELECT v.tc_external_id FROM tcversions v, nodes_hierarchy n WHERE v.id = n.id AND n.parent_id = '" . $tc_id . "';";
	$ext_id = $db->fetchOneValue($sql);
	$ret = $prefix . '-' . $ext_id;
	return $ret;
}

function getReqStat($req,$tplan_id,&$tplan_mgr,&$req_mgr,&$stat_map,$format){
	$db = $req_mgr->db;	
	$req_id = $req['id'];	

	$parent_id = get_parent_id($db,$req_id);
	//$initiative = get_node_name($db,$parent_id);
	$initiative = get_req_spec_id($db,$parent_id);	

	$parent_id = get_parent_id($db,$parent_id);
	//$program = get_node_name($db,$parent_id);
	$program = get_req_spec_id($db,$parent_id);	
	
	
    $coverageContext = null;
	$prefix = get_project_prefix($db,$req['testproject_id']);
    $tcs = (array)$req_mgr->get_coverage($req_id,$coverageContext);
    $tc_ids = array();
	$tci = array();
    foreach ($tcs as $tc) {
        $tc_ids[] = $tc['id'];
		$ext_id = get_external_id($db,$prefix,$tc['id']);
		$tci[$ext_id] = '?';
    }
    $total_tcs = count($tcs);
    $resT = '0';
    $resB = $total_tcs;
    $resP = '0';
    //$coverage = '0/0';
    $conformity = 'uncovered';
    if($total_tcs > 0){
        $filters = array('tcase_id' => $tc_ids);
        $options = array('addExecInfo' => true,'accessKeyType' => 'tcase');
        $tcaseSet = $tplan_mgr->getLTCVNewGeneration($tplan_id, $filters, $options);
		if (is_array($tcaseSet)){
	        $stat = array('total' => 0);
			foreach ($tcaseSet as $key => $tc_info){
				$tc_id = $tc_info['full_external_id'];
				$stat['total'] ++;
				if (isset($tc_info['exec_status'])){
					$status = $tc_info['exec_status'];
					$tci[$tc_id] = $status;
					if (!isset($stat[$status])){
						$stat[$status] = 0;
					}
					$stat[$status]++;
				}else{
					$tci[$tc_id] = ' ';
				}
			}
			list($coverage,$conformity,$resT,$resB,$resP) = evaluate_req($stat_map, $total_tcs, $stat);
		}
    }
	$coverage = '' . $resT . '/' . $resB;
    $req['program'] = $program;
    $req['initiative'] = $initiative;
    $req['resT'] = $resT;
    $req['resB'] = $resB;
    $req['resP'] = $resP;
    $req['coverage'] = $coverage;
    $req['conformity'] = $conformity;
	if($format == 'json'){
		$req['tci'] = $tci;
	}
    return $req;
}

function exportReqCovConf(&$req_spec_mgr,&$req_mgr,	&$tplan_mgr,
	$req_spec_id,$tplan_id,$tplan_name,&$stat_map,$format){
	$db = $req_mgr->db;
	$reqData = get_reqs($req_spec_mgr,$req_mgr,$req_spec_id);
	$itsClient = getItsClient($db);	
    foreach ($reqData as $req_id => $req) {
        $reqData[$req_id] = getReqStat($req, $tplan_id,$tplan_mgr,$req_mgr,$stat_map,$format);
		$reqData[$req_id]["initiative_title"] = $req['req_spec_title'];
		$reqData[$req_id]["req_title"] = $req['title'];
		if(!isset($reqData[$req_id]["epic"])){
			$issue = $itsClient->getIssue($reqData[$req_id]["req_doc_id"]);
			$reqData[$req_id]["epic"] = $issue->fields->customfield_10006;
		}
		$epic = $reqData[$req_id]["epic"];
		if(strpos($epic,'http') === 0){
			$lst = split('/',$epic);
			$epic = end($lst);
		}
		$reqData[$req_id]["epic"] = $epic;
		$issue = $itsClient->getIssue($epic);
		$summary = $issue->fields->summary;
		$reqData[$req_id]["epic_title"] = $summary;
    }
	return $reqData;
}

function exportReqCovConfJson(&$req_spec_mgr,&$req_mgr,	&$tplan_mgr,
	$req_spec_id,$tplan_id,$tplan_name,&$stat_map){
	$reqData = exportReqCovConf($req_spec_mgr,$req_mgr,$tplan_mgr,$req_spec_id,$tplan_id,$tplan_name,$stat_map,'json');
	$CovConf = array();
	foreach($reqData as $r){
		$CovConf[] = array(
			"program" => $r["program"],
			"initiative" => $r["initiative"],
			"initiative_title" => $r["initiative_title"],
			"epic" => $r["epic"],
			"epic_title" => $r["epic_title"],
			"req_doc_id" => $r["req_doc_id"],
			"req_title" => $r["req_title"],
			"resT" => $r["resT"],
			"resP" => $r["resP"],
			"conformity" => $r["conformity"],
			"tci" => $r["tci"]
		);
	}
	$json = json_encode($CovConf);
	return $json;
}

function exportReqCovConfCsv(&$req_spec_mgr,&$req_mgr,	&$tplan_mgr,
	$req_spec_id,$tplan_id,$tplan_name,&$stat_map){
	$reqData = exportReqCovConf($req_spec_mgr,$req_mgr,$tplan_mgr,$req_spec_id,$tplan_id,$tplan_name,$stat_map,'csv');
	$sKeys = array("program","initiative","initiative_title","epic","epic_title","req_doc_id","req_title","resT","resP","conformity");
    $content = exportDataToCSV($reqData,$sKeys,$sKeys,0,',');
    
    $header = "# ";
    $header .= "TimeStamp = " . date('d/m/Y h:i:s', time());
    $header .= "; ";
    $header .= "TestPlan = " . $tplan_name;
    $header .= "\xD\xA";

    $header .= "# Program,Initiative,BI title,epic,Epic Title,ID Story,US Title,Actual Coverage,Passed tests,Feature Status";
    $header .= "\xD\xA";
    
    return $header . $content;
}

function setUpReqStatusCfg()
{
	$results_cfg = config_get('results');
	
	$status_code_map = array();
	foreach ($results_cfg['status_label_for_exec_ui'] as $status => $label) 
	{
		$status_code_map[$status] = $results_cfg['status_code'][$status];
	}
	
	$code_status_map = array_flip($status_code_map);

	return $status_code_map;
}

function get_reqs(&$req_spec_mgr,&$req_mgr,$req_spec_id){
	$specs = get_req_specs($req_spec_mgr,$req_spec_id);
	$specs = array_merge(array($req_spec_id),$specs);
	$reqs = array();
	foreach($specs as $s){
		$r = $req_spec_mgr->get_requirements($s);
		if(is_array($r)){
			$reqs = array_merge($reqs,$r);
		}
	}
	$i = 0;
	foreach($reqs as $r){
		$cfs = $req_mgr->get_linked_cfields($r['id'],$r['version_id']);
		$epic = '';
		foreach($cfs as $c){
			if($c['name'] == 'epic'){
				$epic = $c['value'];
			}
		}
		$reqs[$i++]['epic'] = $epic;
	}
	return $reqs;
}

function get_req_specs(&$req_spec_mgr,$req_spec_id){
	$tree = $req_spec_mgr->getReqTree($req_spec_id);
	//$specs = array($req_spec_id);
	$specs = array();
	foreach($tree['childNodes'] as $s){
		if($s['childNodes'] == NULL){
			continue;
		}
		//if(($s['node_type_id'] != 6) AND ($s['node_type_id'] != 11)){
		if(($s['node_type_id'] != 6)){
			continue;
		}
		$specs[] = $s['id'];
		$l = get_req_specs($req_spec_mgr,$s['id']);
		$specs = array_merge($specs,$l);
	}
	return $specs;
}

function get_project_prefix(&$db,$project_id){
	$sql = "SELECT prefix FROM testprojects WHERE  id = " . $project_id . ";";
	return $db->fetchOneValue($sql);
}

function get_parent_id(&$db,$node_id){
	$sql = "SELECT parent_id FROM nodes_hierarchy WHERE  id = " . $node_id . ";";
	return $db->fetchOneValue($sql);
}

function get_node_name(&$db,$node_id){
	$sql = "SELECT name FROM nodes_hierarchy WHERE  id = " . $node_id . ";";
	return $db->fetchOneValue($sql);
}

function get_req_spec_id(&$db,$node_id){
	$sql = "SELECT doc_id FROM nodes_hierarchy h,req_specs r WHERE  r.id = h.id AND h.id = " . $node_id . ";";
	return $db->fetchOneValue($sql);
}

function getItsClient(&$db){
    $it_mgr = new tlIssueTracker($db);
	$itd = $it_mgr->getByName('JIRA TECHNO');
	//html_dump($itd,'itd');
	$iname = $itd['implementation'];
	$its = new $iname($itd['implementation'],$itd['cfg'],$itd['name']);
	return $its->getAPIClient();
}

?>