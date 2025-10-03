ALTER TABLE messages
  ADD COLUMN read_at DATETIME NULL AFTER created_at,
  ADD INDEX idx_messages_receiver_unread (receiver_id, read_at);


