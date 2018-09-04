--
-- PostgreSQL stored functions and triggers for DBMS-side checking of IntraACL per-page permissions
-- Author: Vitaliy Filippov, 2016
-- License: GNU LGPLv3 or newer
--

--
-- Stored procedure for creating parent_pages table
--
create or replace function /*_*/create_parent_pages() returns void as
$mw$
begin
  create table /*_*/parent_pages (
    parent_page_id int not null,
    page_id int not null,
    primary key (page_id),
    foreign key (page_id) references /*_*/page (page_id) on delete cascade on update cascade,
    foreign key (parent_page_id) references /*_*/page (page_id) on delete cascade on update cascade
  );
  insert into /*_*/parent_pages
    select p1.page_id parent_page_id, p2.page_id page_id
    from /*_*/page p1
    inner join /*_*/intraacl_rules r1 on r1.pe_type=10 and r1.pe_id=p1.page_id and r1.child_type=6 and r1.child_id=0
    inner join /*_*/page p2 on p2.page_namespace=p1.page_namespace
      and p2.page_title like concat(replace(replace(p1.page_title, '%', '\%'), '_', '\_'), '/%')
    left join /*_*/intraacl_rules r2 on r2.pe_type=10 and r2.pe_id=p2.page_id and r2.child_type=6 and r2.child_id=0
    left join /*_*/page p3 on p3.page_namespace=p1.page_namespace
      and p3.page_title like concat(replace(replace(p1.page_title, '%', '\%'), '_', '\_'), '/%')
      and p2.page_title like concat(replace(replace(p3.page_title, '%', '\%'), '_', '\_'), '/%')
    left join /*_*/intraacl_rules r3 on r3.pe_type=10 and r3.pe_id=p3.page_id and r3.child_type=6 and r3.child_id=0
    where r2.pe_id is null and (p3.page_id is null or r3.pe_id is null)
    union all
    select p1.page_id, p1.page_id
      from /*_*/page p1
      inner join /*_*/intraacl_rules r1 on r1.pe_type=10 and r1.pe_id=p1.page_id and r1.child_type=6 and r1.child_id=0;
end
$mw$
language plpgsql;

drop table if exists /*_*/parent_pages;

select /*_*/create_parent_pages();

create or replace function /*_*/refresh_parent_pages_for_children(_page_id int, _page_namespace int, _page_title text) returns void as
$mw$
begin
  -- emulate REPLACE
  with tp (parent_page_id, page_id) as (
    select _page_id, p2.page_id
      from /*_*/page p2
      inner join /*_*/intraacl_rules r1 on r1.pe_type=10 and r1.pe_id=_page_id and r1.child_type=6 and r1.child_id=0
      left join /*_*/intraacl_rules r2 on r2.pe_type=10 and r2.pe_id=p2.page_id and r2.child_type=6 and r2.child_id=0
      left join /*_*/page p3 on p3.page_namespace=_page_namespace
        and p3.page_title like concat(replace(replace(_page_title, '%', '\%'), '_', '\_'), '/%')
        and p2.page_title like concat(replace(replace(p3.page_title, '%', '\%'), '_', '\_'), '/%')
      left join /*_*/intraacl_rules r3 on r3.pe_type=10 and r3.pe_id=p3.page_id and r3.child_type=6 and r3.child_id=0
      where r2.pe_id is null and p2.page_namespace=_page_namespace
        and p2.page_title like concat(replace(replace(_page_title, '%', '\%'), '_', '\_'), '/%')
        and (p3.page_id is null or r3.pe_id is null)
    union all
    select _page_id, p2.page_id
      from /*_*/page p2
      inner join /*_*/intraacl_rules r1 on r1.pe_type=10 and r1.pe_id=_page_id and r1.child_type=6 and r1.child_id=0
      where p2.page_id=_page_id
  ),
  del as (delete from /*_*/parent_pages where (page_id) in (select page_id from tp))
  insert into /*_*/parent_pages (parent_page_id, page_id) select * from tp;
end
$mw$
language plpgsql;

