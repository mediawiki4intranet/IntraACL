CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/halo_acl_pe_rights (
    pe_id INT NOT NULL,
    type ENUM('category', 'page', 'namespace') DEFAULT 'page' NOT NULL,
    right_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (pe_id, type, right_id)
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/intraacl_definitions (
    sd_id INT UNSIGNED NOT NULL PRIMARY KEY,
    pe_id INT,
    type ENUM('category', 'page', 'namespace', 'right', 'group') DEFAULT 'page' NOT NULL,
    UNIQUE KEY (type, pe_id)
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/intraacl_rules (
    sd_id INT UNSIGNED NOT NULL,
    rule_type TINYINT(1) NOT NULL, -- user_right=1 group_right=2 child_sd=3
    action_id TINYINT(1) NOT NULL, -- only for user/group rights
    child_id INT UNSIGNED NOT NULL,
    is_direct TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (sd_id, rule_type, action_id, child_id)
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
