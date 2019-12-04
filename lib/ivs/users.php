<?php

//https://testlink-ivs-qual.ingenico.com/testlink_180129/lib/ivs/users.php?command=getInfo&login=pmcarrot
//https://testlink-ivs-qual.ingenico.com/testlink_180129/lib/ivs/users.php?command=setRole&login=sruch&project=SMR&role=leader
//https://testlink-ivs-qual.ingenico.com/testlink_180129/lib/ivs/users.php?command=setRole&login=sruch&project=SMR&role=delete
//https://testlink-ivs-qual.ingenico.com/testlink_180129/lib/ivs/users.php?command=setRole&login=gmlik&project=*&role=delete
//https://testlink-ivs-qual.ingenico.com/testlink_180129/lib/ivs/users.php?command=setRole&login=pmcarrot&project=root&role=leader

define('DBG',0);


require_once('../../config.inc.php');
require_once('common.php');

$params = get_input();
if($params['role'] == 'admin'){
	$params['role'] = 'guest';
}
if($params['login'] == 'abarantsev'){
	$params['login'] = 'none';
}
if($params['login'] == 'bevain'){
	$params['login'] = 'none';
}
testlinkInitPage($db,FALSE,TRUE);
html_dump($params['command'],"command");
switch($params['command']){
	case 'getInfo': 
		$ret= getInfo($db,$params);
		break;
	case 'setRole': 
		$ret= setRole($db,$params);
		break;
	default:
		$ret = -1;
		break;
}

html_dump($ret,"ret");
echo $ret;

function setRole($db,$params){
	$login = $params['login'];
	$project = $params['project'];
	$role = $params['role'];
	switch($project){
		case '*':
			setAllRole($db,$login,$role);
			break;
		case 'root':
			setRootRole($db,$login,$role);
			break;
		default:
			setProjectRole($db,$login,$project,$role);
			break;
	}
	return getInfo($db,$params);
}

function setAllRole($db,$login,$role){
	$user_id = getUserId($db,$login);
	if($role == 'delete'){
		updateUtrRole($db,$user_id,0,$role_id);
	}else{
		$role_id = getRoleId($db,$role);
		deleteUtr($db,$user_id,0);
	}
}

function setRootRole($db,$login,$role){
	$user_id = getUserId($db,$login);
	$role_id = getRoleId($db,$role);
	$sql = "UPDATE users SET role_id = '$role_id' WHERE id = '$user_id'";
	return $db->exec_query($sql);
}

function setProjectRole($db,$login,$project,$role){
	$user_id = getUserId($db,$login);
	$project_id = getProjectId($db,$project);
	$role_id = 0;
	if($role != 'delete'){
		$role_id = getRoleId($db,$role);
	}
	html_dump($user_id,"user_id");
	html_dump($project_id,"project_id");
	html_dump($role_id,"role_id");
	
	$ret = getUtrRole($db,$user_id,$project_id);
	if(!is_null($ret)){
		if($role_id != $ret){
			updateUtrRole($db,$user_id,$project_id,$role_id);
		}
	}else{
		if($role_id != 0){
			insertUtrRole($db,$user_id,$project_id,$role_id);
		}else{
			deleteUtr($db,$user_id,$project_id);
		}
	}
}

function updateUtrRole($db,$user_id,$project_id,$role_id){
	if($project_id != 0){
		$sql = "UPDATE user_testproject_roles SET role_id = '$role_id' WHERE user_id = '$user_id' AND testproject_id = '$project_id'";
	}else{
		$sql = "UPDATE user_testproject_roles SET role_id = '$role_id' WHERE user_id = '$user_id'";
	}
	return $db->exec_query($sql);
}

function deleteUtr($db,$user_id,$project_id){
	if($project_id != 0){
		$sql = "DELETE FROM user_testproject_roles WHERE user_id = '$user_id' AND testproject_id = '$project_id'";
	}else{
		$sql = "DELETE FROM user_testproject_roles WHERE user_id = '$user_id'";
	}
	return $db->exec_query($sql);
}

function insertUtrRole($db,$user_id,$project_id,$role_id){
	$sql = "INSERT INTO user_testproject_roles (user_id,testproject_id,role_id)	VALUES('$user_id','$project_id','$role_id')";
	return $db->exec_query($sql);
}

function getUserId($db,$login){
	$sql = "SELECT id FROM users WHERE login = '$login'";
	return $db->fetchOneValue($sql);
}

function getProjectId($db,$project){
	$sql = "SELECT id FROM testprojects WHERE prefix = '$project'";
	return $db->fetchOneValue($sql);
}

