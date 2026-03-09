-- feedback tabelle
-- UNIQUE-Constraint auf (sentence-uuid, code, textstelle) ist der Schlüssel
-- für die Race-Condition-Behandlung (siehe unten)

CREATE TABLE IF NOT EXISTS feedback (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sentence-uuid` CHAR(36)     NOT NULL,
    ts              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sentence        VARCHAR(2000) NOT NULL,
    code            VARCHAR(20)  NOT NULL,
    textstelle      VARCHAR(500) NOT NULL,
    correct         TINYINT(1)   NOT NULL,

    -- Verhindert Duplikate und ist Basis für INSERT IGNORE / ON DUPLICATE KEY
    UNIQUE KEY uq_feedback (`sentence-uuid`, code, textstelle),

    -- Für spätere Auswertungen nach Code oder Zeitraum
    INDEX idx_code (code),
    INDEX idx_ts   (ts)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
