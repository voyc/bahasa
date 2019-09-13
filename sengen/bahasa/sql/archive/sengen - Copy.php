<?php
/*
Generate sentences.
*/

require_once('../phplib/config.php');
require_once('../phplib/jlog.php');
require_once('../phplib/dbUtilities.php');
openjlog(basename(__FILE__));

$userid = 2;
$programid = 1;
$userprogramid = 1;

$goptions = array();
$goptions['numSentences'] = 10;
$goptions['wordvocabid'] = 0;
$goptions['patternId'] = 0;
$goptions['subPatternId'] = 0; 
$goptions['complexity'] = 50;
$goptions['simpleSubject'] = false;
$goptions['verbcat'] = false;

$conn = getConnection();

testCase1();
return;

/**
	a sentence object
		host string
		english string
		seed word
		review word
		seed pattern
		review pattern
		array of vocab id's used, other than the seed word
**/

/**
	public
	answer these questions
		use this word in a sentence
		use this pattern in a sentence
		use only words that have been mastered
		use only patterns that have been mastered
		use a word that needs work
		use a pattern that need work

	@input:
		(optional) $numSentences - number of sentences to return, default 1
		(optional) $wordvocabid - seed word 
		(optional) $patternId - seed pattern
		(optional) $subPatternId - seed subpattern
		(optional) $complexity - complexity, default 0

	@output:
		return array of sentence objects
**/
function generateSentence($options) {
	global $goptions;
	$goptions = array_merge($options, $options);
	
	$sentences = array();

	// start with verb.  The verb determines the sentence structures possible.
	$verbs = getVerbs();
	
	// match subjects to verbs randomly
	$subjects = getPersons();
	foreach ($verbs as $num => $verb) {
		$rkey = array_rand($subjects);
		$subject = $subjects[$rkey];
		if (count($subjects) > 1) {
			unset($subjects[$rkey]);
		}
		$sen = array('verb'=>$verb,'subject'=>$subject);
		$sentences[] = 	$sen;
	}

	// add adverbs and adjectives
	foreach ($sentences as $key => &$sen) {
	
		// select an object for each sentence
		$v = $sen['verb'];
		$va = array($v);
		$objects = getOov($va);
		if (count($objects)) {
			$sen['object'] = $objects[0];
		}
	
		// select a wherein clause for each sentence
		$v = $sen['verb'];
		$va = array($v);
		$objects = getOin($va);
		if (count($objects)) {
			$sen['oin'] = $objects[0];
		}

		// select a wherefrom clause for each sentence
		$v = $sen['verb'];
		$va = array($v);
		$objects = getOfrom($va);
		if (count($objects)) {
			$sen['ofrom'] = $objects[0];
		}

		updateUserVocab(array($sen));
	}

	return $sentences;
}

function getVerbs() {
	global $userprogramid, $goptions;
	
	$numSentences = $goptions['numSentences'];
	$verbcat = $goptions['verbcat'];

	$sql = "select v.* ";
	$sql .= "from bahasa.vocab v ";
	$sql .= "join bahasa.vocabcat vc on (v.id = vc.vocabid) ";
	$sql .= "left outer join bahasa.uservocab uv on (v.id = uv.vocabid) ";
	$sql .= "where v.part = 'vrb' ";
	$sql .= "and vc.cat in ($verbcat) ";
	$sql .= "and (uv.userprogramid is null or uv.userprogramid = $userprogramid) ";
	$sql .= "order by coalesce(uv.tunormal,'2000-01-01') ";
	$sql .= "limit $numSentences ";

	$result = executeQuery($sql);
	$numrows = pg_num_rows($result);
	for ($i=0; $i<$numrows; $i++) {
	    $row = pg_fetch_array($result, $i, PGSQL_ASSOC);
		$id = $row['id'];
		$host = $row['host'];
		$eng = $row['eng'];
		$part = $row['part'];
		$cat = $row['cat'];
		$wrd = array('id'=>$id,'host'=>$host,'eng'=>$eng,'part'=>$part,'cat'=>$cat);
		$verbs[] = $wrd;
	}
	return $verbs;
}

