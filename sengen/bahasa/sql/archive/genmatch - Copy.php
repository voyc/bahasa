<?php
/*
Read a match file and generate all the match records.
*/

require_once('../phplib/config.php');
require_once('../phplib/jlog.php');
require_once('../phplib/dbUtilities.php');
require_once('../phplib/excel_reader2.php');

openjlog(basename(__FILE__));

$conn = getConnection();
$giterators = array();
$gits = array();
$glabels = array();
$grpart = '';
$glpart = '';
$gtest = true;
$gdebug = false;
$guid = 1;

$known_functions = array(
	0 => '$name',
	1 => '$possessive',
	2 => '$number',
	3 => '$date',
	4 => '$time',
	5 => '$address',
	6 => '$sentence'
);

$gcnt = 0;

$lines = file('indonesian_match.txt');
foreach ($lines as $line_num => $line) {
	// skip empty lines and comment lines
	if (strlen($line) < 3 || substr($line,0,2) == '//') {
		continue;
	}
	if (substr($line,0,4) == '$eof') {
		echo "match eof on line $line_num\n";
		break;
	}

	$a1 = explode("\t",$line);
	$word = $a1[0];
	$op = $a1[1];
	$rwords = $a1[2];

	$word = trim( $word);
	$word = str_replace( ', ', ',', $word);

	$rwords = trim( $rwords);
	$rwords = str_replace( ', ', ',', $rwords);

	switch ($op) {
		case "=":
			defineLabel($word, $rwords);
		break;
		
		case ":":
			insertMatches($word, $rwords);
		break;

		case "set":
			setParts($word, $rwords);
		break;

		case "cat":
			writeCat($word, $rwords);
		break;
	}
}

echo "$gcnt records inserted from match\n";
if ($gtest) {
	exit;
}

$data = new Spreadsheet_Excel_Reader("verbs.xls");

for ($row=2; $row<=$data->rowcount(); $row++) {
	$subject = $data->val($row,1);
	$verb = $data->val($row,2);
	$class = $data->val($row,3);
	$object = $data->val($row,4);
	$adverbs = $data->val($row,5);
	$wherein = $data->val($row,6);
	$wherefrom = $data->val($row,7);
	$whereto = $data->raw($row,8);
	$when = $data->raw($row,9);
	$how = $data->val($row,10);
	$why = $data->val($row,11);
	$for = $data->val($row,12);
	$withwhom = $data->val($row,13);
	$about = $data->val($row,14);
	$to = $data->val($row,15);
	
	itProcess($verb, 'vrb', 'oin', $wherein, insVocabMatch);
}

echo "$gcnt records inserted total\n";

function dereference($w) {
	$a = preg_split("/,\s*/", $w);
	if (count($a) == 1 && !strlen($a[0])) {
		unset($a[0]);
	}
	return $a;
}

function writeCat($word, $rwords) {
	global $conn, $glabels;

	$cat = 'per';
	$cnt = 0;

	$key = itReset('$person');
	$w = itNext($key);
	while ($w) {
		// lookup vocab, get id
		$v = lookupVocab($w);
		if (!$v) {
			continue;
		}
		$vocabid = $v['id'];
	
		$sql = "insert into bahasa.vocabcat (cat, vocabid) ";
		$sql .= " values ( '$cat', $vocabid)";
		$result = @pg_query($conn, $sql);
		if (!$result) {
			$err = pg_last_error();
			echo "Insert failed ** $err ** ".$sql;
		}
		
		$numrows = pg_affected_rows($result);
		if ($numrows <= 0) {
			echo "Insert failed ** no records updated ** $sql";
		}
		$cnt++;
		$w = itNext($key);
	}
	echo "$cnt vocabcat records written\n";
	return $cnt;
}

function setParts($word, $rwords) {
	global $grpart, $glpart;

	$glpart = $word;
	$grpart = $rwords;
}

function defineLabel($word, $rwords) {
	global $glabels;

	if (!strlen($rwords)) {
		echo "label $word is empty\n";
		return;
	}

	if (substr($rwords,0,9) == "function(" && !isKnownFunction($word)) {
		echo "label $word is not a known function\n";
		return;
	}
	
	$ra = explode(',',$rwords);
	$glabels[$word] = $ra;
}

function isKnownFunction($word) {
	global $known_functions;
	foreach ($known_functions as $num => $func) {
		if ($func == $word) {
			return true;
		}
	}
	return false;
}

