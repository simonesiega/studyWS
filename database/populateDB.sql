BEGIN;

-- ============================================================
-- USERS 
-- Credenziali:
-- 1) mario.rossi@example.com  / Password123!
-- 2) giulia.bianchi@example.com / CiaoMondo123!
-- 3) luca.verdi@example.com / Reti2026!!
-- ============================================================
INSERT INTO users (email, password_hash, first_name, last_name, registration_date, last_access)
VALUES
('mario.rossi@example.com', '$2b$12$zPPEOEwfVInHpODkuftRQ.0/OQLtuDeI2ij4UTeyXjd.3FQaA0ovi', 'Mario', 'Rossi', NOW() - INTERVAL '40 days', NOW() - INTERVAL '1 day'),
('giulia.bianchi@example.com', '$2b$12$cGz3l2njL.4kHGsNvJpluehFfPFWEaqZPCHnHtH.9AZK0OYlbJwoa', 'Giulia', 'Bianchi', NOW() - INTERVAL '20 days', NOW() - INTERVAL '2 hours'),
('luca.verdi@example.com', '$2b$12$NqmSt.hB8.G7DD/4MHI3hOS73utfmfvuQRyC6Wp0aqnOWPVAa0Yie', 'Luca', 'Verdi', NOW() - INTERVAL '10 days', NOW() - INTERVAL '5 hours');

-- ============================================================
-- WORKSPACES 
-- ============================================================
INSERT INTO workspaces (user_id, name, description, cover_image_url, created_at)
VALUES
(1, 'Basi di Dati', 'Appunti + esercizi SQL e normalizzazione', NULL, NOW() - INTERVAL '35 days'),
(1, 'Reti', 'OSI/TCP-IP, esercizi subnetting', NULL, NOW() - INTERVAL '30 days'),
(2, 'Algoritmi', 'Greedy, DP, grafi', NULL, NOW() - INTERVAL '18 days'),
(3, 'Ingegneria del Software', 'Design patterns, UML, test', NULL, NOW() - INTERVAL '9 days');

-- ============================================================
-- DOCUMENTS
-- ============================================================
INSERT INTO documents (workspace_id, owner_id, current_version_id, title, document_type, storage_key, created_at, updated_at)
VALUES
(1, 1, NULL, 'Normalizzazione (1NF-3NF)', 'note', 'notes/db/normalizzazione.md', NOW() - INTERVAL '33 days', NOW() - INTERVAL '3 days'),
(1, 1, NULL, 'Query SQL - Esercizi', 'note', 'notes/db/esercizi-sql.md', NOW() - INTERVAL '32 days', NOW() - INTERVAL '2 days'),
(2, 1, NULL, 'Subnetting - Formulari', 'pdf',  'files/reti/subnetting.pdf', NOW() - INTERVAL '28 days', NOW() - INTERVAL '10 days'),
(3, 2, NULL, 'DP: Knapsack', 'note', 'notes/algo/knapsack.md', NOW() - INTERVAL '15 days', NOW() - INTERVAL '1 day'),
(4, 3, NULL, 'Pattern: Factory & Strategy', 'ppt', 'slides/is/patterns.pptx', NOW() - INTERVAL '7 days', NOW() - INTERVAL '6 days');

-- ============================================================
-- DOCUMENT VERSIONS 
-- ============================================================
-- Doc 1: 2 versioni
INSERT INTO document_versions (document_id, author_id, version_number, created_at, commit_message)
VALUES
(1, 1, 1, NOW() - INTERVAL '33 days', 'Prima stesura: definizioni e esempi'),
(1, 1, 2, NOW() - INTERVAL '3 days',  'Aggiunti esempi di decomposizione e dipendenze');
-- Doc 2: 3 versioni
INSERT INTO document_versions (document_id, author_id, version_number, created_at, commit_message)
VALUES
(2, 1, 1, NOW() - INTERVAL '32 days', 'Lista esercizi base SELECT/JOIN'),
(2, 1, 2, NOW() - INTERVAL '20 days', 'Aggiunti esercizi GROUP BY/HAVING'),
(2, 1, 3, NOW() - INTERVAL '2 days',  'Aggiunti esercizi subquery e CTE');
-- Doc 3: 1 versione
INSERT INTO document_versions (document_id, author_id, version_number, created_at, commit_message)
VALUES
(3, 1, 1, NOW() - INTERVAL '28 days', 'Upload PDF subnetting');
-- Doc 4: 2 versioni (autore Giulia)
INSERT INTO document_versions (document_id, author_id, version_number, created_at, commit_message)
VALUES
(4, 2, 1, NOW() - INTERVAL '15 days', 'Spiegazione DP e tabellazione'),
(4, 2, 2, NOW() - INTERVAL '1 day',  'Aggiunti casi limite e complessità');
-- Doc 5: 1 versione (autore Luca)
INSERT INTO document_versions (document_id, author_id, version_number, created_at, commit_message)
VALUES
(5, 3, 1, NOW() - INTERVAL '7 days', 'Slide iniziali sui pattern');

-- ============================================================
-- SET documents.current_version_id 
-- ============================================================
UPDATE documents d
SET current_version_id = dv.id
FROM document_versions dv
WHERE dv.document_id = d.id
  AND dv.version_number = (
    SELECT MAX(version_number)
    FROM document_versions
    WHERE document_id = d.id
  );

-- ============================================================
-- FLASHCARDS 
-- ============================================================
INSERT INTO flashcards (document_id, question, answer, difficulty, created_at)
VALUES
(1, 'Cos’è una dipendenza funzionale?', 'Relazione X → Y: X determina univocamente Y in una relazione.', 'easy', NOW() - INTERVAL '3 days'),
(1, 'Quando una tabella è in 3NF?', 'È in 2NF e non ha dipendenze transitive da chiavi candidate.', 'medium', NOW() - INTERVAL '3 days'),
(2, 'Differenza tra WHERE e HAVING?', 'WHERE filtra righe prima del grouping, HAVING filtra gruppi dopo GROUP BY.', 'easy', NOW() - INTERVAL '2 days'),
(4, 'Idea base del knapsack 0/1 con DP?', 'DP su indice e capacità: dp[i][w] = max(includi, escludi).', 'medium', NOW() - INTERVAL '1 day'),
(3, 'CIDR /24 quanti host utilizzabili?', '254 host (2^8 - 2).', 'easy', NOW() - INTERVAL '10 days');

-- ============================================================
-- SESSIONS 
-- ============================================================
INSERT INTO sessions (user_id, refresh_token_hash, created_at, expires_at, revoked_at, user_agent, ip)
VALUES
(1, repeat('a', 64), NOW() - INTERVAL '1 day',  NOW() + INTERVAL '6 days', NULL,'Mozilla/5.0 TestClient', '127.0.0.1'),
(1, repeat('b', 64), NOW() - INTERVAL '10 days', NOW() - INTERVAL '3 days', NULL,'Mozilla/5.0 OldClient',  '127.0.0.1'),
(2, repeat('c', 64), NOW() - INTERVAL '2 hours', NOW() + INTERVAL '7 days', NULL,'Mozilla/5.0 TestClient', '127.0.0.1'),
(3, repeat('d', 64), NOW() - INTERVAL '5 hours', NOW() + INTERVAL '7 days', NOW() - INTERVAL '1 hour', 'Mozilla/5.0 TestClient', '127.0.0.1');

COMMIT;
