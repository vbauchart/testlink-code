<?php
/** 
 * TestLink Open Source Project - http://testlink.sourceforge.net/
 * This script is distributed under the GNU General Public License 2 or later. 
 *  
 * @filesource $RCSfile: reqSpecView.php,v $
 * @version $Revision: 1.43 $
 * @modified $Date: 2007/12/03 20:44:54 $ by $Author: schlundus $
 * @author Martin Havlat
 * 
 * Screen to view existing requirements within a req. specification.
 * 
 * rev: 20070415 - franciscom - custom field manager
 *      20070415 - franciscom - added reorder feature
 *
**/
require_once("../../config.inc.php");
require_once("common.php");
require_once("users.inc.php");
require_once('requirements.inc.php');
require_once('attachments.inc.php');
require_once("csv.inc.php");
require_once("xml.inc.php");
require_once('requirement_spec_mgr.class.php');
require_once('requirement_mgr.class.php');

require_once("../../third_party/fckeditor/fckeditor.php");
require_once(dirname("__FILE__") . "/../functions/configCheck.php");
testlinkInitPage($db);

$req_spec_mgr=new requirement_spec_mgr($db);
$req_mgr=new requirement_mgr($db);

$get_cfield_values=array();
$get_cfield_values['req_spec']=0;
$get_cfield_values['req']=0;

$user_feedback='';
$js_msg = null;
$sqlResult = null;
$action = null;
$sqlItem = 'SRS';
$arrReq = array();
$bGetReqs = TRUE; // collect requirements as default
$template = 'reqSpecView.tpl';

$_REQUEST = strings_stripSlashes($_REQUEST);
$reqDocId = isset($_REQUEST['reqDocId']) ? trim($_REQUEST['reqDocId']) : null;
$title = isset($_REQUEST['title']) ? trim($_REQUEST['title']) : null;

$idSRS = isset($_REQUEST['idSRS']) ? $_REQUEST['idSRS'] : null;
$idReq = isset($_REQUEST['idReq']) ? $_REQUEST['idReq'] : null;
$scope = isset($_REQUEST['scope']) ? $_REQUEST['scope'] : null;
$reqStatus = isset($_REQUEST['reqStatus']) ? $_REQUEST['reqStatus'] : TL_REQ_STATUS_VALID;
$reqType = isset($_REQUEST['reqType']) ? $_REQUEST['reqType'] : TL_REQ_TYPE_1;
$countReq = isset($_REQUEST['countReq']) ? intval($_REQUEST['countReq']) : 0;
$bCreate = isset($_REQUEST['create']) ? intval($_REQUEST['create']) : 0;

$tprojectID = isset($_SESSION['testprojectID']) ? $_SESSION['testprojectID'] : 0;
$userID = isset($_SESSION['userID']) ? $_SESSION['userID'] : 0;
$login_name = isset($_SESSION['user']) ? $_SESSION['user'] : null;

$do_export = isset($_REQUEST['exportAll']) ? 1 : 0;
$exportType = isset($_REQUEST['exportType']) ? $_REQUEST['exportType'] : null;

$do_create_tc_from_req = isset($_REQUEST['create_tc_from_req']) ? 1 : 0;
$do_delete_req = isset($_REQUEST['req_select_delete']) ? 1 : 0;

$reorder = isset($_REQUEST['req_reorder']) ? 1 : 0;
$do_req_reorder = isset($_REQUEST['do_req_reorder']) ? 1 : 0;

$arrCov = null;

$tproject = new testproject($db);
$smarty = new TLSmarty();

$of = new fckeditor('scope') ;
$of->BasePath = $_SESSION['basehref'] . 'third_party/fckeditor/';
$of->ToolbarSet = $g_fckeditor_toolbar;;

