insert into flash.user values( 1, 'Guest', 'guest', 1);
insert into flash.user values( 2, 'john@voyc.com', 'hola', 7);
SELECT setval('flash.user_id_seq', 2);

insert into flash.token (userid, token) values (1, 'e328b74e1f6e5738b07ff558b7827d9e');
insert into flash.token (userid, token) values (2, 'f0a9be83f3ad765a42544f210154b88b');
SELECT setval('flash.token_id_seq', 2);

insert into flash.program(id, name, description, version) values(1, 'Spanish', 'spanish', '1');
insert into flash.program(id, name, description, version) values(2, 'Learning Indonesian', 'indonesian', '1');
insert into flash.program(id, name, description, version) values(3, 'You Can Speak Indonesian', 'indonesian', '1');
insert into flash.program(id, name, description, version) values(4, 'Indonesian Gracesima', 'indonesian', '1');
insert into flash.program(id, name, description, version) values(5, 'Multiplication', 'multiplication', '1');
SELECT setval('flash.program_id_seq', 5);
