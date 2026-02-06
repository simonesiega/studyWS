-- ============================================================================
-- TABLE: users
-- ============================================================================
-- Stores user accounts, authentication credentials, and basic profile info.
DROP TABLE IF EXISTS users CASCADE;

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    registration_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_access TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_users_email ON users(email);

COMMENT ON TABLE users IS 'User accounts and authentication credentials';
COMMENT ON COLUMN users.id IS 'Unique user identifier';
COMMENT ON COLUMN users.email IS 'Unique email address for login';
COMMENT ON COLUMN users.password_hash IS 'Bcrypt hashed password (cost=12)';
COMMENT ON COLUMN users.registration_date IS 'Account creation timestamp';
COMMENT ON COLUMN users.last_access IS 'Last login/access timestamp (updated on login)';



-- ============================================================================
-- TABLE: sessions
-- ============================================================================
-- Stores refresh token sessions for:
-- - Token rotation (revoke old, create new on each refresh)
-- - Logout (revoke all sessions)
-- - Multi-device session management
-- - Audit trail (user_agent, ip)
DROP TABLE IF EXISTS sessions CASCADE;

CREATE TABLE sessions (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    refresh_token_hash VARCHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    revoked_at TIMESTAMP,
    user_agent TEXT,
    ip VARCHAR(45),
    UNIQUE(user_id, refresh_token_hash)
);

CREATE INDEX idx_sessions_user_id ON sessions(user_id);
CREATE INDEX idx_sessions_expires_at ON sessions(expires_at);
CREATE INDEX idx_sessions_revoked_at ON sessions(revoked_at);

COMMENT ON TABLE sessions IS 'Refresh token sessions for token rotation and logout';
COMMENT ON COLUMN sessions.id IS 'Unique session identifier';
COMMENT ON COLUMN sessions.user_id IS 'User owning this session';
COMMENT ON COLUMN sessions.refresh_token_hash IS 'SHA-256 hash of the refresh token (never store plaintext)';
COMMENT ON COLUMN sessions.expires_at IS 'Token expiration time (typically 7 days from creation)';
COMMENT ON COLUMN sessions.revoked_at IS 'Timestamp when session was revoked (NULL if active)';
COMMENT ON COLUMN sessions.user_agent IS 'Client user-agent for device identification';
COMMENT ON COLUMN sessions.ip IS 'Client IP address for audit trail';



-- ============================================================================
-- TABLE: workspaces
-- ============================================================================
-- User study workspaces: logical grouping of documents by subject/topic
DROP TABLE IF EXISTS workspaces CASCADE;

CREATE TABLE workspaces (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    cover_image_url VARCHAR(2048),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, name)
);

CREATE INDEX idx_workspaces_user_id ON workspaces(user_id);

COMMENT ON TABLE workspaces IS 'User study workspaces (logical grouping by subject)';
COMMENT ON COLUMN workspaces.id IS 'Unique workspace identifier';
COMMENT ON COLUMN workspaces.user_id IS 'Workspace owner';
COMMENT ON COLUMN workspaces.name IS 'Workspace name (unique per user)';
COMMENT ON COLUMN workspaces.description IS 'Optional workspace description';
COMMENT ON COLUMN workspaces.cover_image_url IS 'Optional cover image URL/path';



-- ============================================================================
-- TABLE: documents
-- ============================================================================
-- Logical document containers with metadata and ownership.
DROP TABLE IF EXISTS documents CASCADE;

