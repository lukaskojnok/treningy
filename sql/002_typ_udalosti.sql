-- Pouzi iba vtedy, ak si uz skor importoval povodny subor 001_rezervacie.sql.
-- Existujuce rezervacie sa oznacia ako treningy.
ALTER TABLE reservations
  ADD COLUMN event_type ENUM('training', 'match') NOT NULL DEFAULT 'training' AFTER id,
  ADD KEY reservation_team_type_date (team_key, event_type, reservation_date);
