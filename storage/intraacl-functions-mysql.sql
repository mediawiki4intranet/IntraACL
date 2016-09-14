--
-- MySQL stored functions and triggers for DBMS-side checking of IntraACL per-page permissions
-- Author: Vitaliy Filippov, 2013+
-- License: GNU LGPLv3 or newer
--

DELIMITER //

-- old permission checker procedure (not needed anymore)
drop function if exists check_read_right //

--
-- Stored procedure for creating parent_pages table
--
drop procedure if exists /*_*/create_parent_pages //

create procedure /*_*/create_parent_pages() modifies sql data
begin
  create table /*_*/parent_pages (
    parent_page_id int unsigned not null,
    page_id int unsigned not null,
    primary key (page_id),
    foreign key (page_id) references /*_*/page (page_id) on delete cascade on update cascade,
    foreign key (parent_page_id) references /*_*/page (page_id) on delete cascade on update cascade
  );
  insert into /*_*/parent_pages (parent_page_id, page_id)
    select p1.page_id, p2.page_id
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
end //

drop table if exists /*_*/parent_pages //

call /*_*/create_parent_pages() //

drop procedure if exists /*_*/refresh_parent_pages_for_children //

create procedure /*_*/refresh_parent_pages_for_children(_page_id int unsigned, _page_namespace int, _page_title text)
modifies sql data
begin
  replace into /*_*/parent_pages (parent_page_id, page_id)
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
      where p2.page_id=_page_id;
end //

drop procedure if exists /*_*/refresh_parent_for_page //

create procedure /*_*/refresh_parent_for_page(_page_id int unsigned, _page_namespace int, _page_title text)
modifies sql data
begin
  declare _parent_id int unsigned default null;
  select /*_*/get_parent_for_page(_page_namespace, _page_title) into _parent_id;
  if _parent_id is not null then
    replace into /*_*/parent_pages (parent_page_id, page_id) values (_parent_id, _page_id);
  else
    delete from /*_*/parent_pages where page_id=_page_id;
  end if;
end //

drop function if exists /*_*/get_parent_for_page //

create function /*_*/get_parent_for_page(_page_namespace int, _page_title text) returns int unsigned
reads sql data
begin
  declare prev int default 0;
  declare pos int default 0;
  declare _parent_id int unsigned default null;
  repeat
    set prev = pos;
    select p1.page_id from /*_*/page p1
      inner join /*_*/intraacl_rules r1 on r1.pe_type=10 and r1.pe_id=p1.page_id and r1.child_type=6 and r1.child_id=0
      where p1.page_namespace=_page_namespace
      and p1.page_title = substr(_page_title, 1, length(_page_title)-pos)
      into _parent_id;
    if _parent_id is not null then
      set pos = 0;
    else
      set pos = locate('/', reverse(_page_title), prev+1);
    end if;
  until pos <= 0 end repeat;
  return _parent_id;
end //

drop procedure if exists /*_*/refresh_all_parents_for_page //

create procedure /*_*/refresh_all_parents_for_page(_page_id int unsigned, _page_namespace int, _page_title text)
modifies sql data
begin
  if _page_namespace is null or _page_title is null then
    select page_namespace, page_title from page where page_id=_page_id into _page_namespace, _page_title;
  end if;
  call /*_*/refresh_parent_pages_for_children(_page_id, _page_namespace, _page_title);
  call /*_*/refresh_parent_for_page(_page_id, _page_namespace, _page_title);
end //

drop procedure if exists /*_*/refresh_parent_pages_for_parent_children //

create procedure /*_*/refresh_parent_pages_for_parent_children(_page_id int unsigned, _page_namespace int, _page_title text)
modifies sql data
begin
  declare _parent_id int unsigned default null;
  if _page_namespace is null or _page_title is null then
    select page_namespace, page_title from page where page_id=_page_id into _page_namespace, _page_title;
  end if;
  select /*_*/get_parent_for_page(_page_namespace, _page_title) into _parent_id;
  if _parent_id is null then
    delete from /*_*/parent_pages where parent_page_id=_page_id;
  else
    update /*_*/parent_pages set parent_page_id=_parent_id where parent_page_id=_page_id;
  end if;
end //

--
-- Stored procedure for creating category_closure table
--
drop procedure if exists /*_*/create_category_closure //

