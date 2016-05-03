<?php
// (c) Copyright 2010 Voyc.com
//----------------------------------------------------------

/**
 * This library file contains database utility functions.
**/

/**
 * Establish a connection to database 
 */ 
function getConnection() {
	global $dbport, $dbname, $dbuser, $dbpassword;
	$conn = @pg_connect("port=$dbport dbname=$dbname user=$dbuser password=$dbpassword");
	if (!$conn) {
		jlog(JLOG_DEBUG,'unable to connect to database');
		return false;
	}
	return $conn;
}

function executeSql($sql) {
	global $conn;
	$result = @pg_query($conn, $sql);
	if (!$result) {
		jlog(JLOG_DEBUG,"executeSql failed ** ".pg_last_error()." ** ".$sql);
		return false;
	}
	$numrows = pg_affected_rows($result);
	return $numrows;
}

function executeQuery($sql) {
	global $conn;
	$result = @pg_query($conn, $sql);
	if (!$result) {
		jlog(JLOG_DEBUG,"executeQuery failed ** ".pg_last_error()." ** ".$sql);
		return false;
	}
	return $result;
}

function executeQueryOne($sql) {
	global $conn;
	$result = @pg_query($conn, $sql);
	if (!$result) {
		jlog(JLOG_DEBUG,"executeQueryOne failed ** ".pg_last_error()." ** ".$sql);
		return false;
	}
	if (!$result) {
		jlog(JLOG_DEBUG,"executeQueryOne failed ** ".pg_last_error()." ** ".$sql);
		return 0;
	}
	$numrows = pg_num_rows($result);
	if ($numrows < 1) {
		jlog(JLOG_DEBUG,"executeQueryOne failed no rows ** ".pg_last_error()." ** ".$sql);
		return 0;
	}
	$row = pg_fetch_array($result, 0);
	$value = $row[0];
	return $value;
}

function getNextSequence($seqname) {
	global $conn;
	$sql = "SELECT nextval('$seqname')";
	$result = @pg_query($conn, $sql);
	if (!$result) {
		jlog(LOG_DEBUG, "nextval error ".pg_last_error()." $sql");
		return 0;
	}
	$numrows = pg_num_rows($result);
	if ($numrows < 1) {
		jlog(LOG_DEBUG, "nextval no rows ".pg_last_error()." $sql");
		return 0;
	}
	$row = pg_fetch_array($result, 0, PGSQL_ASSOC);
	$seq = $row['nextval'];
	return $seq;
}

//----------------------------------------------------------
// (c) Copyright 2010 MapTeam, Inc.
?>