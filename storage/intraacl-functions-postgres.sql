--
-- PostgreSQL stored functions and triggers for DBMS-side checking of IntraACL per-page permissions
-- Author: Vitaliy Filippov, 2016
-- License: GNU LGPLv3 or newer
--

--
-- Stored procedure for creating category_closure table
--
create or replace function /*$wgDBprefix*/create_category_closure() returns void as
$mw$
declare
  n bigint default 0;
  prev bigint default 0;
begin
  create table /*$wgDBprefix*/category_closure as
    with recursive tp (page_id, category_id) as (
      select l.cl_from page_id, p.page_id category_id
      from /*$wgDBprefix*/categorylinks l, /*$wgDBprefix*/page p
      where p.page_namespace=14 and p.page_title=l.cl_to
      union
      select l.cl_from page_id, tp.category_id
      from /*$wgDBprefix*/categorylinks l, /*$wgDBprefix*/page p, tp
      where p.page_namespace=14 and p.page_title=l.cl_to and p.page_id=tp.page_id
    )
    select tp.page_id, tp.category_id from tp;
end
$mw$
language plpgsql;

drop table if exists category_closure;

select create_category_closure();

--
-- Triggers for maintaining category_closure table in actual state
--

-- add all categories for changed_page_id
create or replace function fill_category_closure(changed_page_id int) returns void as
$mw$
begin
  insert into /*$wgDBprefix*/category_closure (page_id, category_id)
    with recursive tp (category_id) as (
      select p.page_id
      from /*$wgDBprefix*/categorylinks c
      inner join /*$wgDBprefix*/page p on c.cl_from=changed_page_id and c.cl_to=p.page_title and p.page_namespace=14
      union
      select c2.category_id from p
      inner join /*$wgDBprefix*/category_closure c2 on c2.page_id=p.category_id
    )
    select changed_page_id, tp.page_id from tp
    -- "insert ignore"
    left join category_closure c2 on c2.page_id=changed_page_id and c2.category_id=tp.page_id
    where c2.page_id is null;
end
$mw$
language plpgsql;

create or replace function add_category_closure_catlinks(pg_id int, cat_id int) returns void as
$mw$
begin
  insert into /*$wgDBprefix*/category_closure
    select pt.pg_id, pt.cat_id from (
      -- add the (pg_id -> cat_id) edge
      select pg_id, cat_id
      union
      -- add indirect edges: (pg_id -> cat_id) (cat_id -> *2) --> (pg_id -> *2)
      select pg_id, c1.category_id from /*$wgDBprefix*/category_closure c1 where c1.page_id=cat_id
      union
      -- add indirect edges: (*1 -> pg_id) (pg_id -> cat_id) --> (*1 -> cat_id)
      select c1.page_id, cat_id from /*$wgDBprefix*/category_closure c1 where c1.category_id=pg_id
      union
      -- add indirect edges: (*1 -> pg_id) (cat_id -> *2) --> (*1 -> *2)
      select c1.page_id, c2.category_id
      from /*$wgDBprefix*/category_closure c1, /*$wgDBprefix*/category_closure c2
      where c1.category_id=pg_id and c2.page_id=cat_id
    ) pt
    -- "insert ignore"
    left join category_closure c2 on c2.page_id=pt.pg_id and c2.category_id=pt.cat_id
    where c2.page_id is null;
end
$mw$
language plpgsql;

create or replace function rm_category_closure_catlinks(old_page_id int, old_cat_id int) returns void as
$mw$
declare
  cur cursor for select page_id from /*$wgDBprefix*/category_closure where category_id=old_cat_id;
begin
  -- rebuild everything to the "left" of deleted edge of categorylinks graph
  for r in cur loop
    delete from /*$wgDBprefix*/category_closure where page_id=r.page_id;
    perform fill_category_closure(r.page_id);
  end loop;
  if old_page_id is not null then
    delete from /*$wgDBprefix*/category_closure where page_id=old_page_id;
    perform fill_category_closure(old_page_id);
  end if;
end
$mw$
language plpgsql;

create or replace function do_insert_category_closure_catlinks() returns trigger as
$mw$
declare
  cat_id int;
begin
  select page_id from /*$wgDBprefix*/page where page_namespace=14 and page_title=NEW.cl_to into cat_id;
  if cat_id is not null then
    perform add_category_closure_catlinks(NEW.cl_from, cat_id);
  end if;
  return NEW;
