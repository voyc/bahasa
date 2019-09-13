/*
 * class Sentence Generator
 */
function SenGen () {
	this.svcBase = "http://www.oldmillcreek.com/svc/";
	this.svcPrefix = "on=g.sengen";
	this.svcLoadVocab = "loadVocab.php";
	this.svcLoadMatch = "loadMatch.php";

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

		// get a starting vocab object
		this.seedVocab = g.data.vocab[this.seedVocabId];

		// get a starting pattern object
		if (!this.patternId) {
			this.patternId = this.pickPattern('sen', this.seedVocab.part);
		}
		this.pattern = g.data.pattern[this.patternId];

		// expand the seed pattern			
		this.expandPattern();

		// substitute words into the expanded pattern
		this.seedWord();
		this.fillPattern();

		// finish sentence strings
		this.sHost = this.aHost.join(' ');
		this.sEng = this.aEng.join(' ');
		this.sHost = this.sHost.substr(0,1).toUpperCase() + this.sHost.substr(1) + '.';
		this.sEng = this.sEng.substr(0,1).toUpperCase() + this.sEng.substr(1) + '.';

		// display
		this.log(this.sHost + '&nbsp;&nbsp;&nbsp;~~~&nbsp;&nbsp;&nbsp;' + this.sEng);
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
			if (pattern.type == type && pattern.host.indexOf('$'+part) > -1) {
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

		if (part == 'adj') { 
			var a = [];
			var match;
			for (var i in g.data.match) {
				match = g.data.match[i];
				if (match.lvocabid == this.seedVocab.id 
						&& match.lpart == this.seedVocab.part 
						&& match.rpart == part
						&& !this.alreadyUsed(match.rvocabid)) {
					a.push(i);
				}
			}
			var r = this.pickRandomArrayElement(a);
			vocab = g.data.vocab[g.data.match[a[r]].rvocabid];
		}
		else if (part == 'nun' && this.patternId == 15) {
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
			for (var i in g.data.vocab) {
				if (g.data.vocab[i].part == part && !this.alreadyUsed(i)) {
					a.push(i);
				}
			}
			var r = this.pickRandomArrayElement(a);
			vocab = g.data.vocab[a[r]];
		}

		this.aVocabIds.push(vocab.id);
		return vocab;
	},
	/**
	 * return true if this word has already been used in this sentence
	 *
	**/
	alreadyUsed: function(vocabid) {
		var vocab = g.data.vocab[vocabid];
		var voc, id;
		for (var i in this.aVocabIds) {
			id = this.aVocabIds[i];
			voc = g.data.vocab[id];
			if (vocab.id == voc.id || vocab.cat == voc.cat) {
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
		this.aVocabIds.push(this.seedVocab.id);
	},
	/**
	 * fillPattern
	 * Substitute words into each slot in the pattern. 
	 * 
	 *  Algorithm
	 *  For each slot in the pattern, call pickWord().
	**/
	fillPattern: function() {
		var vocab;
		for (var i in this.aHost) {
			var slot = this.aHost[i];
			if (slot.substr(0,1) == '$') {
				vocab = this.pickWord(slot.substr(1,3));
				if (!vocab) {
					debugger;
				}
				this.aHost[i] = vocab.host;
				this.replaceArrayElement(this.aEng, slot, vocab.eng);
			}
		}
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
		var url = this.svcBase + this.svcLoadVocab + "?" + this.svcPrefix;
		appendScript(url);
		var url = this.svcBase + this.svcLoadMatch + "?" + this.svcPrefix;
		appendScript(url);
		url = 'pattern.js';
		appendScript(url);
	},
	onVocabLoaded: function() {
		this.log('vocab loaded');
	},
	onMatchLoaded: function() {
		this.log('match loaded');
	},
	onPatternLoaded: function() {
		this.log('pattern loaded');
	},
	log: function(s) {
		$('log').innerHTML += s + '<br/>'; 
	}

/*
	x prohibit the same word used twice
	x support color, taste, opposites
	x $dem not correct in Eng sentence
		Itu tas coklat ini.   ~~~   That is a that brown bag.
	- $nun $nun requires matching
	~ $nun $nun requires first $nun be $dem phrase.
	two possessors requires restrictions, only first one can be pronoun
	one possessor can be proper name
	eng article (a/an) may or may not be appropriate. not in the case of possessor
	
	support banyak, sedikit
	support sengat, sekali
	support score
	support input pattern
	support input complexity
	support vocab mastery
	support verb, adv, preposition
*/

}
