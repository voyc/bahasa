<?php
/*
Read a vocab file and generate all the vocab records.
*/

require_once('../phplib/config.php');
require_once('../phplib/jlog.php');
require_once('../phplib/dbUtilities.php');
openjlog(basename(__FILE__));

$conn = getConnection();

$lines = file('indonesian_vocab.txt');

// add programid
// delete all records for the program

// after re-generating vocab
// we must also re-generate match and lesson plan

// Loop through our array
$cntRead = 0;
$cntWrite = 0;
foreach ($lines as $line_num => $line) {
	$cntRead++;
	$a = explode("\t",$line);
	$host = trim($a[0]);
	$eng  = trim($a[1]);
	$part = trim($a[2]);
	$cat  = trim($a[3]);
	$def  = trim($a[4]);

	$sql = "insert into bahasa.vocab (host, eng, part, cat, def) ";
	$sql .= "values ('$host', '$eng', '$part', '$cat', '$def')";
	$rc = executeSql($sql);
	if ($rc) {
		$cntWrite++;
	}
	else {
		echo "insert $host failed\n";
	}
}
echo "$cntRead read, $cntWrite written\n";

/* check for dupes
select host, count(*)
from bahasa.vocab
group by host
having count(*) > 1
*/

?>