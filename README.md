<p align="center">
  <img src="screen/logo/logo.svg" alt="MEMORANDUM logo" width="160" />
</p>

<h1 align="center">MEMORANDUM</h1>

<p align="center">
  <a href="https://github.com/simonesiega/studyWS/commits/main">
    <img alt="Last commit" src="https://img.shields.io/github/last-commit/simonesiega/studyWS" />
  </a>
  <a href="https://github.com/simonesiega/studyWS/issues">
    <img alt="Issues" src="https://img.shields.io/github/issues/simonesiega/studyWS" />
  </a>
  <a href="https://github.com/simonesiega/studyWS/stargazers">
    <img alt="Stars" src="https://img.shields.io/github/stars/simonesiega/studyWS" />
  </a>
  <a href="https://github.com/simonesiega/studyWS/network/members">
    <img alt="Forks" src="https://img.shields.io/github/forks/simonesiega/studyWS" />
  </a>
  <a href="https://github.com/simonesiega/studyWS/blob/main/LICENSE">
    <img alt="License" src="https://img.shields.io/github/license/simonesiega/studyWS" />
  </a>
</p>

<p align="center">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-native-777BB4" />
  <img alt="PostgreSQL" src="https://img.shields.io/badge/DB-PostgreSQL-336791" />
  <img alt="FastAPI" src="https://img.shields.io/badge/AI-FastAPI-009688" />
  <img alt="Storage" src="https://img.shields.io/badge/Storage-MinIO%20(S3)-C72E49" />
</p>

<p align="center">
  A study platform that helps students manage notes, workspaces, and learning resources, with document versioning and AI microservices.
</p>

<p align="center">
  <video src="screen/frontend/presentation.mp4" width="700" controls></video>
</p>

## Core capabilities

- Workspaces & organization: Keep subjects, courses, and resources structured and searchable.
- Notes editor + autosave: Write fast, iterate continuously, and avoid losing progress.
- Lecture capture: Record lessons and turn them into usable text with automatic transcription.
- AI study tools: Generate summaries and flashcards to speed up review and repetition.
- Accounts: Sign up, sign in, and manage your profile and settings.

## Why MEMORANDUM

Students typically split their workflow across many tools (notes, audio, flashcards, storage, AI).  
**MEMORANDUM** consolidates that workflow into a single pipeline — from lecture capture to exam-ready material — reducing context switching and friction.

## Product direction

MEMORANDUM is designed as a modular education platform where:
- Notes evolve like code (editable and versioned).
- Raw lectures can be transformed automatically into structured learning content.
- AI capabilities are isolated as independent services, so features can evolve without coupling the core app.

## Architecture & Tech Overview

| Area | Technology | Goal |
|---|---|---|
| Architecture | Client–Server | Scalable distributed system |
| Backend | PHP | REST APIs for core operations |
| Database | PostgreSQL | Document history via versioning |
| AI Services | FastAPI | Independent microservices for AI features |
| Storage | MinIO (S3) | Object storage for media and documents |
| Client | Web / Mobile | Modern, mobile-first user experience |

## Contributing & support 🤝

Contributions are welcome.

- For bugs and feature requests, open an [Issue](https://github.com/simonesiega/studyWS/issues).
- For code contributions, open a **Pull Request** with a clear description of the change and its rationale.
- For direct contact, email me at [simonesiega1@gmail.com](mailto:simonesiega1@gmail.com) or reach out on [GitHub](https://github.com/simonesiega).

## License

This project is licensed under the terms of the Apache 2.0 [LICENSE](./LICENSE) file.

## Contributors 🧑‍💻

<p align="center">
  <a href="https://github.com/simonesiega/StudyWS/graphs/contributors">
    <img src="https://contrib.rocks/image?repo=simonesiega/StudyWS&max=24&columns=12" alt="Contributors" />
  </a>
</p>
