-- ============================================================================
-- AUREN — 03_views.sql
-- DBMS Lab Mini Project | Component C: SQL Implementation (views)
--
-- Additive on top of the frozen 01_schema.sql — does not alter any table.
-- This is the first of the required views; more are added here as later
-- phases need them (e.g. a seeker dashboard stats view in Phase 4).
-- ============================================================================
USE auren_db;

-- ----------------------------------------------------------------------------
-- vw_employer_dashboard_stats
-- One row per employer: total jobs posted, active (open) jobs, closed-or-
-- filled jobs, and total applications received across all their jobs.
-- Powers employer/dashboard.php's four stat cards directly — the page does
-- not re-derive these numbers itself, it just SELECTs from this view.
-- ----------------------------------------------------------------------------
DROP VIEW IF EXISTS vw_employer_dashboard_stats;

CREATE VIEW vw_employer_dashboard_stats AS
SELECT
    e.user_id                                                       AS employer_id,
    COUNT(DISTINCT j.job_id)                                        AS total_jobs,
    COUNT(DISTINCT CASE WHEN js.status_name = 'open'
                        THEN j.job_id END)                          AS active_jobs,
    COUNT(DISTINCT CASE WHEN js.status_name IN ('closed', 'filled')
                        THEN j.job_id END)                          AS closed_or_completed,
    COUNT(DISTINCT a.application_id)                                AS total_applicants
FROM Employers e
LEFT JOIN Jobs j          ON j.employer_id = e.user_id
LEFT JOIN Job_Statuses js ON j.status_id   = js.status_id
LEFT JOIN Applications a  ON a.job_id      = j.job_id
GROUP BY e.user_id;

-- ============================================================================
-- END OF 03_views.sql
-- ============================================================================
