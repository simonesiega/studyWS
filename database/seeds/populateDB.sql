INSERT INTO users (email, password_hash, first_name, last_name, registration_date, last_access) VALUES
    ('mario.rossi@email.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mario', 'Rossi', '2024-01-15 10:30:00', '2024-02-06 08:15:00'),
    ('luca.bianchi@email.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Luca', 'Bianchi', '2024-01-20 14:20:00', '2024-02-05 19:45:00'),
    ('anna.verdi@email.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Anna', 'Verdi', '2024-02-01 09:00:00', '2024-02-06 09:10:00'),
    ('admin@demo.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', '2023-12-01 00:00:00', '2024-02-06 06:00:00');

-- Plain text passwords for testing:
-- mario.rossi@email.com -> Password123!
-- luca.bianchi@email.com -> SecurePass456
-- anna.verdi@email.com -> Test789Pass
-- admin@demo.com -> AdminDemo2024

INSERT INTO sessions (user_id, refresh_token_hash, created_at, expires_at, revoked_at, user_agent, ip) VALUES
    -- Active session for Mario (expires in 7 days from creation)
    ((SELECT id FROM users WHERE email = 'mario.rossi@email.com'), 'a1b2c3d4e5f6...', '2024-02-06 08:15:00', '2024-02-13 08:15:00', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '192.168.1.100'),
    -- Expired session for Mario
    ((SELECT id FROM users WHERE email = 'mario.rossi@email.com'), 'b2c3d4e5f6g7...', '2024-01-01 10:00:00', '2024-01-08 10:00:00', NULL, 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)', '192.168.1.105'),
    -- Revoked session (logout) for Luca
    ((SELECT id FROM users WHERE email = 'luca.bianchi@email.com'), 'c3d4e5f6g7h8...', '2024-02-01 14:00:00', '2024-02-08 14:00:00', '2024-02-05 20:00:00', 'Chrome/120.0', '192.168.1.110'),
    -- Active session for Luca
    ((SELECT id FROM users WHERE email = 'luca.bianchi@email.com'), 'd4e5f6g7h8i9...', '2024-02-05 19:45:00', '2024-02-12 19:45:00', NULL, 'Chrome/121.0', '192.168.1.110'),
    -- Active session for Anna
    ((SELECT id FROM users WHERE email = 'anna.verdi@email.com'), 'e5f6g7h8i9j0...', '2024-02-06 09:10:00', '2024-02-13 09:10:00', NULL, 'Safari/17.0', '192.168.1.115');

INSERT INTO workspaces (user_id, name, description, cover_image_url, created_at) VALUES
    -- Mario's workspaces
    ((SELECT id FROM users WHERE email = 'mario.rossi@email.com'), 'Informatica', 'Appunti e progetti di programmazione', '/uploads/covers/informatica.jpg', '2024-01-16 11:00:00'),
    ((SELECT id FROM users WHERE email = 'mario.rossi@email.com'), 'Matematica', 'Analisi matematica e algebra lineare', NULL, '2024-01-18 09:30:00'),
    
    -- Luca's workspaces
    ((SELECT id FROM users WHERE email = 'luca.bianchi@email.com'), 'Fisica', 'Meccanica, termodinamica ed elettromagnetismo', '/uploads/covers/fisica.png', '2024-01-21 15:00:00'),
    ((SELECT id FROM users WHERE email = 'luca.bianchi@email.com'), 'Chimica', 'Chimica organica e inorganica', NULL, '2024-01-25 10:15:00'),
    
    -- Anna's workspaces
    ((SELECT id FROM users WHERE email = 'anna.verdi@email.com'), 'Letteratura Italiana', 'Dante, Petrarca, Boccaccio e letteratura moderna', '/uploads/covers/letteratura.jpg', '2024-02-02 14:00:00'),
    ((SELECT id FROM users WHERE email = 'anna.verdi@email.com'), 'Storia dell''Arte', 'Rinascimento e Barocco', NULL, '2024-02-04 16:30:00');

