<?php
// (c) Copyright 2008, 2012 John Hagstrand
//----------------------------------------------------------

require_once('config.php');
require_once('jlog.php');
require_once('dbUtilities.php');
openjlog(basename(__FILE__));

$conn = getConnection();

function loadVocab() {
	global $conn;

	// inputs
	$taint_on = isset($_GET['on']) ? $_GET['on'] : 0;
	$taint_lu = isset($_GET['lu']) ? $_GET['lu'] : 0;
	$taint_lw = isset($_GET['lw']) ? $_GET['lw'] : 0;
	
	// on is all alpha chars + max three periods.  No spaces.  No other punctuation.
	$on = $taint_on;
	
	// token
	$lu = $taint_lu;
	
	// programid
	$lw = $taint_lw;

	$id = getUserIdForToken($lu);
	$upid = getUserProgramId($id, $lw);

	$sql  = "select uv.id as uvid, v.id as vid, v.host, v.eng, v.part, v.cat, coalesce(uv.state, 'un') as state, coalesce(uv.askednormal,0) as askednormal, coalesce(uv.correctnormal,0) as correctnormal, coalesce(uv.askedreverse,0) as askedreverse, coalesce(uv.correctreverse,0) as correctreverse, uv.tunormal, uv.tureverse ";
	$sql .= "from bahasa.vocab v ";
	$sql .= "left outer join bahasa.uservocab uv on (uv.vocabid = v.id) ";
	$sql .= "where v.part != 'pat' ";
	$sql .= "and uv.userprogramid = $upid ";
	$sql .= "and uv.state = 'mr'";
	$result = executeQuery($sql);
	$numrows = pg_num_rows($result);
	jlog(JLOG_DEBUG, "loadVocab $numrows $sql");
	$arr = array();
	for ($i=0; $i<$numrows; $i++) {
		$row = pg_fetch_array($result, $i, PGSQL_ASSOC);
		$arr[(int)$row['uvid']] = array('uvid' => (int)$row['uvid'], 'vid' => (int)$row['vid'], 'host' => $row['host'], 'eng' => $row['eng'], 'part' => $row['part'], 'cat' => $row['cat'], 's' => $row['state'], 'an' => $row['askednormal'], 'cn' => $row['correctnormal'], 'ar' => $row['askedreverse'], 'cr' => $row['correctreverse'], 'tun' => $row['tunormal'], 'tur' => $row['tureverse']);
	}

	header('Content-type: text/javascript');
	echo "g.data.vocab = " . json_encode($arr) . ";\n";
	echo "$on.onVocabLoaded();";
	return;
}

function loadPattern() {
	global $conn;

	// inputs
	$taint_on = isset($_GET['on']) ? $_GET['on'] : 0;
	
	// on is all alpha chars + max three periods.  No spaces.  No other punctuation.
	$on = $taint_on;

	$sql  = "select v.id, v.host, v.eng, v.part, v.cat ";
	$sql .= "from bahasa.vocab v ";
	$sql .= "join bahasa.uservocab uv on (uv.vocabid = v.id) ";
	$sql .= "where part = 'pat' ";
	$sql .= "and uv.state = 'mr'";
	$result = executeQuery($sql);
	$numrows = pg_num_rows($result);
	$arr = array();
	for ($i=0; $i<$numrows; $i++) {
		$row = pg_fetch_array($result, $i, PGSQL_ASSOC);
		$arr[(int)$row['id']] = array('id' => (int)$row['id'], 'host' => $row['host'], 'eng' => $row['eng'], 'part' => $row['part'], 'cat' => $row['cat']);
	}

	header('Content-type: text/javascript');
	echo "g.data.pattern = " . json_encode($arr) . ";\n";
	echo "$on.onPatternLoaded();";
	return;
}

function loadMatch() {
	global $conn;

	// inputs
	$taint_on = isset($_GET['on']) ? $_GET['on'] : 0;
	
	// on is all alpha chars + max three periods.  No spaces.  No other punctuation.
	$on = $taint_on;

	$sql  = "select id, lvocabid, lpart, rpart, rvocabid ";
	$sql .= "from bahasa.vocabmatch";
	$result = executeQuery($sql);
	$numrows = pg_num_rows($result);
	$arr = array();
	for ($i=0; $i<$numrows; $i++) {
		$row = pg_fetch_array($result, $i, PGSQL_ASSOC);
		$arr[(int)$row['id']] = array('id' => (int)$row['id'], 'lvocabid' => (int)$row['lvocabid'], 'lpart' => $row['lpart'], 'rpart' => $row['rpart'], 'rvocabid' => (int)$row['rvocabid']);
	}

	header('Content-type: text/javascript');
	echo "g.data.match = " . json_encode($arr) . ";\n";
	echo "$on.onMatchLoaded();";
	return;
}

