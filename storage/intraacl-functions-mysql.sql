--
-- MySQL stored functions and triggers for DBMS-side checking of IntraACL per-page permissions
-- Author: Vitaliy Filippov, 2013+
-- License: GNU LGPLv3 or newer
--

DELIMITER //

--
-- Stored procedure for creating parent_pages table
--
drop procedure if exists create_parent_pages //

create procedure create_parent_pages() modifies sql data
begin
  declare n bigint default 0;
  declare prev bigint default 0;
  create table /*$wgDBprefix*/parent_pages (
    parent_page_id int unsigned not null,
    page_id int unsigned not null,
    primary key (page_id),
    foreign key (page_id) references /*$wgDBprefix*/page (page_id) on delete cascade on update cascade,
    foreign key (parent_page_id) references /*$wgDBprefix*/page (page_id) on delete cascade on update cascade
  );
  insert into /*$wgDBprefix*/parent_pages
    select p1.page_id parent_page_id, p2.page_id page_id
    from /*$wgDBprefix*/page p1
    inner join /*$wgDBprefix*/intraacl_rules r1 on r1.pe_type=10 and r1.pe_id=p1.page_id and r1.child_type=6 and r1.child_id=0
    inner join /*$wgDBprefix*/page p2 on p2.page_namespace=p1.page_namespace
      and p2.page_title like concat(replace(replace(p1.page_title, '%', '\%'), '_', '\_'), '/%')
    left join /*$wgDBprefix*/page p3 on p3.page_namespace=p1.page_namespace
      and p3.page_title like concat(replace(replace(p1.page_title, '%', '\%'), '_', '\_'), '/%')
      and p2.page_title like concat(replace(replace(p3.page_title, '%', '\%'), '_', '\_'), '/%')
    left join /*$wgDBprefix*/intraacl_rules r3 on r3.pe_type=10 and r3.pe_id=p3.page_id and r3.child_type=6 and r3.child_id=0
    where p3.page_id is null or r3.pe_id is null;
end //

drop table if exists /*$wgDBprefix*/parent_pages //

call create_parent_pages() //

drop procedure if exists refresh_parent_pages_for_children //

create procedure refresh_parent_pages_for_children(_page_id int unsigned, _page_namespace int, _page_title text)
modifies sql data
begin
  replace into /*$wgDBprefix*/parent_pages
    select _page_id, p2.page_id page_id
      from /*$wgDBprefix*/page p2
      inner join /*$wgDBprefix*/intraacl_rules r1 on r1.pe_type=10 and r1.pe_id=_page_id and r1.child_type=6 and r1.child_id=0
      left join /*$wgDBprefix*/page p3 on p3.page_namespace=_page_namespace
        and p3.page_title like concat(replace(replace(_page_title, '%', '\%'), '_', '\_'), '/%')
        and p2.page_title like concat(replace(replace(p3.page_title, '%', '\%'), '_', '\_'), '/%')
      left join /*$wgDBprefix*/intraacl_rules r3 on r3.pe_type=10 and r3.pe_id=p3.page_id and r3.child_type=6 and r3.child_id=0
      where p2.page_namespace=_page_namespace
        and p2.page_title like concat(replace(replace(_page_title, '%', '\%'), '_', '\_'), '/%')
        and (p3.page_id is null or r3.pe_id is null);
end //

drop procedure if exists refresh_all_parents_for_page //

create procedure refresh_all_parents_for_page(_page_id int unsigned, _page_namespace int, _page_title text)
modifies sql data
begin
  declare prev int default 0;
  declare pos int default 0;
  call refresh_parent_pages_for_children(_page_id, _page_namespace, _page_title);
  repeat
    set pos = locate('/', reverse(_page_title), prev+1);
    if pos > 0 then
      replace into /*$wgDBprefix*/parent_pages
        select p1.page_id parent_page_id, _page_id from /*$wgDBprefix*/page p1
        inner join /*$wgDBprefix*/intraacl_rules r1 on r1.pe_type=10 and r1.pe_id=p1.page_id and r1.child_type=6 and r1.child_id=0
        where p1.page_namespace=_page_namespace
        and p1.page_title = substr(_page_title, 1, length(_page_title)-pos);
      if row_count() > 0 then
        set pos = 0;
      end if;
    end if;
    set prev = pos;
  until pos <= 0 end repeat;
end //

drop procedure if exists refresh_parent_pages_for_parent_children //

