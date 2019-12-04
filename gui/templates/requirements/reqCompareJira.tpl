{include file="inc_head.tpl" openHead='yes' jsValidate="yes"}
{include file="inc_del_onclick.tpl"}

{lang_get var="labels"
          s="select_versions,title_compare_versions_req,version,compare,modified,modified_by,
          btn_compare_selected_versions, context, show_all,author,timestamp,timestamp_lastchange,
          warning_context, warning_context_range, warning_empty_context,warning,custom_field, 
          warning_selected_versions, warning_same_selected_versions,revision,attribute,
          custom_fields,attributes,log_message,use_html_code_comp,use_html_comp,diff_method,
          btn_cancel"}

<link rel="stylesheet" type="text/css" href="{$basehref}third_party/diff/diff.css">
<link rel="stylesheet" type="text/css" href="{$basehref}third_party/daisydiff/css/diff.css">

<script type="text/javascript">
//BUGID 3943: Escape all messages (string)
var alert_box_title = "{$labels.warning|escape:'javascript'}";
var warning_empty_context = "{$labels.warning_empty_context|escape:'javascript'}";
var warning_context_range = "{$labels.warning_context_range|escape:'javascript'}";
var warning_selected_versions = "{$labels.warning_selected_versions|escape:'javascript'}";
var warning_same_selected_versions = "{$labels.warning_same_selected_versions|escape:'javascript'}";
var warning_context = "{$labels.warning_context|escape:'javascript'}";

{literal}
// 20110107 - new diff engine
function triggerContextInput(selected) {
	var context = document.getElementById("context_input");
	if (selected == 0) {
		context.style.display = "none";
	} else {
		context.style.display = "table-row";;
	}
}

function triggerField(field)
{
	if (field.disabled == true) {
    	field.disabled = false;
	} else {
    	field.disabled = true;
	}
}

function triggerRadio(radio, field) {
    	radio[0].checked = false;
    	radio[1].checked = false;
    	radio[field].checked = true;
    	triggerContextInput(field);
}

function valButton(btn) {
    var cnt = -1;
    for (var i=btn.length-1; i > -1; i--) {
        if (btn[i].checked) {
        	cnt = i;
        	i = -1;
        }
    }
    if (cnt > -1) {
    	return true;
    }
    else {
    	return false;
    }
}

function validateForm() {
	return true;
}

</script>
{/literal}

</head>
<body>

{if $gui->jira_status == 'Missing'}
	<h1 class="title">Obsolete requirement</h1> 
	<p>The requirement is missing in JIRA</p>
	{if $gui->status_before == 'O'}
		<p>The Status in TestLink is already Obsolete</p>
		<p>Nothing to do</p>

		<form method="post" action="lib/requirements/reqCompareJira.php" name="req_compare_jira" id="req_compare_jira" onsubmit="return validateForm();" />			
			<p><input type="button" name="OK" value="OK" onclick="javascript:history.back();" /></p>
		</form>
	{else}
		<p>New version will be created, and its status will be set to Obsolete</p>
		<p>Status before: {$gui->status_value[$gui->status_before]}</p>
		<p>Status after: {$gui->status_value[$gui->status_after]}</p>

		<form method="post" action="lib/requirements/reqCompareJira.php" name="req_compare_jira" id="req_compare_jira" onsubmit="return validateForm();" />			
		<p>
			<input type="hidden" name="requirement_id" value="{$gui->req_id}" />
			<input type="hidden" name="status_after" value="{$gui->status_after}" />
			<input type="submit" name="add_version" value="OK"/>
			<input type="button" name="cancel" value="{$labels.btn_cancel}" onclick="javascript:history.back();" />
		</p>
		</form>
	{/if}	
{else}
	<h1 class="title">{$labels.title_compare_versions_req}</h1> 
	<div class="workBack" style="width:99%; overflow:auto;">	
	{if $gui->add_new_version != 'no'}	
		<h1>Differences between the latest version/revision in testlink and the User Story in JIRA Techno</h1>
		<p>A new version will be added</p>
		<p>Status before: {$gui->status_value[$gui->status_before]}</p>
		<p>Status after: {$gui->status_value[$gui->status_after]}</p>
		<form method="post" action="lib/requirements/reqCompareJira.php" name="req_compare_jira" id="req_compare_jira" onsubmit="return validateForm();" />			
		<p>
			<input type="hidden" name="requirement_id" value="{$gui->req_id}" />
			<input type="hidden" name="status_after" value="{$gui->status_after}" />
			<input type="button" name="cancel" value="{$labels.btn_cancel}" onclick="javascript:history.back();" />
			<input type="submit" name="add_version" value="OK" />
		</p>
		</form>

		{if $gui->attrDiff != ''}
		  <h2>{$labels.attributes}</h2>
		  <table border="1" cellspacing="0" cellpadding="2" style="width:60%" class="code">
			<thead>
			  <tr>
				<th style="text-align:left;">{$labels.attribute}</th>
				<th style="text-align:left;">{$gui->leftID}</th>
				<th style="text-align:left;">{$gui->rightID}</th>
			  </tr>
			</thead>
			<tbody>
			  {foreach item=attrDiff from=$gui->attrDiff}
			  <tr>
				<td class="{if $attrDiff.changed}del{else}ins{/if}"; style="font-weight:bold">{$attrDiff.label}</td>
				<td class="{if $attrDiff.changed}del{else}ins{/if}";>{$attrDiff.lvalue}</td>
				<td class="{if $attrDiff.changed}del{else}ins{/if}";>{$attrDiff.rvalue}</td>
			  </tr>
			{/foreach}
			</tbody>
		  </table>
		  <p />
		{/if}

		{foreach item=diff from=$gui->diff}
			<h2>{$diff.heading}</h2>
			<fieldset class="x-fieldset x-form-label-left" >
				<legend class="legend_container" >{$diff.message}</legend>
				{if $diff.count > 0}{$diff.diff}{/if}
			</fieldset>
		{/foreach}
		
		{if $gui->cfieldsDiff != ''}
			<p/>
			<h2>{$labels.custom_fields}</h2>
			<table border="1" cellspacing="0" cellpadding="2" style="width:60%" class="code">
				<thead>
				<tr>
					<th style="text-align:left;">{$labels.custom_field}</th>
					<th style="text-align:left;">{$gui->leftID}</th>
					<th style="text-align:left;">{$gui->rightID}</th>
				</tr>
				</thead>
				<tbody>
					{foreach item=cfDiff from=$gui->cfieldsDiff}
						<tr>
							<td class="{if $cfDiff.changed}del{else}ins{/if}"; style="font-weight:bold">{$cfDiff.label}</td>
							<td class="{if $cfDiff.changed}del{else}ins{/if}";>{$cfDiff.lvalue}</td>
							<td class="{if $cfDiff.changed}del{else}ins{/if}";>{$cfDiff.rvalue}</td>
						</tr>
					{/foreach}
				</tbody>
			</table>
		{/if}

		<form method="post" action="lib/requirements/reqCompareJira.php" name="req_compare_jira" id="req_compare_jira"  onsubmit="return validateForm();" />			
		<p>
			<input type="hidden" name="requirement_id" value="{$gui->req_id}" />
			<input type="hidden" name="status_after" value="{$gui->status_after}" />
			<input type="button" name="cancel" value="{$labels.btn_cancel}" onclick="javascript:history.back();" />
			<input type="submit" name="add_version" value="OK" />
		</p>
		</form>
	{else}
		<h1>No difference found</h1>
		<form method="post" action="lib/requirements/reqCompareJira.php" name="req_compare_jira" id="req_compare_jira"  onsubmit="return validateForm();" />			
		<p>
			<input type="button" name="OK" value="OK" onclick="javascript:history.back();" />
		</p>
		</form>
    {/if}	
	</div>
{/if}
</body>

</html>
