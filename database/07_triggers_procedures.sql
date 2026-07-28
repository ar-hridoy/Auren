-- ============================================================================
-- 07_triggers_procedures.sql
-- Business logic at the database layer: TRIGGERS + STORED PROCEDURES.
--
-- Load order: run AFTER 01..06 and the seed data. These objects act on future
-- activity, so loading them after the seed keeps the seed import clean.
--
-- Contents
--   TRIGGERS
--     trg_applications_before_insert  - integrity: block applying to a job that
--                                       isn't open, or applying to your own job
--     trg_applications_after_update   - business logic: when accepted applicants
--                                       reach a job's vacancies, auto-mark the
--                                       job 'filled'
--     trg_users_before_insert         - normalises email to lower-case
--   PROCEDURES
--     sp_apply_to_job     - encapsulated, transaction-safe "apply" workflow
--     sp_accept_application - accept an applicant (fires the fill trigger)
--     sp_category_demand  - analytical: open jobs + applicants per category
-- ============================================================================

USE auren_db;

-- Clean re-runs
DROP TRIGGER IF EXISTS trg_applications_before_insert;
DROP TRIGGER IF EXISTS trg_applications_after_update;
DROP TRIGGER IF EXISTS trg_users_before_insert;
DROP PROCEDURE IF EXISTS sp_apply_to_job;
DROP PROCEDURE IF EXISTS sp_accept_application;
DROP PROCEDURE IF EXISTS sp_category_demand;

DELIMITER $$

-- ---------------------------------------------------------------------------
-- TRIGGER 1 — data integrity on new applications (BEFORE INSERT)
-- Business rules enforced in the database itself:
--   * a seeker may only apply to a job whose status is 'open'
--   * a seeker may not apply to a job they themselves posted
-- ---------------------------------------------------------------------------
CREATE TRIGGER trg_applications_before_insert
BEFORE INSERT ON Applications
FOR EACH ROW
BEGIN
    DECLARE v_status INT;
    DECLARE v_employer INT;

    SELECT status_id, employer_id INTO v_status, v_employer
    FROM Jobs WHERE job_id = NEW.job_id;

    IF v_status IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot apply: job does not exist.';
    END IF;

    IF v_status <> 1 THEN  -- 1 = open
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot apply: this job is no longer open.';
    END IF;

    IF v_employer = NEW.seeker_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot apply: you cannot apply to your own job.';
    END IF;
END$$

-- ---------------------------------------------------------------------------
-- TRIGGER 2 — auto-fill jobs (AFTER UPDATE)
-- When an application becomes 'accepted', if the number of accepted applicants
-- reaches the job's vacancy count, the job is automatically marked 'filled'.
-- This is real business logic living in the database.
-- ---------------------------------------------------------------------------
CREATE TRIGGER trg_applications_after_update
AFTER UPDATE ON Applications
FOR EACH ROW
BEGIN
    DECLARE v_accepted INT;
    DECLARE v_vacancies INT;

    IF NEW.status_id = 2 AND OLD.status_id <> 2 THEN  -- became accepted
        SELECT COUNT(*) INTO v_accepted
        FROM Applications
        WHERE job_id = NEW.job_id AND status_id = 2;

        SELECT vacancies INTO v_vacancies
        FROM Jobs WHERE job_id = NEW.job_id;

        IF v_accepted >= v_vacancies THEN
            UPDATE Jobs SET status_id = 3   -- 3 = filled
            WHERE job_id = NEW.job_id AND status_id = 1;
        END IF;
    END IF;
END$$

-- ---------------------------------------------------------------------------
-- TRIGGER 3 — normalise email to lower-case (BEFORE INSERT)
-- Keeps the UNIQUE email key consistent regardless of how it was typed.
-- ---------------------------------------------------------------------------
CREATE TRIGGER trg_users_before_insert
BEFORE INSERT ON Users
FOR EACH ROW
BEGIN
    SET NEW.email = LOWER(TRIM(NEW.email));
END$$

-- ---------------------------------------------------------------------------
-- PROCEDURE 1 — apply to a job (transaction-safe workflow)
-- Encapsulates the whole "apply" action: validates the resume belongs to the
-- seeker, then inserts the application inside a transaction. The BEFORE INSERT
-- trigger still enforces the open-job / own-job rules, and the UNIQUE
-- (job_id, seeker_id) constraint blocks duplicates. Any failure rolls back and
-- surfaces a clear message via p_result.
-- ---------------------------------------------------------------------------
CREATE PROCEDURE sp_apply_to_job(
    IN  p_job_id     INT,
    IN  p_seeker_id  INT,
    IN  p_resume_id  INT,
    IN  p_cover      TEXT,
    OUT p_result     VARCHAR(120)
)
BEGIN
    DECLARE v_owner INT;
    -- On any SQL error (including trigger SIGNALs and the UNIQUE constraint),
    -- roll back and report the message instead of aborting.
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_result = 'ERROR: application could not be submitted (already applied, job closed, or invalid data).';
    END;

    START TRANSACTION;

    -- resume must belong to this seeker
    SELECT seeker_id INTO v_owner FROM Resumes WHERE resume_id = p_resume_id;
    IF v_owner IS NULL OR v_owner <> p_seeker_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Resume does not belong to this seeker.';
    END IF;

    INSERT INTO Applications (job_id, seeker_id, resume_id, cover_message, status_id)
    VALUES (p_job_id, p_seeker_id, p_resume_id, p_cover, 1);  -- 1 = pending

    COMMIT;
    SET p_result = 'OK: application submitted.';
END$$

-- ---------------------------------------------------------------------------
-- PROCEDURE 2 — accept an application
-- Sets an application to 'accepted'; the AFTER UPDATE trigger then auto-fills
-- the job if vacancies are met. Demonstrates procedure + trigger working
-- together.
-- ---------------------------------------------------------------------------
CREATE PROCEDURE sp_accept_application(IN p_application_id INT)
BEGIN
    UPDATE Applications SET status_id = 2  -- 2 = accepted
    WHERE application_id = p_application_id;
END$$

-- ---------------------------------------------------------------------------
-- PROCEDURE 3 — category demand analysis (used in Investigation & Analysis)
-- Returns, per category, the number of open jobs and total applicants — a
-- ready-made analytical result set the report can quote.
-- ---------------------------------------------------------------------------
CREATE PROCEDURE sp_category_demand()
BEGIN
    SELECT c.category_name,
           COUNT(DISTINCT j.job_id)                         AS open_jobs,
           COUNT(a.application_id)                          AS total_applications,
           ROUND(COUNT(a.application_id) / NULLIF(COUNT(DISTINCT j.job_id), 0), 2) AS avg_applicants_per_job
    FROM Categories c
    LEFT JOIN Jobs j ON j.category_id = c.category_id AND j.status_id = 1 AND j.deleted_at IS NULL
    LEFT JOIN Applications a ON a.job_id = j.job_id
    GROUP BY c.category_id, c.category_name
    ORDER BY total_applications DESC;
END$$

DELIMITER ;