create procedure /*_*/create_category_closure() modifies sql data
begin
  declare n bigint default 0;
  declare prev bigint default 0;
  create table /*_*/category_closure as select cl_from page_id, page_id category_id
    from /*_*/categorylinks, /*_*/page where page_namespace=14 and page_title=cl_to;
  alter table /*_*/category_closure add primary key (page_id, category_id);
  alter table /*_*/category_closure add key (category_id);
  repeat
    set prev = n;
    replace into /*_*/category_closure (page_id, category_id)
      select c1.page_id, c2.category_id
      from /*_*/category_closure c1, /*_*/category_closure c2
      where c1.category_id=c2.page_id;
    select count(*) from /*_*/category_closure into n;
  until n <= prev end repeat;
end //

drop table if exists /*_*/category_closure //

call /*_*/create_category_closure() //

--
-- Triggers for maintaining category_closure and parent_pages tables in actual state
--

drop procedure if exists /*_*/fill_category_closure //

-- add all categories for changed_page_id
create procedure /*_*/fill_category_closure(changed_page_id int unsigned)
begin
  declare n bigint default 0;
  declare prev bigint default 0;
  insert into /*_*/category_closure (page_id, category_id)
    select changed_page_id, page_id
    from /*_*/categorylinks c, /*_*/page
    where cl_from=changed_page_id and cl_to=page_title and page_namespace=14
    on duplicate key update page_id=values(page_id);
  select count(*) from /*_*/category_closure where page_id=changed_page_id into n;
  while n > prev do
    set prev = n;
    insert into /*_*/category_closure (page_id, category_id)
      select changed_page_id, c2.category_id
      from /*_*/category_closure c1, /*_*/category_closure c2
      where c1.page_id=changed_page_id and c1.category_id=c2.page_id
      on duplicate key update page_id=values(page_id);
    select count(*) from /*_*/category_closure where page_id=changed_page_id into n;
  end while;
end //
drop procedure if exists /*_*/do_insert_category_closure_catlinks //

create procedure /*_*/do_insert_category_closure_catlinks(pg_id int unsigned, cat_id int unsigned)
modifies sql data
begin
  -- add the (pg_id -> cat_id) edge
  insert into /*_*/category_closure values (pg_id, cat_id)
    on duplicate key update page_id=values(page_id);
  -- add indirect edges: (pg_id -> cat_id) (cat_id -> *2) --> (pg_id -> *2)
  insert into /*_*/category_closure
    select pg_id, c1.category_id
    from /*_*/category_closure c1
    where c1.page_id=cat_id
    on duplicate key update page_id=values(page_id);
  -- add indirect edges: (*1 -> pg_id) (pg_id -> cat_id) --> (*1 -> cat_id)
  insert into /*_*/category_closure
    select c1.page_id, cat_id
    from /*_*/category_closure c1
    where c1.category_id=pg_id
    on duplicate key update page_id=values(page_id);
  -- add indirect edges: (*1 -> pg_id) (cat_id -> *2) --> (*1 -> *2)
  insert into /*_*/category_closure
    select c1.page_id, c2.category_id
    from /*_*/category_closure c1, /*_*/category_closure c2
    where c1.category_id=pg_id and c2.page_id=cat_id
    on duplicate key update page_id=values(page_id);
end //

drop trigger if exists /*_*/insert_category_closure_catlinks //

create trigger /*_*/insert_category_closure_catlinks after insert on /*_*/categorylinks for each row
begin
  declare cat_id int unsigned;
  select page_id from /*_*/page where page_namespace=14 and page_title=NEW.cl_to into cat_id;
  if cat_id is not null then
    call /*_*/do_insert_category_closure_catlinks(NEW.cl_from, cat_id);
  end if;
end //

drop procedure if exists /*_*/do_delete_category_closure_catlinks //

create procedure /*_*/do_delete_category_closure_catlinks(old_page_id int unsigned, old_cat_id int unsigned)
modifies sql data
begin
  declare pg_id int unsigned;
  declare fin int default 0;
  declare cur cursor for select page_id
    from /*_*/category_closure where category_id=old_cat_id;
  declare continue handler for not found set fin=1;
  -- rebuild everything to the "left" of deleted edge of categorylinks graph
  open cur;
  repeat
    fetch cur into pg_id;
    delete from /*_*/category_closure where page_id=pg_id;
    call /*_*/fill_category_closure(pg_id);
  until fin end repeat;
  if old_page_id is not null then
    delete from /*_*/category_closure where page_id=old_page_id;
    call /*_*/fill_category_closure(old_page_id);
  end if;
