<?php

require_once("../../config.inc.php");
require_once("common.php");
require_once("users.inc.php");
require_once('requirements.inc.php');
require_once("configCheck.php");
require_once("reqDiffer.inc.php");
require_once('reqJiraRestJsonXml.php');

testlinkInitPage($db,false,false,"checkRights");

$args = init_args($db);
$gui = initializeGui($db,$args,$_SESSION);

$jr = getJiraJql($args);
if($jr){
	$tl = getTlReqs($db,$args);
	$ids = classifyTlJr($tl,$jr,$args);
	$report = scanIds($ids,$tl,$jr,$args);
	$ret = treatNew($jr,$ids,$args);
	$report = array_merge($report,$ret);
	$gui->items = $report;
	$gui->file_check=array('status_ok' => 1, 'msg' => 'ok');
	$gui->userFeedback = array();
}else{
	$gui->file_check=array('status_ok' => 0, 'msg' => 'KO');
}

$templateCfg = templateConfiguration();
$smarty = new TLSmarty;
$smarty->assign('gui',$gui);
$smarty->display($templateCfg->template_dir . $templateCfg->default_template);

function treatNew(&$jr,&$ids,&$args){
	foreach($jr['grp']['groups'] as $gkey => $group){
		foreach($group as $rkey => $jrReq){
			$key = $jrReq['key'];
			if($ids[$key] != 'J'){
				unset($jr['grp']['groups'][$gkey][$rkey]);
			}
		}
		$dim = count($jr['grp']['groups'][$gkey]);
		if($dim == 0){
			unset($jr['grp']['groups'][$gkey]);
		}
	}
	$xml = json2xml($jr['prj'],$jr['grp'],$args->jql);

	$context = new stdClass();
	$context->tproject_id = $args->tproject_id;
	$context->req_spec_id =  0;
    $context->user_id = $args->usr;
	$context->importType = 'XML';
	
	$ret = doReqImportFromXML($args->req_spec_mgr,$args->req_mgr,$xml,$context,null); //,$opts);
	foreach($ret as $i => $r){
		$key = $r['doc_id'];
		if(isset($ids[$key]) && $ids[$key]=='J'){
			$ret[$i]['status_before'] = ' ';
			$ret[$i]['status_after'] = TL_REQ_STATUS_DRAFT;
		}else{
			$ret[$i]['status_before'] = ' ';
			$ret[$i]['status_after'] = ' ';
		}
	}
	return $ret;
}

function scanIds(&$ids,&$tl,&$jr,&$args){
	$report = array();
	foreach($ids as $i => $t){
		switch ($t) {
			case 'T': //only in TestLink => obsolete
				$tlReq = treatObsolete($i,$tl,$args);
				if($tlReq['action'] == 0){ //i.e. already obsolete
					$ids[$i] = 't'; //i.e. nothing to do, already done
					$msg = 'Obsolete - Nothing to do';
				}else{
					$msg = 'Set to Obsolete';
				}
				$report[] = array(
					'doc_id' => $i,
					'title' => $tlReq['title'],
					'import_status' => $msg,
					'status_before' => $tlReq['status'],
					'status_after' => TL_REQ_STATUS_OBSOLETE
				);
				break;
			case 'J': //only in Jira => new req
				//treatNew($i,$jr,$args);
				break;
			default: //'B' - present in both lists
				$tlReq = treatModified($i,$tl,$jr,$args);
				if($tlReq['action'] == 0){ //i.e. no diff
					$ids[$i] = 'b'; //i.e. nothing to do
					$msg = 'No change';
					$status_after = $tlReq['status'];
				}else{
					$msg = 'Updated, set to Review';
					$status_after = TL_REQ_STATUS_REVIEW;
				}
				$report[] = array(
					'doc_id' => $i,
					'title' => $tlReq['title'],
					'import_status' => $msg,
					'status_before' => $tlReq['status'],
					'status_after' => $status_after
				);
				break;
		}
	}
	return $report;
}