create or replace function /*_*/get_parent_for_page(_page_namespace int, _page_title text) returns int as
$mw$
declare
  prev int default 0;
  pos int default 0;
  _parent_id int default null;
begin
  loop
    prev := pos;
    select p1.page_id from /*_*/page p1
      inner join /*_*/intraacl_rules r1 on r1.pe_type=10 and r1.pe_id=p1.page_id and r1.child_type=6 and r1.child_id=0
      where p1.page_namespace=_page_namespace
      and p1.page_title = substr(_page_title, 1, length(_page_title)-pos)
      into _parent_id;
    if found then
      pos := 0;
    else
      pos := position('/' in substr(reverse(_page_title), prev+1));
      if pos > 0 then
        pos := pos+prev;
      end if;
    end if;
    exit when pos <= 0;
  end loop;
  return _parent_id;
end
$mw$
language plpgsql;

create or replace function /*_*/refresh_parent_for_page(_page_id int, _page_namespace int, _page_title text) returns void as
$mw$
declare
  _parent_id int default null;
begin
  delete from /*_*/parent_pages where page_id=_page_id;
  select /*_*/get_parent_for_page(_page_namespace, _page_title) into _parent_id;
  if _parent_id is not null then
    insert into /*_*/parent_pages (parent_page_id, page_id) values (_parent_id, _page_id);
  end if;
end
$mw$
language plpgsql;

create or replace function /*_*/refresh_all_parents_for_page(_page_id int, _page_namespace int, _page_title text) returns void as
$mw$
begin
  if _page_namespace is null or _page_title is null then
    select page_namespace, page_title from page where page_id=_page_id into _page_namespace, _page_title;
  end if;
  perform /*_*/refresh_parent_pages_for_children(_page_id, _page_namespace, _page_title);
  perform /*_*/refresh_parent_for_page(_page_id, _page_namespace, _page_title);
end
$mw$
language plpgsql;

create or replace function /*_*/refresh_parent_pages_for_parent_children(_page_id int, _page_namespace int, _page_title text) returns void as
$mw$
declare
  _parent_id int default null;
begin
  if _page_namespace is null or _page_title is null then
    select page_namespace, page_title from page where page_id=_page_id into _page_namespace, _page_title;
  end if;
  select /*_*/get_parent_for_page(_page_namespace, _page_title) into _parent_id;
  if _parent_id is null then
    delete from /*_*/parent_pages where parent_page_id=_page_id;
  else
    update /*_*/parent_pages set parent_page_id=_parent_id where parent_page_id=_page_id;
  end if;
end
$mw$
language plpgsql;

--
-- Stored procedure for creating category_closure table
--
create or replace function /*_*/create_category_closure() returns void as
$mw$
declare
  n bigint default 0;
  prev bigint default 0;
begin
  create table /*_*/category_closure as
    with recursive tp (page_id, category_id) as (
      select l.cl_from page_id, p.page_id category_id
      from /*_*/categorylinks l, /*_*/page p
      where p.page_namespace=14 and p.page_title=l.cl_to
      union
      select l.cl_from page_id, tp.category_id
      from /*_*/categorylinks l, /*_*/page p, tp
      where p.page_namespace=14 and p.page_title=l.cl_to and p.page_id=tp.page_id
    )
    select tp.page_id, tp.category_id from tp;
end
$mw$
language plpgsql;

drop table if exists /*_*/category_closure;

select /*_*/create_category_closure();

--
-- Triggers for maintaining category_closure table in actual state
--

-- add all categories for changed_page_id
create or replace function /*_*/fill_category_closure(changed_page_id int) returns void as
$mw$
begin
  insert into /*_*/category_closure (page_id, category_id)
    with recursive tp (category_id) as (
      select p.page_id
      from /*_*/categorylinks c
      inner join /*_*/page p on c.cl_from=changed_page_id and c.cl_to=p.page_title and p.page_namespace=14
      union
      select c2.category_id from p
      inner join /*_*/category_closure c2 on c2.page_id=p.category_id
    )
    select changed_page_id, tp.page_id from tp
    -- "insert ignore"
    left join /*_*/category_closure c2 on c2.page_id=changed_page_id and c2.category_id=tp.page_id
    where c2.page_id is null;