//----------------------------
//----------------------------
//----------------------------
	
function login() {
	global $conn;
	
	// inputs
	$taint_on = isset($_GET['on']) ? $_GET['on'] : '';
	$taint_email = isset($_GET['email']) ? $_GET['email'] : '';
	$taint_pword = isset($_GET['pword']) ? $_GET['pword'] : '';
	$taint_lu = isset($_GET['lu']) ? $_GET['lu'] : '';
	
	// on is all alpha chars + max three periods.  No spaces.  No other punctuation.
	$on = $taint_on;
	
	// email
	$email = $taint_email;
	
	// password
	$password = $taint_pword;
	
	// lu (token)
	$lu = $taint_lu;
	
	// get user record, or create new guest user
	$msg = 'ok';
	$id = 0;
	$firstname = 'Guest';
	$lastname = 'Guest';
	$programid = 0;
	$tier = 0;
	if ($lu == "e328b74e1f6e5738b07ff558b7827d9e") {
		jlog(JLOG_DEBUG, "create new guest user x");
		$id = getNextSequence('bahasa.user_id_seq');
		$email = 'email'.rand();
		$password = "pw$id";
		$sql  = "insert into bahasa.user (id, email, password, firstname, lastname, programid, tier) ";
		$sql .= "values ($id, '$email', '$password', '$firstname', '$lastname', $programid, $tier)";
		$rc = executeSql($sql);
		$lu = '';
	}
	else {
		$sql  = "select u.id, u.email, u.firstname, u.lastname, u.programid, u.tier ";
		if ($lu) {
			jlog(JLOG_DEBUG, "login with token $lu");
			$sql .= "from bahasa.user u, bahasa.token t ";
			$sql .= "where t.token = '$lu' ";
			$sql .= "and t.userid = u.id";
		}
		else {
			jlog(JLOG_DEBUG, "login with email/password $email/$password");
			$sql .= " from bahasa.user u";
			$sql .= " where u.email = '". $email."'";
			$sql .= " and u.password = '". $password."'";
		}
		$result = executeQuery($sql);
		if ($result && pg_num_rows($result)) {
			$row = pg_fetch_array($result, 0, PGSQL_ASSOC);
			$id = $row['id'];
			$email = $row['email'];
			$firstname = $row['firstname'];
			$lastname = $row['lastname'];
			$programid = $row['programid'];
			$tier = $row['tier'];
			jlog(JLOG_DEBUG, "got user record by lu, $email, $tier");
		}
		else {
			$msg = "email password not found";
			jlog(JLOG_DEBUG, "user record lookup failed");
		}
	}	

	// todo: check timestamp of token
	if ($id && !$lu) {
		$lu = writeToken($id);
	}

	header('Content-type: text/javascript');
	if ($lu) {
		$a = getPrograms($id);
		echo "g.data.programs = " . json_encode($a) . ";\n";
	}
	$a = array(lu=>$lu, firstname=>$firstname, lastname=>$lastname, programid=>$programid, tier=>$tier);
	echo "g.data.user = " . json_encode($a) . ";\n";
	echo "$on.onLogin(\"$lu\", \"$msg\");";
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
		echo "$on.onRegister(0, \"\", \"email should be one word, no spaces\");";
		return;
	}
	$email = $taint_email;
	
	// pword - one word all ascii chars, no spaces
	if (strlen($taint_pword) <= 0) {
		echo "$on.onRegister(0, \"\", \"password should be one word, no spaces\");";
		return;
	}
	$password = $taint_pword;
	
	// write a token record
	$lu = writeToken($id);
	if (!$lu) {
		$msg = "Unable to register.  System error.";
	}

	if ($lu) {
		$a = getPrograms($id);
		echo "g.programs = " . json_encode($a) . ";\n";
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
	$upid = getUserProgramId($id, $lw);
	$lessonsdata = getLessons($lw, $upid);
	$nextlessonid = getNextLessonId($lw, $upid);
	$lasttimestamp = executeQueryOne( "select max(ts) from bahasa.uservocab where userprogramid = $upid");
	//$programdata = Array( 'programid'=>$lw, 'userprogramid'=>$upid, 'nextlessonid'=>$nextlessonid, 'lasttimestamp'=>$lasttimestamp);
	$programdata = Array( 'programid'=>$lw, 'userprogramid'=>$upid, 'lasttimestamp'=>$lasttimestamp);

	$msg = 'ok';
	$sql  = "update bahasa.user set programid = $lw where id = $id";
	$rc = executeSql($sql);
	if (!$rc) {
		$msg = 'Failed.';
	}

	header('Content-type: text/javascript');
	echo "g.data.lessons = " . json_encode($lessonsdata) . ";\n";
	echo "g.data.program = " . json_encode($programdata) . ";\n";
	echo "g.data.lesson = {};\n";
	echo "g.data.lesson.id = $nextlessonid;\n";
	echo "$on.onProgramLoaded($lw, $upid, $nextlessonid, '$msg');";
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
	
	$upid = getUserProgramId($id, $lw);

	$msg = 'ok';
	
	$sql = "delete from bahasa.uservocab where userprogramid = $upid";
	$rc = executeSql($sql);
	if (!$rc) {
		$msg = 'Failed.';
	}
	$sql = "delete from bahasa.userlesson where userprogramid = $upid";
	$rc = executeSql($sql);
	if (!$rc) {
		$msg = 'Failed.';
	}

	// return the id
	header('Content-type: text/javascript');
	echo "$on.onProgramReset($upid, '$msg');";
	return;
}