// create a new requirement.
if(isset($_REQUEST['createReq']))
{
	$cf_smarty = $req_mgr->html_table_of_custom_field_inputs(null,$tprojectID);
	$smarty->assign('cf',$cf_smarty);
	if ($bCreate)
	{
		$ret = $req_mgr->create($idSRS,$reqDocId,$title, $scope,$userID,$reqStatus, $reqType);
		$user_feedback = $ret['msg'];	                                 
		if($ret['status_ok'])
		{
			$user_feedback = sprintf(lang_get('req_created'), $reqDocId);  
	    
	    $cf_map = $req_mgr->get_linked_cfields(null,$tprojectID) ;
     	 //$req_mgr->values_to_db($_REQUEST,$ret['id'],$cf_map);
		}
	}

  $scope = '';
	$template = 'reqCreate.tpl';
	$bGetReqs = FALSE;
} 
elseif (isset($_REQUEST['editReq']))
{
	$srs = $req_spec_mgr->get_by_id($idSRS);
  
	$smarty->assign('srs_title',$srs['title']);	

	$idReq = intval($_REQUEST['editReq']);
	$arrReq = $req_mgr->get_by_id($idReq);
	if ($arrReq)
	{
		$arrReq['author'] = getUserName($db,$arrReq['author_id']);
		$arrReq['modifier'] = getUserName($db,$arrReq['modifier_id']);
		// $arrReq['coverage'] = $req_mgr->Tc4Req($db,$idReq);
		$arrReq['coverage'] = $req_mgr->get_relationships($idReq);
		
		$reqDocId = $arrReq['req_doc_id'];
		$scope = $arrReq['scope']; 
	}
	$action = 'editReq';
	$template = 'reqEdit.tpl';

	// get custom fields
	$cf_smarty = $req_mgr->html_table_of_custom_field_inputs($idReq);
	$smarty->assign('cf',$cf_smarty);


	$smarty->assign('id',$idReq);	
	$smarty->assign('tableName','requirements');
	$attachmentInfos = getAttachmentInfosFrom($req_mgr,$idReq);
	$smarty->assign('attachmentInfos',$attachmentInfos);	
  // -----------------------------------------------------------
	$bGetReqs = FALSE;
}
elseif (isset($_REQUEST['updateReq']))
{
	$sqlResult = $req_mgr->update($idReq,trim($reqDocId),$title, 
	                              $scope, $userID, $reqStatus, $reqType);
	                              
  $cf_map = $req_mgr->get_linked_cfields(null,$tprojectID) ;
  $req_mgr->values_to_db($_REQUEST,$idReq,$cf_map);
	                              
	$action = 'update';
	$sqlItem = 'Requirement';
}
elseif (isset($_REQUEST['deleteReq']))
{
	$sqlResult = $req_mgr->delete($idReq);
	$action = 'delete';
}
elseif (isset($_REQUEST['editSRS']))
{
	$template = 'reqSpecEdit.tpl';
	
	// get custom fields
	$cf_smarty = $req_spec_mgr->html_table_of_custom_field_inputs($idSRS);
	$smarty->assign('cf',$cf_smarty);
	$action = "editSRS";
}
elseif (isset($_REQUEST['updateSRS']))
{
	$ret=$req_spec_mgr->update($idSRS,$title,$scope,$countReq,$userID);
	$sqlResult=$ret['msg'];
	$get_cfield_values['req_spec']=1;
	
	if( $ret['status_ok'] )
	{
    $cf_map = $req_spec_mgr->get_linked_cfields($idSRS);
    $req_spec_mgr->values_to_db($_REQUEST,$idSRS,$cf_map);
	} 

	$action = 'do_update';
}
elseif ($do_create_tc_from_req || $do_delete_req )
{
	$arrIdReq = isset($_POST['req_id_cbox']) ? $_POST['req_id_cbox'] : null;
	
	if (count($arrIdReq) != 0) {
		if($do_delete_req) 
		{
			foreach ($arrIdReq as $idReq) {
				tLog("Delete requirement id=" . $idReq);
				$tmpResult = $req_mgr->delete($idReq);
				if ($tmpResult != 'ok') {
					$sqlResult .= $tmpResult . '<br />';
				}
			}
			if (empty($sqlResult)) {
				$sqlResult = 'ok';
			}
			$action = 'delete';
		} 
		elseif ($do_create_tc_from_req) 
		{
			  $sqlResult = $req_mgr->create_tc_from_requirement($arrIdReq,$idSRS,$userID);
			  $action = 'do_add';
			  $sqlItem = 'testcases';
		}
	} 
	else 
	{
	    if($do_create_tc_from_req)
	    {
		  	$js_msg = lang_get('cant_create_tc_from_req_nothing_sel');
	    }
	    if($do_delete_req)
	    {
	  		$js_msg = lang_get('cant_delete_req_nothing_sel');
	    }
	}
}
elseif( $reorder )
{
  $bGetReqs=TRUE;
  $template = 'req_spec_order.tpl';
}
elseif( $do_req_reorder )
{
	$nodes_order = isset($_REQUEST['nodes_order']) ? $_REQUEST['nodes_order'] : null;
	$nodes_in_order = transform_nodes_order($nodes_order);
	$req_mgr->set_order($nodes_in_order);
	$get_cfield_values['req_spec']=1;
}
else
{
  $get_cfield_values['req_spec']=1;
}

