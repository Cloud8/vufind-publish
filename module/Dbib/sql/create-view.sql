create or replace view opus_citations as
     select p.oid, p.uid, r.oid citedBy, p2.uid isReferencedBy, o.title
         from opus_references r, opus_publications p, opus_publications p2,
             opus o
         where o.source_opus=r.oid
            and substr(r.uri, LOCATE('/',r.uri,8)+1) = p.uid
            and p.oid != r.oid
            and p2.oid = r.oid
  ;

delete from university;
insert into university 
   values (1, 'Test', 'Zero', 'Cut', null, null, null, null)
  ;
  
delete from opus_domain;
insert into opus_domain 
   values (1, 'Dpub', '/srv/archiv', '/adm/pub/dbib', 'http://localhost', null, null, 1)
  ;

delete from faculty;
insert into faculty values(10, 'Ministry of Sound', 780);
delete from institute;
insert into institute values(1001, 'Department of Music', 10);