function resetlesson() {
	global $conn;
	
	// inputs
	$taint_on = isset($_GET['on']) ? $_GET['on'] : 0;
	$taint_lu = isset($_GET['lu']) ? $_GET['lu'] : 0;
	$taint_lw = isset($_GET['lw']) ? $_GET['lw'] : 0;
	$taint_lesson = isset($_GET['lesson']) ? $_GET['lesson'] : 0;
	
	// on is all alpha chars + max three periods.  No spaces.  No other punctuation.
	$on = $taint_on;
	
	// userid
	$lu = $taint_lu;
	
	// programid
	$lw = $taint_lw;

	// lessonid
	$lesson = $taint_lesson;

	$id = getUserIdForToken($lu);
	
	$upid = getUserProgramId($id, $lw);

	$msg = 'ok';
	
	$sql  = "delete from bahasa.uservocab where id in (";
	$sql .= "select uv.id ";
	$sql .= "from bahasa.lessonvocab lv ";
	$sql .= "join bahasa.uservocab uv on (lv.vocabid = uv.vocabid) ";
	$sql .= "where lv.lessonid = $upid ";
	$sql .= "and uv.userprogramid = $upid)";
	$rc = executeSql($sql);
	if (!$rc) {
		$msg = 'Failed.';
	}
	$sql = "delete from bahasa.userlesson where userprogramid = $upid";
	$rc = executeSql($sql);
	if (!$rc) {
		$msg = 'Failed.';
	}

	// return the id
	header('Content-type: text/javascript');
	echo "$on.onProgramReset($upid, '$msg');";
	return;
}

