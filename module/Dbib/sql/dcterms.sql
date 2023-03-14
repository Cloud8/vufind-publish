
select distinct o.title, o.creator_corporate, o.subject_swd, o.description,
        o.publisher_university, o.contributors_name, o.contributors_corporate,
        o.date_year, from_unixtime(o.date_creation, '%Y-%m-%d') date_creation, 
        from_unixtime(o.date_modified,'%Y-%m-%d') date_modified,
        r.type, r.dini_publtype, o.source_opus, o.source_title,
        o.source_swb, o.verification, o.subject_uncontrolled_german,
        o.subject_uncontrolled_english, o.title_alt, o.description2,
        o.subject_type, from_unixtime(o.date_valid,'%Y-%m-%d') date_valid,
        o.description_lang, o.description2_lang, o.isbn, o.sachgruppe_ddc,
        o.lic, lc.link as license, 
        from_unixtime(d.date_accepted,'%Y-%m-%d') date_accepted, o.bem_extern,
        d.advisor, d.publisher_faculty, f.name faculty_name, 
        i.inst_nr, inst.name inst_name, s.sachgruppe, s.sachgruppe_en, 
        od.ip as accessRights, l.sprache as language, 
        l.iso_639_1, l.code as iso_639_2, l2.iso_639_1 as iso_lang2
 from opus o 
        left join resource_type r on r.typeid = o.type
        left join opus_diss d on o.source_opus=d.source_opus
        left join opus_inst i on o.source_opus=i.source_opus
        left join faculty f on f.nr=d.publisher_faculty
        left join institute inst on i.inst_nr=inst.nr
        left join sachgruppe_ddc s on o.sachgruppe_ddc=s.nr
        left join license lc on o.lic=lc.shortname
        left join opus_domain od on o.bereich_id=od.id and od.ip is not null
        left join language l on o.language=l.code
        left join language l2 on o.description2_lang=l2.code
 where o.source_opus=<oid>
 limit 1
 ;
 select creator_name,reihenfolge,gnd,orcid from opus_autor 
 where source_opus=<oid> order by reihenfolge
 ; 
 select p.class, p.bez from opus_pacs o, pacs2003 p
     where o.class=p.class and source_opus=<oid>
 ;
 select j.class, j.bez from opus_jel o, jel2007 j
     where o.class=j.class and source_opus=<oid>
 ;
 select m.class, m.bez from opus_msc o, msc2000 m
     where o.class=m.class and source_opus=<oid>
 ;
 select c.class, c.bez from opus_ccs o, ccs98 c
     where o.class=c.class and source_opus=<oid>
 ;
 select oid,name,link,tag from opus_links where oid=<oid>
 ;
 -- series
 select s.sr_id, s.name, s.url, s.urn, s.doi, s.zdb, s.faculty, s.year, 
     s.contributor, s.description, r.type, os.sequence_nr, f.name organization, 
     u.universitaet, u.uni_gnd, u.instname, u.inst_gnd
     from schriftenreihen s left join faculty f ON s.faculty=f.nr
         left join resource_type r on s.type=r.typeid,
         opus_schriftenreihe os, university u 
     where os.sr_id=s.sr_id
         and u.id=1
         and os.source_opus=<oid>
 ; -- serial parts
 select concat(d.url,'/',p.uid) url, p.oid, os2.sequence_nr 
     from opus_schriftenreihe os1, opus_schriftenreihe os2, 
         opus_publications p, opus_domain d
     where os1.sr_id=os2.sr_id
      and p.oid = os2.source_opus
      and p.oid <> os1.source_opus
      and d.id=1
      and os1.source_opus=<oid>
 ;
 -- collection
 select c.coll_id, c.coll_name, c.url, c.urn, c.description
         from collections c, opus_coll oc
         where oc.coll_id=c.coll_id
         and oc.source_opus=<oid>
 ;
 -- publications
 select oid, uid, urn, doi, ppn from opus_publications where oid=<oid>
 ;
 -- graph
 select d.url, d.longname, u.universitaet, u.uni_gnd, u.instname, u.inst_gnd 
     from opus_domain d left join university u on u.id=d.domain, opus o 
     where d.id=o.bereich_id and o.source_opus=<oid>
 ;
 -- files
 select name, ifnull(extent,'') extent 
     from opus_files where oid=<oid>
 ;

 -- toc table of contents
 select seq, label, page, number from opus_toc where oid=<oid>  
 order by seq
 ;
 -- references
 select r.oid, r.uri, r.title, r.date, r.cite, r.authors 
 from opus_references r, opus_publications p
 where p.oid=r.oid and substr(r.uri,33)!=p.uid
     and r.oid=<oid>
 ;
 -- citations
 select c.oid, c.isReferencedBy, c.title 
 from opus_citations c
 where c.oid=<oid>
 -- -------------------------------------------------------------------------