// 20071106 - franciscom
if( $get_cfield_values['req_spec'] )
{
	// get custom fields
	$cf_smarty = $req_spec_mgr->html_table_of_custom_field_values($idSRS);
	$smarty->assign('cf',$cf_smarty);
}


// collect existing reqs for the SRS
if ($bGetReqs)
{
	$arrReq = $req_spec_mgr->get_requirements($idSRS);
}

// collect existing document data
$arrSpec = $tproject->getReqSpec($tprojectID,$idSRS);
$arrSpec[0]['author'] = getUserName($db,$arrSpec[0]['author_id']);
$arrSpec[0]['modifier'] = getUserName($db,$arrSpec[0]['modifier_id']);
$srs_title = $arrSpec[0]['title'];


$smarty->assign('idSRS', $idSRS);
$smarty->assign('user_feedback', $user_feedback);
$smarty->assign('srs_title', $srs_title);
$smarty->assign('arrSpec', $arrSpec);
$smarty->assign('arrReq', $arrReq);
$smarty->assign('arrCov', $arrCov);
$smarty->assign('sqlResult', $sqlResult);
$smarty->assign('sqlItem', $sqlItem);
$smarty->assign('action', $action);
$smarty->assign('name',$title);
$smarty->assign('selectReqStatus', $arrReqStatus);
$smarty->assign('modify_req_rights', has_rights($db,"mgt_modify_req")); 

$of->Value="";
if (!is_null($scope))
	$of->Value=$scope;
else if ($action && $action != 'create')
{
	$of->Value=$arrSpec[0]['scope'];
}

$export_types=$req_spec_mgr->get_export_file_types();

if($do_export)
{
	$reqData = $req_spec_mgr->get_requirements($idSRS);
	$pfn = null;
	switch(strtoupper($exportType))
	{
		case 'CSV':
			$pfn = "exportReqDataToCSV";
			$fileName = 'reqs.csv';
			break;
		case 'XML':
			$pfn = "exportReqDataToXML";
			$fileName = 'reqs.xml';
			break;
	}
	if ($pfn)
	{
		$content = $pfn($reqData);
		downloadContentsToFile($content,$fileName);
		
		// why this exit() ?
		// If we don't use it, we will find in the exported file
		// the contents of the smarty template.
		exit();
	}
}
// ----------------------------------------------------------

$smarty->assign('js_msg',$js_msg);
$smarty->assign('exportTypes',$export_types);
$smarty->assign('scope',$of->CreateHTML());
$smarty->display($template);
?>