function putlesson() {
	global $conn;

	// inputs
	$taint_lu = isset($_GET['lu']) ? $_GET['lu'] : 0;
	$taint_program = isset($_GET['program']) ? $_GET['program'] : 0;
	$taint_on = isset($_GET['on']) ? $_GET['on'] : 0;
	$taint_lesson = isset($_GET['lesson']) ? $_GET['lesson'] : 0;
	$taint_data = isset($_GET['data']) ? $_GET['data'] : 0;
	
	// token
	$lu = $taint_lu;
	
	// program is positive integer between 1 and max_int
	$program = $taint_program;
	
	// on is all alpha chars + max three periods.  No spaces.  No other punctuation.
	$on = $taint_on;
	
	// lesson is an integer
	$lesson = $taint_lesson;

	// data is a json string
	$data = rawurldecode($taint_data);
	
	$id = getUserIdForToken($lu);
	$upid = getUserProgramId($id, $program);
	jlog(JLOG_DEBUG, "putlesson $lesson");

	// update/insert each uservocab record
	$decoded = json_decode($data);
	foreach ($decoded as $key => $val) {
		$value = (array) $val;
		$vocabid = $value['v'];
		$state = $value['s'];
		$askedNormal = $value['an'];
		$correctNormal = $value['cn'];
		$askedReverse = $value['ar'];
		$correctReverse = $value['cr'];
		$tu = (substr($state,1,1) == 'r') ? 'tureverse' : 'tunormal';
		jlog(JLOG_DEBUG, "update $upid, $vocabid, $state, $askedNormal, $correctNormal, $askedReverse, $correctReverse, $tu");

		// avoid race condition
		$sql = "select state, askedNormal, askedReverse from bahasa.uservocab ";
		$sql .= "where userprogramid = $upid and vocabid = $vocabid";
		$result = executeQuery($sql);
		$numrows = pg_num_rows($result);
		if ($numrows) {
		    $row = pg_fetch_array($result, 0, PGSQL_ASSOC);
		    $currentState = $row['state'];
		    $currentAskedNormal = $row['askedNormal'];
		    $currentAskedReverse = $row['askedReverse'];
		    if ($currentAskedNormal > $askedNormal || $currentAskedNormal > $askedNormal) {
		    	jlog(JLOG_DEBUG, "update out of sequence. aborted.");
			}
			else {
				$st = ($currentState == 'mr') ? '' : ",state='$state'";
				$sql = "update bahasa.uservocab set askedNormal=$askedNormal, correctNormal=$correctNormal, askedReverse=$askedReverse, correctReverse=$correctReverse, $tu=now() $st";
				$sql .= " where userprogramid = $upid and vocabid = $vocabid";
				$rc = executeSql($sql);
		    	jlog(JLOG_DEBUG, "updated $rc");
			}
		}
		else {
			$sql = "insert into bahasa.uservocab (userprogramid,vocabid,state,askednormal,correctnormal,askedreverse,correctreverse) values ($upid, $vocabid, '$state', $askedNormal, $correctNormal, $askedReverse, $correctReverse)";
			$rc = executeSql($sql);
			jlog(JLOG_DEBUG, "inserted $rc");  // this never happens because all uv records are written at the first getlesson
		}
	}

	// determine the new lesson mastery value
	$sql  = "select coalesce(uv.state, 'un') as state, count(*) ";
	$sql .= "from bahasa.vocab v ";
	$sql .= "join bahasa.lessonvocab lv on (v.id = lv.vocabid) ";
	$sql .= "left join bahasa.uservocab uv on (v.id = uv.vocabid) ";
	$sql .= "where (lv.lessonid = $lesson) ";
	$sql .= "and (uv.userprogramid = $upid or uv.userprogramid is null) ";
	$sql .= "group by state ";
	$result = executeQuery($sql);
	$numrows = pg_num_rows($result);
	$arr = array('un'=>0, 'wn'=>0, 'rn'=>0, 'mn'=>0, 'wr'=>0, 'rr'=>0, 'mr'=>0);
	$total = 0;
	for ($i=0; $i<$numrows; $i++) {
	    $row = pg_fetch_array($result, $i, PGSQL_ASSOC);
		$state = $row['state'];
		$count = $row['count'];
		$arr[$state] = $count;
		$total += $count;
	}
	$lessonmastery = 1;  // default to in-progress
	if ($arr['un'] == 0 && $arr['wn'] == 0 && $arr['rn'] == 0) {
		$lessonmastery = 2;  // if none still normal, we are in reverse
	}
	if ($arr['mr'] == $total) {
		$lessonmastery = 50;  // if all are mastered reverse, lesson is mastered
	}

	// update or insert userlesson
	$sql = "update bahasa.userlesson set mastery = $lessonmastery where userprogramid = $upid and lessonid = $lesson";
	$rc = executeSql($sql);
	if (!$rc) {
		$sql = "insert into bahasa.userlesson (userprogramid, lessonid, mastery) values( $upid, $lesson, $lessonmastery)";
		executeSql($sql);
	}
	$ls = ($rc) ? "updated" : "inserted";
	jlog(JLOG_DEBUG, "lessonmastery $lessonmastery $ls");
	
	header('Content-type: text/javascript');
	echo "$on.onLessonDataUpdated($lesson, $lessonmastery);";
	return;
}

