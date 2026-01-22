# StudyWS – Smart Study Platform
## Version 1.0 – Technical Report
---

## Table of Contents
1. [Introduction](#introduction)
2. [Project Context and Objectives](#project-context-and-objectives)
3. [System Architecture](#system-architecture) 
4. [Database](#database)

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
                │  • versions              │      │  • Audio processing          │
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

### Database
The **Database and Data Model** section explains StudyWS’s database logical structure and how the application’s core information is organized. 

**Description:** The `users` table stores each registered user’s identity, authentication credentials (hashed password), and basic profile info. 

### Table: `users` 
| Field | Type | Description |
|---|---|---|
| id | INT PRIMARY KEY | Unique identifier.  |
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

**Description:** The `workspaces` table groups study materials into logical containers owned by a specific user, enabling better organization (e.g., by subject). 

### Table: `workspaces` 
| Field | Type | Description |
|---|---|---|
| id | INT PRIMARY KEY | Unique identifier.  |
| user_id | INT FOREIGN KEY | References `users.id` (workspace owner).  |
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