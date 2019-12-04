<?php
require_once("../../config.inc.php");
require_once("common.php");
require('../../third_party/diff/diff.php');
require('../../third_party/daisydiff/src/HTMLDiff.php');

function compareTJ($sbs,&$args){
	$labels = init_labels(
		array(
			"num_changes" => null,
			"no_changes" => null, 
			"diff_subtitle_req" => null,
			"version_short" => null,
			"diff_details_req" => null,
			"type" => null,
			"status" => null,
			"name" => null,
			"expected_coverage" => null,
			"revision_short" => null,
			"version_revision" => null
		)
	);
	$status_before = $sbs['left_item']['status'];
	
	$attrDiff = getAttrDiff($sbs['left_item'],$sbs['right_item'],$labels);
	
	$cfields = getCFToCompare($sbs,$args);
	$cfieldsDiff = null;
	if( !is_null($cfields) ){
		$cfieldsDiff = getCFDiff($cfields,$args->req_mgr);
	}
	
	$diff = array("scope" => array());
	foreach($diff as $key => $val){
		$htmlDiffer = new HTMLDiffer();
		list($differences, $diffcount) = $htmlDiffer->htmlDiff($sbs['left_item'][$key], $sbs['right_item'][$key]);
		$diff[$key]["diff"] = $differences;
		$diff[$key]["count"] = $diffcount;
		$diff[$key]["heading"] = lang_get($key);
		
		// are there any changes? then display! if not, nothing to show here
		$additional = '';
		$msg_key = "no_changes";
		if ($diff[$key]["count"] > 0) 
		{
			$msg_key = "num_changes";
			$additional = $diff[$key]["count"];
		}		
		$diff[$key]["message"] = sprintf($labels[$msg_key], $key, $additional);
	}

	$status_after = getJiraStatus($diff,$attrDiff,$cfieldsDiff);
	
	return array(
		'status_before' => $status_before,
		'diff' => $diff,
		'attrDiff' => $attrDiff,
		'cfieldsDiff' => $cfieldsDiff,
		'status_after' => $status_after,
		'jira_status' => $sbs['right_item']['jira_status']
	);
}

function getAttrDiff($leftSide,$rightSide,$labels){
	$req_cfg = config_get('req_cfg'); 
	$key2loop = array(
		'name' => null, 
		//'status' => 'status_labels',
		//'type' => 'type_labels',
		//'expected_coverage' => null
	);
	foreach($key2loop as $fkey => $lkey)
	{
		// Need to decode
		$cmp[$fkey] = array('label' => htmlspecialchars($labels[$fkey]),
		                   'lvalue' => $leftSide[$fkey],'rvalue' => $rightSide[$fkey],
		                   'changed' => $leftSide[$fkey] != $rightSide[$fkey]);
		             
		if( !is_null($lkey) )
		{
			$decode = $req_cfg->$lkey;
			$cmp[$fkey]['lvalue'] = lang_get($decode[$cmp[$fkey]['lvalue']]);
			$cmp[$fkey]['rvalue'] = lang_get($decode[$cmp[$fkey]['rvalue']]);
		}                   
	}		
	return $cmp;	
}

function getCFToCompare($sides,&$args){
	$cfields = array('left_side' => array('key' => 'left_item', 'value' => null), 
		'right_side' => array('key' => 'right_item', 'value' => null));
		
	$target_id = $sides['left_item']['item_id'];
	$cfs = $args->req_mgr->get_linked_cfields(null,$target_id,$args->tproject_id);
	
	foreach($cfs as $n => $fld){
		$cfields['left_side']['value'][$n] = $fld;
		$cfields['right_side']['value'][$n]['name'] = $fld['name'];
		$cfields['right_side']['value'][$n]['label'] = $fld['label'];
		switch ($fld['name']) {
			case 'fixversions':
				$cfields['right_side']['value'][$n]['value'] = $sides['right_item']['fixversions'];
				break;
			case 'usurl':
				$cfields['right_side']['value'][$n]['value'] = $sides['right_item']['us_url'];
				break;
			case 'epic':
				$cfields['right_side']['value'][$n]['value'] = $sides['right_item']['epic'];
				break;
			case 'jira_status':
				$cfields['right_side']['value'][$n]['value'] = $sides['right_item']['jira_status'];
				break;
		}
	}
	return $cfields;
}