function getPersons() {
	global $userprogramid, $goptions;

	$numSentences = $goptions['numSentences'];

	if ($goptions['simpleSubject']) {
		$sql = "select v.* ";
		$sql .= "from bahasa.vocab v ";
		$sql .= "where v.host = 'dia' ";
	}
	else {
		$sql = "select v.* ";
		$sql .= "from bahasa.vocab v ";
		$sql .= "join bahasa.vocabcat vc on (v.id = vc.vocabid) ";
		$sql .= "left outer join bahasa.uservocab uv on (v.id = uv.vocabid) ";
		$sql .= "where vc.cat = '\$person' ";
		$sql .= "and (uv.userprogramid is null or uv.userprogramid = $userprogramid) ";
		$sql .= "order by coalesce(uv.tunormal,'2000-01-01') ";
		$sql .= "limit $numSentences";
	}
	$result = executeQuery($sql);
	$numrows = pg_num_rows($result);
	$words = array();
	for ($i=0; $i<$numrows; $i++) {
	    $row = pg_fetch_array($result, $i, PGSQL_ASSOC);
		$id = $row['id'];
		$host = $row['host'];
		$eng = $row['eng'];
		$part = $row['part'];
		$cat = $row['cat'];
		$wrd = array('id'=>$id,'host'=>$host,'eng'=>$eng,'part'=>$part,'cat'=>$cat);
		$words[] = $wrd;
	}
	return $words;
}

function getListOfIds($words) {
	$ids = '';
	foreach ($words as $key => $wrd) {
		if (strlen($ids)) {
			$ids .= ",";
		}
		$ids .= $wrd['id'];
	}
	return $ids;
}

function getOov($verbs) {
	global $userprogramid;
	$ids = getListOfIds($verbs);
	$sql .= "select v.id, v.host, v.eng, v.part, v.cat ";
	$sql .= "from bahasa.vocab v ";
	$sql .= "join bahasa.vocabmatch vm on (v.id = vm.rvocabid) ";
	$sql .= "left outer join bahasa.uservocab uv on (v.id = uv.vocabid) ";
	$sql .= "where vm.rpart = 'oov' ";
	$sql .= "and vm.lvocabid in ($ids) ";
	$sql .= "and (uv.userprogramid is null or uv.userprogramid = $userprogramid) ";
	$sql .= "order by coalesce(uv.tunormal,'2000-01-01') ";

	$result = executeQuery($sql);
	$numrows = pg_num_rows($result);
	$words = array();
	for ($i=0; $i<$numrows; $i++) {
	    $row = pg_fetch_array($result, $i, PGSQL_ASSOC);
		$id = $row['id'];
		$host = $row['host'];
		$eng = $row['eng'];
		$part = $row['part'];
		$cat = $row['cat'];
		$wrd = array('id'=>$id,'host'=>$host,'eng'=>$eng,'part'=>$part,'cat'=>$cat);
		$words[] = $wrd;
	}
	return $words;
}

function getOin($verbs) {
	global $userprogramid;
	$ids = getListOfIds($verbs);
	$sql .= "select v.id, v.host, v.eng, v.part, v.cat ";
	$sql .= "from bahasa.vocab v ";
	$sql .= "join bahasa.vocabmatch vm on (v.id = vm.rvocabid) ";
	$sql .= "left outer join bahasa.uservocab uv on (v.id = uv.vocabid) ";
	$sql .= "where vm.rpart = 'oin' ";
	$sql .= "and vm.lvocabid in ($ids) ";
	$sql .= "and (uv.userprogramid is null or uv.userprogramid = $userprogramid) ";
	$sql .= "order by coalesce(uv.tunormal,'2000-01-01') ";
	$result = executeQuery($sql);
	$numrows = pg_num_rows($result);
	$words = array();
	for ($i=0; $i<$numrows; $i++) {
	    $row = pg_fetch_array($result, $i, PGSQL_ASSOC);
		$id = $row['id'];
		$host = $row['host'];
		$eng = $row['eng'];
		$part = $row['part'];
		$cat = $row['cat'];
		$wrd = array('id'=>$id,'host'=>$host,'eng'=>$eng,'part'=>$part,'cat'=>$cat);
		$words[] = $wrd;
	}
	return $words;
}


/*
	get seed word, discover part
	get sentence patterns that include this part, pick n
	expand the pattern, but substituting sub-patterns
	substitute words into the sub-patterns
	

	if ($patternId == nun adj adj adj
		pick nun and adj
		pick second adj
		pick third adj
	if pattern == nun vrb
	if pattern == nun vrb obj
	if pattern == nun vrb adv
	if pattern == nun vrb when
	if pattern == nun vrb where
	if pattern == nun vrb how
	if pattern == nun vrb when where how

	noun verb combinations
	person verb
	use each person only once
	use each verb only once
	do not repeat

	$a = array();
	$subject = 
	$verb = 
*/
		

function generateSubPattern($subPatternId) {
}

