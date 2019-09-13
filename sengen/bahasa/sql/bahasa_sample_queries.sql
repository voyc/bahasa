/* display match table with words instead of vocabids */
select l.host, r.host
from bahasa.match m, bahasa.vocab l, bahasa.vocab r
where m.lvocabid = l.id
and m.rvocabid = r.id

/* generate list of all nun-col-adj phrases */ 
select l.host, rc.host, ra.host, ra.eng, rc.eng, l.eng, l.id, rc.id, ra.id 
from bahasa.match adj, bahasa.match col, bahasa.vocab l, bahasa.vocab ra, bahasa.vocab rc
where adj.lpart = 'nun' and adj.rpart = 'adj'
and adj.lvocabid = col.lvocabid
and col.lpart = 'nun' and col.rpart = 'col'    /* col is no longer a part, it is a cat column in the vocab table */
and adj.lvocabid = l.id
and adj.rvocabid = ra.id
and col.rvocabid = rc.id

/* list lessons for a user program */
select * 
from bahasa.lesson l
left outer join bahasa.userlesson ul on (ul.lessonid = l.id or ul.lessonid = null)
where l.programid = 9

/* find current lesson for a user program */
select * 
from bahasa.lesson l
left outer join bahasa.userlesson ul on (ul.lessonid = l.id or ul.lessonid = null)
where l.programid = 9
and (ul.userprogramid = 1 or ul.userprogramid is null)
and (ul.mastery < 5 or ul.mastery is null)
order by seq asc
limit 1;

/* determine lesson state by looking at uservocab, not userlesson */
select l.id, min(l.programid) as programid, min(l.seq) as seq, min(l.name) as name, min(l.title) as title, min(l.description) as description, count(lv.id) as numvocab, max(uv.state) as maxstate, sum(uv.askednormal) as askednormal, sum(uv.correctnormal) as correctnormal, sum(uv.askedreverse) as askedreverse, sum(uv.correctreverse) as correctreverse, min(uv.ts) as ts,
sum(CASE WHEN uv.state='un' THEN 1 ELSE 0 END) as count_un,
sum(CASE WHEN uv.state='wn' THEN 1 ELSE 0 END) as count_wn,
sum(CASE WHEN uv.state='rn' THEN 1 ELSE 0 END) as count_rn,
sum(CASE WHEN uv.state='mn' THEN 1 ELSE 0 END) as count_mn,
sum(CASE WHEN uv.state='wr' THEN 1 ELSE 0 END) as count_wr,
sum(CASE WHEN uv.state='rr' THEN 1 ELSE 0 END) as count_rr,
sum(CASE WHEN uv.state='mr' THEN 1 ELSE 0 END) as count_mr, 
(count(lv.id) > 0 and sum(CASE WHEN uv.state='mr' THEN 1 ELSE 0 END) = count(lv.id)) as ismastered
from bahasa.lesson l
left join bahasa.lessonvocab lv on (l.id = lv.lessonid)
left join bahasa.uservocab uv on (lv.vocabid = uv.vocabid)
group by l.id
order by l.id

/**/
/**/

/* for a given noun, generate a phrase with one adj */
nun = meja 
select l.id, r.id, r.cat, r.id, l.host, r.host
from bahasa.vocabmatch vm, bahasa.vocab l, bahasa.vocab r
where vm.lpart = 'nun' and vm.rpart = 'adj'
and l.id = vm.lvocabid
and r.id = vm.rvocabid
and l.id in (5794)

/* pick a second adj, not conflicting with the first */
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

/* pick a third adj, not conflicting with the first and second */
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
