<?php
/*
Read a lesson plan file and generate all the content for that program.
Generate:
	program record
	lesson records
	lessonvocab records
*/

require_once('../phplib/config.php');
require_once('../phplib/jlog.php');
require_once('../phplib/dbUtilities.php');
openjlog(basename(__FILE__));

$conn = getConnection();

$programid = 0;

$name = '';
$title = '';
$description = '';
$avocab = array();
$aidiom = array();
$apattern = array();

$lines = file('indonesian_lesson_plan.txt');
$seq = 1;
$total = 0;
foreach ($lines as $line_num => $line) {
	if ($seq < 0) {
		break;
	}
	$a = explode(":",$line);
	$action = trim($a[0]);
	$data = trim($a[1]);
	//echo "$action  ~~  $data\n";

	switch ($action) {
		case 'program':
			$programid = getProgramId($data);
			if ($programid) {
				deleteProgram($programid);
			}
		break;
		case 'name':
			$name = $data;
		break;
		case 'title':
			$title = $data;
		break;
		case 'description':
			$description = $data;
		break;
		case 'vocab':
			$avocab = explode(",",$data);
		break;
		case 'idioms': 
			$aidiom = explode(",",$data);
		break;
		case 'patterns':
			$apattern = explode(",",$data);
		break;
		case '':
			if ($name) {
				$cnt = writeLesson($programid, $seq, $name, $title, $description, $avocab, $aidiom, $apattern);
				$name = '';
				$title = '';
				$description = '';
				$avocab = array();
				$aidiom = array();
				$apattern = array();
				$seq++;
				$total += $cnt;
			}
		break;
		case 'end':
			$seq = -1;
		break;
	}
}
echo "Program completed.  Total vocab count: $total\n";
return;

//-------------------------------

function writeLesson($programid, $seq, $name, $title, $description, $avocab, $aidiom, $apattern) {
	// write lesson record
	$lessonid = getNextSequence('bahasa.lesson_id_seq');
	$sql = "insert into bahasa.lesson ";
	$sql .= "values ($lessonid, $programid, $seq, '$name', '$title', '$description')";
	$rc = executeSql($sql);
	if (!$rc) {
		echo "insert lesson $name failed\n";
	}

	// write lessonvocab records
	$vocseq = 1;
	foreach ($avocab as $num => $vocab) {
		$bo = insertLessonVocab($lessonid, $vocab, $vocseq);
		if ($bo) {
			$vocseq++;
		}
	}
	foreach ($aidiom as $num => $vocab) {
		$bo = insertLessonVocab($lessonid, $vocab, $vocseq);
		if ($bo) {
			$vocseq++;
		}
	}
	foreach ($apattern as $num => $vocab) {
		$bo = insertLessonVocab($lessonid, $vocab, $vocseq);
		if ($bo) {
			$vocseq++;
		}
	}

	$cnt = $vocseq-1;
	echo "Lesson $seq completed.  vocab count: $cnt\n";
	return $cnt;
}

function getProgramId($name) {
	global $conn;
	$id = 0;
	$sql = "select id from bahasa.program where name = '$name';";
	$id = executeQueryOne($sql);
	if (!$id) {
		$id = getNextSequence('bahasa.program_id_seq');
		$sql = "insert into bahasa.program values ($id, '$name', '', '1')";
		executeSql($sql);
	}
	return $id;
}

function insertLessonVocab($lessonid, $vocab, $seq) {
	$success = false;
	$voc = trim($vocab);
	if ($voc) {
		$vocabid = executeQueryOne( "select id from bahasa.vocab where host = '$voc'");
		if ($vocabid) {
			$lvid = executeQueryOne( "select id from bahasa.lessonvocab where vocabid = '$vocabid'");
			if ($lvid) {
				echo "vocab $voc already introduced in earlier lesson\n";
			}
			else {
				$sql = "insert into bahasa.lessonvocab (lessonid, seq, vocabid) ";
				$sql .= "values ($lessonid, $seq, $vocabid)";
				$success = executeSql($sql);
				if (!$success) {
					echo "insert lessonvocab $name failed\n";
				}
			}
		}
		else {
			echo "no vocab record found for $voc\n";
		}
	}
	return $success;
}

function deleteProgram($programid) {
	$sql = "delete from bahasa.lessonvocab where lessonid in (";
	$sql .= "select lv.lessonid from bahasa.lesson l ";
	$sql .= "join bahasa.lessonvocab lv on (l.id = lv.lessonid) ";
	$sql .= "where l.programid = $programid)";
	$rc = executeSql($sql);
	$s = ($rc) ? 'successful' : 'failed';
	echo "Deletion of lessonvocab records $s\n";

	$rc = executeSql("delete from bahasa.lesson where programid = $programid");
	$s = ($rc) ? 'successful' : 'failed';
	echo "Deletion of lesson records $s\n";
}

?>
