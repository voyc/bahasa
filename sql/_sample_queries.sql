select c.programid, min(p.code) as code, min(p.name) as name, count(*) 
from bahasa.card c, bahasa.program p
where c.programid = p.id
group by c.programid

