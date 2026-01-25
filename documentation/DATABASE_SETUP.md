# Database Setup Guide

## Overview
This guide explains how to set up and populate the PostgreSQL database for StudyWS.

## Table of Contents
1. [Prerequisites](#prerequisites)
2. [Database Creation](#database-creation)
3. [Schema Creation](#schema-creation)
4. [Data Population](#data-population)
5. [Test Credentials](#test-credentials)
6. [Indexes and Constraints](#indexes-and-constraints)
7. [Verification](#verification)
8. [Troubleshooting](#troubleshooting)

---

## Prerequisites

Before starting, ensure you have:
- **PostgreSQL 12+** installed and running
- **psql** command-line tool available
- Appropriate permissions to create databases and tables

### Installation (if needed)

**Windows:**
```powershell
# Download installer from https://www.postgresql.org/download/windows/
# Or use Chocolatey:
choco install postgresql
```

**Linux (Ubuntu/Debian):**
```bash
sudo apt update
sudo apt install postgresql postgresql-contrib
```

**macOS:**
```bash
brew install postgresql@15
brew services start postgresql@15
```

---

## Database Creation

### Step 1: Connect to PostgreSQL

Connect to PostgreSQL as the `postgres` superuser:

```bash
# Windows/Linux/macOS
psql -U postgres
```

If prompted for a password, enter your PostgreSQL admin password.

### Step 2: Create the Database

Execute the following SQL command:

```sql
CREATE DATABASE studyws
    WITH 
    OWNER = postgres
    ENCODING = 'UTF8'
    LC_COLLATE = 'en_US.UTF-8'
    LC_CTYPE = 'en_US.UTF-8'
    TEMPLATE = template0;
```

Verify the database was created:

```sql
\l studyws
```

Exit the PostgreSQL shell:

```sql
\q
```

---

## Schema Creation

The `createDB.sql` script creates all tables, constraints, and indexes.

### Execution Methods

#### Method 1: Using psql (Recommended)

```bash
# Navigate to the database directory
cd ...\studyWS\database

# Execute the script
psql -U postgres -d studyws -f createDB.sql
```

#### Method 2: Using psql Interactive Mode

```bash
# Connect to the database
psql -U postgres -d studyws

# Execute the script
\i .../studyWS/database/createDB.sql
```

**Note:** Use forward slashes (`/`) in the path when inside psql, even on Windows.

#### Method 3: Using pgAdmin

1. Open **pgAdmin**
2. Connect to your PostgreSQL server
3. Right-click on the `studyws` database → **Query Tool**
4. Open `createDB.sql` file
5. Click **Execute** (F5)

### What Gets Created

The script creates the following tables in order:

1. **users** - User accounts and authentication
2. **sessions** - Refresh token sessions for JWT rotation
3. **workspaces** - Logical grouping of documents
4. **document_versions** - Version history (created before documents due to circular FK)
5. **documents** - Document containers and metadata
6. **flashcards** - Study flashcards linked to documents

---

## Data Population

The `populateDB.sql` script inserts test data for development and testing.

### Execution

```bash
# Navigate to the database directory
cd ...\studyWS\database

# Execute the script
psql -U postgres -d studyws -f populateDB.sql
```

Or from inside psql:

```sql
\i .../studyWS/database/populateDB.sql
```

### What Gets Inserted

- **3 test users** with bcrypt-hashed passwords
- **4 workspaces** distributed among users
- **5 documents** with different types (note, pdf, ppt)
- **11 document versions** showing version history
- **5 flashcards** for study/review
- **4 sessions** including active and revoked tokens

---

## Test Credentials

The following test accounts are created by `populateDB.sql`:

| Email                        | Password       | First Name | Last Name |
|------------------------------|----------------|------------|-----------|
| mario.rossi@example.com      | Password123!   | Mario      | Rossi     |
| giulia.bianchi@example.com   | CiaoMondo123!  | Giulia     | Bianchi   |
| luca.verdi@example.com       | Reti2026!!     | Luca       | Verdi     |

### Password Hashing

All passwords are hashed using **bcrypt** with cost factor 12:
- The password hashes in `populateDB.sql` use the `$2b$12$` prefix
- Backend authentication must use bcrypt to verify these hashes
- **Never store plaintext passwords in production**

### Usage Example

Use these credentials to test authentication endpoints:

```bash
# Test login
curl -X POST http://localhost/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "mario.rossi@example.com",
    "password": "Password123!"
  }'
```

---

## Indexes and Constraints

### Primary Keys
All tables use `SERIAL PRIMARY KEY` for auto-incrementing IDs:
- `users.id`
- `sessions.id`
- `workspaces.id`
- `documents.id`
- `document_versions.id`
- `flashcards.id`

### Unique Constraints

| Table              | Columns                      | Purpose                              |
|--------------------|------------------------------|--------------------------------------|
| users              | email                        | Prevent duplicate accounts           |
| sessions           | (user_id, refresh_token_hash)| Prevent duplicate sessions           |
| workspaces         | (user_id, name)              | Unique workspace names per user      |
| document_versions  | (document_id, version_number)| Sequential versioning integrity      |

### Foreign Keys

| From Table         | Column             | References        | On Delete        |
|--------------------|--------------------|-------------------|------------------|
| sessions           | user_id            | users(id)         | CASCADE          |
| workspaces         | user_id            | users(id)         | CASCADE          |
| documents          | workspace_id       | workspaces(id)    | CASCADE          |
| documents          | owner_id           | users(id)         | CASCADE          |
| documents          | current_version_id | doc_versions(id)  | SET NULL         |
| document_versions  | document_id        | documents(id)     | CASCADE          |
| document_versions  | author_id          | users(id)         | RESTRICT         |
| flashcards         | document_id        | documents(id)     | CASCADE          |

**Note:** The circular dependency between `documents` and `document_versions` is resolved by:
1. Creating `document_versions` table first with nullable `document_id`
2. Creating `documents` table
3. Adding the foreign key constraint to `document_versions` afterward

### Indexes for Performance

The following indexes optimize common queries:

**User Queries:**
- `idx_users_email` - Fast email lookups during login

**Session Management:**
- `idx_sessions_user_id` - List sessions by user
- `idx_sessions_expires_at` - Cleanup expired sessions
- `idx_sessions_revoked_at` - Filter active sessions

**Workspace & Document Access:**
- `idx_workspaces_user_id` - List user workspaces
- `idx_documents_workspace_id` - List documents in workspace
- `idx_documents_owner_id` - List documents by owner
- `idx_documents_current_version` - Fast current version lookup

**Version History:**
- `idx_document_versions_document_id` - Version history by document
- `idx_document_versions_author_id` - Versions by author (audit trail)

**Flashcards:**
- `idx_flashcards_document_id` - Flashcards by document

---

## Verification

After running both scripts, verify the setup:

### Step 1: Connect to the Database

```bash
psql -U postgres -d studyws
```

### Step 2: List All Tables

```sql
\dt
```

Expected output:
```
              List of relations
 Schema |       Name        | Type  |  Owner   
--------+-------------------+-------+----------
 public | document_versions | table | postgres
 public | documents         | table | postgres
 public | flashcards        | table | postgres
 public | sessions          | table | postgres
 public | users             | table | postgres
 public | workspaces        | table | postgres
```

### Step 3: Check Row Counts

```sql
SELECT 
    'users' AS table_name, COUNT(*) AS rows FROM users
UNION ALL
SELECT 'sessions', COUNT(*) FROM sessions
UNION ALL
SELECT 'workspaces', COUNT(*) FROM workspaces
UNION ALL
SELECT 'documents', COUNT(*) FROM documents
UNION ALL
SELECT 'document_versions', COUNT(*) FROM document_versions
UNION ALL
SELECT 'flashcards', COUNT(*) FROM flashcards
ORDER BY table_name;
```

Expected output:
```
    table_name     | rows 
-------------------+------
 document_versions |   11
 documents         |    5
 flashcards        |    5
 sessions          |    4
 users             |    3
 workspaces        |    4
```

### Step 4: Verify Indexes

```sql
\di
```

You should see all indexes listed (prefixed with `idx_`).

### Step 5: Test a Query

```sql
SELECT 
    u.first_name, 
    u.last_name, 
    w.name AS workspace, 
    d.title AS document
FROM users u
JOIN workspaces w ON w.user_id = u.id
JOIN documents d ON d.workspace_id = w.id
WHERE u.email = 'mario.rossi@example.com';
```

Expected output should show Mario's workspaces and documents.

---

## Troubleshooting

### Error: "database already exists"

If you need to recreate the database:

```sql
-- Connect as postgres
psql -U postgres

-- Drop and recreate
DROP DATABASE IF EXISTS studyws;
CREATE DATABASE studyws;
\q
```

### Error: "permission denied"

Ensure you're running commands as a user with appropriate permissions:

```bash
# Try with sudo on Linux
sudo -u postgres psql -d studyws -f createDB.sql

# Or grant permissions
psql -U postgres -c "GRANT ALL PRIVILEGES ON DATABASE studyws TO your_user;"
```

### Error: "relation already exists"

The `createDB.sql` includes `DROP TABLE IF EXISTS ... CASCADE`, so this shouldn't happen. If it does:

```sql
-- Connect to the database
psql -U postgres -d studyws

-- Drop all tables manually
DROP SCHEMA public CASCADE;
CREATE SCHEMA public;
\q

-- Re-run createDB.sql
psql -U postgres -d studyws -f createDB.sql
```

### Foreign Key Constraint Violations

If you get FK errors during population:

1. Ensure `createDB.sql` ran successfully first
2. Check that `populateDB.sql` is executed in a single transaction (it uses `BEGIN`/`COMMIT`)
3. Verify no manual modifications were made to the schema

### Character Encoding Issues

If you see encoding errors:

```sql
-- Recreate database with explicit encoding
CREATE DATABASE studyws
    ENCODING = 'UTF8'
    LC_COLLATE = 'C'
    LC_CTYPE = 'C'
    TEMPLATE = template0;
```


