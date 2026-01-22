# StudyWS – Smart Study Platform
## Version 1.0 – Technical Report
---

## Table of Contents
1. [Introduction](#introduction)
2. [Project Context and Objectives](#project-context-and-objectives)
3. [System Architecture](#system-architecture) 
4. [Database](#database)
5. [Endpoint](#endpoint)

## Introduction
**StudyWS** is a web and mobile application that helps high school and university students manage and organize their learning journey. It enables users to create, edit, and manage notes and learning resources within a structured workspace, while integrating document versioning and AI-based microservices for automated learning-content generation. 

The project represents an education-focused alternative to tools such as Notion and NotebookLM, supporting end-to-end study workflow: from automatic lecture transcription to the generation of flashcards, as well as the automated creation of questions, quizzes, and summaries to facilitate review and retention. 


## Project Context and Objectives
### Project Vision

StudyWS borns from the need to join modern study tools into a single, integrated platform. 
The platform is intended to provide centralized management of notes and documents, voice-based content acquisition (lecture transcription), and the automated generation of review materials. 

### Primary Objectives

| Objective | Description |
|----------|-------------|
| **End-to-end architecture** | Design and implement a complete and scalable client–server–database system. |
| **RESTful backend** | Develop REST APIs to manage core application data and operations. |
| **Document version control** | Implement a versioning mechanism that tracks document evolution over time. |
| **AI microservices** | Divide AI capabilities (e.g., transcription, summarization, flashcard generation) into dedicated Python microservices. |
| **Modern mobile client** | Deliver a responsive and user-friendly mobile client. |


## Features and Requirements
### User-Facing Features

The application supports the following usage flow:

#### Core Features
- **Text editor**: creation and editing of notes and documents
- **Workspace organization**: logical grouping of study materials within workspaces
- **Autosave**: automatic persistence of changes during editing
- **Audio recording**: voice capture of lectures and lessons
- **Automatic transcription**: conversion of audio recordings into text
- **AI-generated summaries**: automated generation of concise content overviews
- **Flashcards**: automatic creation of review material for spaced repetition and practice

#### Access and Security
- **Sign-up and sign-in**: user authentication
- **Account management**: user profile and settings management

### Non-Functional Requirements
- **Responsiveness**: UI usable across devices with different screen sizes
- **Scalability**: architecture designed to scale with growth in users and documents
- **Privacy**: local/on-device processing whenever feasible (e.g., voice transcription)


## System Architecture 
StudyWS adopts a **client–server–microservices** architecture pattern, structured into three main components.
This design separates the user-facing application layer from the core backend services and the AI-oriented processing layer to improve maintainability and scalability:

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                                  CLIENT LAYER                                │
│  Flutter Mobile App / Web Application                                        │
│  • UI + Editor + Version Viewer                                              │
│  • Audio Record (local)                                                      │
│  • REST client + WebSocket client                                            │
└───────────────────────────────┬───────────────────────────────┬──────────────┘
                                │ HTTPS / REST                  │ WebSocket
                                ▼                               ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                           EDGE / APPLICATION LAYER                           │
│  Reverse Proxy (Nginx)                                                       │
│  ├─ PHP Backend REST API (PHP native)                                        │
│  │   • AuthN/AuthZ (JWT / OAuth2)                                            │
│  │   • Workspace/Document CRUD                                               │
│  │   • Versioning logic                                                      │
│  │   • Generates Upload URLs (pre-signed)                                    │
│  │   • Creates AI Jobs + exposes /jobs/{id}                                  │
│  └─ Realtime Gateway (WS)                                                    │
│      • Job progress / events                                                 │
└───────────────────────────────┬───────────────────────────────┬──────────────┘
                                │ SQL                           │ AI services
                                ▼                               ▼
                ┌──────────────────────────┐      ┌──────────────────────────────┐
                │   Relational DB #1       │      │      Python Worker Pool      │
                │     (PostgreSQL)         │      │        (FastAPI)             │
                │  • users                 │      │  • Whisper transcription     │
                │  • workspaces            │      │  • Summarization             │
                │  • documents (paths)     │      │  • Flashcard generation      │
                │  • document_versions     │      │  • Audio processing          │
                │  • jobs/status           │      └───────────────┬──────────────┘
                └──────────────┬───────────┘                      │ read/write artifacts
                               │ Path                             ▼
                               ▼                    ┌────────────────────────────────┐
                ┌──────────────────────────┐        │  Object Storage (MinIO – S3)   │
                │        File System       │        │  • audio/{id}.m4a              │
                │  Document storage        │        │  • transcripts/{id}.txt        │
                │  • PDF                   │        │  • exports/{id}.pdf            │
                │  • PPT                   │        └────────────────────────────────┘
                │  • .wav                  │
                │  • .mp4                  │
                └──────────────────────────┘

```

NOTE: **MinIO** is used as a local S3-compatible object storage for development
and testing. In production, MinIO will be replaced with a managed cloud
object storage service (e.g., AWS S3 or an equivalent provider).


## Database
The **Database and Data Model** section explains StudyWS’s database logical structure and how the application’s core information is organized. 


### Table: `users` 
The `users` table stores each registered user’s identity, authentication credentials (hashed password), and basic profile info. 
| Field | Type | Description |
|---|---|---|
| id | **INT PRIMARY KEY** | Unique identifier.  |
| email | VARCHAR(255) UNIQUE | User email address.  |
| password_hash | VARCHAR(255) | Password hash (bcrypt).  |
| first_name | VARCHAR(100) | User first name.  |
| last_name | VARCHAR(100) | User last name.  |
| registration_date | TIMESTAMP | Sign-up date.  |
| last_access | TIMESTAMP | Last login/access time.  |

### Constraints & Indexes
- Primary Key: `id`
- Unique: `email`

### Relationships
- `users (1) -> (N) workspaces` via `workspaces.user_id`
---

### Table: `workspaces` 
The `workspaces` table groups study materials into logical containers owned by a specific user, enabling better organization (e.g., by subject). 
| Field | Type | Description |
|---|---|---|
| id | **INT PRIMARY KEY** | Unique identifier.  |
| user_id | *INT FOREIGN KEY* | References `users.id` (workspace owner).  |
| name | VARCHAR(255) | Workspace name (e.g., "Physics", "Biology"). **Unique per user** (UNIQUE(`user_id`, `name`)).  |
| description | TEXT | Optional description.  |
| cover_image_url | VARCHAR(2048) | Optional cover image URL/path for the workspace (used to visually represent it in the UI). |
| created_at | TIMESTAMP | When the workspace was created.  |

### Constraints & Indexes
- Primary Key: `id`
- Foreign Key: `user_id` -> `users.id`
- Unique per user: `UNIQUE(user_id, name)`

### Relationships
- `workspaces (N) -> (1) users` via `user_id`
- `workspaces (1) -> (N) documents` via `documents.workspace_id` (se presente)
---

### Table: `documents`

The `documents` table represents the logical container of a document (metadata, ownership, workspace membership). 
| Field | Type | Description |
|---|---|---|
| id | **INT PRIMARY KEY** | Unique identifier. |
| workspace_id | *INT FOREIGN KEY* | References `workspaces.id` (document container). |
| owner_id | *INT FOREIGN KEY* | References `users.id` (document creator/owner). |
| current_version_id | *INT FOREIGN KEY* | References `document_versions.id` (the most recent version). |
| title | VARCHAR(255) | Document title (e.g., “Lecture 3 – Integrals”). |
| document_type | VARCHAR(50) | E.g., `note`, `pdf`, `ppt`, `audio`, `video` (enum-like). |
| storage_key | VARCHAR(255) | Path/key to the most recent stored file. |
| created_at | TIMESTAMP | Creation timestamp. |
| updated_at | TIMESTAMP | Last update timestamp. |

### Constraints & Indexes
- Primary Key: `id`.
- Foreign Keys:
  - `workspace_id` -> `workspaces.id`
  - `owner_id` -> `users.id`
  - `current_version_id` -> `document_versions.id`

### Relationships
- `workspaces (1) -> (N) documents` via `documents.workspace_id`.
- `users (1) -> (N) documents` via `documents.owner_id`.
---

### Table: `document_versions`

The `document_versions` table stores the version history for each document, including a sequential version number, an optional textual diff against the previous version, and metadata about who made the change and when.  

| Field | Type | Description |
|---|---|---|
| id | **INT PRIMARY KEY** | Unique identifier. |
| document_id | *INT FOREIGN KEY* | References `documents.id` (the document being versioned). |
| author_id | *INT FOREIGN KEY* | References `users.id` (who performed the change). |
| version_number | INT | Sequential version number (1, 2, 3, ...). |
| created_at | TIMESTAMP | When this version was saved. |
| commit_message | TEXT | Optional description of the change. |

### Constraints & Indexes
- Primary Key: `id`.
- Foreign Keys:
  - `document_id` -> `documents.id`
  - `author_id` -> `users.id`

### Relationships
- `documents (1) -> (N) document_versions` via `document_versions.document_id`.
- `users (1) -> (N) document_versions` via `document_versions.author_id`.

## Endpoints
All endpoints are public (client-facing) and secured via JWT unless marked otherwise.

### Auth & Session
| Method | Path                   | Auth | Description |
|--------|------------------------|------|-------------|
| POST   | /auth/register         | No   | Create account (email/password + profile). |
| POST   | /auth/login            | No   | Obtain access token (and optional refresh token). |
| POST   | /auth/refresh          | No   | Rotate/refresh tokens. |
| POST   | /auth/logout           | Yes  | Invalidate refresh token / server-side session record (if implemented). |
| POST   | /auth/password/forgot  | No   | Start password reset flow (email). |
| POST   | /auth/password/reset   | No   | Complete password reset. |

### Users
| Method | Path        | Auth | Description |
|--------|-------------|------|-------------|
| GET    | /users/me   | Yes  | Get current user profile. |
| PATCH  | /users/me   | Yes  | Update profile fields. |
| DELETE | /users/me   | Yes  | Delete account (optional in v1). |

### Workspaces
| Method | Path                             | Auth | Description |
|--------|---------------------------------|------|-------------|
| GET    | /workspaces                     | Yes  | List user workspaces (pagination/search). |
| POST   | /workspaces                     | Yes  | Create workspace. |
| GET    | /workspaces/{workspaceId}       | Yes  | Get workspace details. |
| PATCH  | /workspaces/{workspaceId}       | Yes  | Update workspace metadata. |
| DELETE | /workspaces/{workspaceId}       | Yes  | Delete workspace (cascade or soft-delete). |
| GET    | /workspaces/{workspaceId}/stats | Yes  | Aggregate counts (documents, versions, jobs). |

### Documents
| Method | Path                                      | Auth | Description |
|--------|------------------------------------------|------|-------------|
| GET    | /workspaces/{workspaceId}/documents      | Yes  | List documents in workspace (filters by type, updated_at). |
| POST   | /workspaces/{workspaceId}/documents      | Yes  | Create a document container (title/type). |
| GET    | /documents/{documentId}                  | Yes  | Get document metadata (includes current_version_id). |
| PATCH  | /documents/{documentId}                  | Yes  | Rename/move/change metadata. |
| DELETE | /documents/{documentId}                  | Yes  | Delete document + versions + artifacts (policy-defined). |

### Document versions
| Method | Path                                           | Auth | Description |
|--------|-----------------------------------------------|------|-------------|
| GET    | /documents/{documentId}/versions             | Yes  | List versions (descending by version_number). |
| POST   | /documents/{documentId}/versions             | Yes  | Create new version (autosave/commit). Body can include content_full or diff_patch. |
| GET    | /versions/{versionId}                        | Yes  | Get version metadata (author, timestamps, hash). |
| GET    | /versions/{versionId}/content                | Yes  | Get reconstructed full content for that version. |
| POST   | /documents/{documentId}/versions/{versionId}/restore | Yes  | Set as current (updates documents.current_version_id). |

### pre-signed URLs
| Method | Path                                 | Auth | Description |
|--------|-------------------------------------|------|-------------|
| POST   | /uploads/presign                     | Yes  | Create a pre-signed PUT URL (returns upload_url, storage_key, expiry). |
| POST   | /uploads/complete                    | Yes  | Confirm upload finished (optional if needed for consistency). |
| GET    | /files/{storageKey}/download-url     | Yes  | Create short-lived download URL for an artifact/file. |

### Audio recordings
| Method | Path                        | Auth | Description |
|--------|----------------------------|------|-------------|
| POST   | /documents/{documentId}/audio | Yes  | Create “audio recording” record (returns id + presign info or expects pre-upload). |
| GET    | /documents/{documentId}/audio | Yes  | List audio items linked to the document. |
| DELETE | /audio/{audioId}            | Yes  | Delete audio + related artifacts (policy-defined). |

### Flashcards
| Method | Path                                           | Auth | Description |
|--------|-----------------------------------------------|------|-------------|
| GET    | /documents/{documentId}/flashcards           | Yes  | List flashcards for a document (filters: difficulty). |
| POST   | /documents/{documentId}/flashcards           | Yes  | Create a flashcard (manual). |
| POST   | /documents/{documentId}/flashcards:generate | Yes  | Create AI job to generate flashcards from a source (version/transcript). |
| PATCH  | /flashcards/{flashcardId}                    | Yes  | Edit question/answer/difficulty. |
| DELETE | /flashcards/{flashcardId}                    | Yes  | Remove flashcard. |

### Exports 
| Method | Path                                | Auth | Description |
|--------|------------------------------------|------|-------------|
| POST   | /documents/{documentId}/exports     | Yes  | Create export job (PDF). |
| GET    | /documents/{documentId}/exports     | Yes  | List exports (artifact URLs via download-url endpoint). |
| GET    | /exports/{exportId}                 | Yes  | Get export metadata + status. |

### AI Jobs
| Method | Path                   | Auth | Description |
|--------|------------------------|------|-------------|
| POST   | /jobs                  | Yes  | Create job (type: transcribe, summarize, flashcards, process_audio, export_pdf, …). |
| GET    | /jobs/{jobId}          | Yes  | Get job status (queued/running/succeeded/failed/canceled) + progress + result pointers. |
| GET    | /jobs                  | Yes  | List jobs (filters: status, type, workspaceId, documentId). |
| POST   | /jobs/{jobId}/cancel    | Yes  | Request cancellation. |
| GET    | /jobs/{jobId}/artifacts | Yes  | List produced artifacts (storage keys). |