INSERT INTO documents (workspace_id, owner_id, title, document_type, storage_key, created_at, updated_at) VALUES
    -- Informatica workspace (Mario)
    ((SELECT id FROM workspaces WHERE name = 'Informatica' AND user_id = (SELECT id FROM users WHERE email = 'mario.rossi@email.com')), 
     (SELECT id FROM users WHERE email = 'mario.rossi@email.com'), 
     'Algoritmi di Ordinamento', 'pdf', 'docs/mario/alg_ordinamento_v1.pdf', '2024-01-17 10:00:00', '2024-01-20 14:30:00'),
    
    ((SELECT id FROM workspaces WHERE name = 'Informatica' AND user_id = (SELECT id FROM users WHERE email = 'mario.rossi@email.com')), 
     (SELECT id FROM users WHERE email = 'mario.rossi@email.com'), 
     'Progettazione Database', 'note', 'docs/mario/db_design_v3.md', '2024-01-19 11:00:00', '2024-02-01 09:15:00'),
    
    -- Matematica workspace (Mario)
    ((SELECT id FROM workspaces WHERE name = 'Matematica' AND user_id = (SELECT id FROM users WHERE email = 'mario.rossi@email.com')), 
     (SELECT id FROM users WHERE email = 'mario.rossi@email.com'), 
     'Analisi 1 - Limiti e Continuità', 'pdf', 'docs/mario/analisi_limiti.pdf', '2024-01-22 16:00:00', '2024-01-22 16:00:00'),
    
    -- Fisica workspace (Luca)
    ((SELECT id FROM workspaces WHERE name = 'Fisica' AND user_id = (SELECT id FROM users WHERE email = 'luca.bianchi@email.com')), 
     (SELECT id FROM users WHERE email = 'luca.bianchi@email.com'), 
     'Meccanica Classica - Newton', 'pdf', 'docs/luca/newton_mechanics.pdf', '2024-01-23 09:00:00', '2024-01-28 11:30:00'),
    
    ((SELECT id FROM workspaces WHERE name = 'Fisica' AND user_id = (SELECT id FROM users WHERE email = 'luca.bianchi@email.com')), 
     (SELECT id FROM users WHERE email = 'luca.bianchi@email.com'), 
     'Elettromagnetismo di Maxwell', 'ppt', 'docs/luca/maxwell_em.pptx', '2024-01-27 14:00:00', '2024-02-05 18:00:00'),
    
    -- Chimica workspace (Luca)
    ((SELECT id FROM workspaces WHERE name = 'Chimica' AND user_id = (SELECT id FROM users WHERE email = 'luca.bianchi@email.com')), 
     (SELECT id FROM users WHERE email = 'luca.bianchi@email.com'), 
     'Chimica Organica - Idrocarburi', 'note', 'docs/luca/org_idrocarburi_v2.md', '2024-01-30 10:30:00', '2024-02-03 16:45:00'),
    
    -- Letteratura workspace (Anna)
    ((SELECT id FROM workspaces WHERE name = 'Letteratura Italiana' AND user_id = (SELECT id FROM users WHERE email = 'anna.verdi@email.com')), 
     (SELECT id FROM users WHERE email = 'anna.verdi@email.com'), 
     'Divina Commedia - Inferno', 'pdf', 'docs/anna/dante_inferno.pdf', '2024-02-03 11:00:00', '2024-02-05 10:20:00'),
    
    ((SELECT id FROM workspaces WHERE name = 'Letteratura Italiana' AND user_id = (SELECT id FROM users WHERE email = 'anna.verdi@email.com')), 
     (SELECT id FROM users WHERE email = 'anna.verdi@email.com'), 
     'Decameron - Analisi Novelle', 'note', 'docs/anna/boccaccio_decameron.md', '2024-02-05 15:00:00', '2024-02-06 08:30:00');

