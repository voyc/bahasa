<?php
// (c) Copyright 2008, 2012 John Hagstrand
//----------------------------------------------------------

require_once('config.php');
require_once('jlog.php');
openjlog(basename(__FILE__));

//establish a connection to database 
$conn = @pg_connect("port=$dbport dbname=$dbname user=$dbuser password=$dbpassword");
if (!$conn) {
	jlog(JLOG_DEBUG,"unable to connect");
}

function loadVocab() {
	global $conn;

	// inputs
	$taint_on = isset($_GET['on']) ? $_GET['on'] : 0;
	
	// on is all alpha chars + max three periods.  No spaces.  No other punctuation.
	$on = $taint_on;

	// the query
	$sql  = "select id, host, eng, part, cat ";
	$sql .= "from flash.vocab";

	//execute SQL query 
	$result = @pg_query($conn, $sql);
	if (!$result) {
	    echo "Query error ".pg_last_error()." $sql";
	    return;
	}
	
	// return the resultset
	$numrows = pg_num_rows($result);
	$arr = array();
	for ($i=0; $i<$numrows; $i++) {
		$row = pg_fetch_array($result, $i, PGSQL_ASSOC);
		$arr[(int)$row['id']] = array('id' => (int)$row['id'], 'host' => $row['host'], 'eng' => $row['eng'], 'part' => $row['part'], 'cat' => $row['cat']);
	}
	echo "g.data.vocab = " . json_encode($arr) . ";\n";
	
	// return the id
	echo "$on.onVocabLoaded();";
	return;
}

function loadMatch() {
	global $conn;

	// inputs
	$taint_on = isset($_GET['on']) ? $_GET['on'] : 0;
	
	// on is all alpha chars + max three periods.  No spaces.  No other punctuation.
	$on = $taint_on;

	// the query
	$sql  = "select id, lvocabid, lpart, rpart, rvocabid ";
	$sql .= "from flash.match";

	//execute SQL query 
	$result = @pg_query($conn, $sql);
	if (!$result) {
	    echo "Query error ".pg_last_error()." $sql";
	    return;
	}

	// return the resultset
	$numrows = pg_num_rows($result);
	$arr = array();
	for ($i=0; $i<$numrows; $i++) {
		$row = pg_fetch_array($result, $i, PGSQL_ASSOC);
		$arr[(int)$row['id']] = array('id' => (int)$row['id'], 'lvocabid' => (int)$row['lvocabid'], 'lpart' => $row['lpart'], 'rpart' => $row['rpart'], 'rvocabid' => (int)$row['rvocabid']);
	}
	echo "g.data.match = " . json_encode($arr) . ";\n";
	
	// return the id
	echo "$on.onMatchLoaded();";
	return;
}

//----------------------------
//----------------------------
//----------------------------
	
function login() {
	global $conn;
	
	// inputs
	$taint_on = isset($_GET['on']) ? $_GET['on'] : 0;
	$taint_email = isset($_GET['email']) ? $_GET['email'] : 0;
	$taint_pword = isset($_GET['pword']) ? $_GET['pword'] : 0;
	$taint_lu = isset($_GET['lu']) ? $_GET['lu'] : 0;
	
	// on is all alpha chars + max three periods.  No spaces.  No other punctuation.
	$on = $taint_on;
	
	// email
	$email = $taint_email;
	
	// pword
	$pword = $taint_pword;
	
	// pword
	$lu = $taint_lu;
	
	// the query
	$sql  = "select id, username, programid";
	$sql .= " from flash.user";
	if ($lu) {
		$sql .= " where id = (select userid from flash.token where token = '". $lu ."')";
	}
	else {
		$sql .= " where username = '". $email."'";
		$sql .= " and password = '". $pword."'";
	}
	
	//execute SQL query 
	$result = @pg_query($conn, $sql);
	if (!$result) {
	    echo "Query error ".pg_last_error()." $sql";
	    return;
	}
	
	// get the number of rows in the resultset
	$numrows = pg_num_rows($result);
	
	$id = 0;
	$msg = "";
	$name = "";
	if ($numrows) {
		$row = pg_fetch_array($result, 0, PGSQL_ASSOC);
		$id = $row['id'];
		$name = $row['username'];
		$programid = $row['programid'];
	}
	else {
		$msg = "Email/password not found";
	}
	
	// calc a new token
	if (!$lu) {
		$lu = writeToken($id);
		if (!$lu) {
			$msg = 'Login failed due to system error.';
		}
	}
	
	if ($lu) {
		$a = getPrograms($id);
		echo "g.data.programs = " . json_encode($a) . ";\n";
	}
	
	// return the id
	echo "$on.onLogin(\"$lu\", \"$name\", \"$msg\", $programid);";
	return;
}