function findTlReq($id,&$tl){
	foreach($tl as $doc_id => $tlReq){
		if($id == $doc_id){
			$tlReq['name'] = $tlReq['title'];
			$tlReq['item_id'] = $tlReq['version_id'];
			return $tlReq;
		}
	}
	return null;
}

function findJrReq($id,&$jr){
	foreach($jr['grp']['groups'] as $gkey => $group){
		foreach($group as $rkey => $jrReq){
			if($id == $jrReq['key']){
				return $jrReq;
			}
		}
	}
	return null;
}

function treatObsolete($id,&$tl,&$args){
	$tlReq = findTlReq($id,$tl);
	if($tlReq['status'] == TL_REQ_STATUS_OBSOLETE){
		$tlReq['action'] = 0;
		return $tlReq;
	}
	addReqVersionJira(
		$tlReq['id'], //$req_id,
		null, //$jira_req,
		$args->usr, //$usr,
		TL_REQ_STATUS_OBSOLETE, //$new_status,
		$args->req_mgr, //$reqMgr
		$args->tproject_id, //$tproject_id,
		true //$batch
		);
	$tlReq['action'] = 1;
	return $tlReq;
}

function treatNewOld($id,&$jr,&$args){
	$jrReq = findJrReq($id,$jr);
	$reqdoc_id = $jrReq['key'];
	$title = $jrReq['summary'];
	$scope = concatSummaryDescription($jrReq['summary'],$jrReq['description']);
	
	$args->req_mgr->create(
		1370284, //$srs_id,
		$reqdoc_id,
		$title,
		$scope,
		$args->usr, //$user_id,
		TL_REQ_STATUS_REVIEW //$status = TL_REQ_STATUS_VALID,
		//$type = TL_REQ_TYPE_INFO,
		//$expected_coverage=1,
		//$node_order=0,
		//$tproject_id=null, 
		//$options=null
	);
}

function treatModified($id,&$tl,&$jr,&$args){
	$tlReq = findTlReq($id,$tl);
	$jrReq = findJrReq($id,$jr);
	$sbs = array();
	$sbs['left_item'] = $tlReq;
	$scope = concatSummaryDescription($jrReq['summary'],$jrReq['description']);
	$sbs['right_item'] = array(
		'scope' => $scope,
		'status' => $tlReq['status'],
		'type' => $tlReq['type'],
		'expected_coverage' => $tlReq['expected_coverage'],
		'item_id' => $tlReq['item_id'],
		'name' => substr($jrReq['summary'],0,100),
		'fixversions' => $jrReq['fixVersions'],
		'jira_status' => $jrReq['status'],
		'us_url' => JIRA_TECHNO_URL_BROWSE . $jrReq['key'],
		'epic' => JIRA_TECHNO_URL_BROWSE . $jrReq['Epic Link (10006)'],
	);
	$ret = compareTJ($sbs,$args);
	$args->status_after = $ret['status_after'];
	if($tlReq['status'] == TL_REQ_STATUS_OBSOLETE){
		$args->status_after = TL_REQ_STATUS_REVIEW;
	}
	$ret = 0;
	if($args->status_after != ' '){
		addReqVersionJira(
			$tlReq['id'], //$req_id,
			$jrReq, //$jira_req,
			$args->usr, //$usr,
			$args->status_after, //$new_status,
			$args->req_mgr, //$reqMgr
			$args->tproject_id, //$tproject_id,
			true //$batch
			);		
		$ret = 1;
	}
	$tlReq['action'] = $ret;
	return $tlReq;
}

