<?php
/** 
 * TestLink Open Source Project - http://testlink.sourceforge.net/
 * This script is distributed under the GNU General Public License 2 or later. 
 *
 * @package 	  TestLink
 * @author      asimon
 * @copyright   2005-2012, TestLink community 
 * @filesource  reqCompareVersions.php
 * @link 		    http://www.teamst.org/index.php
 *
 * Compares selected requirements versions with each other.
 *
 * @internal revisions
 * @since 1.9.6
 */

require_once("../../config.inc.php");
require_once("common.php");
require_once("reqDiffer.inc.php");
require_once('reqJiraRestJsonXml.php');

$templateCfg = templateConfiguration();
testlinkInitPage($db);
$smarty = new TLSmarty();

$args = init_args($db);
$gui = initializeGui($db,$args);

if($args->add_version == 'OK'){
	if(($args->status_after != TL_REQ_STATUS_OBSOLETE) || ($args->status_before != TL_REQ_STATUS_OBSOLETE)){
		addVersionJira($db,$gui,$args);
	}
    $tpl = "reqView.php?requirement_id={$args->req_id}";
	header("Location: {$tpl}");
	exit();
}else{
	compareTestLinkJira($db,$gui,$args,$smarty,$templateCfg);
}

function addVersionJira($db,$gui,$args){
	$req_id = $args->req_id;
	$jira_req = null;
	if($new_status != TL_REQ_STATUS_OBSOLETE){
		$bb = getBareBonesReq($db,$req_id);
		$req_doc_id = $bb['req_doc_id'];
		$jira_req = getJiraReq($req_doc_id);
	}
	addReqVersionJira(
		$req_id,
		$jira_req,
		$_SESSION['userID'], //$usr,
		$args->status_after, //$new_status,
		$args->req_mgr,
		$args->tproject_id, //$tproject_id,
		false //$batch
	);
}

function compareTestLinkJira($db,$gui,$args,$smarty,$templateCfg){
	$sbs = getItemsToCompare($gui->history);

	$ret = compareTJ($sbs,$args);
	$gui->status_before = $ret['status_before'];
	$gui->status_after = $ret['status_after'];
	$gui->jira_status = $ret['jira_status'];
	$gui->diff = $ret['diff'];
	$gui->attrDiff = $ret['attrDiff'];
	$gui->cfieldsDiff = $ret['cfieldsDiff'];
	
	$gui->add_new_version = 'yes';
	if($gui->status_after == ' '){
		$gui->status_after = $gui->status_before;
		$gui->add_new_version = 'no';
	}
	$gui->leftID = ' TestLink ';
	$gui->rightID = ' JIRA ';

	$smarty->assign('gui', $gui);
	$smarty->display($templateCfg->template_dir . $templateCfg->default_template);
}

function getBareBonesReq($dbHandler,$reqID)
{
	$debugMsg = ' Function: ' . __FUNCTION__;
	$tables = tlObjectWithDB::getDBTables(array('requirements','nodes_hierarchy'));
	$sql = 	" /* $debugMsg */ SELECT REQ.req_doc_id, NH_REQ.name " .
			" FROM {$tables['requirements']} REQ " .
			" JOIN {$tables['nodes_hierarchy']} NH_REQ	ON  NH_REQ.id = REQ.id " .
			" WHERE REQ.id = " . intval($reqID);
			
	$bones = $dbHandler->get_recordset($sql);		

	return $bones[0];
}

function getLastVersionInfo(&$itemSet){
	$v = 0;
	$r = 0;
	$last = 0;
	foreach($itemSet as $item) 
	{
		$v1 = $item['version'];
		$r1 = $item['revision'];
		if(($v1 * 1000 + $r1) > ($v * 1000 + $r)) {
			$last = $item;
			$v = $v1;
			$r = $r1;
		}
	}
	return $last;
}

