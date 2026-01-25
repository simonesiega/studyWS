# StudyWS - Quick Start Guide

Get StudyWS up and running in under 10 minutes! This guide walks you through the essential steps to set up your local development environment.

## Table of Contents
1. [Prerequisites](#prerequisites)
2. [Clone and Setup](#clone-and-setup)
3. [Environment Configuration](#environment-configuration)
4. [Database Setup](#database-setup)
5. [Start Services](#start-services)
6. [First Test Call](#first-test-call)

---

## Prerequisites

Before starting, ensure you have the following installed:

### Required Software

| Software    | Version | Purpose                          | Download                              |
|-------------|---------|----------------------------------|---------------------------------------|
| PHP         | 8.0+    | Backend API runtime              | https://www.php.net/downloads         |
| PostgreSQL  | 12+     | Relational database              | https://www.postgresql.org/download   |
| Composer    | Latest  | PHP dependency manager (optional)| https://getcomposer.org               |

### Verification

Check that everything is installed:

```powershell
# PHP version
php -v
# Expected: PHP 8.0.x or higher

# PostgreSQL version
psql --version
# Expected: psql (PostgreSQL) 12.x or higher
```

---

## Clone and Setup

### 1. Clone the Repository

```powershell
# Navigate to your desired directory
cd C:\Users\YourName\Desktop

# Clone the repository
git clone https://github.com/simonesiega/studyWS.git
cd studyWS
```

### 2. Verify Project Structure

```powershell
ls
```

You should see:
```
backend/
database/
documentation/
infra/
screen/
tests/
.env.example
LICENSE
README.md
```

---

## Environment Configuration

### Quick Setup

**Step 1:** Copy the environment template:

```powershell
Copy-Item -Path ".env.example" -Destination ".env" -Force
```

**Step 2:** Generate a secure JWT secret:

```powershell
$bytes = New-Object byte[] 32
[Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
$secret = [Convert]::ToBase64String($bytes)
Write-Host "Your JWT Secret: $secret"
```

**Step 3:** Edit `.env` and paste the generated JWT secret:

```powershell
code .env  # or: notepad .env
```

**For detailed configuration options, see [ENV_SETUP.md](ENV_SETUP.md)**

---

## Database Setup

### Quick Setup

**Step 1:** Create the database:

```powershell
psql -U postgres -c "CREATE DATABASE studyws;"
```

**Step 2:** Create schema and populate test data:

```powershell
cd database
psql -U postgres -d studyws -f createDB.sql
psql -U postgres -d studyws -f populateDB.sql
```

**Step 3:** Verify:

```powershell
psql -U postgres -d studyws -c "\dt"
psql -U postgres -d studyws -c "SELECT email FROM users;"
```

You should see 6 tables and 3 test users.

**For detailed setup, troubleshooting, and schema information, see [DATABASE_SETUP.md](DATABASE_SETUP.md)**

---

## Start Services

### 1. Start PHP Backend

Open a new PowerShell terminal and start the PHP development server:

```powershell
# Navigate to backend folder
cd C:\Users\YourName\Desktop\studyWS\backend

# Start PHP server
php -S localhost:8080 -t public
```

You should see:
```
[Sat Jan 25 10:30:00 2026] PHP 8.2.0 Development Server (http://localhost:8080) started
```

**Keep this terminal open!** The server is now running.

### 2. Verify Backend is Running

Open a new PowerShell terminal and test:

```powershell
curl http://localhost:8080
```

Expected response (error is normal - no route defined for `/`):
```json
{"error":"Not Found","message":"Route not found"}
```

This confirms the backend is running!

---

## First Test Call

### 1. Register a New User

Create a test file for registration:

```powershell
# Create test directory if needed
New-Item -Path "C:\Users\YourName\Desktop\studyWS\tests" -ItemType Directory -Force

# Navigate to tests
cd C:\Users\YourName\Desktop\studyWS\tests
```

Create `register_test.json`:

```json
{
  "email": "test@example.com",
  "password": "TestPassword123!",
  "firstName": "Test",
  "lastName": "User"
}
```

Send the registration request:

```powershell
curl -X POST http://localhost:8080/auth/register `
  -H "Content-Type: application/json" `
  -d '@register_test.json'
```

Expected response:
```json
{
  "success": true,
  "message": "User registered successfully",
  "user": {
    "id": 4,
    "email": "test@example.com",
    "firstName": "Test",
    "lastName": "User"
  }
}
```

### 2. Login with Test User

Create `login_test.json`:

```json
{
  "email": "mario.rossi@example.com",
  "password": "Password123!"
}
```

Send login request:

```powershell
curl -X POST http://localhost:8080/auth/login `
  -H "Content-Type: application/json" `
  -d '@login_test.json'
```

Expected response:
```json
{
  "success": true,
  "accessToken": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "refreshToken": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "expiresIn": 3600,
  "user": {
    "id": 1,
    "email": "mario.rossi@example.com",
    "firstName": "Mario",
    "lastName": "Rossi"
  }
}
```

**Copy the `accessToken`** - you'll need it for authenticated requests!

### 3. Get User Profile (Authenticated Request)

Replace `YOUR_ACCESS_TOKEN` with the token from step 2:

```powershell
curl http://localhost:8080/users/me `
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

Expected response:
```json
{
  "id": 1,
  "email": "mario.rossi@example.com",
  "firstName": "Mario",
  "lastName": "Rossi",
  "registrationDate": "2025-12-16T10:30:00Z",
  "lastAccess": "2026-01-24T10:30:00Z"
}
```

**Congratulations!** Your StudyWS backend is up and running!

---

## Available Test Credentials

The database was populated with these test accounts:

| Email                        | Password       | First Name | Last Name |
|------------------------------|----------------|------------|-----------|
| mario.rossi@example.com      | Password123!   | Mario      | Rossi     |
| giulia.bianchi@example.com   | CiaoMondo123!  | Giulia     | Bianchi   |
| luca.verdi@example.com       | Reti2026!!     | Luca       | Verdi     |

Use any of these to test authentication!

---

## Troubleshooting

### Backend won't start

**Error:** `PHP Fatal error: Uncaught Exception: JWT_SECRET environment variable is required`

**Solution:** Make sure you created the `.env` file and set a valid `JWT_SECRET`. See [ENV_SETUP.md](ENV_SETUP.md) for details.

### Database connection failed

**Error:** `SQLSTATE[08006] Unable to connect to PostgreSQL`

**Solution:** 
1. Check PostgreSQL is running: `pg_isready`
2. Verify credentials in `.env` match your PostgreSQL setup
3. Ensure database exists: `psql -U postgres -l | grep studyws`

### Login returns "Invalid credentials"

**Solution:**
- Double-check the password (case-sensitive!)
- Verify test data was inserted: `psql -U postgres -d studyws -c "SELECT email FROM users;"`

### "Route not found" error

**Solution:**
- Verify the URL path is correct (e.g., `/auth/login`, not `/login`)
- Check the backend server is running on port 8080
- Make sure you're using the correct HTTP method (POST for login, not GET)

---

## Development Workflow

### Daily Startup

```powershell
# 1. Make sure PostgreSQL is running
pg_isready

# 2. Start backend server
cd C:\Users\YourName\Desktop\studyWS\backend
php -S localhost:8080 -t public
```

### Making Database Changes

```powershell
# 1. Update createDB.sql or populateDB.sql
# 2. Drop and recreate database
psql -U postgres -c "DROP DATABASE IF EXISTS studyws;"
psql -U postgres -c "CREATE DATABASE studyws;"

# 3. Re-run scripts
cd database
psql -U postgres -d studyws -f createDB.sql
psql -U postgres -d studyws -f populateDB.sql
```

### Testing Changes

```powershell
# Use curl for quick tests
curl -X POST http://localhost:8080/auth/login `
  -H "Content-Type: application/json" `
  -d '{"email":"mario.rossi@example.com","password":"Password123!"}'
```

---

## Useful Commands Cheat Sheet

### Database
```powershell
# Connect to database
psql -U postgres -d studyws

# List tables
psql -U postgres -d studyws -c "\dt"

# Query users
psql -U postgres -d studyws -c "SELECT * FROM users;"

# Backup database
pg_dump -U postgres -d studyws -F c -f studyws_backup.dump

# Restore database
pg_restore -U postgres -d studyws studyws_backup.dump
```

### Backend
```powershell
# Start server
cd backend
php -S localhost:8080 -t public

# Check PHP configuration
php -i | Select-String "extension_dir"

# View loaded extensions
php -m
```

### Testing
```powershell
# Test health endpoint
curl http://localhost:8080/health

# Test with JSON body
curl -X POST http://localhost:8080/endpoint `
  -H "Content-Type: application/json" `
  -d '{"key":"value"}'

# Test with authentication
curl http://localhost:8080/users/me `
  -H "Authorization: Bearer YOUR_TOKEN"
```