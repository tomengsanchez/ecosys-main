-- Main table for all content types (similar to WordPress's wp_posts)
CREATE TABLE `objects` (
  `object_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `object_author` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0, -- Corresponds to a user ID (you'll need a users table)
  `object_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `object_date_gmt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `object_content` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `object_title` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `object_excerpt` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `object_status` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'publish', -- e.g., 'publish', 'draft', 'pending', 'private', 'trash'
  `comment_status` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open', -- 'open', 'closed'
  `ping_status` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open', -- 'open', 'closed' (for trackbacks/pingbacks)
  `object_password` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '', -- For password-protected entries
  `object_name` VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '', -- URL-friendly slug
  `object_modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `object_modified_gmt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `object_parent` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0, -- For hierarchical post types (e.g., pages)
  `guid` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '', -- Globally Unique Identifier (can be a permalink)
  `menu_order` INT(11) NOT NULL DEFAULT 0, -- For ordering items
  `object_type` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'post', -- KEY: 'post', 'page', 'product', 'event', 'your_custom_type'
  `object_mime_type` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '', -- For attachments
  PRIMARY KEY (`object_id`),
  KEY `object_type_status_date` (`object_type`, `object_status`, `object_date`, `object_id`),
  KEY `object_parent` (`object_parent`),
  KEY `object_author` (`object_author`),
  KEY `object_name` (`object_name`(191)) -- Index prefix for utf8mb4 compatibility with older MySQL versions
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Metadata for objects (similar to WordPress's wp_postmeta)
-- This table allows you to add custom fields to any object without altering the `objects` table structure.
CREATE TABLE `objectmeta` (
  `meta_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `object_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
  `meta_key` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_value` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`meta_id`),
  KEY `object_id` (`object_id`),
  KEY `meta_key` (`meta_key`(191)) -- Index prefix for utf8mb4 compatibility
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Terms table (for categories, tags, etc. - similar to WordPress's wp_terms)
CREATE TABLE `terms` (
  `term_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `slug` VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `term_group` BIGINT(10) NOT NULL DEFAULT 0, -- For grouping terms (rarely used by default in WP)
  PRIMARY KEY (`term_id`),
  KEY `slug` (`slug`(191)), -- Index prefix
  KEY `name` (`name`(191)) -- Index prefix
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Term Taxonomy table (defines the type of term - category, tag, custom taxonomy - similar to WordPress's wp_term_taxonomy)
-- This table links terms to a specific taxonomy (e.g. 'category', 'tag', 'product_color').
CREATE TABLE `term_taxonomy` (
  `term_taxonomy_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `term_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
  `taxonomy` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '', -- e.g., 'category', 'post_tag', 'product_category'
  `description` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0, -- For hierarchical taxonomies (e.g., parent category)
  `count` BIGINT(20) NOT NULL DEFAULT 0, -- Number of objects associated with this term in this taxonomy
  PRIMARY KEY (`term_taxonomy_id`),
  UNIQUE KEY `term_id_taxonomy` (`term_id`, `taxonomy`), -- Ensures a term is unique within a taxonomy
  KEY `taxonomy` (`taxonomy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Term Relationships table (links objects to terms via term_taxonomy - similar to WordPress's wp_term_relationships)
-- This is the junction table connecting objects to their terms (categories, tags, etc.).
CREATE TABLE `term_relationships` (
  `object_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
  `term_taxonomy_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
  `term_order` INT(11) NOT NULL DEFAULT 0, -- Order of terms for an object (rarely used by default)
  PRIMARY KEY (`object_id`, `term_taxonomy_id`), -- Composite primary key
  KEY `term_taxonomy_id` (`term_taxonomy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: Users table (if you need user accounts)
-- This is a simplified version. WordPress's user system is more complex.
CREATE TABLE `users` (
  `user_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_login` VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_pass` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '', -- Store hashed passwords!
  `user_nicename` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '', -- Display name
  `user_email` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_url` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_registered` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_status` INT(11) NOT NULL DEFAULT 0, -- 0 = active, 1 = inactive/banned
  `display_name` VARCHAR(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`user_id`),
  KEY `user_login_key` (`user_login`),
  KEY `user_nicename` (`user_nicename`),
  KEY `user_email` (`user_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: User Meta table (similar to WordPress's wp_usermeta)
-- For storing additional information about users.
CREATE TABLE `usermeta` (
  `umeta_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
  `meta_key` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_value` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`umeta_id`),
  KEY `user_id` (`user_id`),
  KEY `meta_key` (`meta_key`(191)) -- Index prefix
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: Options table (for global site settings - similar to WordPress's wp_options)
CREATE TABLE `options` (
  `option_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `option_name` VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '', -- Max length 191 for unique key with utf8mb4
  `option_value` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `autoload` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes', -- 'yes' or 'no' (whether to load on every page)
  PRIMARY KEY (`option_id`),
  UNIQUE KEY `option_name` (`option_name`) -- Option names must be unique
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