/* for a given noun, generate a phrase with one adj 
nun = meja 
select l.id, r.id, r.cat, r.id, l.host, r.host
from bahasa.vocabmatch vm, bahasa.vocab l, bahasa.vocab r
where vm.lpart = 'nun' and vm.rpart = 'adj'
and l.id = vm.lvocabid
and r.id = vm.rvocabid
and l.id in (5794)
*/

/* pick a second adj, not conflicting with the first 
meja kecil
nun = 5794
adj = 5892
cat = 1
select r.id, r.cat, r.host
from bahasa.vocabmatch vm, bahasa.vocab r
where vm.lpart = 'nun' and vm.rpart = 'adj'
and vm.lvocabid = 5794
and vm.rvocabid = r.id
and r.cat not in ('1')
*/

/* pick a third adj, not conflicting with the first and second 
meja kecil, jigau
nun = 5794
adj = 5892, 5872
cat = 1, clr
select r.id, r.cat, r.host
from bahasa.vocabmatch vm, bahasa.vocab r
where vm.lpart = 'nun' and vm.rpart = 'adj'
and vm.lvocabid = 5794
and vm.rvocabid = r.id
and r.cat not in ('1', 'clr')
*/

/**
	test cases
		generate 10 sentences using a specific word
			multiple patterns
		generate 10 phrases using a specific word
		generate 1 phrase/sentence of each pattern, with specified pattern
			with specifed word
			with specifed sub-pattern
			with specified review word
			with specified review pattern

	three new words per day
		with lots of practice and review

	chattycatty
	shut up
	
	x when genmatch runs, let it also generate a vocabcat table
	x first, get category per for person
	second, generate sentences with pattern $person $verb
		select v.host, v.eng 
		from bahasa.vocabcat vc, bahasa.vocab v
		where vc.vocabid = v.id
	third, implement $name() function for Tuan Sulendra,  I Wayan Sulendra, etc.
	four, add oov
	five, add adverb

**/

function composeSentence($value, $lang) {
	$di = array('host'=>'di', 'eng'=>'in');
	
	$s =  '';
	if ($value['subject'][$lang]) {
		$s .= $value['subject'][$lang];
	}
	
	if ($value['verb'][$lang]) {
		if ($s) {
			$s .= " ";
		}
		$s .= $value['verb'][$lang];
	}
	 
	if ($value['object'][$lang]) {
		$s .= " " . $value['object'][$lang];
	}
	
	if ($value['oin'][$lang]) {
		$s .= " " . $di[$lang] . " " . $value['oin'][$lang];
	}

	$s .= ".";
	$s = ucfirst($s);
	return $s;
}

/*
	update/insert each uservocab record used in a sentence
*/
function updateUserVocab($sen) {
	global $userprogramid;
	foreach ($sen as $k => $val) {
		foreach ($val as $key => $value) {
			$op = "update";
			$rc = "";
			$vocabid = $value['id'];
			$host = $value['host'];
			$sql = "select * from bahasa.uservocab where userprogramid = $userprogramid and vocabid = $vocabid";
			$result = executeQuery($sql);
			if ($result) {
				if (pg_num_rows($result)) {
				    $row = pg_fetch_array($result, 0, PGSQL_ASSOC);
					$id = $row['id'];
					$askednormal = $row['askednormal'];
					$askednormal++;
					$sql = "update bahasa.uservocab set tunormal = now(), askednormal = $askednormal where id = $id";
					$rc = executeSql($sql);
				}
				else {
					$sql = "insert into bahasa.uservocab (userprogramid,vocabid, tunormal, askednormal) values ($userprogramid, $vocabid, now(), 1)";
					$rc = executeSql($sql);
					$op = "insert";
				}
				//echo "$host $op $rc\n";
			}
			else {
				echo "select failed for: $host, $key\n";
				var_dump($value);
			}
		}
	}
}

function testCase1() {
	$opt = array();
	$opt['numSentences'] = 30;
	$opt['simpleSubject'] = true;
	$opt['verbcat'] = '\$move';
	$opt['verbcat'] = '\$relocate';
	$opt['verbcat'] = "'\$move', '\$relocate'";
	$sen = generateSentence($opt);

	//updateUserVocab($sen);
	
	foreach($sen as $key => $value) {
		//echo $value['subject']['host'] . " " . $value['verb']['host'] . " " . $value['object']['host'] . " di " . $value['oin']['host'] . " ~ " . $value['subject']['eng'] . " " . $value['verb']['eng'] . " " . $value['object']['eng'] . " in " . $value['oin']['eng'] . "\n";
		$sEng = composeSentence($value, 'eng');
		$sHost = composeSentence($value, 'host');
		echo "$sHost - $sEng\n";
	}
	
}
