/* 
This SQL is designed for postgres.

After creating this schema, execute the GRANT statements found in the comments section of the config.php file.
*/

/*
grant connect on database jhagstrand_bahasa to webuser64;
grant usage on schema bahasa to webuser64;
grant select on bahasa.program to webuser64;
grant select on bahasa.program_id_seq to webuser64;
grant select on bahasa.card to webuser64;
grant select on bahasa.card_id_seq to webuser64;
grant select on bahasa.userprogram  to webuser64;
grant select on bahasa.userprogram_id_seq to webuser64;
grant select on bahasa.usercard to webuser64;
grant select on bahasa.usercard_id_seq to webuser64;
*/


create schema bahasa;

/* static content tables */

/* program: one program for each language */
drop table bahasa.program;
create table bahasa.program (
	id serial,
	name varchar(20),
	code char(2),
	title varchar(50),
	description varchar(500)
);

/* card: one card for each card/answer pair */
drop table bahasa.card;
create table bahasa.card (
	id serial,
	programid integer not null default 0,
	level integer not null default 0,
	seq integer not null default 0,
	quest varchar(500) not null default '',
	answer varchar(500) not null default '',
	translit varchar(500) not null default '',
	part char(1) not null default '',
	subpart char(3) not null default '',
	gender char(1) not null default '',
	audio varchar(20) default null,
	components varchar(1000) default null,
	numcomponents integer not null default 0
);
create unique index ndx_card_programlevelseq on bahasa.card (programid, level, seq);
comment on column bahasa.card.programid is 'foreign key to program table';
comment on column bahasa.card.level is 'level number within the program';
comment on column bahasa.card.seq is 'sequence within level';
comment on column bahasa.card.part is 'a:alphabet, s:syllable, w:word, p:phrase, s:sentence, g:greeting';
comment on column bahasa.card.subpart is 'see chart for valid values per part';
comment on column bahasa.card.quest is 'question in foreign study language';
comment on column bahasa.card.answer is 'answer in users native langauge (english)';
comment on column bahasa.card.translit is 'transliteration of the question';


/* dynamic tables saving users' progress  */

/* userprogram: one record for each program a user has worked on, saving the level attained in that program */
drop table bahasa.userprogram;
create table bahasa.userprogram (
	id serial primary key,
	userid integer not null default 0,
	programid integer not null default 0,
	level integer not null default 0,
	start timestamp not null default now(),
	recent timestamp not null default now()
);
create unique index ndx_userprogram_user on bahasa.userprogram (userid);

/* usercard: one record, two counts, for each user/card/state/dir  */
drop table bahasa.usercard;
create table bahasa.usercard (
	id serial,
	userid integer,
	cardid integer,
	state char,
	dir char(2),
	asked integer,
	correct integer
);
create unique index ndx_usercard_usercard on bahasa.usercard (userid, cardid);
comment on column bahasa.usercard.state is 'm:mastered, r:review, w:working, u:untried.';
comment on column bahasa.usercard.dir is 'qa:forward, aq:reverse.';
comment on column bahasa.usercard.asked is 'Count of times this card has been asked.';
comment on column bahasa.usercard.correct is 'Count of times this card has been answered correctly.';
