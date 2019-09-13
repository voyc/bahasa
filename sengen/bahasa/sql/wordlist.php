<?php
/*
Read a text file, print a list of words, and whether or not they are in vocab db.
*/

require_once('../phplib/config.php');
require_once('../phplib/jlog.php');
require_once('../phplib/dbUtilities.php');
openjlog(basename(__FILE__));

//$filename = 'worddlist_vi_reading.txt';
$filename = $argv[1];

$conn = getConnection();

$contents = file_get_contents($filename);
$a = preg_split('/[\s\,\.\'\"\?\!\@\#\$\%\^\&\*\(\)]+/', $contents);
usort($a, 'cmp');

// remove dupes
for ($i=1, $j=0, $n=count($a); $i<$n; ++$i) {
    if (!strcasecmp($a[$i], $a[$j])) {
        unset($a[$i]);
    } 
    else {
        $j = $i;
    }
}

// remove junk
for ($i=0, $n=count($a); $i<$n; ++$i) {
    if (!strcmp($a[$i], '-') 
	    	|| !strcmp($a[$i], '') 
	    	|| is_numeric(substr($a[$i],0,1))) {
        unset($a[$i]);
    } 
}

// find words in the db
$b = array();
$list = '';
foreach ($a as $key => $value) {
	if (strlen($list)) {
		$list .= ",";
	}
	$list .= "'$value'";
}
$list = strtolower($list);
$sql = "select host from bahasa.vocab where host in ($list) order by host";
$result = executeQuery($sql);
$numrows = pg_num_rows($result);
for ($i=0; $i<$numrows; $i++) {
	$row = pg_fetch_array($result, $i, PGSQL_ASSOC);
	$host = $row['host'];
	array_push($b,$host);
}

// print words already in the db
echo "Words already in the DB\n";
foreach ($b as $key => $value) {
	echo "$value\n";
}

// print words NOT in the db
echo "\n";
echo "Words not yet in the DB\n";
foreach ($a as $key => $value) {
	if (!isWordInDB($value)) {
		echo "$value\n";
	}
}

function isWordInDB($w) {
	global $b;
	$bo = false;
	foreach ($b as $key => $value) {
		if (!strcasecmp($value, $w)) {
			$bo = true;
			break;
		}
	}
	return $bo;	
}

function cmp($a, $b) {
    return strcasecmp($a, $b);
}

/*
	$a1 = explode("\t",$line);
	$word = $a1[0];
	$rwords = $a1[1];

	// lookup vocab, get id and part
	$a = lookupVocab($word);
	$lvocabid = $a['id'];
	$lpart = $a['part'];

	$r = trim( $rwords);
	$r = str_replace( ', ', ',', $r);
	$ra = explode(',',$r);
	foreach ($ra as $rword_num => $rword) {
		// lookup vocab, get id and part
		$v = lookupVocab($rword);
		$rvocabid = $v['id'];
		$rpart = $v['part'];

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
		$cnt++;
	}
}
echo "$cnt records inserted\n";
*/

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
	}

	$row = pg_fetch_array($result, 0, PGSQL_ASSOC);
	
	$id = $row['id'];
	$part = $row['part'];
	
	return array("id" => $id, "part" => $part);
}
?>