function classifyTlJr(&$tl,&$jr,&$args){
	$tl_ids = array();
	foreach($tl as $doc_id => $tlReq){
		$tl_ids[] = $doc_id;
	}

	$jr_ids = array();
	foreach($jr['grp']['groups'] as $gkey => $group){
		foreach($group as $rkey => $jrReq){
			$jr_ids[] = $jrReq['key'];
		}
	}

	$ids = array();
	foreach($tl_ids as $i => $tkey){
		foreach($jr_ids as $j => $jkey){
			if($tkey == $jkey){
				$ids[$tkey] = 'B';
				unset($jr_ids[$j]);
				break;
			}
		}
		if(isset($ids[$tkey])){
			unset($tl_ids[$i]);
		}
	}
	foreach($tl_ids as $i => $tkey){
		$ids[$tkey] = 'T';
	}
	foreach($jr_ids as $j => $jkey){
		$ids[$jkey] = 'J';
	}
	return $ids;
}

function getTlReqs(&$db,&$args){
	$tproject_mgr = new testproject($db);
	$req_mgr = new requirement_mgr($db);
	$reqIDs = $tproject_mgr->get_all_requirement_ids($args->req_spec_id);
	$tlReqs = array();
	foreach($reqIDs as $reqId){
		 $info = $req_mgr->get_by_id($reqId,requirement_mgr::LATEST_VERSION);
		 $doc_id = $info[0]['req_doc_id'];
		 $tlReqs[$doc_id] = $info[0];
	}
	return $tlReqs;
}

function getJiraJql(&$args){
	$jql = $args->jql;
	$pg = getJson($jql);
	return $pg;
}

function getCfJql($req_spec_mgr,$idCard){
  $cf_map = $req_spec_mgr->get_linked_cfields($idCard);
	foreach($cf_map as $k => $f){
		if($f['name'] == 'jql'){
			return $f['value'];
		}		
	}
	return '';
}

function init_args(&$db){
	$req_spec_mgr = new requirement_spec_mgr($db);
	$req_mgr = new requirement_mgr($db);
	$iParams = array(
		"req_spec_id" => array(tlInputParameter::INT_N),
		"refreshTree" => array(tlInputParameter::INT_N)
		);
	$args = new stdClass();
	$args->req_spec_mgr = $req_spec_mgr;
	$args->req_mgr = $req_mgr;
	R_PARAMS($iParams,$args);
	$args->tproject_id = isset($_SESSION['testprojectID']) ? intval($_SESSION['testprojectID']) : 0;
	$args->tproject_name = isset($_SESSION['testprojectName']) ? $_SESSION['testprojectName'] : null;
	$args->usr = $_SESSION['userID'];
  
	$args->req_spec = $req_spec_mgr->get_by_id($args->req_spec_id);
	$args->req_spec_revision_id = $args->req_spec['revision_id'];
	$idCard = array('parent_id' => $args->req_spec_id, 'item_id' => $args->req_spec_revision_id, 'tproject_id' => $args->tproject_id);
	$args->jql = getCfJql($req_spec_mgr,$idCard);
	return $args;
}

function initializeGui(&$dbHandler,&$argsObj,$session){
	$gui=new stdClass();
	$gui->file_check = array('status_ok' => 1, 'msg' => 'ok');
	$gui->items=null;
	$gui->importResult = null;
	$gui->refreshTree = false;

	$gui->req_spec = null;
	$gui->req_spec_id = $argsObj->req_spec_id;

	$gui->main_descr = sprintf(lang_get('tproject_import_req_spec'),$argsObj->tproject_name);

	$gui->status_value = array(
		' ' => ' ',
		TL_REQ_STATUS_VALID => 'Valid',
		TL_REQ_STATUS_NOT_TESTABLE => 'Not testable',
		TL_REQ_STATUS_DRAFT => 'Draft',
		TL_REQ_STATUS_REVIEW => 'Review',
		TL_REQ_STATUS_REWORK => 'Rework',
		TL_REQ_STATUS_FINISH => 'Finish',
		TL_REQ_STATUS_IMPLEMENTED => 'Implemented',
		TL_REQ_STATUS_OBSOLETE => 'Obsolete'
	);
	return $gui;    
}

function checkRights(&$db,&$user){
  return true;
}


?>