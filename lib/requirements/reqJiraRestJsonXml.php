<?php 
require_once('../../config.inc.php');
require_once('common.php');
require_once('exec.inc.php');
define('JIRA_TECHNO_URL','https://jira.techno.ingenico.com/'); 
define('JIRA_TECHNO_URL_BROWSE',JIRA_TECHNO_URL . 'browse/'); 

function getJson($jql){
	//$project = 'GREP'; //!!
	//$jql = '"Parent Initiative" ~ GREP and fixVersion is not null and fixVersion = NG-2 AND  "Sprint Latest" ~ GREP and status in ("Sprint Done","E2E Done","E2E failed","E2E testing")  ORDER BY cf[11604] ASC';
//"Parent Initiative" ~ GREP and fixVersion is not null and fixVersion = NG-2 AND  "Sprint Latest" ~ GREP and status in ("Sprint Done","E2E Done","E2E failed","E2E testing")  ORDER BY cf[11604] ASC

	$itsClient = getItsClient();

	$cnx = $itsClient->testLogin();
	if(!$cnx){
		return false;
	}
	
	$issues = getJql($itsClient,$jql);
	if(!$issues){
		return false;
	}
	$items = getEssentials($issues);

	$k = $items[0]['key'];
	$a = explode("-",$k);
	$project = $a[0];
	$prj = $itsClient->getProject($project); 
	
	$projectProperties = getProjectEssentials($prj);

	$groups = buildGroups($projectProperties,$items);
	return array('prj' => $projectProperties,'grp' => $groups);
}

function json2xml($prj,$groups,$jql){
	$xml = new SimpleXMLExtended('<?xml version="1.0" encoding="utf-8"?><requirement-specification></requirement-specification>');
	
	$url = "https://jira.techno.ingenico.com/issues/?jql=" . urlencode($jql);
	$root = add_spec($xml,$groups['key'],$groups['name'],'<p><a target="_blank" href="' . $url . '">JQL used</a></p>' );
//	$root = add_spec($xml,$groups['key'],$groups['name'],$jql);

	$cfs = $root->addChild('custom_fields');
	$cf = $cfs->addChild('custom_field');
	$cf->addChild('name','jql');
	$cf->addChild('value',$jql);

	foreach($groups['groups'] as $k => $g){
		group2xml($root,$k,$g);
	}
	return $xml;
}

function group2xml(&$xml,$key,$grp){
	$title = buildGroupTitle($grp);
	if(substr($title,0,strlen($key)+1) == $key . ' '){
		$title = substr($title,strlen($key)+1);
	}
	$spec = add_spec($xml,$key,$title,'...description...');
	foreach($grp as $k => $r){
		$description = buildReqDescription($r);
		add_req($spec,$r['key'],$r['summary'],$description,$r['fixVersions'],$r['status'],
			$r['Business Initiative (11200)'],$r['Epic Link (10006)'],$r['Parent Initiative (11604)']
		);
	}
}

function buildGroupTitle($group){
	$title = '';
	foreach($group as $r){
		$pi = $r['Parent Initiative (11604)'];
		if(strlen($pi) > strlen($title)){
			$title = $pi;
		}
	}
	return $title;
}

function concatSummaryDescription($summary,$description){
	$d = '';

	$d .= "<p>";
	$d .= $summary;
	$d .= "</p>";

	$d .= "<p>";
	$d .= $description;
	$d .= "</p>";

	$d = str_replace('&','and',$d);
	$d = str_replace('“','"',$d);
	$d = str_replace('”','"',$d);
	$d = str_replace('«','"',$d);
	$d = str_replace('»','"',$d);
	$d = str_replace('‘','"',$d);
	$d = str_replace('’','"',$d);
	$d = str_replace('–','-',$d);
	
	return $d;
	
}

function buildReqDescription($r){
	$txt = concatSummaryDescription($r['summary'],$r['description']);
	return $txt;
}

function add_spec(&$xml,$docid,$title,$description){
	$req_spec = $xml->addChild('req_spec');
	$req_spec->addAttribute('doc_id', $docid);
	$req_spec->addAttribute('title', substr($title,0,100));
	$req_spec->scope = NULL;
	$req_spec->scope->addCData($description);
	$req_spec->addChild('type',TL_REQ_SPEC_TYPE_SECTION);
	return $req_spec;
}

function add_req(&$xml,$docid,$title,$description,$fixversions,$status,
	$business_initiative,$epic_link,$parent_initiative
	){
	$req = $xml->addChild('requirement');
	$req->addChild('docid',$docid);
	$req->title = NULL;
	$req->title->addCData(substr($title,0,100));
	$req->description = NULL;
	$req->description->addCData($description);
	$req->addChild('type',TL_REQ_TYPE_USE_CASE);
	$req->addChild('status',TL_REQ_STATUS_DRAFT);
	$cfs = $req->addChild('custom_fields');
	$cf = $cfs->addChild('custom_field');
	$cf->addChild('name','fixversions');
	$cf->addChild('value',$fixversions);
	$cf = $cfs->addChild('custom_field');
	$cf->addChild('name','usurl');
	$cf->addChild('value','https://jira.techno.ingenico.com/browse/' . $docid);
	//$cf = $cfs->addChild('custom_field');
	//$cf->addChild('name','initiative');
	//$cf->addChild('value',$business_initiative);
	//$cf = $cfs->addChild('custom_field');
	//$cf->addChild('name','parent');
	//$cf->addChild('value',$parent_initiative);
	$cf = $cfs->addChild('custom_field');
	$cf->addChild('name','epic');
	$cf->addChild('value','https://jira.techno.ingenico.com/browse/' . $epic_link);
	$cf = $cfs->addChild('custom_field');
	$cf->addChild('name','jira_status');
	$cf->addChild('value',$status);
	return $req;
}

