CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/halo_acl_rights (
    right_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    actions INT NOT NULL,
    groups TEXT,
    users TEXT,
    description TEXT,
    name TEXT,
    origin_id INT UNSIGNED NOT NULL
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/halo_acl_pe_rights (
    pe_id INT NOT NULL,
    type ENUM('category', 'page', 'namespace') DEFAULT 'page' NOT NULL,
    right_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (pe_id, type, right_id)
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/halo_acl_rights_hierarchy (
    parent_right_id INT UNSIGNED NOT NULL,
    child_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (parent_right_id, child_id)
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/halo_acl_security_descriptors (
    sd_id INT UNSIGNED NOT NULL PRIMARY KEY,
    pe_id INT,
    type ENUM('category', 'page', 'namespace', 'right') DEFAULT 'page' NOT NULL,
    mr_groups TEXT,
    mr_users TEXT
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/halo_acl_groups (
    group_id INT UNSIGNED NOT NULL PRIMARY KEY,
    group_name VARCHAR(255) NOT NULL,
    mg_groups TEXT,
    mg_users TEXT
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/halo_acl_group_members (
    parent_group_id INT UNSIGNED NOT NULL,
    child_type ENUM('group', 'user') DEFAULT 'user' NOT NULL,
    child_id INT NOT NULL,
    PRIMARY KEY (parent_group_id, child_type, child_id)
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/halo_acl_special_pages (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/halo_acl_quickacl (
    sd_id INT NOT NULL,
    user_id INT NOT NULL,
    qa_default TINYINT(1) NOT NULL,
    PRIMARY KEY (sd_id, user_id)
) /*$wgDBTableOptions*/;
