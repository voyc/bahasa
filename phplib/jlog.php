<?php
/**
	jlog(JLOG_DEBUG,)
	
	webfaction does not support syslog, so I'm replacing all 
	calls to syslog with jlog.
	
	jlog wraps native php error_log
	input $code is ignored.
	
	error_log type
		0, to apache errorlog, or echoed to console
		1, to designated email
		2, not used
		3, to designated file
		4, to SAPI handler
**/
define("JLOG_ERROR", 1);
define("JLOG_NOTICE", 2);
define("JLOG_WARN", 3);
define("JLOG_DEBUG", 4);

$jlog_debug_logfile = '/home/jhagstrand/logs/user/bahasa_php.log';
$jlog_notice_logfile = '/home/jhagstrand/logs/user/bahasa_notice.log';
$jlog_email = 'john@voyc.com';
$jlog_sourcefile = 'testfile.php';

function openjlog($sourcefile) {
	global $jlog_sourcefile;
	$jlog_sourcefile = $sourcefile;
}

function jlog($type, $msg) {
	global $jlog_debug_logfile, $jlog_notice_logfile, $jlog_email, $jlog_sourcefile;

	// format the message
	$m = date(DATE_RFC2822) . ' ' . $jlog_sourcefile . ' ' . $msg . "\n";

	// write the message to log
	error_log($m, 3, $jlog_debug_logfile);

	if ($type == JLOG_NOTICE) {
		// write the message also to special log
		error_log($m, 3, $jlog_notice_logfile);
	}

	if ($type == JLOG_WARN) {
		// write the message also to email
		error_log($m, 1, $jlog_email);
	}
}
?>
