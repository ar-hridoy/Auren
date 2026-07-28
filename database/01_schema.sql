-- ============================================================================
-- AUREN — Temporary Job Marketplace
-- 01_schema.sql
--
-- DBMS Lab Mini Project | Component B: System Design & Schema
-- This script provides: ER-derived CREATE TABLE scripts, normalized to 3NF,
-- with PRIMARY KEY / FOREIGN KEY / CHECK / UNIQUE constraints.
--
-- Business Rule references (Rn) point to Auren_Business_Rules.docx.
-- EP references point to the DBMS Lab Guideline's Complex Engineering Problem
-- attributes (EP1: fundamentals-based design, EP2: conflicting requirements,
-- EP4: infrequently encountered issues — see closing summary at bottom).
-- ============================================================================

DROP DATABASE IF EXISTS auren_db;
CREATE DATABASE auren_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE auren_db;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- SECTION 1 — LOOKUP TABLES
-- Replaces what would otherwise be ENUM columns (role, employer_type,
-- job status, application status, salary type). Each is its own table so the
-- system can grow (e.g. adding a new job status) without an ALTER TABLE /
-- schema migration, and so no single column tries to mean two different
-- things depending on context.
-- ============================================================================

CREATE TABLE Roles (
    role_id     INT AUTO_INCREMENT PRIMARY KEY,
    role_name   VARCHAR(30) NOT NULL UNIQUE
);

CREATE TABLE Employer_Types (
    employer_type_id INT AUTO_INCREMENT PRIMARY KEY,
    type_name         VARCHAR(30) NOT NULL UNIQUE
);

CREATE TABLE Job_Statuses (
    status_id   INT AUTO_INCREMENT PRIMARY KEY,
    status_name VARCHAR(30) NOT NULL UNIQUE
);

CREATE TABLE Application_Statuses (
    status_id   INT AUTO_INCREMENT PRIMARY KEY,
    status_name VARCHAR(30) NOT NULL UNIQUE
);

CREATE TABLE Salary_Types (
    salary_type_id INT AUTO_INCREMENT PRIMARY KEY,
    type_name      VARCHAR(30) NOT NULL UNIQUE
);

-- Distinct from Salary_Types: Salary_Types answers "how is payment calculated"
-- (per hour, per day, fixed...); Job_Types answers "what kind of engagement is
-- this" (a one-day gig vs. a fixed-term contract vs. an internship). A job's
-- pay could be, e.g., "Monthly" salary on a "Contract" job type — two
-- independent facts, so they get two independent lookup tables rather than
-- one column trying to answer both questions.
CREATE TABLE Job_Types (
    job_type_id   INT AUTO_INCREMENT PRIMARY KEY,
    job_type_name VARCHAR(30) NOT NULL UNIQUE
);

