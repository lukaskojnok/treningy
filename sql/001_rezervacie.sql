-- Importuj do existujucej databazy. Existujuce tabulky admins sa nemenia.
CREATE TABLE IF NOT EXISTS reservations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_type ENUM('training', 'match') NOT NULL,
  team_key VARCHAR(80) NOT NULL,
  coach_login VARCHAR(255) NOT NULL,
  coach_name VARCHAR(160) NOT NULL,
  field_key VARCHAR(40) NOT NULL,
  reservation_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  note TEXT NOT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by VARCHAR(255) NOT NULL,
  updated_by VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY reservation_range (reservation_date, field_key, start_time, end_time),
  KEY reservation_team_date (team_key, reservation_date),
  KEY reservation_team_type_date (team_key, event_type, reservation_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservation_parts (
  reservation_id BIGINT UNSIGNED NOT NULL,
  part_key VARCHAR(40) NOT NULL,
  PRIMARY KEY (reservation_id, part_key),
  KEY part_reservation (part_key, reservation_id),
  CONSTRAINT reservation_parts_parent FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Spolocny zamok serializuje kratke transakcie zapisov (aj z roznych PHP procesov).
CREATE TABLE IF NOT EXISTS reservation_write_lock (
  id TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
INSERT IGNORE INTO reservation_write_lock (id) VALUES (1);