function getRoleId($db,$role){
	$sql = "SELECT id FROM roles WHERE description = '$role'";
	return $db->fetchOneValue($sql);
}

//function getUtrRole($db,$login,$project){
function getUtrRole($db,$user_id,$project_id){
	//SELECT r.description  //utr.role_id
	//	FROM user_testproject_roles utr, roles r, users u, testprojects p
	//	WHERE u.id = utr.user_id AND utr.role_id = r.id AND utr.testproject_id = p.id
	//		AND u.login = 'sruch' AND p.prefix = 'SMR'
	//$sql = "SELECT utr.role_id FROM user_testproject_roles utr, roles r, users u, testprojects p ";
	//$sql .=  "WHERE u.id = utr.user_id AND utr.role_id = r.id AND utr.testproject_id = p.id ";
	//$sql .=  "AND u.login = '$login' AND p.prefix = '$project'";
	$sql = "SELECT role_id FROM user_testproject_roles WHERE user_id = '$user_id' AND testproject_id = '$project_id'";
	$ret = $db->fetchOneValue($sql);
	return $ret;
}

function getInfo($db,$params){
	$login = $params['login'];
	$info = getUserLogin($db,$login);
	$info_gen = array(
		'id' => $info['id'],
		'login' => $info['login'],
		'email' => $info['email'],
		'first' => $info['first'],
		'last' => $info['last'],
		'role_id' => $info['role_id'],
		'role' => $info['description'],
		'active' => $info['active'],
		'auth_method' => $info['auth_method']
	);
	html_dump($json,"json");
	$tmp = getUserProjectRoles($db,$params['login']);
	//$project_roles = $tmp[$info['id']];
	$project_roles = $tmp[$login];
	html_dump($project_roles,"project_roles");
	$info_roles = array();
	foreach($project_roles as $i => $r){
		$info_roles[$i] = array(
			'user_id' => $r['user_id'],
			'login' => $r['login'],
			'testproject_id' => $r['testproject_id'],
			'testproject_prefix' => $r['prefix'],
			'role_id' => $r['role_id'],
			'role' => $r['description']
		);
	}
	html_dump($info_roles,"info_roles");	
	
	$json = json_encode( array(
		'general' => $info_gen,
		'project_roles' => $info_roles
	));
	return $json;
}

function getUserProjectRoles($db,$login){
	html_dump($login,"login");
	$sql = "SELECT r.user_id,u.login,r.testproject_id,p.prefix,r.role_id,d.description ";
	$sql .=   "FROM user_testproject_roles r, testprojects p, users u, roles d ";
	$sql .=   "WHERE u.login='$login' AND p.id = r.testproject_id AND u.id = r.user_id AND d.id = r.role_id ORDER BY prefix;";
//SELECT r.user_id,u.login,r.testproject_id,p.prefix,r.role_id,d.description
//	FROM user_testproject_roles r, testprojects p, users u, roles d
//	WHERE u.login='pmcarrot' AND p.id = r.testproject_id AND u.id = r.user_id AND d.id = r.role_id;
	html_dump($sql,"sql");
	//$ret = $db->fetchRowsIntoMap($sql,'user_id',1);
	$ret = $db->fetchRowsIntoMap($sql,'login',1);
	html_dump($ret,"ret");
	return $ret;
}

function getUserLogin($db,$login){
	html_dump($login,"login");
	$sql = "SELECT u.id, u.login, u.first, u.last, u.email, u.role_id, r.description , u.active, u.auth_method ";
	$sql .= "FROM users u, roles r ";
	$sql .= "WHERE u.role_id = r.id AND u.login = '$login'";
	
//SELECT u.id, u.login, u.first, u.last, u.email, u.role_id, r.description , u.active, u.auth_method
//  FROM users u, roles r
//  WHERE u.role_id = r.id AND u.login = 'bevain'
  
//tLog(basename(__FILE__) . '.' . __FUNCTION__ . '::sql','ERROR',"AB");
//tLog(print_r($sql,TRUE),'ERROR',"AB");
	html_dump($sql,"sql");
	$ret = $db->fetchFirstRow($sql);
	html_dump($ret,"ret");
	return $ret;
}

function get_input(){
	$url = $_SERVER["REQUEST_URI"];
	html_dump($url,"url");
	$str = parse_url($url, PHP_URL_QUERY);
	html_dump($str,"str");
	parse_str($str, $params);
	html_dump($params,"params");
	return $params;
}

function html_dump($var,$str=''){
	if(DBG == 1){
		echo $str . ':<pre>' . var_export($var, true) . '</pre>';
	}
}
?>
