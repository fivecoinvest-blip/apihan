-- Performance Optimization: Add Indexes
-- Ignoring errors if indexes already exist

-- Users table indexes
CREATE INDEX idx_users_balance ON users(id, balance);
CREATE INDEX idx_users_phone ON users(phone);
CREATE INDEX idx_users_status ON users(status, last_login);
CREATE INDEX idx_users_currency ON users(currency);

-- Transactions table indexes
CREATE INDEX idx_transactions_user_date ON transactions(user_id, created_at);
CREATE INDEX idx_transactions_type ON transactions(type, created_at);
CREATE INDEX idx_transactions_game ON transactions(game_uid, created_at);

-- Games table indexes
CREATE INDEX idx_games_active_category ON games(is_active, category, sort_order);
CREATE INDEX idx_games_provider ON games(provider, is_active);
CREATE INDEX idx_games_uid ON games(game_uid);

-- Game sessions indexes
CREATE INDEX idx_game_sessions_user ON game_sessions(user_id, started_at);
CREATE INDEX idx_game_sessions_status ON game_sessions(status, started_at);

-- Analyze tables
ANALYZE TABLE users;
ANALYZE TABLE transactions;
ANALYZE TABLE games;
ANALYZE TABLE game_sessions;

SELECT 'Database indexes created successfully!' as Status;