end
$mw$
language plpgsql;

create or replace function /*_*/add_category_closure_catlinks(pg_id int, cat_id int) returns void as
$mw$
begin
  insert into /*_*/category_closure
    select pt.pg_id, pt.cat_id from (
      -- add the (pg_id -> cat_id) edge
      select pg_id, cat_id
      union
      -- add indirect edges: (pg_id -> cat_id) (cat_id -> *2) --> (pg_id -> *2)
      select pg_id, c1.category_id from /*_*/category_closure c1 where c1.page_id=cat_id
      union
      -- add indirect edges: (*1 -> pg_id) (pg_id -> cat_id) --> (*1 -> cat_id)
      select c1.page_id, cat_id from /*_*/category_closure c1 where c1.category_id=pg_id
      union
      -- add indirect edges: (*1 -> pg_id) (cat_id -> *2) --> (*1 -> *2)
      select c1.page_id, c2.category_id
      from /*_*/category_closure c1, /*_*/category_closure c2
      where c1.category_id=pg_id and c2.page_id=cat_id
    ) pt
    -- "insert ignore"
    left join /*_*/category_closure c2 on c2.page_id=pt.pg_id and c2.category_id=pt.cat_id
    where c2.page_id is null;
end
$mw$
language plpgsql;

create or replace function /*_*/rm_category_closure_catlinks(old_page_id int, old_cat_id int) returns void as
$mw$
declare
  cur cursor for select page_id from /*_*/category_closure where category_id=old_page_id;
begin
  -- rebuild everything to the "left" of deleted edge of categorylinks graph
  for r in cur loop
    delete from /*_*/category_closure where page_id=r.page_id;
    perform /*_*/fill_category_closure(r.page_id);
  end loop;
  if old_page_id is not null then
    delete from /*_*/category_closure where page_id=old_page_id;
    perform /*_*/fill_category_closure(old_page_id);
  end if;
end
$mw$
language plpgsql;

create or replace function /*_*/do_insert_category_closure_catlinks() returns trigger as
$mw$
declare
  cat_id int;
begin
  select page_id from /*_*/page where page_namespace=14 and page_title=NEW.cl_to into cat_id;
  if cat_id is not null then
    perform /*_*/add_category_closure_catlinks(NEW.cl_from, cat_id);
  end if;
  return NEW;
end
$mw$
language plpgsql;
drop trigger if exists /*_*/insert_category_closure_catlinks on /*_*/categorylinks;
create trigger /*_*/insert_category_closure_catlinks after insert on /*_*/categorylinks
for each row execute procedure /*_*/do_insert_category_closure_catlinks();

create or replace function /*_*/do_delete_category_closure_catlinks() returns trigger as
$mw$
declare
  cat_id int;
begin
  select page_id from /*_*/page where page_namespace=14 and page_title=OLD.cl_to into cat_id;
  if cat_id is not null then
    perform /*_*/rm_category_closure_catlinks(OLD.cl_from, cat_id);
  end if;
  return OLD;
end
$mw$
language plpgsql;
drop trigger if exists /*_*/delete_category_closure_catlinks on /*_*/categorylinks;
create trigger /*_*/delete_category_closure_catlinks after delete on /*_*/categorylinks
for each row execute procedure /*_*/do_delete_category_closure_catlinks();

create or replace function /*_*/do_update_category_closure_catlinks() returns trigger as
$mw$
declare
  cat_id int;
begin
  -- treat update as delete+insert
  if OLD.cl_from != NEW.cl_from or OLD.cl_to != NEW.cl_to then
    select page_id from /*_*/page where page_namespace=14 and page_title=OLD.cl_to into cat_id;
    if cat_id is not null then
      perform /*_*/rm_category_closure_catlinks(OLD.cl_from, cat_id);
    end if;
    select page_id from /*_*/page where page_namespace=14 and page_title=NEW.cl_to into cat_id;
    if cat_id is not null then
      perform /*_*/add_category_closure_catlinks(NEW.cl_from, cat_id);
    end if;
  end if;
  return NEW;