INSERT INTO document_versions (document_id, author_id, version_number, is_current, created_at, commit_message) VALUES
    -- Algoritmi di Ordinamento (v1, v2=current)
    ((SELECT id FROM documents WHERE title = 'Algoritmi di Ordinamento'), 
     (SELECT id FROM users WHERE email = 'mario.rossi@email.com'), 
     1, FALSE, '2024-01-17 10:00:00', 'Versione iniziale - bubble sort e insertion sort'),
    ((SELECT id FROM documents WHERE title = 'Algoritmi di Ordinamento'), 
     (SELECT id FROM users WHERE email = 'mario.rossi@email.com'), 
     2, TRUE, '2024-01-20 14:30:00', 'Aggiunto quicksort e mergesort, corretto errori'),
    
    -- Progettazione Database (v1, v2, v3=current)
    ((SELECT id FROM documents WHERE title = 'Progettazione Database'), 
     (SELECT id FROM users WHERE email = 'mario.rossi@email.com'), 
     1, FALSE, '2024-01-19 11:00:00', 'Schema ER iniziale'),
    ((SELECT id FROM documents WHERE title = 'Progettazione Database'), 
     (SELECT id FROM users WHERE email = 'mario.rossi@email.com'), 
     2, FALSE, '2024-01-25 16:00:00', 'Normalizzazione 3NF'),
    ((SELECT id FROM documents WHERE title = 'Progettazione Database'), 
     (SELECT id FROM users WHERE email = 'mario.rossi@email.com'), 
     3, TRUE, '2024-02-01 09:15:00', 'Aggiunte query SQL e ottimizzazione indici'),
    
    -- Analisi 1 (v1=current, single version)
    ((SELECT id FROM documents WHERE title = 'Analisi 1 - Limiti e Continuità'), 
     (SELECT id FROM users WHERE email = 'mario.rossi@email.com'), 
     1, TRUE, '2024-01-22 16:00:00', 'Prima versione completa'),
    
    -- Meccanica Classica (v1, v2=current)
    ((SELECT id FROM documents WHERE title = 'Meccanica Classica - Newton'), 
     (SELECT id FROM users WHERE email = 'luca.bianchi@email.com'), 
     1, FALSE, '2024-01-23 09:00:00', 'Leggi del moto'),
    ((SELECT id FROM documents WHERE title = 'Meccanica Classica - Newton'), 
     (SELECT id FROM users WHERE email = 'luca.bianchi@email.com'), 
     2, TRUE, '2024-01-28 11:30:00', 'Aggiunte forze e energia'),
    
    -- Elettromagnetismo di Maxwell (v1=current)
    ((SELECT id FROM documents WHERE title = 'Elettromagnetismo di Maxwell'), 
     (SELECT id FROM users WHERE email = 'luca.bianchi@email.com'), 
     1, TRUE, '2024-01-27 14:00:00', 'Slides complete - equazioni di Maxwell'),
    
    -- Chimica Organica (v1, v2=current)
    ((SELECT id FROM documents WHERE title = 'Chimica Organica - Idrocarburi'), 
     (SELECT id FROM users WHERE email = 'luca.bianchi@email.com'), 
     1, FALSE, '2024-01-30 10:30:00', 'Alcani e alcheni'),
    ((SELECT id FROM documents WHERE title = 'Chimica Organica - Idrocarburi'), 
     (SELECT id FROM users WHERE email = 'luca.bianchi@email.com'), 
     2, TRUE, '2024-02-03 16:45:00', 'Aggiunti alchini e composti aromatici'),
    
    -- Divina Commedia (v1, v2=current)
    ((SELECT id FROM documents WHERE title = 'Divina Commedia - Inferno'), 
     (SELECT id FROM users WHERE email = 'anna.verdi@email.com'), 
     1, FALSE, '2024-02-03 11:00:00', 'Note sul primo cerchio'),
    ((SELECT id FROM documents WHERE title = 'Divina Commedia - Inferno'), 
     (SELECT id FROM users WHERE email = 'anna.verdi@email.com'), 
     2, TRUE, '2024-02-05 10:20:00', 'Completata analisi fino al nono cerchio'),
    
    -- Decameron (v1=current)
    ((SELECT id FROM documents WHERE title = 'Decameron - Analisi Novelle'), 
     (SELECT id FROM users WHERE email = 'anna.verdi@email.com'), 
     1, TRUE, '2024-02-05 15:00:00', 'Analisi prime 10 novelle');