function getCFDiff($cfields,&$reqMgr)
{
	// echo __FUNCTION__;
	$cmp = null;
	
	// Development Note
	// All versions + revisions (i.e. child items) have the same qty of linked CF
	// => both arrays will have same size()
	//
	// This is because to get cfields we look only to CF enabled for node type.
	$cfieldsLeft = $cfields['left_side']['value'];
	$cfieldsRight = $cfields['right_side']['value'];
	if( !is_null($cfieldsLeft) )
	{
		$key2loop = array_keys($cfieldsLeft);
		$cmp = array();
		$type_code = $reqMgr->cfield_mgr->get_available_types();
		$key2convert = array('lvalue','rvalue');
		
		$formats = array('date' => config_get( 'date_format'));
		$cfg = config_get('gui');
		$cfCfg = config_get('custom_fields');
		foreach($key2loop as $cf_key)
		{
			// $cfg->show_custom_fields_without_value 
			// false => At least one value has to be <> NULL to include on comparsion results
			// 
		    if( $cfCfg->show_custom_fields_without_value == true ||
		    	($cfCfg->show_custom_fields_without_value == false &&
		    	 ( (!is_null($cfieldsRight) && !is_null($cfieldsRight[$cf_key]['value'])) ||
		    	   (!is_null($cfieldsLeft) && !is_null($cfieldsLeft[$cf_key]['value'])) )
		      	) 
		      )		 
		    {	  
				$cmp[$cf_key] = array('label' => htmlspecialchars($cfieldsLeft[$cf_key]['label']),
				                      'lvalue' => $cfieldsLeft[$cf_key]['value'],
				                      'rvalue' => !is_null($cfieldsRight) ? $cfieldsRight[$cf_key]['value'] : null,
				                      'changed' => $cfieldsLeft[$cf_key]['value'] != $cfieldsRight[$cf_key]['value']);
			
				if($type_code[$cfieldsLeft[$cf_key]['type']] == 'date' ||
				   $type_code[$cfieldsLeft[$cf_key]['type']] == 'datetime') 
				{
					$t_date_format = str_replace("%","",$formats['date']); // must remove %
					foreach($key2convert as $fx)
					{
						if( ($doIt = ($cmp[$cf_key][$fx] != null)) )
						{
							switch($type_code[$cfieldsLeft[$cf_key]['type']])
							{
								case 'datetime':
    	    				            $t_date_format .= " " . $cfg->custom_fields->time_format;
								break ;
							}
						}	                       
						if( $doIt )
						{
						  	$cmp[$cf_key][$fx] = date($t_date_format,$cmp[$cf_key][$fx]);
						}
					}
				} 
			} // mega if
		}  // foreach		
	}
	return count($cmp) > 0 ? $cmp : null;	
}

function getJiraStatus($diff,$attrDiff,$cfieldsDiff){
	$mod = false;
	$obs = false;
	$new = false;
	foreach($attrDiff as $key => $val) {
		if($key == 'status'){
			continue;
		}
		if($key == 'name'){
			if($val['rvalue'] == ''){
				$obs = true;
			}else if(strlen($val['lvalue'])<2){
				$new = true;
			}
		}
		if($val['changed'] >0){
			$mod = true;
		}
	}
	
	foreach($diff as $key => $val) {
		if($val['count'] >0){
			$mod = true;
		}
	}

	foreach($cfieldsDiff as $key => $val) {
		if($val['changed'] >0){
			$mod = true;
		}
	}
	
	$ret = ' ';
	if($obs) {
		$ret = TL_REQ_STATUS_OBSOLETE;
	}else if($mod) {
		if($new){
			$ret = TL_REQ_STATUS_DRAFT;
		}else{
			$ret = TL_REQ_STATUS_REVIEW;
		}
	}	
	
	return $ret;
}

?>