function register() {
	global $conn;
	
	// inputs
	$taint_on = isset($_GET['on']) ? $_GET['on'] : 0;
	$taint_email = isset($_GET['email']) ? $_GET['email'] : 0;
	$taint_pword = isset($_GET['pword']) ? $_GET['pword'] : 0;
	
	// on is all alpha chars + max three periods.  No spaces.  No other punctuation.
	$on = $taint_on;
	
	// email - one word all ascii chars, no spaces
	if (strlen($taint_email) <= 0) {
		jlog(LOG_DEBUG, "Query error ".pg_last_error()." $sql");
		echo "$on.onRegister(0, \"\", \"email (username) should be one word, no spaces\");";
		return;
	}
	$email = $taint_email;
	
	// pword - one word all ascii chars, no spaces
	if (strlen($taint_pword) <= 0) {
		echo "$on.onRegister(0, \"\", \"password should be one word, no spaces\");";
		return;
	}
	$pword = $taint_pword;
	
	// get the next user id
	$sql = "SELECT nextval('flash.user_id_seq')";
	$result = @pg_query($conn, $sql);
	if (!$result) {
		jlog(LOG_DEBUG, "Query error ".pg_last_error()." $sql");
		echo "$on.onRegister(0, \"\", \"Unable to register. System error.\");";
		return;
	}
	$numrows = pg_affected_rows($result);
	if ($numrows < 0) {
		jlog(LOG_DEBUG, "Query error ".pg_last_error()." $sql");
		echo "$on.onRegister(0, \"\", \"Unable to register. System error.\");";
		return;
	}
	$row = pg_fetch_array($result, 0, PGSQL_ASSOC);
	$id = $row['nextval'];
	
	// write the user record
	$sql  = "insert";
	$sql .= " into flash.user";
	$sql .= " (id, username, password, programid)";
	$sql .= " values (".$id.",'".$email."','".$pword."',1)";
	$result = @pg_query($conn, $sql);
	if (!$result) {
	 	jlog(LOG_DEBUG, "Insert error ".pg_last_error()." $sql");
		echo "$on.onRegister(0, \"\", \"Unable to register.  This email/password is already in use.\");";
	    return;
	}
	$numrows = pg_affected_rows($result);
	if (!$numrows) {
		$msg = "Unable to register.  Try a different email/password.";
		echo "$on.onRegister(0, \"\", \"Unable to register. Try a different email/password.\");";
		return;
	}
	
	// write a token record
	$lu = writeToken($id);
	if (!$lu) {
		$msg = "Unable to register.  System error.";
	}

	if ($lu) {
		$a = getPrograms($id);
		echo "g.data.programs = " . json_encode($a) . ";\n";
	}
	
	// return the id
	echo "$on.onRegister(\"$lu\", \"$email\", \"$msg\", 1);";
	return;
}

function setprogram() {
	global $conn;
	
	// inputs
	$taint_on = isset($_GET['on']) ? $_GET['on'] : 0;
	$taint_lu = isset($_GET['lu']) ? $_GET['lu'] : 0;
	$taint_lw = isset($_GET['lw']) ? $_GET['lw'] : 0;
	
	// on is all alpha chars + max three periods.  No spaces.  No other punctuation.
	$on = $taint_on;
	
	// userid
	$lu = $taint_lu;
	
	// programid
	$lw = $taint_lw;

	$id = getUserIdForToken($lu);
	
	$msg = 'ok';
	
	// the query
	$sql  = "update flash.user set programid = $lw where id = $id";
	$rc = executeSql($conn, $sql);
	if (!$rc) {
		$msg = 'Failed.';
	}
	// return the id
	echo "$on.onProgramLoaded($lw, '$msg');";
	return;
}

