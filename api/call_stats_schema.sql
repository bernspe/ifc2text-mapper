-- call_stats Tabelle
-- Wird pro Analyze-Aufruf einmal beschrieben

CREATE TABLE IF NOT EXISTS call_stats (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ts              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sentence-uuid` CHAR(36)        NOT NULL,
    sentence        VARCHAR(2000)   NOT NULL,
    success         TINYINT(1)      NOT NULL,  -- 1 = OK, 0 = Fehler
    error_msg       VARCHAR(500)    NULL,       -- NULL wenn success = 1
    match_count     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    duration_ms     SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    INDEX idx_ts      (ts),
    INDEX idx_success (success),
    INDEX idx_uuid    (`sentence-uuid`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