function getlesson() {
	global $conn;

	// inputs
	$taint_lu = isset($_GET['lu']) ? $_GET['lu'] : 0;
	$taint_program = isset($_GET['program']) ? $_GET['program'] : 0;
	$taint_on = isset($_GET['on']) ? $_GET['on'] : 0;
	
	// token
	$lu = $taint_lu;
	
	// program is positive integer between 1 and max_int
	$program = $taint_program;
	
	// on is all alpha chars + max three periods.  No spaces.  No other punctuation.
	$on = $taint_on;
	
	$id = getUserIdForToken($lu);
	$upid = getUserProgramId($id, $program);
	$nextlessonid = getNextLessonId($program, $upid);
	$lessonname = executeQueryOne( "select name from bahasa.lesson where id = $nextlessonid");

	// get vocab records
	$arr = array();
	$sql  = "select lv.id as lvid, lv.seq, v.id as vid, v.host, v.eng, v.part, v.cat, uv.id as uvid, coalesce(uv.state, 'un') as state, coalesce(uv.askednormal,0) as askednormal, coalesce(uv.correctnormal,0) as correctnormal, coalesce(uv.askedreverse,0) as askedreverse, coalesce(uv.correctreverse,0) as correctreverse ";
	$sql .= "from bahasa.vocab v ";
	$sql .= "join bahasa.lessonvocab lv on (v.id = lv.vocabid) ";
	$sql .= "left outer join bahasa.uservocab uv on (v.id = uv.vocabid) ";
	$sql .= "where lv.lessonid = $nextlessonid ";
	$sql .= "and (uv.userprogramid = $upid or uv.userprogramid is null) ";
	$sql .= "order by seq";
	jlog(JLOG_DEBUG, "getlesson $sql");
	$result = executeQuery($sql);
	if ($result) {
		$numrows = pg_num_rows($result);
		for ($j=0; $j<$numrows; $j++) {
		    $row = pg_fetch_array($result, $j, PGSQL_ASSOC);
			$arr[(int)$row['lvid']] = array('lvid' => (int)$row['lvid'], 'uvid' => (int)$row['uvid'], 'n' => (int)$row['seq'], 'host' => $row['host'], 'vid' => (int)$row['vid'], 'eng' => $row['eng'], 'part' => $row['part'], 'cat' => $row['cat'], 's' => $row['state'], 'an' => $row['askednormal'], 'cn' => $row['correctnormal'], 'ar' => $row['askedreverse'], 'cr' => $row['correctreverse']);
		}
	}

	// replace null uvid's with a new id's
	$uvid = getNextSequence('bahasa.uservocab_id_seq');
	$nextuvid = $uvid;
	jlog('JLOG_DEBUG', "first sequence for uservocab: $nextuvid");
	$narr = array();
	foreach ($arr as $key => $value) {
		jlog('JLOG_DEBUG', "value uvid: " . $value['uvid']);
		if ($value['uvid'] == 0) {
			jlog('JLOG_DEBUG', "fixing value of null uvid: $nextuvid");
			$value['uvid'] = $nextuvid;
			$nextuvid++;
		}
		$narr[$value['uvid']] = $value;
	}

	$filename = "../presentation/$lessonname.html"; 
	$spresentation = '';
	if (file_exists($spresentation)) {
		$spresentation = file_get_contents($filename);
		$spresentation = str_replace("\n", "", $spresentation);
		$spresentation = str_replace("\r", "", $spresentation);
	}
	
	$filename = "../completion/$lessonname.html"; 
	$scompletion = '';
	if (file_exists($scompletion)) {
		$scompletion = file_get_contents($filename);
		$scompletion = str_replace("\n", "", $scompletion);
		$scompletion = str_replace("\r", "", $scompletion);
	}

	header('Content-type: text/javascript');
	echo "g.data.lesson = {};\n";
	echo "g.data.lesson.id = $nextlessonid;\n";
	echo "g.data.lesson.vocab = " . json_encode($narr) . ";\n";
	echo "g.data.lesson.presentation = '$spresentation';\n";
	echo "g.data.lesson.completion = '$scompletion';\n";
	echo "$on.onLessonDataLoaded();";

	// write all uv records with newly assigned id, then update the sequence
	if ($nextuvid > $uvid) {
		foreach ($narr as $key => $value) {
			if ($value['uvid'] >= $uvid) {
				$id = $value['uvid'];
				$vid = $value['vid'];
				$sql = "insert into bahasa.uservocab (id, userprogramid,vocabid) values ($id, $upid, $vid)";
				jlog( 'JLOG_DEBUG', "background insert of new uservocab record $sql");
				executeSql( $sql);
			}
		}
		executeSql( "SELECT setval('bahasa.uservocab_id_seq', $nextuvid)");
	}

	return;
}

