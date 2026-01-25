# Environment Setup Guide

## Overview
This guide shows how to create your local `.env` file starting from `.env.example`, step by step, using Windows PowerShell.

## Prerequisites
- PowerShell 5+ (default on Windows).
- The repository already cloned locally 

## 1) Copy the template
Run the copy command from the project root:

```powershell
Copy-Item -Path ".env.example" -Destination ".env" -Force
```

## 2) Set required values
Open the new `.env` and adjust values for your environment:

- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`: database connection.
- `JWT_SECRET`: strong random key (Base64 recommended).
- `JWT_ACCESS_EXPIRY`, `JWT_REFRESH_EXPIRY`: expiry in seconds.
- `APP_ENV`, `APP_DEBUG`: runtime mode and debug flag.
- `SERVER_PORT`: PHP server/listener port.

You can edit with VS Code or Notepad:

```powershell
code .env
# or
notepad .env
```

## 3) Generate a strong JWT secret (recommended)
Generate a 32-byte random key, Base64 encoded:

```powershell
$bytes = New-Object byte[] 32; [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes); [Convert]::ToBase64String($bytes)
```

Copy the output and paste it into `JWT_SECRET` in `.env`.

## 4) Quick example configuration
Example values (adjust as needed):

```dotenv
DB_HOST=localhost
DB_PORT=5432
DB_NAME=studyws
DB_USER=studyws_user
DB_PASSWORD=your_secure_password_here

JWT_SECRET=base64-random-generated-value
JWT_ACCESS_EXPIRY=3600
JWT_REFRESH_EXPIRY=604800

APP_ENV=development
APP_DEBUG=true
SERVER_PORT=8080
```

## 5) Verify the file is loaded
From the backend folder, ensure your runtime reads `.env` (example for PHP built-in server):

```powershell
Set-Location backend
php -S localhost:8080 -t public
```

If the app starts without missing-variable errors, the `.env` file is being read.

## 6) Keep secrets safe
- Do not commit `.env` to source control.
- Rotate `JWT_SECRET` if compromised.
- Use strong, non-default database passwords.