function resetprogram() {
	global $conn;
	
	// inputs
	$taint_on = isset($_GET['on']) ? $_GET['on'] : 0;
	$taint_lu = isset($_GET['lu']) ? $_GET['lu'] : 0;
	$taint_lw = isset($_GET['lw']) ? $_GET['lw'] : 0;
	
	// on is all alpha chars + max three periods.  No spaces.  No other punctuation.
	$on = $taint_on;
	
	// userid
	$lu = $taint_lu;
	
	// programid
	$lw = $taint_lw;

	$id = getUserIdForToken($lu);
	
	$upid = getUserProgramId($id, $program);

	$msg = 'ok';
	
	// the query
	$sql = "delete from flash.progress where userprogramid = $upid and id in ( ";
	$sql .= "select p.id ";
	$sql .= "from flash.quest q ";
	$sql .= "left outer join flash.progress p on (q.id = p.questid) ";
	$sql .= "where q.programid = $lw ";
	$sql .= "order by q.seq ";
	$sql .= ")";

	$rc = executeSql($conn, $sql);
	if (!$rc) {
		$msg = 'Failed.';
	}
	// return the id
	echo "$on.onProgramReset($upid, '$msg');";
	return;
}

function getflash() {
	global $conn;

	// inputs
	$taint_lu = isset($_GET['lu']) ? $_GET['lu'] : 0;
	$taint_program = isset($_GET['program']) ? $_GET['program'] : 0;
	$taint_on = isset($_GET['on']) ? $_GET['on'] : 0;
	$taint_action = isset($_GET['action']) ? $_GET['action'] : 0;
	$taint_data = isset($_GET['data']) ? $_GET['data'] : 0;
	$taint_req = isset($_GET['req']) ? $_GET['req'] : 0;
	$taint_highseq = isset($_GET['n']) ? $_GET['n'] : 0;
	
	// token
	$lu = $taint_lu;
	
	// program is positive integer between 1 and max_int
	$program = $taint_program;
	
	// on is all alpha chars + max three periods.  No spaces.  No other punctuation.
	$on = $taint_on;
	
	// action is either get or put
	$action = ($taint_action == "get") ? "get" : "put";
	
	// data is a json string
	$data = rawurldecode($taint_data);
	
	// req is the number of untried questions requested, sometimes 0
	//if ($taint_req is number between 0 and 1000
	$req = $taint_req;
	
	// highseq is the highest seq already received by the client
	//if ($taint_highseq is number between 0 and 1000
	$highseq = $taint_highseq;
	
	$id = getUserIdForToken($lu);

	$upid = getUserProgramId($id, $program);

	// on a put, update the database first, then do a query and return questions to the page
	if ($action == "put") {
		update($upid, $data);
		if ($req > 0) {
			$whereClause = "p.state is null and q.seq > " . $highseq;
			$maxUntriedCount = $req;
			$limit = $req;
		}
		else {
			return;
		}
	} 
	
	// on a get, do the initial query of w and r questions along with the untried
	else { // $action == "get"
		$maxUntriedCount = $req;
		$limit = $req * 2;
		$whereClause = "(p.state <> 'm' or p.state is null)";
	}
	
	// the query
	$sql  = "select q.id, q.seq, q.quest, q.answer, p.state";
	$sql .= " from flash.quest q";
	$sql .= " left outer join flash.progress p on (q.id = p.questid and p.userprogramid = $upid)";
	$sql .= " where q.programid = ". $program;
	$sql .= " and (p.userprogramid = $upid or p.userprogramid is null)";
	$sql .= " and $whereClause";
	$sql .= " order by q.seq";
	$sql .= " limit $limit";

	//execute SQL query 
	$result = @pg_query($conn, $sql);
	if (!$result) {
	    echo "Query error ".pg_last_error()." $sql";
	    return;
	}
	
	// get the number of rows in the resultset
	$numrows = pg_num_rows($result);
	
	// begin the script
	echo "$on.lesson = [\n";
	
	// iterate through resultset
	$untriedCount = 0;
	for ($j=0; $j<$numrows; $j++) {
	    $row = pg_fetch_array($result, $j, PGSQL_ASSOC);
	    $i = $row['id'];
	    $n = $row['seq'];
	    $q = addslashes($row['quest']);
	    $a = addslashes($row['answer']);
	    $s = $row['state'];
	
	    if ($s == "") {
	    	$s = "u";
		    $untriedCount++;
		}
		if ($untriedCount > $maxUntriedCount) {
			break;
		}
	
	    echo "{i:$i,n:$n,q:\"$q\",a:\"$a\",s:\"$s\"},\n";
	}
	
	// finish the script
	echo "]; $on.onLessonDataLoaded();";
	return;
}