end
$mw$
language plpgsql;
drop trigger if exists /*_*/update_category_closure_catlinks on /*_*/categorylinks;
create trigger /*_*/update_category_closure_catlinks after update on /*_*/categorylinks
for each row execute procedure /*_*/do_update_category_closure_catlinks();

-- Handle additions/removals of category pages
create or replace function /*_*/do_insert_category_closure_page() returns trigger as
$mw$
begin
  if NEW.page_namespace=14 then
    -- new category is emerging
    insert into /*_*/category_closure (page_id, category_id)
      select l.cl_from, NEW.page_id
      from /*_*/categorylinks l
      -- "insert ignore"
      left join /*_*/category_closure c2 on c2.page_id=l.cl_from and c2.category_id=NEW.page_id
      where l.cl_to=NEW.page_title and c2.page_id is null;
    insert into /*_*/category_closure (page_id, category_id)
      select c1.page_id, c2.category_id
      from /*_*/category_closure c1, /*_*/category_closure c2
      -- "insert ignore"
      left join /*_*/category_closure c3 on c3.page_id=c1.page_id and c3.category_id=c2.category_id
      where c1.category_id=c2.page_id and c2.category_id=NEW.page_id;
  end if;
  -- add new parent/child subpage records
  perform /*_*/refresh_all_parents_for_page(NEW.page_id, NEW.page_namespace, NEW.page_title);
  return NEW;
end
$mw$
language plpgsql;
drop trigger if exists /*_*/insert_category_closure_page on /*_*/page;
create trigger /*_*/insert_category_closure_page after insert on /*_*/page
for each row execute procedure /*_*/do_insert_category_closure_page();

create or replace function /*_*/do_delete_category_closure_page() returns trigger as
$mw$
begin
  if OLD.page_namespace=14 then
    perform /*_*/rm_category_closure_catlinks(NULL, OLD.page_id);
  end if;
  perform /*_*/refresh_parent_pages_for_parent_children(OLD.page_id, OLD.page_namespace, OLD.page_title);
  return OLD;
end
$mw$
language plpgsql;
drop trigger if exists /*_*/delete_category_closure_page on /*_*/page;
create trigger /*_*/delete_category_closure_page after delete on /*_*/page
for each row execute procedure /*_*/do_delete_category_closure_page();

create or replace function /*_*/do_update_category_closure_page() returns trigger as
$mw$
begin
  -- can normally happen only upon rename of a category page
  -- also treat as delete+insert
  if (OLD.page_namespace!=NEW.page_namespace or OLD.page_id!=NEW.page_id OR OLD.page_title!=NEW.page_title) then
    if OLD.page_namespace=14 then
      perform /*_*/rm_category_closure_catlinks(NULL, OLD.page_id);
    end if;
    if NEW.page_namespace=14 then
      -- new category is emerging
      insert into /*_*/category_closure (page_id, category_id)
        select l.cl_from, NEW.page_id
        from /*_*/categorylinks l
        -- "insert ignore"
        left join /*_*/category_closure c2 on c2.page_id=l.cl_from and c2.category_id=NEW.page_id
        where l.cl_to=NEW.page_title and c2.page_id is null;
      insert into /*_*/category_closure (page_id, category_id)
        select c1.page_id, c2.category_id
        from /*_*/category_closure c1, /*_*/category_closure c2
        -- "insert ignore"
        left join /*_*/category_closure c3 on c3.page_id=c1.page_id and c3.category_id=c2.category_id
        where c1.category_id=c2.page_id and c2.category_id=NEW.page_id;
    end if;
    -- update parent/child subpage records
    perform /*_*/refresh_parent_pages_for_parent_children(OLD.page_id, OLD.page_namespace, OLD.page_title);
    perform /*_*/refresh_all_parents_for_page(NEW.page_id, NEW.page_namespace, NEW.page_title);
  end if;
  return NEW;
end
$mw$
language plpgsql;
drop trigger if exists /*_*/update_category_closure_page on /*_*/page;
create trigger /*_*/update_category_closure_page after update on /*_*/page
for each row execute procedure /*_*/do_update_category_closure_page();