CREATE TABLE Categories (
    category_id   INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE Industries (
    industry_id   INT AUTO_INCREMENT PRIMARY KEY,
    industry_name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE Company_Sizes (
    size_id    INT AUTO_INCREMENT PRIMARY KEY,
    size_label VARCHAR(30) NOT NULL UNIQUE
);

CREATE TABLE Skills (
    skill_id   INT AUTO_INCREMENT PRIMARY KEY,
    skill_name VARCHAR(80) NOT NULL UNIQUE
);

-- ============================================================================
-- SECTION 2 — GEOGRAPHIC HIERARCHY
-- Normalized as Country -> Division -> District -> Area instead of one flat
-- table with repeated text. A flat (country, division, district, area) row on
-- every job would make "division_name" transitively dependent on "district"
-- rather than on the job itself — exactly the kind of dependency 3NF forbids.
-- ============================================================================

CREATE TABLE Countries (
    country_id   INT AUTO_INCREMENT PRIMARY KEY,
    country_name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE Divisions (
    division_id   INT AUTO_INCREMENT PRIMARY KEY,
    division_name VARCHAR(100) NOT NULL,
    country_id    INT NOT NULL,
    CONSTRAINT fk_division_country FOREIGN KEY (country_id)
        REFERENCES Countries(country_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT uq_division UNIQUE (division_name, country_id)
);

CREATE TABLE Districts (
    district_id   INT AUTO_INCREMENT PRIMARY KEY,
    district_name VARCHAR(100) NOT NULL,
    division_id   INT NOT NULL,
    CONSTRAINT fk_district_division FOREIGN KEY (division_id)
        REFERENCES Divisions(division_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT uq_district UNIQUE (district_name, division_id)
);

CREATE TABLE Areas (
    area_id     INT AUTO_INCREMENT PRIMARY KEY,
    area_name   VARCHAR(100) NOT NULL,
    district_id INT NOT NULL,
    CONSTRAINT fk_area_district FOREIGN KEY (district_id)
        REFERENCES Districts(district_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT uq_area UNIQUE (area_name, district_id)
);

-- ============================================================================
-- SECTION 3 — USERS (supertype) + EMPLOYERS / SEEKERS (subtypes)
-- R1: a user has exactly one role. Rather than a nullable employer_type
-- column on Users (meaningless for seekers/admins), role-specific attributes
-- live in their own subtype table, keyed 1:1 back to Users. A row simply
-- does not exist in Employers unless that user is an employer.
-- ============================================================================

CREATE TABLE Users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id       INT NOT NULL,
    phone         VARCHAR(20),
    is_verified   BOOLEAN NOT NULL DEFAULT FALSE,   -- present, unenforced in MVP (per finalized decision)
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    TIMESTAMP NULL DEFAULT NULL,       -- reserved for future soft-delete
    CONSTRAINT fk_user_role FOREIGN KEY (role_id)
        REFERENCES Roles(role_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    -- NOTE: this LIKE pattern only catches the grossest malformed emails
    -- (missing '@', missing a domain dot) — it is not real email validation.
    -- Kept here to demonstrate a CHECK constraint at the schema level, but
    -- proper validation (format, deliverability) belongs in the PHP layer.
    -- Also note: CHECK constraints are parsed-but-ignored on MySQL < 8.0.16
    -- and MariaDB < 10.2 — this schema assumes MySQL 8.0+/MariaDB 10.2+,
    -- and the application layer re-validates every constraint below as a
    -- backstop in case an older engine is used for grading/demo.
    CONSTRAINT chk_user_email CHECK (email LIKE '%_@__%.__%')
);

-- R2/R7: only employers can create jobs. Existence of a row here IS the
-- employer designation — no boolean flag needed on Users.
CREATE TABLE Employers (
    user_id           INT PRIMARY KEY,
    employer_type_id  INT NOT NULL,
    CONSTRAINT fk_employer_user FOREIGN KEY (user_id)
        REFERENCES Users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_employer_type FOREIGN KEY (employer_type_id)
        REFERENCES Employer_Types(employer_type_id) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- R3/R4: only seekers can apply, and only authenticated seekers (no guests).
-- Existence of a row here IS the seeker designation.
CREATE TABLE Seekers (
    user_id INT PRIMARY KEY,
    CONSTRAINT fk_seeker_user FOREIGN KEY (user_id)
        REFERENCES Users(user_id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- ============================================================================
-- SECTION 4 — COMPANIES
-- 1 : 0..1 with Employers (confirmed: one employer manages exactly one
-- company profile for the MVP) — enforced via UNIQUE on employer_id.
-- ============================================================================

CREATE TABLE Companies (
    company_id   INT AUTO_INCREMENT PRIMARY KEY,
    employer_id  INT NOT NULL UNIQUE,
    company_name VARCHAR(150) NOT NULL,
    description  TEXT,
    website      VARCHAR(200),
    address      VARCHAR(255),
    company_logo VARCHAR(255),
    industry_id  INT,
    size_id      INT,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_company_employer FOREIGN KEY (employer_id)
        REFERENCES Employers(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_company_industry FOREIGN KEY (industry_id)
        REFERENCES Industries(industry_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_company_size FOREIGN KEY (size_id)
        REFERENCES Company_Sizes(size_id) ON DELETE SET NULL ON UPDATE CASCADE
);

-- ============================================================================
-- SECTION 5 — JOBS
-- R5/R6: one employer -> many jobs; one job -> exactly one employer.
-- R11: job status lifecycle (Open/Closed/Filled/Expired) via Job_Statuses FK.
-- ============================================================================

CREATE TABLE Jobs (
    job_id               INT AUTO_INCREMENT PRIMARY KEY,
    employer_id          INT NOT NULL,
    company_id           INT,                        -- NULL when employer_type = individual
    category_id          INT NOT NULL,
    area_id              INT NOT NULL,
    salary_type_id       INT NOT NULL,
    job_type_id           INT NOT NULL,
    status_id            INT NOT NULL,
    title                VARCHAR(150) NOT NULL,
    description          TEXT NOT NULL,
    requirements         TEXT,                        -- separate from description
    pay_rate             DECIMAL(10,2) NOT NULL,
    vacancies            INT NOT NULL DEFAULT 1,
    experience_required  VARCHAR(150),
    application_deadline DATE,
    start_date           DATE,
    end_date             DATE,
    job_views            INT NOT NULL DEFAULT 0,
    is_featured          BOOLEAN NOT NULL DEFAULT FALSE,
    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at           TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_job_employer FOREIGN KEY (employer_id)
        REFERENCES Employers(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_job_company FOREIGN KEY (company_id)
        REFERENCES Companies(company_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_job_category FOREIGN KEY (category_id)
        REFERENCES Categories(category_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_job_area FOREIGN KEY (area_id)
        REFERENCES Areas(area_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_job_salary_type FOREIGN KEY (salary_type_id)
        REFERENCES Salary_Types(salary_type_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_job_job_type FOREIGN KEY (job_type_id)
        REFERENCES Job_Types(job_type_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_job_status FOREIGN KEY (status_id)
        REFERENCES Job_Statuses(status_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_job_pay_rate CHECK (pay_rate > 0),
    CONSTRAINT chk_job_vacancies CHECK (vacancies > 0),
    CONSTRAINT chk_job_dates CHECK (end_date IS NULL OR start_date IS NULL OR end_date >= start_date)
);

-- ============================================================================
-- SECTION 6 — RESUMES + SKILLS (M:N)
-- One seeker profile can hold one working resume; a resume has many skills,
-- and a skill can appear on many resumes -> classic M:N, resolved with a
-- composite-key junction table (Improvement 5).
-- ============================================================================

-- MVP decision: one active resume per seeker, not versioned resume history.
-- Enforced structurally via UNIQUE(seeker_id) rather than left to app logic.
CREATE TABLE Resumes (
    resume_id    INT AUTO_INCREMENT PRIMARY KEY,
    seeker_id    INT NOT NULL UNIQUE,
    headline     VARCHAR(150),
    summary      TEXT,
    education    TEXT,
    experience   TEXT,
    resume_path  VARCHAR(255),
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_resume_seeker FOREIGN KEY (seeker_id)
        REFERENCES Seekers(user_id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE Resume_Skills (
    resume_id INT NOT NULL,
    skill_id  INT NOT NULL,
    PRIMARY KEY (resume_id, skill_id),
    CONSTRAINT fk_rs_resume FOREIGN KEY (resume_id)
        REFERENCES Resumes(resume_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rs_skill FOREIGN KEY (skill_id)
        REFERENCES Skills(skill_id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- ============================================================================
-- SECTION 7 — APPLICATIONS
-- R8/R9: seeker -> many applications; job -> many applications.
-- R10: a seeker cannot apply twice to the same job -> UNIQUE(job_id, seeker_id).
-- R12: application status lifecycle (Pending/Accepted/Rejected).
-- ============================================================================

CREATE TABLE Applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    job_id         INT NOT NULL,
    seeker_id      INT NOT NULL,
    resume_id      INT NOT NULL,
    cover_message  TEXT,
    status_id      INT NOT NULL,
    applied_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_app_job FOREIGN KEY (job_id)
        REFERENCES Jobs(job_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_app_seeker FOREIGN KEY (seeker_id)
        REFERENCES Seekers(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_app_resume FOREIGN KEY (resume_id)
        REFERENCES Resumes(resume_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_app_status FOREIGN KEY (status_id)
        REFERENCES Application_Statuses(status_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT uq_application_once UNIQUE (job_id, seeker_id)   -- R10
);

-- ============================================================================
-- SECTION 8 — SAVED JOBS (M:N junction, composite PK — Improvement 8)
-- The (seeker_id, job_id) pair is itself the identity of a "saved job" row;
-- a surrogate key would only duplicate what the composite key already gives.
-- ============================================================================

CREATE TABLE Saved_Jobs (
    seeker_id INT NOT NULL,
    job_id    INT NOT NULL,
    saved_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (seeker_id, job_id),
    CONSTRAINT fk_saved_seeker FOREIGN KEY (seeker_id)
        REFERENCES Seekers(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_saved_job FOREIGN KEY (job_id)
        REFERENCES Jobs(job_id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- ============================================================================
-- SECTION 9 — INDEXES
-- Support the search/filter requirements (category, area, salary range,
-- status) called out in Requirements Specification 4.7, beyond what the
-- FK/UNIQUE constraints above already index automatically.
-- ============================================================================

CREATE INDEX idx_jobs_status      ON Jobs(status_id);
CREATE INDEX idx_jobs_category    ON Jobs(category_id);
CREATE INDEX idx_jobs_area        ON Jobs(area_id);
CREATE INDEX idx_jobs_pay_rate    ON Jobs(pay_rate);
CREATE INDEX idx_jobs_deadline    ON Jobs(application_deadline);
CREATE INDEX idx_jobs_job_type    ON Jobs(job_type_id);
CREATE INDEX idx_jobs_featured    ON Jobs(is_featured);
CREATE INDEX idx_applications_status ON Applications(status_id);

-- ============================================================================
-- SECTION 10 — SEED REFERENCE DATA (lookup / hierarchy tables only)
-- Transactional data (Users, Jobs, Applications, >=20 rows/table) is
-- populated separately in 02_seed_data.sql per Component C of the guideline.
-- ============================================================================

INSERT INTO Roles (role_name) VALUES ('employer'), ('seeker'), ('admin');

INSERT INTO Employer_Types (type_name) VALUES ('individual'), ('company');

INSERT INTO Job_Statuses (status_name) VALUES ('open'), ('closed'), ('filled'), ('expired');

INSERT INTO Application_Statuses (status_name) VALUES ('pending'), ('accepted'), ('rejected');

INSERT INTO Salary_Types (type_name) VALUES ('hourly'), ('daily'), ('weekly'), ('monthly'), ('fixed');

INSERT INTO Job_Types (job_type_name) VALUES
    ('Hourly Gig'), ('Daily Gig'), ('Weekly Task'), ('Monthly Engagement'),
    ('Contract'), ('Internship'), ('Project');

INSERT INTO Categories (category_name) VALUES
    ('Home Repair & Maintenance'), ('Tutoring & Teaching'), ('Event Staffing'),
    ('Delivery & Errands'), ('IT & Tech Support'), ('Cleaning Services'),
    ('Moving & Labor'), ('Photography & Media'), ('Data Entry'), ('Graphic Design');

INSERT INTO Industries (industry_name) VALUES
    ('Retail'), ('Hospitality'), ('Construction'), ('Information Technology'),
    ('Education'), ('Healthcare'), ('Logistics'), ('Manufacturing');

INSERT INTO Company_Sizes (size_label) VALUES
    ('1-10'), ('11-50'), ('51-200'), ('201-500'), ('500+');

INSERT INTO Skills (skill_name) VALUES
    ('Communication'), ('Microsoft Excel'), ('Customer Service'), ('Carpentry'),
    ('Plumbing'), ('Graphic Design'), ('Photography'), ('Web Development'),
    ('Content Writing'), ('Event Planning'), ('Driving License'), ('English Fluency');

INSERT INTO Countries (country_name) VALUES ('Bangladesh');

INSERT INTO Divisions (division_name, country_id) VALUES
    ('Dhaka', 1), ('Chattogram', 1), ('Khulna', 1);

INSERT INTO Districts (district_name, division_id) VALUES
    ('Dhaka', 1), ('Gazipur', 1),
    ('Chattogram', 2), ('Cox''s Bazar', 2),
    ('Khulna', 3);

INSERT INTO Areas (area_name, district_id) VALUES
    ('Dhanmondi', 1), ('Gulshan', 1), ('Mirpur', 1), ('Uttara', 1), ('Mohammadpur', 1),
    ('Tongi', 2), ('Konabari', 2),
    ('Agrabad', 3), ('Panchlaish', 3),
    ('Cox''s Bazar Sadar', 4),
    ('Khulna Sadar', 5);

-- ============================================================================
-- END OF 01_schema.sql
-- ============================================================================