function update($upid, $data) {
	global $conn;
	$decoded = json_decode($data);
	foreach($decoded as $key => $val) {
		//execute SQL update
		$value = (array) $val;
		$sql = "update flash.progress set state='".$value['s']."',asked=".$value['a'].",correct=".$value['c'];
		$sql .= " where questid = ".$value['i']." and userprogramid = $upid";
		$result = pg_query($conn, $sql);
	
		// if that fails, try an insert
		if (!$result || !pg_affected_rows($result)) {
			$sql = "insert into flash.progress (userprogramid,questid,state,asked,correct) values ($upid,".$value['i'].",'".$value['s']."',".$value['a'].",".$value['c'].")";
			$result = @pg_query($conn, $sql);
			if (!$result || !pg_affected_rows($result)) {
			    echo "Insert error ".pg_last_error()." $sql";
			}
		}
	}
}

function getPrograms() {
	global $conn;

	// the query
	$sql  = "select p.id, p.name";
	$sql .= " from flash.program p";
	
	//execute SQL query 
	$result = @pg_query($conn, $sql);
	if (!$result) {
		jlog(JLOG_DEBUG,"query failed ** ".pg_last_error()." ** ".$sql);
		return false;
	}
	
	// return the resultset
	$numrows = pg_num_rows($result);
	$arr = array();
	for ($i=0; $i<$numrows; $i++) {
		$row = pg_fetch_array($result, $i, PGSQL_ASSOC);
		$arr[(int)$row['id']] = $row['name'];
	}
	return $arr;	
}

function getUserProgramId($id, $programid) {
	global $conn;
	$sql = "select id from flash.userprogram where userid = $id and programid = $programid";
	$result = @pg_query($conn, $sql);
	if (!$result) {
		jlog(JLOG_DEBUG, "lookup failed ".pg_last_error()." $sql");
		return 0;
	}
	
	// if no record, insert it now
	$numrows = pg_num_rows($result);
	if ($numrows < 1) {
		$sql = "insert into flash.userprogram (userid, programid, startdate) values ($id, $programid, now())";
		$result = @pg_query($conn, $sql);
		if (!$result) {
			jlog(JLOG_DEBUG, "insert failed".pg_last_error()." $sql");
			return 0;
		}

		$sql = "select id from flash.userprogram where userid = $id and programid = $programid";
		$result = @pg_query($conn, $sql);
		if (!$result) {
			jlog(JLOG_DEBUG, "lookup failed ".pg_last_error()." $sql");
			return 0;
		}
	}
	$row = pg_fetch_array($result, 0, PGSQL_ASSOC);
	$upid = (int)$row['id'];
	return $upid;
}

function executeSql($conn, $sql) {
	$result = @pg_query($conn, $sql);
	if (!$result) {
		jlog(JLOG_DEBUG,"executeSql failed ** ".pg_last_error()." ** ".$sql);
		return false;
	}
	
	$numrows = pg_affected_rows($result);
	if ($numrows <= 0) {
		jlog(JLOG_DEBUG,"no records updated");
	}
	return true;
}

function getUserIdForToken($token) {
	global $conn;
	$sql = "select userid from flash.token where token = '$token'";
	$result = @pg_query($conn, $sql);
	if (!$result) {
		jlog(JLOG_DEBUG, "Token lookup failed".pg_last_error()." $sql");
		return 0;
	}
	$numrows = pg_num_rows($result);
	if ($numrows < 1) {
		jlog(JLOG_DEBUG, "Token lookup returned no row".pg_last_error()." $sql");
		return 0;
	}
	$row = pg_fetch_array($result, 0, PGSQL_ASSOC);
	$uid = (int)$row['userid'];
	return $uid;
}

function writeToken($id) {
	global $conn;

	// calc a new token
	$ct = mktime();
	$lu = md5('moogoo'.$ct);
	
	// write a token record
	$sql = "insert into flash.token (userid, token) values ($id, '$lu')";
	$rc = executeSql($conn, $sql);
	if (!$rc) {
		return false;
	}
	return $lu;
}

//----------------------------------------------------------
// (c) Copyright 2008, 2012 John Hagstrand
?>