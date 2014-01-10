--
-- MySQL stored functions and triggers for DBMS-side checking of IntraACL per-page permissions
-- Author: Vitaliy Filippov, 2013
-- License: GNU LGPLv3 or newer
--

DELIMITER //

drop function if exists check_read_right //

--
-- Stored function for checking IntraACL rights on DBMS side, with right ID and override mode parameters
-- (this way of checking is most optimal)
-- right_id = (1:read, 2:edit, 4:create, 8:move, 10:delete)
-- override_mode = (0:extend, 1:override, 2:shrink)
--
create function check_read_right(user_id int unsigned, page_id int unsigned, page_namespace int,
  right_id int, override_mode int) returns tinyint reads sql data
begin
  declare p int;
  declare n int;
  declare r int;
  set r = 1;
  set right_id = right_id | (right_id << 8);
  -- check page right
  select bit_or(actions), count(1) from /*$wgDBprefix*/intraacl_rules r where r.pe_type=4 and r.pe_id=page_id and
    (r.child_type=9 and r.child_id=user_id or r.child_type=6 and r.child_id=0 or r.child_type=7 and r.child_id=0) into p, n;
  if n > 0 then set r = 0; end if;
  if override_mode=1 and n > 0 or
    override_mode=0 and (p & right_id) or
    override_mode=2 and !(p & right_id) then
    return (p & right_id);
  end if;
  -- check category rights for category page
  if page_namespace = 14 then
    select bit_or(actions), count(1) from /*$wgDBprefix*/intraacl_rules r where r.pe_type=2 and r.pe_id=page_id and
      (r.child_type=9 and r.child_id=user_id or r.child_type=6 and r.child_id=0 or r.child_type=7 and r.child_id=0) into p, n;
    if n > 0 then set r = 0; end if;
    if override_mode=1 and n > 0 or
      override_mode=0 and (p & right_id) or
      override_mode=2 and !(p & right_id) then
      return (p & right_id);
    end if;
  end if;
  -- check category rights
  select bit_or(actions), count(1) from /*$wgDBprefix*/intraacl_rules r, /*$wgDBprefix*/category_closure c
    where r.pe_type=2 and c.page_id=page_id and r.pe_id=c.category_id and
    (r.child_type=9 and r.child_id=user_id or r.child_type=6 and r.child_id=0 or r.child_type=7 and r.child_id=0) into p, n;
  if n > 0 then set r = 0; end if;
  if override_mode=1 and n > 0 or
    override_mode=0 and (p & right_id) or
    override_mode=2 and !(p & right_id) then
    return (p & right_id);
  end if;
  -- check namespace right
  select bit_or(actions), count(1) from /*$wgDBprefix*/intraacl_rules r where r.pe_type=1 and r.pe_id=page_namespace and
    (r.child_type=9 and r.child_id=user_id or r.child_type=6 and r.child_id=0 or r.child_type=7 and r.child_id=0) into p, n;
  if n > 0 then set r = 0; end if;
  if override_mode=1 and n > 0 or
    override_mode=0 and (p & right_id) or
    override_mode=2 and !(p & right_id) then
    return (p & right_id);
  end if;
  return r;
end //

DELIMITER ;

--
-- Stored procedure for creating category_closure table
--
DELIMITER //

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

drop table if exists category_closure //

call create_category_closure() //

DELIMITER ;

--
-- Triggers for maintaining category_closure table in actual state
--

DELIMITER //

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
    on duplicate key update page_id=changed_page_id;
  select count(*) from /*$wgDBprefix*/category_closure where page_id=changed_page_id into n;
  while n > prev do
    set prev = n;
    insert into /*$wgDBprefix*/category_closure (page_id, category_id)
      select changed_page_id, c2.category_id
      from /*$wgDBprefix*/category_closure c1, /*$wgDBprefix*/category_closure c2
      where c1.page_id=changed_page_id and c1.category_id=c2.page_id
      on duplicate key update page_id=changed_page_id;
    select count(*) from /*$wgDBprefix*/category_closure where page_id=changed_page_id into n;
  end while;
end //
drop procedure if exists do_insert_category_closure_catlinks //

create procedure do_insert_category_closure_catlinks(pg_id int unsigned, cat_id int unsigned)
modifies sql data
begin
  -- fill the "right side" of categorylinks graph (parent categories for cat_id)
  call fill_category_closure(cat_id);
  -- add the edge itself
  insert into /*$wgDBprefix*/category_closure values (pg_id, cat_id);
  -- add the "right side" to pages on the "left side"
  insert into /*$wgDBprefix*/category_closure
    select c1.page_id, c2.category_id
    from /*$wgDBprefix*/category_closure c1, /*$wgDBprefix*/category_closure c2
    where c1.category_id=cat_id and c2.page_id=cat_id
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

-- We should handle additions/removals of category pages
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
end //

drop trigger if exists delete_category_closure_page //

create trigger delete_category_closure_page after delete on /*$wgDBprefix*/page for each row
begin
  if OLD.page_namespace=14 then
    call do_delete_category_closure_catlinks(NULL, OLD.page_id);
  end if;
end //

drop trigger if exists update_category_closure_page //

create trigger update_category_closure_page after update on /*$wgDBprefix*/page for each row
begin
  -- can normally happen only upon rename of a category page
  -- also treat as delete+insert
  if (OLD.page_namespace=14 or NEW.page_namespace=14) and
     (OLD.page_namespace!=NEW.page_namespace or OLD.page_id!=NEW.page_id) then
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
end //

DELIMITER ;