end
$mw$
language plpgsql;
drop trigger if exists insert_category_closure_catlinks on /*$wgDBprefix*/categorylinks;
create trigger insert_category_closure_catlinks after insert on /*$wgDBprefix*/categorylinks
for each row execute procedure do_insert_category_closure_catlinks();

create or replace function do_delete_category_closure_catlinks() returns trigger as
$mw$
declare
  cat_id int;
begin
  select page_id from /*$wgDBprefix*/page where page_namespace=14 and page_title=OLD.cl_to into cat_id;
  if cat_id is not null then
    perform rm_category_closure_catlinks(OLD.cl_from, cat_id);
  end if;
  return OLD;
end
$mw$
language plpgsql;
drop trigger if exists delete_category_closure_catlinks on /*$wgDBprefix*/categorylinks;
create trigger delete_category_closure_catlinks after delete on /*$wgDBprefix*/categorylinks
for each row execute procedure do_delete_category_closure_catlinks();

create or replace function do_update_category_closure_catlinks() returns trigger as
$mw$
declare
  cat_id int;
begin
  -- treat update as delete+insert
  if OLD.cl_from != NEW.cl_from or OLD.cl_to != NEW.cl_to then
    select page_id from /*$wgDBprefix*/page where page_namespace=14 and page_title=OLD.cl_to into cat_id;
    if cat_id is not null then
      perform rm_category_closure_catlinks(OLD.cl_from, cat_id);
    end if;
    select page_id from /*$wgDBprefix*/page where page_namespace=14 and page_title=NEW.cl_to into cat_id;
    if cat_id is not null then
      perform add_category_closure_catlinks(NEW.cl_from, cat_id);
    end if;
  end if;
  return NEW;
end
$mw$
language plpgsql;
drop trigger if exists update_category_closure_catlinks on /*$wgDBprefix*/categorylinks;
create trigger update_category_closure_catlinks after update on /*$wgDBprefix*/categorylinks
for each row execute procedure do_update_category_closure_catlinks();

-- Handle additions/removals of category pages
create or replace function do_insert_category_closure_page() returns trigger as
$mw$
begin
  if NEW.page_namespace=14 then
    -- only direct categories can emerge
    insert into /*$wgDBprefix*/category_closure (page_id, category_id)
      select l.cl_from, NEW.page_id
      from /*$wgDBprefix*/categorylinks l
      -- "insert ignore"
      left join category_closure c2 on c2.page_id=l.cl_from and c2.category_id=NEW.page_id
      where l.cl_to=NEW.page_title and c2.page_id is null;
  end if;
  return NEW;
end
$mw$
language plpgsql;
drop trigger if exists insert_category_closure_page on /*$wgDBprefix*/page;
create trigger insert_category_closure_page after insert on /*$wgDBprefix*/page
for each row execute procedure do_insert_category_closure_page();

create or replace function do_delete_category_closure_page() returns trigger as
$mw$
begin
  if OLD.page_namespace=14 then
    perform rm_category_closure_catlinks(NULL, OLD.page_id);
  end if;
  return OLD;
end
$mw$
language plpgsql;
drop trigger if exists delete_category_closure_page on /*$wgDBprefix*/page;
create trigger delete_category_closure_page after delete on /*$wgDBprefix*/page
for each row execute procedure do_delete_category_closure_page();

create or replace function do_update_category_closure_page() returns trigger as
$mw$
begin
  -- can normally happen only upon rename of a category page
  -- also treat as delete+insert
  if (OLD.page_namespace=14 or NEW.page_namespace=14) and
     (OLD.page_namespace!=NEW.page_namespace or OLD.page_id!=NEW.page_id OR OLD.page_title!=NEW.page_title) then
    if OLD.page_namespace=14 then
      perform rm_category_closure_catlinks(NULL, OLD.page_id);
    end if;
    if NEW.page_namespace=14 then
      -- only direct categories can emerge
      insert into /*$wgDBprefix*/category_closure (page_id, category_id)
        select l.cl_from, NEW.page_id
        from /*$wgDBprefix*/categorylinks l
        -- "insert ignore"
        left join category_closure c2 on c2.page_id=l.cl_from and c2.category_id=NEW.page_id
        where l.cl_to=NEW.page_title and c2.page_id is null;
    end if;
  end if;
  return NEW;
end
$mw$
language plpgsql;
drop trigger if exists update_category_closure_page on /*$wgDBprefix*/page;
create trigger update_category_closure_page after update on /*$wgDBprefix*/page
for each row execute procedure do_update_category_closure_page();
