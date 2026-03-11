CREATE TABLE IF NOT EXISTS watch_sessions (
      session_id TEXT PRIMARY KEY,
      user_id TEXT NOT NULL,
      event_id TEXT NOT NULL,
      current_state TEXT NOT NULL,
      started_at TEXT NOT NULL,
      last_event_at TEXT NOT NULL,
      last_received_at TEXT NOT NULL,
      last_position REAL,
      last_quality TEXT,
      event_count INTEGER NOT NULL,
      total_watch_seconds INTEGER NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_watch_sessions_event_id
    ON watch_sessions (event_id);

CREATE INDEX IF NOT EXISTS idx_watch_sessions_last_event_at
    ON watch_sessions (last_event_at);

CREATE TABLE IF NOT EXISTS watch_session_events (
    event_row_id TEXT PRIMARY KEY,
    session_id TEXT NOT NULL,
    user_id TEXT NOT NULL,
    event_type TEXT NOT NULL,
    sdk_event_timestamp TEXT NOT NULL,
    received_at TEXT NOT NULL,
    stream_event_id TEXT NOT NULL,
    position REAL,
    quality TEXT,
    raw_payload TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_watch_session_events_session_id
    ON watch_session_events (session_id);

CREATE INDEX IF NOT EXISTS idx_watch_session_events_stream_event_id
    ON watch_session_events (stream_event_id);
