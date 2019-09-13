/*
 * class Sentence Generator
 */
function SenGen () {
	this.svcBase = "http://www.oldmillcreek.com/html/svc/";
	this.svcPrefix = "on=g.sengen";
	this.svcLoadVocab = "loadVocab.php";
	this.svcLoadMatch = "loadMatch.php";
	this.svcLoadPattern = "loadPattern.php";

	// work areas shared by all methods
	// seems like a bad design idea.
	// Instead, why not make the sub-functions local functions within the generateSentence() function.
	this.seedVocabId = 0;
	this.patternId = 0;
	this.complexity = 0;
	this.seedVocab = null;
	this.pattern = null;
	this.aHost = [];
	this.aEng = [];
	this.sHost = '';
	this.sEng = '';
	this.aVocabIds = [];

	/**
	 * complexity
	 * complexity increases as the lesson mastery increases
	 *
	 * mastery
	 * each word has mastery
	 * each pattern has mastery
	 * each lesson has mastery
	 * master==0 means it has not yet been introduced
	 * mastery==50 means the lesson has been learned
	 * mastery > 50 increases as the number of usages increases
	 *
	 * a lesson is a group of questions
	 * a question is a word and/or a pattern
	 * a pattern represents a phrase or sentence 
	**/
}

SenGen.prototype = {
	/**
	 * generateSentence()
	 * Generate a sentence containing a given word.
	 * The pattern of the sentence can be specified.  If not it will be generated randomly.
	 *
	 * Inputs:
	 * @wordvocabid - id of the pattern to expand
	 * @complexity - number from 0 to 100 indicating desired complexity of pattern
	 * return null.  Update the input object o.
	 *
	 * Algorithm:
	 * Gather all expandable slots in the host pattern.
	 *     (currently, only $nun? is expandable)
	 * Choose one at random.
	 * Find the same slot in the english pattern.
	 * Call pickPattern() to get an appropriate subpattern.
	 * Replace the slot with the subpattern, in both host and english patterns.
	 *  
	**/
	generateSentence: function(wordvocabid, patternId, subPatternId, complexity) {
		this.seedVocabId = wordvocabid;
		this.complexity = complexity || 0;
		this.patternId = patternId || '';
		this.subPatternId = subPatternId || '';

		this.aHost = [];
		this.aEng = [];
		this.aVocabIds = [];
		this.seedVocab = null;

		// get the starting vocab object
		if (this.seedVocabId) {
			this.seedVocab = g.data.vocab[this.seedVocabId] || g.data.lesson.vocab[this.seedVocabId];
		}

		// get a starting pattern object
		if (!this.patternId) {
			//this.patternId = this.pickPattern('sen', this.seedVocab.part);
			this.patternId = this.pickPattern(null, this.seedVocab.part);
		}
		this.pattern = g.data.pattern[this.patternId] || g.data.lesson.vocab[this.patternId];
		if (!this.pattern) {
			console.log(['sentence request unfulfilled.  no pattern. ', wordvocabid, patternId, subPatternId, complexity]);
			return null;
		}

		// if seed vocab not specified, choose one to match the specified pattern
		if (!this.seedVocab) {
			this.seedVocab = this.chooseSeedVocab(patternId);
		}

		// expand the seed pattern			
		this.expandPattern();

		// substitute words into the expanded pattern
		if (this.seedVocab) {
			var rc = this.seedWord();
			if (!rc) {
				console.log('sentence request unfulfilled.  cannot seed word '+this.seedVocab.host+' into pattern '+this.patternId+' '+this.pattern.host);
				return null;
			}
		}
		var rc = this.fillPattern();
		if (!rc) {
			var tword = (this.seedVocab) ? this.seedVocab.host : 'none';
			var tpattern = (this.pattern) ? this.pattern.host : 'none';
			console.log('sentence request unfulfilled.  no sentence possible.  word:'+tword+' , pattern:'+tpattern);
			return null;
		}

		// finish sentence strings
		this.sHost = this.aHost.join(' ');
		this.sEng = this.aEng.join(' ');
		if (this.pattern.part == 'sen') {
			this.sHost = this.sHost.substr(0,1).toUpperCase() + this.sHost.substr(1) + '.';
			this.sEng = this.sEng.substr(0,1).toUpperCase() + this.sEng.substr(1) + '.';
		}

		// display
		console.log('sentence request successful.  ' + this.sHost + '  ~~~  ' + this.sEng);
		
		return {q:this.sHost, a:this.sEng, va:this.vocabIds};
	},
	
	/**
	 *  Find the most recently mastered word that has a part that appears in the requested pattern.
	 *  @patternid
	 *  return a vocab object
	**/
	chooseSeedVocab: function(patternid) {
		var voc = null;

		voc = g.data.vocab[1275];

		// get the pattern
		// get the parts used in the pattern
		// sort the vocab list by update date, descending order
		// start at the top of the list (most recent), take the first one that matches on part

		return voc;
	},

	/**
	 *  @type - type of pattern: sen, nnp, etc.
	 *  @part - part of speech that must be included in the pattern
	 *  return a pattern id
	 *
	 *  Algorithm
	 *  Gather all the patterns that match type and part.
	 *  Choose one at random.
	**/
	pickPattern: function(type, part) {
		var patternid = -1;
		var a = [];
		var pattern, allowed;
		for (var i in g.data.pattern) {
			pattern = g.data.pattern[i];
			allowed = false;
			if ((pattern.cat == type || !type) && pattern.host.indexOf('$'+part) > -1) {
				allowed = true;
			}
			if (this.patternId == 13 && i == 7) {
				allowed = false;
			}
			if (this.patternId == 15 && !(this.vocab.cat == 'pro' || this.vocab.cat == 'per')) {
				allowed = false;
			}
			if (allowed) {
				a.push(i);
			}
		}
		var r = this.pickRandomArrayElement(a);
		patternid = a[r];
		return patternid;
	},
	/** 
	 * pickWord()
	 * Pick a word of the specified part that is compatible with the seed word.
	 *  @part - part of speech of the word requested
	 *  return a vocab object
	 *
	 *  Algorithm
	 *  If part is adj, 
	 *  	Use the match table and the part to gather all the candidate vocab words
	 *  	Choose one at random.
	 *  else,
	 *      Use the vocab table to gather all words matching on part
	 *  	Choose one at random.
	**/
	pickWord: function(part) {
		var vocab = null;

		// pick an adj to go with a noun
		if (part == 'adj' && this.seedVocab && this.seedVocab.part == 'nun') { 
			var a = [];
			var match;
			for (var i in g.data.match) {
				match = g.data.match[i];
				if (match.lvocabid == this.seedVocab.vid 
						&& match.lpart == this.seedVocab.part 
						&& match.rpart == part
						&& !this.alreadyUsed(match.rvocabid)) {
					a.push(i);
				}
			}
			if (a.length) {
				var r = this.pickRandomArrayElement(a);
				var vocabid = g.data.match[a[r]].rvocabid;
				vocab = this.getVocabByVid(vocabid);
			}
		}
		// pick a noun to go with an adj
		else if (part == 'nun' && this.seedVocab && this.seedVocab.part == 'adj') {
			var a = [];
			var match;
			for (var i in g.data.match) {
				match = g.data.match[i];
				if (match.rvocabid == this.seedVocab.vid 
						&& match.rpart == this.seedVocab.part 
						&& match.lpart == part
						&& !this.alreadyUsed(match.lvocabid)) {
					a.push(i);
				}
			}
			if (a.length) {
				var r = this.pickRandomArrayElement(a);
				var vocabid = g.data.match[a[r]].lvocabid;
				vocab = this.getVocabByVid(vocabid);
			}
		}
		else if (part == 'nun' && this.patternId == 15) {
			debugger;
			var a = [];
			var voc;
			for (var i in g.data.vocab) {
				voc = g.data.vocab[i];
				if (voc.part == part 
						&& !this.alreadyUsed(i)
						&& ((this.seedVocab.cat == 'fam' && voc.cat == 'per')
						|| (this.seedVocab.cat == 'per' && voc.cat == 'fam'))) {
					a.push(i);
				}
			}
			var r = this.pickRandomArrayElement(a);
			vocab = g.data.vocab[a[r]];
		}
		else {
			var a = [];
			var voc;
			for (var i in g.data.vocab) {
				voc = g.data.vocab[i];
				if (voc.part == part && !this.alreadyUsed(voc.vid)) {
					a.push(i);
				}
			}
			var r = this.pickRandomArrayElement(a);
			vocab = g.data.vocab[a[r]];
		}

		if (vocab) {
			this.aVocabIds.push(vocab.uvid);
		}
		return vocab;
	},
	/**
	 * return true if this word has already been used in this sentence
	 *
	**/
	getVocabByVid: function(vid) {
		for (var i in g.data.vocab) {
			if (g.data.vocab[i].vid == vid) {
				return g.data.vocab[i];
			}
		}
		for (var i in g.data.lesson.vocab) {
			if (g.data.lesson.vocab[i].vid == vid) {
				return g.data.lesson.vocab[i];
			}
		}
		return null;
	},
	alreadyUsed: function(vocabid) {
		//var vocab = g.data.vocab[vocabid];
		var vocab = this.getVocabByVid(vocabid);
		if (!vocab) {
			return true; // this does not mean it's already used.  it means it's not yet mastered.
		}
		var voc, id;
		for (var i in this.aVocabIds) {
			id = this.aVocabIds[i];
			voc = g.data.vocab[id] || g.data.lesson.vocab[id];
			if (vocab.uvid == voc.uvid || (vocab.cat != '   ' && vocab.cat == voc.cat)) {
				return true;
			}
		}
		return false;
	},
	/** 
	 * seedWord()
	 * Substitute the seed word into one of the slots in the pattern.
	 *
	 *  Algorithm
	 *  Gather all expandable slots in the host pattern
	 *  	Use the match table and the part to gather all the candidate vocab words
	 *  	Choose one at random.
	 *  else,
	 *      Use the vocab table to gather all words matching on part
	 *  	Choose one at random.
	**/
	seedWord: function() {
		// find all expandable slots in host pattern
		var a = [];
		for (var i in this.aHost) {
			var word = this.aHost[i];
			if (word.substr(1,3) == this.seedVocab.part) {
				a.push(i);
			}
		}
		
		// cannot seed word into this pattern
		if (a.length <= 0) {
			return false;
		}
		
		// pick one slot to seed
		var r = this.pickRandomArrayElement(a);
		var aHostI = a[r];
		var slot = this.aHost[a[r]];

		// find same slot in the Eng pattern
		var aEngI = -1;
		var a = [];
		for (var i in this.aEng) {
			var word = this.aEng[i];
			if (word == slot) {
				aEngI = i;
			}
		}

		// make the substitution in both host and eng
		this.aHost[aHostI] = this.seedVocab.host;
		this.aEng[aEngI] = this.seedVocab.eng;
		this.aVocabIds.push(this.seedVocab.uvid);
		return true;
	},
	/**
	 * fillPattern
	 * Substitute words into each slot in the pattern. 
	 * 
	 *  Algorithm
	 *  For each slot in the pattern, call pickWord().
	**/
	fillPattern: function() {
		var success = true;
		var vocab;
		for (var i in this.aHost) {
			var slot = this.aHost[i];
			if (slot.substr(0,1) == '$') {
				vocab = this.pickWord(slot.substr(1,3));
				if (!vocab) {
					success = false;
					break;
				}
				this.aHost[i] = vocab.host;
				this.replaceArrayElement(this.aEng, slot, vocab.eng);
			}
		}
		return success;
	},

	// Replace one element in an array.
	replaceArrayElement: function(a, currentValue, newValue) {
		for (var i in a) {
			if (a[i] == currentValue) {
				a[i] = newValue;
			}
		}
	},
	// Return the index of a random element in an array.
	pickRandomArrayElement: function(a) {
		return parseInt(Math.random() * a.length);
	},

	/**
	 * expandPattern()
	 * Substitute zero or many slots in a pattern with subpatterns.
	 *
	 * Algorithm:
	 * Gather all expandable slots in the host pattern.
	 *     (currently, only $nun? is expandable)
	 * Choose one at random.
	 * Find the same slot in the english pattern.
	 * Call pickPattern() to get an appropriate subpattern.
	 * Replace the slot with the subpattern, in both host and english patterns.
	 *  
	**/
	expandPattern: function() {
		this.aHost = this.pattern.host.split(' ');
		this.aEng = this.pattern.eng.split(' ');
		
		if (this.complexity < 20) {
			return;
		}

		// find all expandable slots in host pattern
		var a = [];
		for (var i in this.aHost) {
			var word = this.aHost[i];
			if (word.substr(0,4) == '$nun') {
				a.push(i);
			}
		}
		
		// pick one slot to expand
		var r = this.pickRandomArrayElement(a);
		var aHostI = a[r];
		var slot = this.aHost[a[r]];

		// find same slot in the Eng pattern
		var aEngI = -1;
		var a = [];
		for (var i in this.aEng) {
			var word = this.aEng[i];
			if (word == slot) {
				aEngI = i;
			}
		}

		// pick a phrase pattern to substitute into this slot
		if (!this.subPatternId) {
			this.subPatternId = this.pickPattern('nnp', 'nun');
		}

		// make the substitution in both host and eng
		this.aHost[aHostI] = g.data.pattern[this.subPatternId].host;
		this.aEng[aEngI] = g.data.pattern[this.subPatternId].eng;

		this.aHost = this.aHost.join(' ').split(' ');
		this.aEng = this.aEng.join(' ').split(' ');
	},

	start: function() {
		var url = this.svcBase + this.svcLoadVocab + "?" + this.svcPrefix + "&lu=" + g.data.user.lu + "&lw=" + g.data.user.programid;
		appendScript(url);
		url = this.svcBase + this.svcLoadMatch + "?" + this.svcPrefix;
		appendScript(url);
		url = this.svcBase + this.svcLoadPattern + "?" + this.svcPrefix;
		appendScript(url);
	},
	onVocabLoaded: function() {
		// fix dates
		var x,tun,tur;
		for (x in g.data.vocab) {
			tun = new Date(g.data.vocab[x].tun);
			tur = new Date(g.data.vocab[x].tur);
			g.data.vocab[x].tun = tun;
			g.data.vocab[x].tur = tur;
		}
		console.log('vocab loaded');
	},
	onMatchLoaded: function() {
		console.log('match loaded');
	},
	onPatternLoaded: function() {
		console.log('pattern loaded');
	},
	addToVocab: function(x) {
		
	}
}