class SimpleXMLExtended extends SimpleXMLElement { //https://stackoverflow.com/questions/6260224/how-to-write-cdata-using-simplexmlelement
  public function addCData($cdata_text) {
    $node = dom_import_simplexml($this); 
    $no   = $node->ownerDocument; 
    $node->appendChild($no->createCDATASection($cdata_text)); 
  } 
}

function getGroupStat($groups){
	$sta = array();
	foreach($groups as $k => $g){
		$s = 'dim=' . count($g);
		$s .= ', items=[';
		foreach($g as $i){
			$s .= $i['key'] . ',';
		}
		$s .= ']';
		$sta[$k] = $s;
	}
	return $sta;
}
	
function buildGroups($project,&$items){
	$root = [
		'key' => $project['key'],
		'name' => $project['name'],
		'description' => $project['description']
	];
	$groups = [];
	foreach($items as $i => $item){
		if($item['type'] != 'Story'){
			continue;
		}
		$key = $item['Parent Initiative (11604)'];
		if(is_null($key)){
			$key = 'NULL';
		}else{
			$parts = preg_split('/\s+/', trim($key)); //https://stackoverflow.com/questions/1792950/explode-string-by-one-or-more-spaces-or-tabs
			$key = $parts[0];
		}
		//if(!array_key_exists($key,$groups)){
		if(!isset($groups[$key])){
			$groups[$key] = array();
		}
		$groups[$key][] = $item;
		$items[$i]['group'] = $key;
	}
	$root['groups'] = $groups;
	return $root;
}

function buildEpicTree($idx,$items){
	$epic = $items[$idx];
	$tree = [
		'key' => $epic['key'],
		'type' => $epic['type'],
		'name' => $epic['summary'],
		'description' => $epic['description']
	];
	$stories = [];
	$j = 0;
	$key = $epic['key'];
	foreach($items as $i => $item){
		if($item['type'] != 'Story'){
			continue;
		}
		if(!startsWith($item['Epic Link (10006)'],$key)){
			continue;
		}
		$stories[$j] = $item;
		$j++;
	}
	$tree['stories'] = $stories;
	return $tree;
}
	
function buildInitiativeTree($idx,$items){
	$initiative = $items[$idx];
	$tree = [
		'key' => $initiative['key'],
		'type' => $initiative['type'],
		'name' => $initiative['summary'],
		'description' => $initiative['description']
	];
	$epics = [];
	$j = 0;
	$key = $initiative['key'];
	foreach($items as $i => $item){
		if($item['type'] != 'Epic'){
			continue;
		}
		if(!startsWith($item['Business Initiative (11200)'],$key)){
			continue;
		}
		$epics[$j] = buildEpicTree($i,$items);
		//$epics[$j] = $item;
		$j++;
	}
	$tree['epics'] = $epics;
	return $tree;
}
	
function buildProjectTree($project,$items){
	$tree = [
		'key' => $project['key'],
		'name' => $project['name'],
		'description' => $project['description']
	];
	$intitiatives = [];
	$j = 0;
	foreach($items as $i => $item){
		if($item['type'] != 'Initiative'){
			continue;
		}
		$intitiatives[$j] = buildInitiativeTree($i,$items);
		$j++;
	}
	$tree['initiatives'] = $intitiatives;
	return $tree;
}

function getIssueEssentials($issue){
	$key = $issue->key;
	$id = $issue->id;
	$type = $issue->fields->issuetype->name;
	$summary = $issue->fields->summary;
	$description = $issue->fields->description;
	$business_initiative = isset($issue->fields->customfield_11200) ? $issue->fields->customfield_11200 : null;
	$epic_link = isset($issue->fields->customfield_10006) ? $issue->fields->customfield_10006 : null;
	$parent_initiative = isset($issue->fields->customfield_11604) ? $issue->fields->customfield_11604 : null;
	//$resolution = $issue->fields->resolution->name;
	$status = $issue->fields->status->name;
	//$priority = $issue->fields->priority->name;
	//$assignee = $issue->fields->assignee->key;
	$sprint_latest = isset($issue->fields->customfield_11912) ? $issue->fields->customfield_11912 : null;
	$fixVersions = array();
	foreach($issue->fields->fixVersions as $v){
		$fixVersions[] = $v->name;
	}
	
	$items = [
		'key' => $key,
		'id' => $id,
		'type' => $type,
		'summary' => trim($summary),
		'description' => $description,
		//'description' => 'omitted',
		'Business Initiative (11200)' => $business_initiative,
		'Epic Link (10006)' => $epic_link,
		'Parent Initiative (11604)' => $parent_initiative,
		//'resolution' => $resolution,
		'status' => $status,
		//'priority' => $priority,
		//'assignee' => $assignee,
		'Sprint Latest (11912)' => $sprint_latest,
		'fixVersions' => implode(",",$fixVersions)
	];
	return $items;
}