function insertMatches($word, $rwords) {

	global $glabels, $glpart, $grpart;


	itProcess($word, $glpart, $grpart, $rwords, insVocabMatch);
	return;


	$la = explode(',',$word);
	if (!count($la)) {
		$la[] = $word;
	}
	
	foreach ($la as $lword_num => $lword) {

		// lookup vocab, get id and part
		$a = lookupVocab($lword);
		if (!$a) {
			continue;
		}
		$lvocabid = $a['id'];
		$lpart = $a['part'];
	
		$ra = explode(',',$rwords);
		foreach ($ra as $rword_num => $rword) {
			if ($glabels[$rword]) {
				//echo "substitute $rword\n";
				$key = itReset($rword);
				$w = itNext($key);
				while ($w) {
					insertVocabMatch($lvocabid, $lpart, $w);
					$w = itNext($key);
				}
			}
			else {
				insertVocabMatch($lvocabid, $lpart, $rword);
			}
		}
	}
}

function lookupVocab($word) {
	global $conn;
	
	$sql = "select id, part from bahasa.vocab where host = '$word'";
	$result = @pg_query($conn, $sql);
	if (!$result) {
	    echo "Query error ".pg_last_error()." $sql";
	    return;
	}
	
	$numrows = pg_num_rows($result);
	if ($numrows <= 0) {
		echo "cannot find vocab word: $word \n";
		return false;
	}

	$row = pg_fetch_array($result, 0, PGSQL_ASSOC);
	
	$id = $row['id'];
	$part = $row['part'];
	
	return array("id" => $id, "part" => $part);
}

//-------------------------
// iterator one - replace these
//------------------------- 

function insertVocabMatch($lvocabid, $lpart, $rword) {	
	global $conn, $gcnt, $glpart, $grpart;

//if ($grpart == 'oov') {
//	echo "$lvocabid, $lpart, $grpart, $rword\n";
//}


	// lookup vocab, get id and part
	$v = lookupVocab($rword);
	if (!$v) {
		return;
	}
	$rvocabid = $v['id'];
	$rpart = $v['part'];

	$lpart = $glpart;
	$rpart = $grpart;

	$sql = "insert into bahasa.vocabmatch (lvocabid, lpart, rpart, rvocabid) ";
	$sql .= " values ( $lvocabid, '$lpart', '$rpart', $rvocabid)";
	$result = @pg_query($conn, $sql);
	if (!$result) {
		$err = pg_last_error();
		echo "Insert failed ** $err ** ".$sql;
	}
	
	$numrows = pg_affected_rows($result);
	if ($numrows <= 0) {
		echo "Insert failed ** no records updated ** $sql";
	}
	$gcnt++;
}

function itReset($label) {
	global $giterators, $glabels;
	
	// create new iterator
	$it = array();
	$it[] = array( 'label' => $label, 'index' => 0);
	
	// save it
	$key = guid();
	$giterators[$key] = $it;
	return $key;
}

function itNext($key) {
	global $giterators, $glabels;
	$w = '';
	$it = &$giterators[$key];

	$count = count($it);
	$label = $it[$count-1]['label'];
	$index = $it[$count-1]['index'];

	$w = $glabels[$label][$index];

	if (substr($w,0,1) == '$') {
		while (substr($w,0,1) == '$') {
			$it[] = array( 'label' => $w, 'index' => 0);
			$w = itNext($key);
		}
	}
	else if (substr($w,0,1) == '_') {
		iterateFunction($w);
	}
	else {
		if (($it[$count-1]['index'] + 1) >= count($glabels[$label])) {
			array_pop($it);
		}
		$count = count($it);
		$it[$count-1]['index']++;
	}
	return $w;
}

//-------------------------
// iterator two
//------------------------- 

/*
lpart	rpart	count
wrd	prq	5
nun	adj	1325
vrb	oin	760
vrb	oov	3305
wrd	opp	15


lpart	rpart	count
wrd	prq	5
nun	adj	1325
vrb	oin	760
vrb	oov	3409
wrd	opp	15


detect dupes

select lvocabid, lpart, rpart, rvocabid, count(*)
from bahasa.vocabmatch
group by lvocabid, lpart, rpart, rvocabid
having count(*) < 2;

*/

