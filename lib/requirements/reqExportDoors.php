<?php
//AB20170908>
/** Coverage Export 
 *
**/
require_once("../../config.inc.php");
require_once("csv.inc.php");
require_once("xml.inc.php");
require_once("common.php");
require_once("requirements.inc.php");
require_once("reqExportCoverageConformity.php");

testlinkInitPage($db,false,false,"checkRights");
$templateCfg = templateConfiguration();
$req_spec_mgr = new requirement_spec_mgr($db);
$args = init_args();
$gui = initializeGui($db,$args,$req_spec_mgr);

switch($args->doAction)
{
  case 'export':
    $smarty = new TLSmarty();
    $smarty->assign('gui', $gui);
    $smarty->display($templateCfg->template_dir . $templateCfg->default_template);
  break;
    
  case 'doExport':
    $req_mgr = new requirement_mgr($db);
    $tplan_mgr = new testplan($db);
    $stat_map = setUpReqStatusCfg();
    $args->TestPlanName = $gui->tplans[$args->testPlan];
    doExport($args,$req_spec_mgr,$req_mgr,$tplan_mgr,$stat_map);
  break;
}

function checkRights(&$db,&$user)
{
  return $user->hasRight($db,'mgt_view_req');
}

function init_args()
{
  $_REQUEST = strings_stripSlashes($_REQUEST);
  $args = new stdClass();
  $args->doAction = isset($_REQUEST['doAction']) ? $_REQUEST['doAction'] : 'export';
  $args->exportType = isset($_REQUEST['exportType']) ? $_REQUEST['exportType'] : null;
  $args->req_spec_id = isset($_REQUEST['req_spec_id']) ? $_REQUEST['req_spec_id'] : null;
  $args->export_filename = isset($_REQUEST['export_filename']) ? $_REQUEST['export_filename'] : "";
  $args->testPlan = isset($_REQUEST['testPlan']) ? $_REQUEST['testPlan'] : null;
  
  $args->tproject_id = isset($_REQUEST['tproject_id']) ? $_REQUEST['tproject_id'] : 0;
    if( $args->tproject_id == 0 )
    { 
    $args->tproject_id = isset($_SESSION['testprojectID']) ? $_SESSION['testprojectID'] : 0;
  }
  $args->scope = isset($_REQUEST['scope']) ? $_REQUEST['scope'] : 'items';
  $args->user = $_SESSION['currentUser']; //AB141223
  return $args;  
}

function initializeGui(&$dbHandler,&$argsObj,&$req_spec_mgr)
{
  $gui = new stdClass();
  $gui->exportTypes = array('csv' => "CSV", 'json' => "JSON", 'xml' => "XML");
  $gui->exportType = $argsObj->exportType; 
  $gui->scope = $argsObj->scope;
  $gui->tproject_id = $argsObj->tproject_id;
  
   $gui->req_spec = $req_spec_mgr->get_by_id($argsObj->req_spec_id);
   $gui->req_spec_id = $argsObj->req_spec_id;   
 
  $gui->export_filename = trim($argsObj->export_filename);
  if($gui->export_filename == "")
  {
      $srs_id = $gui->req_spec['doc_id'];
      $exportFileName = $srs_id . "-" . $gui->req_spec['title'];
      $exportFileName = str_replace(array(" "),array("_"),$exportFileName);
      $gui->export_filename = $exportFileName . '_' . date('Ymdhis', time());
  }
  
  $gui->tplans = $argsObj->user->getAccessibleTestPlans($dbHandler,$argsObj->tproject_id,null,
        array('output' =>'combo'));

  return $gui;  
}

function doExport(&$argsObj,&$req_spec_mgr,&$req_mgr,&$tplan_mgr,&$stat_map)
{
  $pfn = null;
  $fileExt = '';
  switch($argsObj->exportType)
  {
    case 'csv':
        $pfn = "exportReqCovConfCsv";
        $fileExt = '.csv';
        $content = $pfn($req_spec_mgr,$req_mgr,$tplan_mgr,$argsObj->req_spec_id,
                $argsObj->testPlan,$argsObj->TestPlanName,$stat_map);
    break;

    case 'json':
        $pfn = "exportReqCovConfJson";
        $fileExt = '.json';
        $content = $pfn($req_spec_mgr,$req_mgr,$tplan_mgr,$argsObj->req_spec_id,
                $argsObj->testPlan,$argsObj->TestPlanName,$stat_map);
    break;

    case 'xml': //AB141218: this case is disabled in JavaScript ValidateForm function
      $pfn = "exportReqSpecToXML";
      $fileExt = '.xml';
      $content = TL_XMLEXPORT_HEADER;
      $optionsForExport['RECURSIVE'] = $argsObj->scope == 'items' ? false : true;
      $openTag = $argsObj->scope == 'items' ? "requirements>" : 'requirement-specification>';
      
      switch($argsObj->scope)
      {
        case 'tree':
          $reqSpecSet = $req_spec_mgr->getFirstLevelInTestProject($argsObj->tproject_id);
          $reqSpecSet = array_keys($reqSpecSet);
        break;
          
        case 'branch':
        case 'items':
          $reqSpecSet = array($argsObj->req_spec_id);
        break;
      }
      
      $content .= "<" . $openTag . "\n";
      if(!is_null($reqSpecSet))
      {
        foreach($reqSpecSet as $reqSpecID)
        {
          $content .= $req_spec_mgr->$pfn($reqSpecID,$argsObj->tproject_id,$optionsForExport);
        }
      }
      $content .= "</" . $openTag . "\n";
    break;
  }
  $argsObj->fileExt = $fileExt;

  if ($pfn)
  {
    $fileName = $argsObj->export_filename;
    if(strtolower(substr($fileName,-4)) != $fileExt){ 
        $fileName .= $fileExt;
    }
    downloadContentsToFile($content,$fileName);
    exit();
  }
}

//<AB20170908
?>