create procedure refresh_parent_pages_for_parent_children(_page_id int unsigned)
modifies sql data
begin
  declare parent_id int unsigned default 0;
  declare parent_namespace int default 0;
  declare parent_title text default '';
  select page_id, page_namespace, page_title from /*$wgDBprefix*/parent_pages pp, /*$wgDBprefix*/page p
    where pp.page_id=_page_id and pp.parent_page_id=p.page_id into parent_id, parent_namespace, parent_title;
  delete from /*$wgDBprefix*/parent_pages where parent_page_id=_page_id;
  if parent_id > 0 then
    call refresh_parent_pages_for_children(parent_id, parent_namespace, parent_title);
  end if;
end //

--
-- Stored procedure for creating category_closure table
--
drop procedure if exists create_category_closure //

create procedure create_category_closure() modifies sql data
begin
  declare n bigint default 0;
  declare prev bigint default 0;
  create table /*$wgDBprefix*/category_closure as select cl_from page_id, page_id category_id
    from /*$wgDBprefix*/categorylinks, /*$wgDBprefix*/page where page_namespace=14 and page_title=cl_to;
  alter table /*$wgDBprefix*/category_closure add primary key (page_id, category_id);
  alter table /*$wgDBprefix*/category_closure add key (category_id);
  repeat
    set prev = n;
    replace into /*$wgDBprefix*/category_closure (page_id, category_id)
      select c1.page_id, c2.category_id
      from /*$wgDBprefix*/category_closure c1, /*$wgDBprefix*/category_closure c2
      where c1.category_id=c2.page_id;
    select count(*) from /*$wgDBprefix*/category_closure into n;
  until n <= prev end repeat;
end //

drop table if exists /*$wgDBprefix*/category_closure //

call create_category_closure() //

--
-- Triggers for maintaining category_closure and parent_pages tables in actual state
--

drop procedure if exists fill_category_closure //

-- add all categories for changed_page_id
create procedure fill_category_closure(changed_page_id int unsigned)
begin
  declare n bigint default 0;
  declare prev bigint default 0;
  insert into /*$wgDBprefix*/category_closure (page_id, category_id)
    select changed_page_id, page_id
    from /*$wgDBprefix*/categorylinks c, /*$wgDBprefix*/page
    where cl_from=changed_page_id and cl_to=page_title and page_namespace=14
    on duplicate key update page_id=values(page_id);
  select count(*) from /*$wgDBprefix*/category_closure where page_id=changed_page_id into n;
  while n > prev do
    set prev = n;
    insert into /*$wgDBprefix*/category_closure (page_id, category_id)
      select changed_page_id, c2.category_id
      from /*$wgDBprefix*/category_closure c1, /*$wgDBprefix*/category_closure c2
      where c1.page_id=changed_page_id and c1.category_id=c2.page_id
      on duplicate key update page_id=values(page_id);
    select count(*) from /*$wgDBprefix*/category_closure where page_id=changed_page_id into n;
  end while;
end //
drop procedure if exists do_insert_category_closure_catlinks //

create procedure do_insert_category_closure_catlinks(pg_id int unsigned, cat_id int unsigned)
modifies sql data
begin
  -- add the (pg_id -> cat_id) edge
  insert into /*$wgDBprefix*/category_closure values (pg_id, cat_id)
    on duplicate key update page_id=values(page_id);
  -- add indirect edges: (pg_id -> cat_id) (cat_id -> *2) --> (pg_id -> *2)
  insert into /*$wgDBprefix*/category_closure
    select pg_id, c1.category_id
    from /*$wgDBprefix*/category_closure c1
    where c1.page_id=cat_id
    on duplicate key update page_id=values(page_id);
  -- add indirect edges: (*1 -> pg_id) (pg_id -> cat_id) --> (*1 -> cat_id)
  insert into /*$wgDBprefix*/category_closure
    select c1.page_id, cat_id
    from /*$wgDBprefix*/category_closure c1
    where c1.category_id=pg_id
    on duplicate key update page_id=values(page_id);
  -- add indirect edges: (*1 -> pg_id) (cat_id -> *2) --> (*1 -> *2)
  insert into /*$wgDBprefix*/category_closure
    select c1.page_id, c2.category_id
    from /*$wgDBprefix*/category_closure c1, /*$wgDBprefix*/category_closure c2
    where c1.category_id=pg_id and c2.page_id=cat_id
    on duplicate key update page_id=values(page_id);
end //

drop trigger if exists insert_category_closure_catlinks //

create trigger insert_category_closure_catlinks after insert on /*$wgDBprefix*/categorylinks for each row
begin
  declare cat_id int unsigned;
  select page_id from /*$wgDBprefix*/page where page_namespace=14 and page_title=NEW.cl_to into cat_id;
  if cat_id is not null then
    call do_insert_category_closure_catlinks(NEW.cl_from, cat_id);
  end if;