function getEssentials($issues){
	$items = [];
	$j = 0;
	foreach($issues as $i => $issue){
		$items[$j++] = getIssueEssentials($issue);
	}
	return $items;
}

function getJql($itsClient,$jql){
	$query = array(
		'JQL' => $jql		
	);
	$args = '&maxResults=-1&fields=id,key,issuetype,summary,description,fixVersions,customfield_11200,customfield_10006,customfield_11604,resolution,status,priority,assignee,customfield_11912';
	$items = $itsClient->queryIssue($query,$args); 
	return $items;
}

function getProjectEssentials($project){
	$key = $project->key;
	$name = $project->name;
	$description = $project->description;
	
	$items = [
		'key' => $key,
		'name' => $name,
		'description' => $description,
	];
	return $items;
}

function getItsClient(){
	testlinkInitPage($db,FALSE,TRUE);
    $it_mgr = new tlIssueTracker($db);
	$itd = $it_mgr->getByName('JIRA TECHNO');
	$iname = $itd['implementation'];
	$its = new $iname($itd['implementation'],$itd['cfg'],$itd['name']);
	return $its->getAPIClient();
}

function startsWith($haystack, $needle){ //https://stackoverflow.com/questions/834303/startswith-and-endswith-functions-in-php
     $length = strlen($needle);
     return (substr($haystack, 0, $length) === $needle);
}

function addReqVersionJira($req_id,$jira_req,$usr,$new_status,$reqMgr,$tproject_id,$batch){
	$date = date('d/m/Y h:i:s', time());
	if($batch){
		$log_message = 'Version added ' . $date . ' while synchronizing JQL';
	}else{
		$log_message = 'Version added ' . $date . ' after comparing with JIRA User Story';
	}
	$ret = $reqMgr->create_new_version($req_id,$usr,null,$log_message);
	$version = $ret['id'];
	
	if($new_status != TL_REQ_STATUS_OBSOLETE){
		//$jira_req = getJiraReq($req_doc_id);
		$scope = concatSummaryDescription($jira_req['summary'],$jira_req['description']);
		$reqdoc_id = $jira_req['key'];
		$title = $jira_req['summary'];
	}else{
		$scope = false;
		$reqdoc_id = false;
		$title = false;
	}
	$reqMgr->update(
		$req_id,
		$version,
		$reqdoc_id, 
		$title,
		$scope,
		$usr, //$user_id,
		$new_status, //$status,
		TL_REQ_TYPE_USE_CASE, //$type,
        1 //$expected_coverage,
		//$node_order=null,
		//$tproject_id=null,
		//$skip_controls=0,
        //$create_revision=false,
		//$log_msg=null
		);

	if($new_status != TL_REQ_STATUS_OBSOLETE){
		$cfs = $reqMgr->get_linked_cfields(
			$req_id,
			$version,
			$tproject_id
			);
		$cfs_jira = array();
		$hash = array();
		foreach($cfs as $n => $fld){
			$idx = 'custom_field_';
			$idx .= $fld['type'];
			$idx .= '_' . $n;
			switch ($fld['name']) {
				case 'fixversions':
					$hash[$idx] = $jira_req['fixVersions'];
					break;
				case 'usurl':
					$hash[$idx] = JIRA_TECHNO_URL_BROWSE . $jira_req['key'];
					break;
				case 'epic':
					$hash[$idx] = JIRA_TECHNO_URL_BROWSE . $jira_req['Epic Link (10006)'];
					break;
				case 'jira_status':
					$hash[$idx] = $jira_req['status'];
					break;
			}
		}	
		$reqMgr->values_to_db($hash,$version);
	}
}

function doReqImportFromXML(&$reqSpecMgr,&$reqMgr,&$simpleXMLObj,$importContext,$importOptions)
{
  $items = array();
  $isReqSpec = property_exists($simpleXMLObj,'req_spec');
  if($isReqSpec)
  {
    foreach($simpleXMLObj->req_spec as $xkm)
    {
      $dummy = $reqSpecMgr->createFromXML($xkm,$importContext->tproject_id,$importContext->req_spec_id,
                        $importContext->user_id,null,$importOptions);
      $items = array_merge($items,$dummy);
    }
  }   
  else
  {
    $loop2do = count($simpleXMLObj->requirement);
    for($kdx=0; $kdx < $loop2do; $kdx++)
    {   
      $dummy = $reqMgr->createFromXML($simpleXMLObj->requirement[$kdx],$importContext->tproject_id,
                                        $importContext->req_spec_id,$importContext->user_id,null,$importOptions);
      $items = array_merge($items,$dummy);
    }
  }
    return $items;
}

?>
