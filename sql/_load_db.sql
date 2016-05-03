/* program table */
insert into bahasa.program(id, name, code, title, description) values(1, 'indonesian', 'id', 'Indonesian', 'Indonesian');
insert into bahasa.program(id, name, code, title, description) values(2, 'spanish'   , 'es', 'Spanish'   , 'Spanish'   );
insert into bahasa.program(id, name, code, title, description) values(3, 'thai'      , 'th', 'Thai'      , 'Thai'      );
insert into bahasa.program(id, name, code, title, description) values(4, 'czech'     , 'cs', 'Czech'     , 'Czech'     );
insert into bahasa.program(id, name, code, title, description) values(5, 'tibetan'   , 'bo', 'Tibetan'   , 'Tibetan'   );
insert into bahasa.program(id, name, code, title, description) values(6, 'sanskrit'  , 'sa', 'Sanskrit'  , 'Sanskrit'  );
SELECT setval('bahasa.program_id_seq', 6);