CREATE TABLE documents (
    id SERIAL PRIMARY KEY,
    workspace_id INT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    owner_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    document_type VARCHAR(50),
    storage_key VARCHAR(255),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_documents_workspace_id ON documents(workspace_id);
CREATE INDEX idx_documents_owner_id ON documents(owner_id);

COMMENT ON TABLE documents IS 'Document containers with metadata and version tracking';
COMMENT ON COLUMN documents.id IS 'Unique document identifier';
COMMENT ON COLUMN documents.workspace_id IS 'Workspace containing this document';
COMMENT ON COLUMN documents.owner_id IS 'Document creator/owner';
COMMENT ON COLUMN documents.title IS 'Document title';
COMMENT ON COLUMN documents.document_type IS 'Type: note, pdf, ppt, audio, video';
COMMENT ON COLUMN documents.storage_key IS 'Path/key to the stored file in MinIO/S3';



-- ============================================================================
-- TABLE: document_versions
-- ============================================================================
-- Version history for each document.
DROP TABLE IF EXISTS document_versions CASCADE;

CREATE TABLE document_versions (
    id SERIAL PRIMARY KEY,
    document_id INT NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    author_id INT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    version_number INT NOT NULL,
    is_current BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    commit_message TEXT,
    UNIQUE(document_id, version_number)
);

CREATE INDEX idx_document_versions_document_id ON document_versions(document_id);
CREATE INDEX idx_document_versions_author_id ON document_versions(author_id);

CREATE UNIQUE INDEX idx_document_versions_current 
    ON document_versions(document_id) 
    WHERE is_current = TRUE;

COMMENT ON TABLE document_versions IS 'Version history for documents (supports rollback/restore)';
COMMENT ON COLUMN document_versions.id IS 'Unique version identifier';
COMMENT ON COLUMN document_versions.document_id IS 'Document this version belongs to';
COMMENT ON COLUMN document_versions.author_id IS 'User who created this version';
COMMENT ON COLUMN document_versions.version_number IS 'Sequential version number (1, 2, 3, ...)';
COMMENT ON COLUMN document_versions.is_current IS 'TRUE if this is the current/latest version (only one per document)';
COMMENT ON COLUMN document_versions.commit_message IS 'Optional commit message describing the change';



-- ============================================================================
-- TABLE: flashcards
-- ============================================================================
-- Study flashcards: Q&A pairs linked to documents.
DROP TABLE IF EXISTS flashcards CASCADE;

CREATE TABLE flashcards (
    id SERIAL PRIMARY KEY,
    document_id INT NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    difficulty VARCHAR(20) DEFAULT 'medium',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_flashcards_document_id ON flashcards(document_id);

COMMENT ON TABLE flashcards IS 'Study flashcards for spaced repetition';
COMMENT ON COLUMN flashcards.id IS 'Unique flashcard identifier';
COMMENT ON COLUMN flashcards.document_id IS 'Document this flashcard belongs to';
COMMENT ON COLUMN flashcards.question IS 'Question text';
COMMENT ON COLUMN flashcards.answer IS 'Answer text';
COMMENT ON COLUMN flashcards.difficulty IS 'Difficulty level: easy, medium, hard';



-- ============================================================================
-- TABLE: rate_limit_log
-- ============================================================================
-- Logs API requests for rate limiting purposes. Tracks per-IP request counts
-- within time windows to prevent abuse and detect suspicious patterns.
DROP TABLE IF EXISTS rate_limit_log CASCADE;

CREATE TABLE rate_limit_log (
    id SERIAL PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    path VARCHAR(255) NOT NULL,       
    timestamp INTEGER NOT NULL,       
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_rate_limit_ip_path_timestamp 
    ON rate_limit_log(ip, path, timestamp);

CREATE INDEX idx_rate_limit_timestamp 
    ON rate_limit_log(timestamp);

COMMENT ON TABLE rate_limit_log IS 'API request logs for rate limiting and abuse prevention';
COMMENT ON COLUMN rate_limit_log.id IS 'Unique log entry identifier';
COMMENT ON COLUMN rate_limit_log.ip IS 'Client IP address making the request (IPv4 or IPv6)';
COMMENT ON COLUMN rate_limit_log.path IS 'API endpoint path accessed';
COMMENT ON COLUMN rate_limit_log.timestamp IS 'Unix timestamp of the request for rate window calculations';
COMMENT ON COLUMN rate_limit_log.created_at IS 'Record insertion timestamp';
