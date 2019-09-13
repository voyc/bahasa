create schema bahasa;

/* static content tables */
create table bahasa.program (
	id serial,
	name varchar(50),
	description varchar(500),
	version varchar(10)
);
create unique index ndx_programname on bahasa.program (name);

create table bahasa.vocab (
	id serial primary key,
	host varchar(50) not null,
	eng varchar(50) not null,
	part char(3) not null default 'unk',
	cat char(3) not null default '',
	popularity integer not null default 0,
	region integer not null default 0,
	def varchar(500) not null default ''
);
COMMENT ON COLUMN bahasa.vocab.part IS 'unk, nun, pro, adj, tst, col, adv, vrb, dem, qty';

create table bahasa.vocabmatch (
	id serial primary key,
	lvocabid integer not null,
	lpart varchar(30) not null,
	rpart varchar(30) not null,
	rvocabid integer not null
);
COMMENT ON COLUMN bahasa.vocabmatch.lpart IS 'unk, nun, pro, adj, tst, col, adv, vrb, dem, qty';
COMMENT ON COLUMN bahasa.vocabmatch.rpart IS 'unk, nun, pro, adj, tst, col, adv, vrb, dem, qty';

create table bahasa.vocabcat (
	id serial primary key,
	cat varchar(30) not null,
	vocabid integer not null
);

create table bahasa.lesson (
	id serial primary key,
	programid integer not null default 0,
	seq integer not null default 0,
	name varchar(30) not null default '',
	title varchar(50) not null default '',
	description varchar(500) not null default ''
);

create table bahasa.lessonvocab (
	id serial primary key,
	lessonid integer not null default 0,
	seq integer not null default 0,
	vocabid integer not null default 0
);

/* dynamic tables */
create table bahasa.user (
	id serial primary key,
	email varchar(250) not null default '',
	firstname varchar(250) not null default '',
	lastname varchar(250) not null default '',
	password varchar(20) not null default '',
	tier integer not null default 0,
	programid integer not null default 0
);
CREATE UNIQUE INDEX ndx_user_email_password
  ON bahasa."user"
  USING btree
  (email, "password");

create table bahasa.token (
	id serial primary key,
	userid integer not null default 0,
	tm timestamp without time zone not null default now(),
	token varchar(50)
);

create table bahasa.userprogram (
	id serial primary key,
	userid integer not null default 0,
	programid integer not null default 0,
	start timestamp not null default now(),
	mastery integer not null default 0
);

create table bahasa.userlesson (
	id serial primary key,
	userprogramid integer not null default 0,
	lessonid integer not null default 0,
	start timestamp not null default now(),
	mastery integer not null default 0
);

create table bahasa.uservocab (
	id serial primary key,
	userprogramid integer not null default 0,
	vocabid integer not null default 0,
	state char(2) not null default 'un',
	askednormal integer not null default 0,
	correctnormal integer not null default 0,
	askedreverse integer not null default 0,
	correctreverse integer not null default 0,
	ts timestamp not null default now(),
	tunormal timestamp not null default now(),
	tureverse timestamp not null default now()
);
COMMENT ON COLUMN bahasa.uservocab.state IS 'un:untriedNormal, wn:workNormal, rn:reviewNormal, mn:masteredNormal, wr:workReverse, rr:reviewReverse, mr:masteredReverse';
