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
$guid = 1;
$gprev = null;
$gmatchcnt = 0;
$gcatcnt = 0;

$known_functions = array(
	0 => '$name',
	1 => '$possessive',
	2 => '$number',
	3 => '$date',
	4 => '$time',
	5 => '$address',
	6 => '$sentence'
);

/**
 * Part 1.  Read the match file
**/
consoleMsg( "Process match text file\n");
$lines = file('indonesian_match.txt');
foreach ($lines as $line_num => $line) {
	// skip empty lines and comment lines
	if (strlen($line) < 3 || substr($line,0,2) == '//') {
		continue;
	}
	if (substr($line,0,4) == '$eof') {
		consoleMsg( "match eof on line $line_num\n");
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

consoleMsg( "$gmatchcnt match records inserted\n");
consoleMsg( "$gcatcnt cat records inserted\n");
$gmatchcnt = 0;
$gcatcnt = 0;

/**
 * Part 2.  Read the verbs spreadsheet
**/
consoleMsg( "\nProcess verbs spreadsheet file\n");
$data = new Spreadsheet_Excel_Reader("verbs.xls");
for ($row=2; $row<=$data->rowcount(); $row++) {
	$subject = $data->val($row,1);
	$verb = $data->val($row,2);
	$cat = $data->val($row,3);
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
	
	process($verb, 'vrb', 'oin', $wherein, insertVocabMatch);
	process($verb, 'vrb', 'cat', $cat, insertVocabCat);
}

consoleMsg( "$gmatchcnt match records inserted\n");
consoleMsg( "$gcatcnt cat records inserted\n");

/*
	input $w is a list of words
	output is a fully exploded and de-duped array
*/
function dereference($w) {
	// explode word list into an array
	$a = preg_split("/,\s*/", $w);
	
	// if $w is empty, return an empty array
	if (count($a) == 1 && !strlen($a[0])) {
		unset($a[0]);
	}

	// process all the defined $label words in the list
	while (explodeLabel(&$a));
	$a = array_unique($a);
	
	// return the array
	return $a;
}

function explodeLabel($a) {
	global $glabels;
	
	if (!is_array($a)) {
		consoleMsg( "not an array a: $a\n");
		return 0;
	}
	
	$out = array();
	$cnt = 0;
	foreach ($a as $n => $w) {
		if (substr($w,0,1) == '$') {
			if (!is_array($glabels[$w])) {
				consoleMsg( "not an array w: $w\n");
			}
			unset($a[$n]);
			$out = array_merge($out, $glabels[$w]);
			$cnt++;
		}
		else {
			$out[] = $w;
		}
	}
	$a = $out;
	return $cnt;
}

function writeCat($word, $rwords) {
	global $conn, $glabels, $gcatcnt;

	foreach ($glabels as $label => $list) {
		$a = dereference(implode(", ", $list));
		foreach ($a as $n => $w) {
			// lookup vocab, get id
			$v = lookupVocab($w);
			if (!$v) {
				continue;
			}
			$vocabid = $v['id'];
		
			$sql = "insert into bahasa.vocabcat (cat, vocabid) ";
			$sql .= " values ( '$label', $vocabid)";
			$result = @pg_query($conn, $sql);
			if (!$result) {
				$err = pg_last_error();
				consoleMsg( "Insert failed ** $err ** ".$sql);
			}
			
			$numrows = pg_affected_rows($result);
			if ($numrows <= 0) {
				consoleMsg( "Insert failed ** no records updated ** $sql");
			}
			$gcatcnt++;
		}
	}
	return;
}

function setParts($word, $rwords) {
	global $glpart, $grpart;

	$glpart = $word;
	$grpart = $rwords;
}

function defineLabel($word, $rwords) {
	global $glabels;

	if (!strlen($rwords)) {
		consoleMsg( "label $word is empty\n");
		return;
	}

	if (substr($rwords,0,9) == "function(" && !isKnownFunction($word)) {
		consoleMsg( "label $word is not a known function\n");
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

function insertMatches($lwords, $rwords) {
	global $glpart, $grpart;
	process($lwords, $glpart, $grpart, $rwords, insertVocabMatch);
}

function process($lwords, $lpart, $rpart, $rwords, $callback) {
	$la = dereference($lwords);
	$ra = dereference($rwords);
	debugMsg( "$lwords : $rwords\n");
	debugMsg( implode(", ", $la) . " : " . implode(", ", $ra) . "\n");

	foreach ($la as $lword_num => $lword) {
		foreach ($ra as $rword_num => $rword) {
			$callback($lword, $lpart, $rpart, $rword);
		}
	}
}

function lookupVocab($word) {
	global $conn;
	
	$sql = "select id, part from bahasa.vocab where host = '$word'";
	$result = @pg_query($conn, $sql);
	if (!$result) {
	    consoleMsg( "Query error ".pg_last_error()." $sql");
	    return;
	}
	
	$numrows = pg_num_rows($result);
	if ($numrows <= 0) {
		consoleMsg( "cannot find vocab word: $word \n");
		return false;
	}

	$row = pg_fetch_array($result, 0, PGSQL_ASSOC);
	
	$id = $row['id'];
	$part = $row['part'];
	
	return array("id" => $id, "part" => $part);
}

function insertVocabMatch($wordLeft, $partLeft, $partRight, $wordRight) {
	global $conn, $gmatchcnt, $gprev;

	// lookup vocab, get id and part
	if ($wordLeft == $gprev['word']) {
		$lvocabid = $gprev['id'];
		$lpart = $gprev['part'];
	}
	else {
		$vLeft = lookupVocab($wordLeft);
		if (!$vLeft) {
			return;
		}
		$lvocabid = $vLeft['id'];
		$lpart = $vLeft['part'];
		if ($partLeft) {
			$lpart = $partLeft;
		}
		$gprev = array('word'=>$wordLeft, 'id'=>$lvocabid, 'part'=>$lpart);
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
	debugMsg( "$wordLeft, $lpart, $rpart, $wordRight\n");
	$sql = "insert into bahasa.vocabmatch (lvocabid, lpart, rpart, rvocabid) ";
	$sql .= " values ( $lvocabid, '$lpart', '$rpart', $rvocabid)";
	$result = @pg_query($conn, $sql);
	if (!$result) {
		$err = pg_last_error();
		consoleMsg( "Insert failed ** $err ** ".$sql);
	}
	$numrows = pg_affected_rows($result);
	if ($numrows <= 0) {
		consoleMsg( "Insert failed ** no records updated ** $sql");
	}
	$gmatchcnt++;
}

function insertVocabCat($wordLeft, $partLeft, $partRight, $wordRight) {
	global $conn, $gcatcnt;

	$cat = '$'.$wordRight;

	// lookup vocab, get id
	$v = lookupVocab($wordLeft);
	if (!$v) {
		return;
	}
	$vocabid = $v['id'];

	$sql = "insert into bahasa.vocabcat (cat, vocabid) ";
	$sql .= " values ( '$cat', $vocabid)";
	$result = @pg_query($conn, $sql);
	if (!$result) {
		$err = pg_last_error();
		consoleMsg( "Insert failed ** $err ** ".$sql);
		return;
	}
	
	$numrows = pg_affected_rows($result);
	if ($numrows <= 0) {
		consoleMsg( "Insert failed ** no records updated ** $sql");
		return;
	}
	$gcatcnt++;
}

function consoleMsg($s) {
	echo $s;
}

function debugMsg($s) {
//	echo $s;
}
?>