function getItemsToCompare(&$itemSet)
{
	$lr = array();
	$lr['left_item'] = getLastVersionInfo($itemSet);
	$item = $lr['left_item'];

	$jira_req = getJiraReq($lr['left_item']['req_doc_id']);	
	$scope = concatSummaryDescription($jira_req['summary'],$jira_req['description']);
	
	$lr['right_item'] = array(
		'scope' => $scope,
		'status' => $item['status'],
		'type' => $item['type'],
		'expected_coverage' => $item['expected_coverage'],
		'item_id' => $item['item_id'],
		'name' => substr($jira_req['summary'],0,100),
		'fixversions' => $jira_req['fixVersions'],
		'us_url' => JIRA_TECHNO_URL_BROWSE . $jira_req['key'],
		'epic' => JIRA_TECHNO_URL_BROWSE . $jira_req['Epic Link (10006)'],
		'jira_status' => $jira_req['status'],
	);
	
	return $lr;
}

function getJiraReq($req){
	$itsClient = getItsClient();
	$issue = $itsClient->getIssue($req); 
	if(isset($issue->key)){
		$items = getIssueEssentials($issue);
	}else{
		$items = [
			'key' => $req,
			'id' => 0,
			'summary' => '',
			'description' => '',
			'Epic Link (10006)' => '',
			'status' => 'Missing',
			'fixVersions' => ''
		];
	}
	return $items;
}

function init_args(&$db){
	$args = new stdClass();
	$args->req_id = isset($_REQUEST['requirement_id']) ? $_REQUEST['requirement_id'] : 0;
	$args->add_version = isset($_REQUEST['add_version']) ? $_REQUEST['add_version'] : '';
	$args->status_after = isset($_REQUEST['status_after']) ? $_REQUEST['status_after'] : '';
    $args->tproject_id = isset($_SESSION['testprojectID']) ? $_SESSION['testprojectID'] : 0;
	$diffEngineCfg = config_get("diffEngine");
	$args->req_mgr = new requirement_mgr($db);

	$args->context = null;
	if( !isset($_REQUEST['context_show_all'])) 
	{
		$args->context = (isset($_REQUEST['context']) && is_numeric($_REQUEST['context'])) ? $_REQUEST['context'] : $diffEngineCfg->context;
	}
	return $args;
}

function initializeGui(&$dbHandler,&$argsObj){
	$reqCfg = config_get('req_cfg');
	$guiObj = new stdClass();
    $guiObj->history = $argsObj->req_mgr->get_history($argsObj->req_id,array('output' => 'array','decode_user' => true));
	
	// Truncate log message
	if( $reqCfg->log_message_len > 0 )
	{	
		$loop2do = count($guiObj->history);
		for($idx=0; $idx < $loop2do; $idx++)
		{
			if( strlen($guiObj->history[$idx]['log_message']) > $reqCfg->log_message_len )
			{
				$guiObj->history[$idx]['log_message'] = substr($guiObj->history[$idx]['log_message'],0,$reqCfg->log_message_len) . '...';
			}
			// 20101215 - Julian: removed nl2br() to avoid multiline on compare page. tooltip shows better formatting.
			$guiObj->history[$idx]['log_message'] = htmlspecialchars($guiObj->history[$idx]['log_message']);
		}
	} 
	$guiObj->req_id = $argsObj->req_id;
	$guiObj->context = $argsObj->context;
	$guiObj->add_version = $argsObj->add_version;
	//$guiObj->version_short = $lbl['version_short'];
	//$guiObj->version_short = null;
	$guiObj->diff = null;
	
	$guiObj->status_value = array(
		TL_REQ_STATUS_VALID => 'Valid',
		TL_REQ_STATUS_NOT_TESTABLE => 'Not testable',
		TL_REQ_STATUS_DRAFT => 'Draft',
		TL_REQ_STATUS_REVIEW => 'Review',
		TL_REQ_STATUS_REWORK => 'Rework',
		TL_REQ_STATUS_FINISH => 'Finish',
		TL_REQ_STATUS_IMPLEMENTED => 'Implemented',
		TL_REQ_STATUS_OBSOLETE => 'Obsolete'
	);
	
	return $guiObj;
}
?>