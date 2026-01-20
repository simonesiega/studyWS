# StudyWS – Smart Study Platform
## Version 1.0 – Technical Report
---

## Table of Contents
1. [Introduction](#introduction)
2. [Project Context and Objectives](#project-context-and-objectives)

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


## Architettura del Sistema {#architettura-del-sistema}
StudyWS adopts a **client–server–microservices** architecture pattern, structured into three main components.
This design separates the user-facing application layer from the core backend services and the AI-oriented processing layer to improve maintainability and scalability:

```
┌──────────────────────────────────────────────────────────────────────┐
│                             CLIENT LAYER                             │
│  Flutter Mobile App / WEB application                                │
│  • UI + Editor + Version Viewer                                      │
│  • Audio Record (local)                                              │
│  • REST client + WebSocket client                                    │
└───────────────┬────────────────────────────────────┬─────────────────┘
                │ HTTPS/REST                         │ WebSocket
                ▼                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                         EDGE / APPLICATION LAYER                       │
│  Reverse Proxy (Nginx)                                                 │
│   ├─ PHP Backend REST API (PHP native)                                 │
│   │   • AuthN/AuthZ (JWT/OAuth2)                                       │
│   │   • Workspace/Document CRUD                                        │
│   │   • Versioning logic                                               │
│   │   • Generates Upload URLs (pre-signed)                             │
│   │   • Creates AI Jobs + exposes /jobs/{id}                           │
│   └─ Realtime Gateway (WS)                                             │
│       • Job progress/events                                            │
└───────────────┬───────────────────────────────────┬────────────────────┘
                │ SQL                               │ AI services
                ▼                                   │
     ┌──────────────────────┐                       │
     │  Relational DB       │                       │
     │  (PostgreSQL)        │                       │
     │  • users             │                       │
     │  • workspaces        │                       │
     │  • documents         │                       │
     │  • versions          │                       ▼
     │  • jobs/status       │        ┌──────────────────────────────┐
     └──────────────────────┘        │  Python Worker Pool          │
                                     │  (FastAPI)                   │
                                     │  • Whisper transcription     │
                                     │  • Summarization             │
                                     │  • Flashcard generation      │
                                     │  • Audio processing          │
                                     └──────────────┬───────────────┘
                                                    │ read/write artifacts
                                                    ▼
                                     ┌────────────────────────────────┐
                                     │ Object Storage (MinIO – S3)    │
                                     │ • audio/{id}.m4a               │
                                     │ • transcripts/{id}.txt         │
                                     │ • exports/{id}.pdf             │
                                     └────────────────────────────────┘
```

NOTE: **MinIO** is used as a local S3-compatible object storage for development
and testing. In production, MinIO will be replaced with a managed cloud
object storage service (e.g., AWS S3 or an equivalent provider).