end //

drop trigger if exists /*_*/delete_category_closure_catlinks //

create trigger /*_*/delete_category_closure_catlinks after delete on /*_*/categorylinks for each row
begin
  declare cat_id int unsigned;
  select page_id from /*_*/page where page_namespace=14 and page_title=OLD.cl_to into cat_id;
  if cat_id is not null then
    call /*_*/do_delete_category_closure_catlinks(OLD.cl_from, cat_id);
  end if;
end //

drop trigger if exists /*_*/update_category_closure_catlinks //

create trigger /*_*/update_category_closure_catlinks after update on /*_*/categorylinks for each row
begin
  -- treat update as delete+insert
  declare cat_id int unsigned;
  if OLD.cl_from != NEW.cl_from or OLD.cl_to != NEW.cl_to then
    select page_id from /*_*/page where page_namespace=14 and page_title=OLD.cl_to into cat_id;
    if cat_id is not null then
      call /*_*/do_delete_category_closure_catlinks(OLD.cl_from, cat_id);
    end if;
    select page_id from /*_*/page where page_namespace=14 and page_title=NEW.cl_to into cat_id;
    if cat_id is not null then
      call /*_*/do_insert_category_closure_catlinks(NEW.cl_from, cat_id);
    end if;
  end if;
end //

--
-- Also handle additions/removals of category pages
--

-- page added -> refresh_parent_pages_for_children(), refresh_parent_for_page()
-- page removed -> refresh_parent_pages_for_parent_children()
-- page renamed -> refresh_parent_pages_for_parent_children(), refresh_parent_pages_for_children(), refresh_parent_for_page()

drop trigger if exists /*_*/insert_category_closure_page //

create trigger /*_*/insert_category_closure_page after insert on /*_*/page for each row
begin
  if NEW.page_namespace=14 then
    -- new category is emerging
    insert into /*_*/category_closure (page_id, category_id)
      select cl_from, NEW.page_id
      from /*_*/categorylinks
      where cl_to=NEW.page_title
      on duplicate key update page_id=values(page_id);
    insert into /*_*/category_closure (page_id, category_id)
      select c1.page_id, c2.category_id
      from /*_*/category_closure c1, /*_*/category_closure c2
      where c1.category_id=c2.page_id and c2.category_id=NEW.page_id
      on duplicate key update page_id=values(page_id);
  end if;
  -- add new parent/child subpage records
  call /*_*/refresh_all_parents_for_page(NEW.page_id, NEW.page_namespace, NEW.page_title);
end //

drop trigger if exists /*_*/delete_category_closure_page //

create trigger /*_*/delete_category_closure_page after delete on /*_*/page for each row
begin
  if OLD.page_namespace=14 then
    call /*_*/do_delete_category_closure_catlinks(NULL, OLD.page_id);
  end if;
  call /*_*/refresh_parent_pages_for_parent_children(OLD.page_id, OLD.page_namespace, OLD.page_title);
end //

drop trigger if exists /*_*/update_category_closure_page //

create trigger /*_*/update_category_closure_page after update on /*_*/page for each row
begin
  if (OLD.page_namespace!=NEW.page_namespace or OLD.page_id!=NEW.page_id OR OLD.page_title!=NEW.page_title) then
    -- can normally happen only upon rename of a category page
    -- also treat as delete+insert
    if OLD.page_namespace=14 then
      call /*_*/do_delete_category_closure_catlinks(NULL, OLD.page_id);
    end if;
    if NEW.page_namespace=14 then
      -- new category is emerging
      insert into /*_*/category_closure (page_id, category_id)
        select cl_from, NEW.page_id
        from /*_*/categorylinks
        where cl_to=NEW.page_title
        on duplicate key update page_id=values(page_id);
      insert into /*_*/category_closure (page_id, category_id)
        select c1.page_id, c2.category_id
        from /*_*/category_closure c1, /*_*/category_closure c2
        where c1.category_id=c2.page_id and c2.category_id=NEW.page_id
        on duplicate key update page_id=values(page_id);
    end if;
    -- update parent/child subpage records
    call /*_*/refresh_parent_pages_for_parent_children(OLD.page_id, OLD.page_namespace, OLD.page_title);
    call /*_*/refresh_all_parents_for_page(NEW.page_id, NEW.page_namespace, NEW.page_title);
  end if;
end //

DELIMITER ;