end //

drop procedure if exists do_delete_category_closure_catlinks //

create procedure do_delete_category_closure_catlinks(old_page_id int unsigned, old_cat_id int unsigned)
modifies sql data
begin
  declare pg_id int unsigned;
  declare fin int default 0;
  declare cur cursor for select page_id
    from /*$wgDBprefix*/category_closure where category_id=old_cat_id;
  declare continue handler for not found set fin=1;
  -- rebuild everything to the "left" of deleted edge of categorylinks graph
  open cur;
  repeat
    fetch cur into pg_id;
    delete from /*$wgDBprefix*/category_closure where page_id=pg_id;
    call fill_category_closure(pg_id);
  until fin end repeat;
  if old_page_id is not null then
    delete from /*$wgDBprefix*/category_closure where page_id=old_page_id;
    call fill_category_closure(old_page_id);
  end if;
end //

drop trigger if exists delete_category_closure_catlinks //

create trigger delete_category_closure_catlinks after delete on /*$wgDBprefix*/categorylinks for each row
begin
  declare cat_id int unsigned;
  select page_id from /*$wgDBprefix*/page where page_namespace=14 and page_title=OLD.cl_to into cat_id;
  if cat_id is not null then
    call do_delete_category_closure_catlinks(OLD.cl_from, cat_id);
  end if;
end //

drop trigger if exists update_category_closure_catlinks //

create trigger update_category_closure_catlinks after update on /*$wgDBprefix*/categorylinks for each row
begin
  -- treat update as delete+insert
  declare cat_id int unsigned;
  if OLD.cl_from != NEW.cl_from or OLD.cl_to != NEW.cl_to then
    select page_id from /*$wgDBprefix*/page where page_namespace=14 and page_title=OLD.cl_to into cat_id;
    if cat_id is not null then
      call do_delete_category_closure_catlinks(OLD.cl_from, cat_id);
    end if;
    select page_id from /*$wgDBprefix*/page where page_namespace=14 and page_title=NEW.cl_to into cat_id;
    if cat_id is not null then
      call do_insert_category_closure_catlinks(NEW.cl_from, cat_id);
    end if;
  end if;
end //

--
-- Also handle additions/removals of category pages
--

-- page added -> refresh_parent_pages_for_children(), refresh_parent_for_page()
-- page removed -> refresh_parent_pages_for_parent_children()
-- page renamed -> refresh_parent_pages_for_parent_children(), refresh_parent_pages_for_children(), refresh_parent_for_page()

drop trigger if exists insert_category_closure_page //

create trigger insert_category_closure_page after insert on /*$wgDBprefix*/page for each row
begin
  if NEW.page_namespace=14 then
    -- only direct categories can emerge
    insert into /*$wgDBprefix*/category_closure (page_id, category_id)
      select cl_from, NEW.page_id
      from /*$wgDBprefix*/categorylinks
      where cl_to=NEW.page_title
      on duplicate key update page_id=values(page_id);
  end if;
  -- add new parent/child subpage records
  call refresh_all_parents_for_page(NEW.page_id, NEW.page_namespace, NEW.page_title);
end //

drop trigger if exists delete_category_closure_page //

create trigger delete_category_closure_page after delete on /*$wgDBprefix*/page for each row
begin
  if OLD.page_namespace=14 then
    call do_delete_category_closure_catlinks(NULL, OLD.page_id);
  end if;
  call refresh_parent_pages_for_parent_children(OLD.page_id);
end //

drop trigger if exists update_category_closure_page //

create trigger update_category_closure_page after update on /*$wgDBprefix*/page for each row
begin
  if (OLD.page_namespace!=NEW.page_namespace or OLD.page_id!=NEW.page_id OR OLD.page_title!=NEW.page_title) then
    if (OLD.page_namespace=14 or NEW.page_namespace=14) then
      -- can normally happen only upon rename of a category page
      -- also treat as delete+insert
      if OLD.page_namespace=14 then
        call do_delete_category_closure_catlinks(NULL, OLD.page_id);
      end if;
      if NEW.page_namespace=14 then
        -- only direct categories can emerge
        insert into /*$wgDBprefix*/category_closure (page_id, category_id)
          select cl_from, NEW.page_id
          from /*$wgDBprefix*/categorylinks
          where cl_to=NEW.page_title
          on duplicate key update page_id=values(page_id);
      end if;
    end if;
    -- update parent/child subpage records
    call refresh_parent_pages_for_parent_children(OLD.page_id);
    call refresh_all_parents_for_page(NEW.page_id, NEW.page_namespace, NEW.page_title);
  end if;
end //
