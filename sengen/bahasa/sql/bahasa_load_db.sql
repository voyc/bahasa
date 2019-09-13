/* static content tables */
insert into bahasa.program(id, name, description, version) values(1, 'Indonesian', 'indonesian', '1');
insert into bahasa.program(id, name, description, version) values(2, 'Spanish', 'spanish', '1');
SELECT setval('bahasa.program_id_seq', 2);

insert into bahasa.user values( 1, 'emailguest', 'Guest', 'Anonymous', 'pwguest', 0, 1);
insert into bahasa.user values( 2, 'john@voyc.com', 'John', 'Hagstrand', 'hola', 1, 2);
SELECT setval('bahasa.user_id_seq', 2);

insert into bahasa.token (userid, token) values (1, 'e328b74e1f6e5738b07ff558b7827d9e');
insert into bahasa.token (userid, token) values (2, 'f0a9be83f3ad765a42544f210154b88b');
SELECT setval('bahasa.token_id_seq', 2);

