--
-- Highly optimised right/group rule storage table
-- See GlobalFunctions.php (class IACL) for type/id/bitmask descriptions
--
CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/intraacl_rules (
    -- parent protected element type and ID
    -- pe_type = any of IACL::PE_* except PE_USER, PE_ALL_USERS, PE_REG_USERS
    pe_type TINYINT(1) NOT NULL,
    pe_id INT UNSIGNED NOT NULL,
    -- child protected element type and ID
    -- child_type = any of IACL::PE_*
    child_type TINYINT(1) NOT NULL,
    child_id INT UNSIGNED NOT NULL,
    -- Bit mask: lower byte is for direct rights, higher byte is for inherited rights
    actions SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (pe_type, pe_id, child_type, child_id),
    KEY (child_type, child_id)
) /*$wgDBTableOptions*/;

--
-- Quick ACL templates
--
CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/intraacl_quickacl (
    user_id INT UNSIGNED NOT NULL,
    pe_type TINYINT(1) NOT NULL,
    pe_id INT UNSIGNED NOT NULL,
    qa_default TINYINT(1) NOT NULL,
    PRIMARY KEY (user_id, pe_type, pe_id),
    KEY (pe_type, pe_id)
) /*$wgDBTableOptions*/;

--
-- References from existing right definitions to non-existing ("bad") ones.
-- Saved so we can do "forward declarations" of rights.
--
CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/intraacl_badlinks (
    bl_from INT UNSIGNED NOT NULL,
    bl_namespace INT NOT NULL,
    bl_title VARCHAR(255) BINARY NOT NULL,
    PRIMARY KEY (bl_from, bl_namespace, bl_title),
    KEY (bl_namespace, bl_title)
) /*$wgDBTableOptions*/;

--
-- Surrogate IDs for special pages
--
CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/halo_acl_special_pages (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
) /*$wgDBTableOptions*/;