function getPrograms() {
	global $conn;

	// the query
	$sql  = "select p.id, p.name";
	$sql .= " from bahasa.program p";
	
	//execute SQL query 
	$result = @pg_query($conn, $sql);
	if (!$result) {
		jlog(JLOG_ERROR,"query failed ** ".pg_last_error()." ** ".$sql);
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

function getNextLessonId($programid, $upid) {
	$sql  = "select l.id ";
	$sql .= "from bahasa.lesson l ";
	$sql .= "left outer join bahasa.userlesson ul on (ul.lessonid = l.id or ul.lessonid = null) ";
	$sql .= "where l.programid = $programid ";
	$sql .= "and (ul.userprogramid = $upid or ul.userprogramid is null) ";
	$sql .= "and (ul.mastery < 5 or ul.mastery is null) ";
	$sql .= "order by seq asc ";
	$sql .= "limit 1";
	$id = executeQueryOne($sql);
	jlog(JLOG_DEBUG, "getNextLessonId $sql");
	return $id;
}

function getLessons($programid, $upid) {
	global $conn;
	$sql  = "select min(l.id) as lessonid, min(l.programid) as programid, l.seq, min(l.name) as name, min(l.title) as title, min(l.description) as description, ";
	$sql .= "min(ul.id) as userlessonid, min(ul.userprogramid) as upid, min(ul.start) as start, min(ul.mastery) as mastery, count(lv.id) as vocabcount ";
	$sql .= "from bahasa.lesson l ";
	$sql .= "join bahasa.lessonvocab lv on (lv.lessonid = l.id) ";
	$sql .= "left join bahasa.userlesson ul on (ul.lessonid = l.id) ";
	$sql .= "where l.programid = 1 ";
	$sql .= "and (ul.userprogramid = 1 or ul.userprogramid is null) ";
	$sql .= "group by l.seq ";
	$sql .= "order by l.seq ";
	$result = executeQuery($sql);
	if (!$result) {
		jlog(JLOG_ERROR,"getlessons query failed ** ".pg_last_error()." ** ".$sql);
		return false;
	}
	$numrows = pg_num_rows($result);
	$arr = array();
	for ($i=0; $i<$numrows; $i++) {
		$row = pg_fetch_array($result, $i, PGSQL_ASSOC);
		$arr[(int)$row['lessonid']] = array('lessonid' => (int)$row['lessonid'], 'userlessonid' => (int)$row['userlessonid'], 'programid' => (int)$row['programid'], 'seq' => (int)$row['seq'], 'name' => $row['name'], 'title' => $row['title'], 'description' => $row['description'], 'start' => $row['start'], 'mastery' => (int)$row['mastery'], 'vocabcount' => (int)$row['vocabcount']);
	}
	return $arr;	
}

function getUserProgramId($id, $programid) {
	global $conn;
	$sql = "select id from bahasa.userprogram where userid = $id and programid = $programid";
	$result = @pg_query($conn, $sql);
	if (!$result) {
		jlog(JLOG_ERROR, "lookup failed ".pg_last_error()." $sql");
		return 0;
	}
	
	// if no record, insert it now
	$numrows = pg_num_rows($result);
	if ($numrows < 1) {
		$sql = "insert into bahasa.userprogram (userid, programid) values ($id, $programid)";
		$result = @pg_query($conn, $sql);
		if (!$result) {
			jlog(JLOG_ERROR, "insert failed".pg_last_error()." $sql");
			return 0;
		}

		$sql = "select id from bahasa.userprogram where userid = $id and programid = $programid";
		$result = @pg_query($conn, $sql);
		if (!$result) {
			jlog(JLOG_ERROR, "lookup failed ".pg_last_error()." $sql");
			return 0;
		}
	}
	$row = pg_fetch_array($result, 0, PGSQL_ASSOC);
	$upid = (int)$row['id'];
	return $upid;
}

function getUserIdForToken($token) {
	global $conn;
	$sql = "select userid from bahasa.token where token = '$token'";
	$result = @pg_query($conn, $sql);
	if (!$result) {
		jlog(JLOG_ERROR, "Token lookup failed".pg_last_error()." $sql");
		return 0;
	}
	$numrows = pg_num_rows($result);
	if ($numrows < 1) {
		jlog(JLOG_ERROR, "Token lookup returned no row".pg_last_error()." $sql");
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
	$sql = "insert into bahasa.token (userid, token) values ($id, '$lu')";
	$rc = executeSql($sql);
	if (!$rc) {
		return false;
	}
	return $lu;
}

//----------------------------------------------------------
// (c) Copyright 2008, 2012 John Hagstrand
?>