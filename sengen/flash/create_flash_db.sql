create schema flash;

create table flash.user (
	id serial primary key,
	username varchar(250) not null,
	password varchar(20) not null,
	programid integer not null default 0
);
CREATE UNIQUE INDEX user_username_password
  ON flash."user"
  USING btree
  (username, "password");

create table flash.token (
	id serial primary key,
	userid integer not null default 0,
	tm timestamp without time zone not null default now(),
	token varchar(50)
);

create table flash.program (
id serial,
name varchar(50),
description varchar(500),
version varchar(10)
);

create table flash.quest (
id serial,
programid integer,
lesson integer,
seq integer,
conversation integer,
quest varchar(500),
answer varchar(500)
);
create unique index ndx_programseq on flash.quest (programid, seq);

create table flash.userprogram (
id serial,
userid integer,
programid integer,
startdate timestamp,
next_questid integer,
last_used timestamp,
working_size integer,
review_size integer,
quest_count integer
);

create table flash.progress (
id serial,
userprogramid integer,
questid integer,
state char,
asked integer,
correct integer,
recent char(10),
lastn integer
);
COMMENT ON COLUMN flash.progress.state IS 'm:mastered, r:review, w:working, u:untried.';
COMMENT ON COLUMN flash.progress.asked IS 'Count of times this question has been asked.';
COMMENT ON COLUMN flash.progress.correct IS 'Count of times this question has been answered correctly.';
COMMENT ON COLUMN flash.progress.recent IS 'The 10 most recent answers.  Each byte is space, 0, or 1.';

create table flash.vocab (
	id serial primary key,
	host varchar(50) not null, 
	eng varchar(50) not null, 
	part char(3) not null default 'unk',
	cat char(3) not null default '',
	popularity integer not null default 0,
	region integer not null default 0
);
COMMENT ON COLUMN flash.vocab.part IS 'unk, nun, pro, adj, tst, col, adv, vrb, dem, qty';

create table flash.match (
	id serial primary key,
	lvocabid integer not null, 
	lpart char(3) not null,
	rpart char(3) not null,
	rvocabid integer not null
);
COMMENT ON COLUMN flash.match.lpart IS 'unk, nun, pro, adj, tst, col, adv, vrb, dem, qty';
COMMENT ON COLUMN flash.match.rpart IS 'unk, nun, pro, adj, tst, col, adv, vrb, dem, qty';
