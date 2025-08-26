-- #! sqlite
-- #{ binding
-- #    { init
CREATE TABLE IF NOT EXISTS bindings (
    telegram_id INTEGER NOT NULL,
    player_name TEXT NOT NULL,
    notifications_enabled INTEGER NOT NULL DEFAULT 1,
    two_factor_enabled INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (telegram_id, player_name)
);
CREATE TABLE IF NOT EXISTS temporary_codes (
    code TEXT PRIMARY KEY,
    type TEXT NOT NULL,
    telegram_id INTEGER NOT NULL,
    player_name TEXT NOT NULL,
    expires_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_temp_telegram_id ON temporary_codes (telegram_id);
CREATE INDEX IF NOT EXISTS idx_temp_player_name ON temporary_codes (player_name);
CREATE TABLE IF NOT EXISTS meta_data (key TEXT PRIMARY KEY, value TEXT);
-- #    }
-- #    { get_bound_player_names
-- #        :telegram_id int
SELECT player_name FROM bindings WHERE telegram_id = :telegram_id;
-- #    }
-- #    { add_permanent_binding
-- #        :telegram_id int
-- #        :player_name string
INSERT OR IGNORE INTO bindings (telegram_id, player_name) VALUES (:telegram_id, :player_name);
-- #    }
-- #    { remove_permanent_binding
-- #        :telegram_id int
-- #        :player_name string
DELETE FROM bindings WHERE telegram_id = :telegram_id AND player_name = :player_name;
-- #    }
-- #    { is_player_name_bound
-- #        :player_name string
SELECT 1 FROM bindings WHERE player_name = :player_name LIMIT 1;
-- #    }
-- #    { get_telegram_id_by_player_name
-- #        :player_name string
SELECT telegram_id FROM bindings WHERE player_name = :player_name;
-- #    }
-- #    { create_temporary_binding
-- #        :player_name string
-- #        :telegram_id int
-- #        :code string
-- #        :expires_at int
INSERT INTO temporary_codes (code, type, telegram_id, player_name, expires_at) VALUES (:code, 'bind', :telegram_id, :player_name, :expires_at);
-- #    }
-- #    { find_temporary_binding_by_code
-- #        :code string
SELECT * FROM temporary_codes WHERE code = :code AND type = 'bind';
-- #    }
-- #    { find_temporary_binding_by_player_name
-- #        :player_name string
SELECT * FROM temporary_codes WHERE player_name = :player_name AND type = 'bind';
-- #    }
-- #    { delete_temporary_binding
-- #        :code string
DELETE FROM temporary_codes WHERE code = :code AND type = 'bind';
-- #    }
-- #    { create_temporary_unbind_code
-- #        :telegram_id int
-- #        :player_name string
-- #        :code string
-- #        :expires_at int
INSERT INTO temporary_codes (code, type, telegram_id, player_name, expires_at) VALUES (:code, 'unbind', :telegram_id, :player_name, :expires_at);
-- #    }
-- #    { find_temporary_unbind_code
-- #        :code string
SELECT * FROM temporary_codes WHERE code = :code AND type = 'unbind';
-- #    }
-- #    { delete_temporary_unbind_code
-- #        :code string
DELETE FROM temporary_codes WHERE code = :code AND type = 'unbind';
-- #    }
-- #    { toggle_notifications
-- #        :player_name string
UPDATE bindings SET notifications_enabled = 1 - notifications_enabled WHERE player_name = :player_name;
-- #    }
-- #    { are_notifications_enabled
-- #        :player_name string
SELECT notifications_enabled FROM bindings WHERE player_name = :player_name;
-- #    }
-- #    { is_two_factor_enabled
-- #        :player_name string
SELECT two_factor_enabled FROM bindings WHERE player_name = :player_name;
-- #    }
-- #    { set_two_factor
-- #        :player_name string
-- #        :enabled int
UPDATE bindings SET two_factor_enabled = :enabled WHERE player_name = :player_name;
-- #    }
-- #    { get_telegram_offset
SELECT value FROM meta_data WHERE key = 'telegram_offset';
-- #    }
-- #    { set_telegram_offset
-- #        :offset int
INSERT OR REPLACE INTO meta_data (key, value) VALUES ('telegram_offset', :offset);
-- #    }
-- #    { get_user_state
-- #        :key string
SELECT value FROM meta_data WHERE key = :key;
-- #    }
-- #    { set_user_state
-- #        :key string
-- #        :value string
INSERT OR REPLACE INTO meta_data (key, value) VALUES (:key, :value);
-- #    }
-- #    { delete_user_state
-- #        :key string
DELETE FROM meta_data WHERE key = :key;
-- #    }
-- #    { delete_expired_temporary_bindings
-- #        :time int
DELETE FROM temporary_codes WHERE expires_at < :time;
-- #    }
-- #}
