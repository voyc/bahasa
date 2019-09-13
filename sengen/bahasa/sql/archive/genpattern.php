<?php
/*
Read a pattern file and generate all the pattern records.
*/

require_once('../phplib/config.php');
require_once('../phplib/jlog.php');
require_once('../phplib/dbUtilities.php');
openjlog(basename(__FILE__));

$conn = getConnection();

$lines = file('indonesian_patterns.txt');

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

	$sql = "insert into bahasa.vocab (host, eng, part, cat) ";
	$sql .= "values ('$host', '$eng', '$part', '$cat')";
	$rc = executeSql($sql);
	if ($rc) {
		$cntWrite++;
	}
}
echo "$cntRead read, $cntWrite written\n";
?>