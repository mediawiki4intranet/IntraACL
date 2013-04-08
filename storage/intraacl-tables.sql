-- Highly optimised right/group rule storage table
CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/intraacl_rules (
    pe_type TINYINT(1) NOT NULL,    -- category=1   page=2   namespace=3   right=4      group=5
    pe_id INT UNSIGNED NOT NULL,    -- cat_page_id  page_id  namespace_id  def_page_id  def_page_id
    child_type TINYINT(1) NOT NULL, -- category=1   page=2   namespace=3   right=4      group=5      user=6
    child_id INT UNSIGNED NOT NULL, -- cat_page_id  page_id  namespace_id  def_page_id  def_page_id  user_id
    actions SMALLINT UNSIGNED NOT NULL,  -- bit mask, lower byte is for direct rights, higher byte is for indirect rights
    PRIMARY KEY (pe_type, pe_id, child_type, child_id),
    KEY (child_type, child_id)
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