INSERT INTO flashcards (document_id, question, answer, difficulty, created_at) VALUES
    -- Flashcards per Algoritmi di Ordinamento
    ((SELECT id FROM documents WHERE title = 'Algoritmi di Ordinamento'),
     'Qual è la complessità temporale di QuickSort nel caso medio?',
     'O(n log n) nel caso medio, O(n²) nel caso peggiore',
     'medium',
     '2024-01-21 10:00:00'),
    ((SELECT id FROM documents WHERE title = 'Algoritmi di Ordinamento'),
     'Quale algoritmo di ordinamento è stabile?',
     'MergeSort è stabile. QuickSort e HeapSort non sono stabili di default',
     'hard',
     '2024-01-22 14:30:00'),
    
    -- Flashcards per Progettazione Database
    ((SELECT id FROM documents WHERE title = 'Progettazione Database'),
     'Cosa significa 3NF (Terza Forma Normale)?',
     'Una tabella è in 3NF se è in 2NF e non ci sono dipendenze transitive',
     'hard',
     '2024-01-26 09:15:00'),
    ((SELECT id FROM documents WHERE title = 'Progettazione Database'),
     'Qual è lo scopo della chiave primaria?',
     'Identificare univocamente ogni record in una tabella',
     'easy',
     '2024-01-28 11:00:00'),
    
    -- Flashcards per Meccanica Classica
    ((SELECT id FROM documents WHERE title = 'Meccanica Classica - Newton'),
     'Enuncia la prima legge di Newton',
     'Un corpo permane nel suo stato di quiete o di moto rettilineo uniforme a meno che non intervenga una forza esterna',
     'medium',
     '2024-01-24 16:00:00'),
    ((SELECT id FROM documents WHERE title = 'Meccanica Classica - Newton'),
     'Formula della seconda legge di Newton',
     'F = ma (Forza = massa × accelerazione)',
     'easy',
     '2024-01-26 10:30:00'),
    
    -- Flashcards per Elettromagnetismo
    ((SELECT id FROM documents WHERE title = 'Elettromagnetismo di Maxwell'),
     'Quante sono le equazioni di Maxwell?',
     'Quattro equazioni fondamentali',
     'easy',
     '2024-01-29 14:00:00'),
    ((SELECT id FROM documents WHERE title = 'Elettromagnetismo di Maxwell'),
     'Cosa descrive la legge di Faraday-Lenz?',
     'La variazione del flusso magnetico induce una forza elettromotrice',
     'hard',
     '2024-02-01 09:00:00'),
    
    -- Flashcards per Chimica Organica
    ((SELECT id FROM documents WHERE title = 'Chimica Organica - Idrocarburi'),
     'Qual è il nome IUPAC del metano?',
     'Metano (CH4) - alcano più semplice',
     'easy',
     '2024-02-01 11:30:00'),
    ((SELECT id FROM documents WHERE title = 'Chimica Organica - Idrocarburi'),
     'Differenza tra alcani e alcheni',
     'Gli alcani hanno solo legami semplici C-C, gli alcheni hanno almeno un doppio legame C=C',
     'medium',
     '2024-02-04 15:00:00'),
    
    -- Flashcards per Divina Commedia
    ((SELECT id FROM documents WHERE title = 'Divina Commedia - Inferno'),
     'Chi è la "selva oscura" all''inizio del poema?',
     'Metafora del peccato e della perdizione morale',
     'medium',
     '2024-02-04 10:00:00'),
    ((SELECT id FROM documents WHERE title = 'Divina Commedia - Inferno'),
     'Chi guida Dante attraverso l''Inferno?',
     'Virgilio, simbolo della ragione umana',
     'easy',
     '2024-02-06 08:30:00'),
    
    -- Flashcards per Decameron
    ((SELECT id FROM documents WHERE title = 'Decameron - Analisi Novelle'),
     'Quanti sono i novellatori del Decameron?',
     'Dieci novellatori (7 donne e 3 uomini)',
     'easy',
     '2024-02-06 09:00:00'),
    ((SELECT id FROM documents WHERE title = 'Decameron - Analisi Novelle'),
     'Qual è il tema della prima novella (Ser Ciappelletto)?',
     'L''ipocrisia e la credulità popolare',
     'hard',
     '2024-02-06 10:15:00');

INSERT INTO rate_limit_log (ip, path, timestamp, created_at) VALUES
    ('192.168.1.100', '/api/auth/login', 1707207300, '2024-02-06 08:15:00'),
    ('192.168.1.100', '/api/auth/login', 1707207310, '2024-02-06 08:15:10'),
    ('192.168.1.100', '/api/auth/login', 1707207320, '2024-02-06 08:15:20'),
    ('192.168.1.100', '/api/documents', 1707207400, '2024-02-06 08:16:40'),
    ('192.168.1.100', '/api/documents', 1707207410, '2024-02-06 08:16:50'),
    ('192.168.1.110', '/api/auth/login', 1707202800, '2024-02-05 19:00:00'),
    ('192.168.1.110', '/api/workspaces', 1707206400, '2024-02-05 20:00:00'),
    ('10.0.0.50', '/api/auth/login', 1707199200, '2024-02-05 18:00:00'),
    ('10.0.0.50', '/api/auth/login', 1707199260, '2024-02-05 18:01:00'),
    ('10.0.0.50', '/api/auth/login', 1707199320, '2024-02-05 18:02:00'),
    ('10.0.0.50', '/api/auth/login', 1707199380, '2024-02-05 18:03:00'),
    ('10.0.0.50', '/api/auth/login', 1707199440, '2024-02-05 18:04:00'),
    ('10.0.0.50', '/api/auth/register', 1707199500, '2024-02-05 18:05:00');