function insVocabMatch($wordLeft, $partLeft, $partRight, $wordRight) {
	global $conn, $gcnt, $gtest, $gdebug;

	// lookup vocab, get id and part
	$vLeft = lookupVocab($wordLeft);
	if (!$vLeft) {
		return;
	}
	$lvocabid = $vLeft['id'];
	$lpart = $vLeft['part'];
	if ($partLeft) {
		$lpart = $partLeft;
	}

	// lookup vocab, get id and part
	$vRight = lookupVocab($wordRight);
	if (!$vRight) {
		return;
	}
	$rvocabid = $vRight['id'];
	$rpart = $vRight['part'];
	if ($partRight) {
		$rpart = $partRight;
	}

	// insert one record
	if ($gtest) {
		echo "$wordLeft, $lpart, $rpart, $wordRight\n";
	}
	$sql = "insert into bahasa.vocabmatch (lvocabid, lpart, rpart, rvocabid) ";
	$sql .= " values ( $lvocabid, '$lpart', '$rpart', $rvocabid)";
	$result = @pg_query($conn, $sql);
	if (!$result) {
		$err = pg_last_error();
		echo "Insert failed ** $err ** ".$sql;
	}
	$numrows = pg_affected_rows($result);
	if ($numrows <= 0) {
		echo "Insert failed ** no records updated ** $sql";
	}
	$gcnt++;
}

function itProcess($listLeft, $partLeft, $partRight, $listRight, $callback) {
	$keyLeft = itGet($listLeft);
	$keyRight = itGet($listRight);
	if ($keyLeft && $keyRight) {
		while ($wLeft = gitNext($keyLeft)) {
			while ($wRight = gitNext($keyRight)) {
				$callback($wLeft, $partLeft, $partRight, $wRight);
			}
			itStartover($keyRight);
		}
	}
	itRelease($keyRight);
	itRelease($keyLeft);
}

function itGet($label) {
	global $gits, $glabels;

	$slabel = $label;
	$key = guid();

	// if label is a list, copy it to a temporary label and use it as a label
	if (!isset($glabels[$label])) {
		$a = dereference($label);
		if (!count($a)) {
			return null;
		}
		$glabels[$key] = $a;
		$slabel = $key;
	}
	
	// create new iterator
	$it = array();
	$it[] = array( 'label' => $slabel, 'index' => 0);
	$gits[$key] = $it;
	return $key;
}

function itStartover($key) {
	global $gits;
	$it = &$gits[$key];
	$count = count($it);
	$it[$count-1]['index'] = 0;
}

function itRelease($key) {
	global $gits;
	unset($gits[$key]);
	unset($glabels[$key]);
}

function guid() {
	global $guid;
	return strval($guid++);
}

function gitNext($key) {
	global $gits, $glabels, $gdebug;
	if ($gdebug) echo "$key enter\n";
	$w = '';
	$it = &$gits[$key];

	$count = count($it);
	$label = $it[$count-1]['label'];
	$index = $it[$count-1]['index'];

	$w = $glabels[$label][$index];
	
	if (!$w && substr($label,0,1) == '$' && $index == 0) {
		echo "label $label not found\n";
	}

	if (substr($w,0,1) == '$') {
		while (substr($w,0,1) == '$') {
			$it[] = array( 'label' => $w, 'index' => 0);
			$w = gitNext($key);
		}
	}
	else if (substr($w,0,1) == '_') {
		iterateFunction($w);
	}
	else {
		if ($gdebug) echo "$key ".count($it)." ".$it[$count-1]['index']." ".count($glabels[$label])."\n";
//		var_dump($it);
		if (count($it) > 1 && ($it[$count-1]['index'] + 1) >= count($glabels[$label])) {
			array_pop($it);
			if ($gdebug) echo "$key popped\n";
			if ($gdebug) echo "$key ".count($it)." ".$it[$count-1]['index']." ".count($glabels[$label])."\n";
//			var_dump($it);
			$count = count($it);
			$it[$count-1]['index']++;
	
			$label = $it[$count-1]['label'];
			$index = $it[$count-1]['index'];
			$w = $glabels[$label][$index];
		}
		else {
			$count = count($it);
			$it[$count-1]['index']++;
		}

		if ($gdebug) echo "$key else $count\n";
	}

	if ($gdebug) echo "$key exit $w\n";
	return $w;
}

?>
