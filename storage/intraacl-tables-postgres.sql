--
-- Highly optimised right/group rule storage table
-- See GlobalFunctions.php (class IACL) for type/id/bitmask descriptions
--
CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/intraacl_rules (
    -- parent protected element type and ID
    -- pe_type = any of IACL::PE_* except PE_USER, PE_ALL_USERS, PE_REG_USERS
    pe_type SMALLINT NOT NULL,
    pe_id INT NOT NULL,
    -- child protected element type and ID
    -- child_type = any of IACL::PE_*
    child_type SMALLINT NOT NULL,
    child_id INT NOT NULL,
    -- Bit mask: lower byte is for direct rights, higher byte is for inherited rights
    actions SMALLINT NOT NULL,
    PRIMARY KEY (pe_type, pe_id, child_type, child_id)
) /*$wgDBTableOptions*/;
CREATE INDEX /*$wgDBprefix*/intraacl_rules_child ON /*$wgDBprefix*/intraacl_rules (child_type, child_id);

--
-- Quick ACL templates
--
CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/intraacl_quickacl (
    user_id INT NOT NULL,
    pe_type SMALLINT NOT NULL,
    pe_id INT NOT NULL,
    qa_default SMALLINT NOT NULL,
    PRIMARY KEY (user_id, pe_type, pe_id)
) /*$wgDBTableOptions*/;
CREATE INDEX /*$wgDBprefix*/intraacl_quickacl_pe ON /*$wgDBprefix*/intraacl_quickacl (pe_type, pe_id);

--
-- References from existing right definitions to non-existing ("bad") ones.
-- Saved so we can do "forward declarations" of rights.
--
CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/intraacl_badlinks (
    bl_from INT NOT NULL,
    bl_namespace INT NOT NULL,
    bl_title TEXT NOT NULL,
    PRIMARY KEY (bl_from, bl_namespace, bl_title)
) /*$wgDBTableOptions*/;
CREATE INDEX /*$wgDBprefix*/intraacl_badlinks_bl ON /*$wgDBprefix*/intraacl_badlinks (bl_namespace, bl_title);

--
-- Surrogate IDs for special pages
--
CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/halo_acl_special_pages (
    id SERIAL NOT NULL PRIMARY KEY,
    name TEXT NOT NULL
) /*$wgDBTableOptions*/;
