-- Import into the loop_feedback database only. Existing records are not reset.
CREATE TABLE IF NOT EXISTS loop_board (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    document LONGTEXT NOT NULL,
    revision VARCHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO loop_board (id, document, revision)
VALUES (1, '{"projects":[],"developers":[],"items":[]}', '');
CREATE TABLE IF NOT EXISTS loop_images (
    id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    mime VARCHAR(32) NOT NULL,
    content MEDIUMBLOB NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
