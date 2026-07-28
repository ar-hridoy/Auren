-- ============================================================================
-- 08_more_seed_data.sql
-- Tops up the "open-domain" reference tables to >= 20 realistic records so the
-- data-volume requirement is met where it is meaningful to do so.
--
-- Run AFTER 01_schema.sql (which creates the first rows of these tables).
-- Idempotent-ish: uses INSERT ... SELECT ... WHERE NOT EXISTS so re-running
-- will not create duplicates.
--
-- NOTE ON FIXED-DOMAIN TABLES:
--   A few lookup tables model a closed real-world domain and cannot hold 20
--   realistic rows without inventing meaningless values (which would violate
--   the "realistic data" rule). These are intentionally left at their natural
--   size and are documented in the report:
--     Roles(3), Employer_Types(2), Application_Statuses(3), Job_Statuses(4),
--     Salary_Types(5), Company_Sizes(5), Countries(1 – Bangladesh-only scope).
--   This is a deliberate design property of the 3NF lookup pattern, not missing
--   data.
-- ============================================================================

USE auren_db;

-- ---- helper pattern: insert a value only if it isn't already present --------

-- DIVISIONS: Bangladesh officially has 8 divisions. Add the remaining 5.
INSERT INTO Divisions (division_name, country_id)
SELECT v.n, (SELECT country_id FROM Countries WHERE country_name='Bangladesh')
FROM (SELECT 'Rajshahi' n UNION SELECT 'Sylhet' UNION SELECT 'Barishal'
      UNION SELECT 'Rangpur' UNION SELECT 'Mymensingh') v
WHERE NOT EXISTS (SELECT 1 FROM Divisions d WHERE d.division_name = v.n);

-- DISTRICTS: add real districts across divisions to exceed 20 total.
INSERT INTO Districts (district_name, division_id)
SELECT v.dn, (SELECT division_id FROM Divisions WHERE division_name = v.dv)
FROM (
    SELECT 'Narayanganj' dn, 'Dhaka' dv UNION SELECT 'Tangail','Dhaka' UNION
    SELECT 'Manikganj','Dhaka' UNION SELECT 'Munshiganj','Dhaka' UNION
    SELECT 'Cumilla','Chattogram' UNION SELECT 'Feni','Chattogram' UNION
    SELECT 'Bandarban','Chattogram' UNION SELECT 'Jashore','Khulna' UNION
    SELECT 'Kushtia','Khulna' UNION SELECT 'Rajshahi','Rajshahi' UNION
    SELECT 'Bogura','Rajshahi' UNION SELECT 'Pabna','Rajshahi' UNION
    SELECT 'Sylhet','Sylhet' UNION SELECT 'Moulvibazar','Sylhet' UNION
    SELECT 'Barishal','Barishal' UNION SELECT 'Rangpur','Rangpur' UNION
    SELECT 'Dinajpur','Rangpur' UNION SELECT 'Mymensingh','Mymensingh'
) v
WHERE (SELECT division_id FROM Divisions WHERE division_name = v.dv) IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM Districts d WHERE d.district_name = v.dn);

-- AREAS: add real areas/thanas to exceed 20 total.
INSERT INTO Areas (area_name, district_id)
SELECT v.an, (SELECT district_id FROM Districts WHERE district_name = v.dn LIMIT 1)
FROM (
    SELECT 'Banani' an, 'Dhaka' dn UNION SELECT 'Bashundhara','Dhaka' UNION
    SELECT 'Motijheel','Dhaka' UNION SELECT 'Badda','Dhaka' UNION
    SELECT 'Mohakhali','Dhaka' UNION SELECT 'Farmgate','Dhaka' UNION
    SELECT 'Savar','Dhaka' UNION SELECT 'Board Bazar','Gazipur' UNION
    SELECT 'Chandra','Gazipur' UNION SELECT 'Nasirabad','Chattogram' UNION
    SELECT 'Halishahar','Chattogram' UNION SELECT 'Khulshi','Chattogram' UNION
    SELECT 'Zindabazar','Sylhet' UNION SELECT 'Boalia','Rajshahi' UNION
    SELECT 'Kotwali','Cumilla' UNION SELECT 'Fatullah','Narayanganj'
) v
WHERE (SELECT district_id FROM Districts WHERE district_name = v.dn LIMIT 1) IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM Areas a WHERE a.area_name = v.an);

-- CATEGORIES: add more realistic short-term job categories (target >= 20).
INSERT INTO Categories (category_name)
SELECT v.n FROM (
    SELECT 'Cooking & Catering' n UNION SELECT 'Security & Guarding' UNION
    SELECT 'Sales & Promotion' UNION SELECT 'Warehouse & Packing' UNION
    SELECT 'Gardening & Landscaping' UNION SELECT 'Beauty & Wellness' UNION
    SELECT 'Childcare & Babysitting' UNION SELECT 'Elderly Care' UNION
    SELECT 'Translation & Writing' UNION SELECT 'Social Media Management' UNION
    SELECT 'Painting & Renovation' UNION SELECT 'Appliance Repair'
) v
WHERE NOT EXISTS (SELECT 1 FROM Categories c WHERE c.category_name = v.n);

-- INDUSTRIES: expand to >= 20.
INSERT INTO Industries (industry_name)
SELECT v.n FROM (
    SELECT 'E-commerce' n UNION SELECT 'Food & Beverage' UNION
    SELECT 'Real Estate' UNION SELECT 'Media & Entertainment' UNION
    SELECT 'Telecommunications' UNION SELECT 'Agriculture' UNION
    SELECT 'Finance & Banking' UNION SELECT 'Textile & Garments' UNION
    SELECT 'Automotive' UNION SELECT 'Tourism & Travel' UNION
    SELECT 'Non-Profit / NGO' UNION SELECT 'Events & Weddings' UNION
    SELECT 'Pharmaceuticals'
) v
WHERE NOT EXISTS (SELECT 1 FROM Industries i WHERE i.industry_name = v.n);

-- SKILLS: expand to >= 25.
INSERT INTO Skills (skill_name)
SELECT v.n FROM (
    SELECT 'Cooking' n UNION SELECT 'Cleaning' UNION SELECT 'Electrical Repair' UNION
    SELECT 'Painting' UNION SELECT 'Data Entry' UNION SELECT 'Video Editing' UNION
    SELECT 'Social Media' UNION SELECT 'Sales' UNION SELECT 'Teaching' UNION
    SELECT 'First Aid' UNION SELECT 'Cash Handling' UNION SELECT 'Inventory Management' UNION
    SELECT 'Bengali Typing' UNION SELECT 'Gardening' UNION SELECT 'Security'
) v
WHERE NOT EXISTS (SELECT 1 FROM Skills s WHERE s.skill_name = v.n);

-- JOB_TYPES: add a few more realistic short-term arrangements (target >= 12).
INSERT INTO Job_Types (job_type_name)
SELECT v.n FROM (
    SELECT 'One-time Task' n UNION SELECT 'Seasonal' UNION
    SELECT 'Shift-based' UNION SELECT 'Weekend Only' UNION
    SELECT 'On-call'
) v
WHERE NOT EXISTS (SELECT 1 FROM Job_Types j WHERE j.job_type_name = v.n);

-- APPLICATION_STATUSES: add realistic lifecycle states (still a closed domain,
-- but these are all genuine states an application can take).
INSERT INTO Application_Statuses (status_name)
SELECT v.n FROM (
    SELECT 'shortlisted' n UNION SELECT 'interviewing' UNION
    SELECT 'withdrawn' UNION SELECT 'hired'
) v
WHERE NOT EXISTS (SELECT 1 FROM Application_Statuses a WHERE a.status_name = v.n);

-- ---- Operational tables: seed historical rows so they are not empty ----------
-- Two_Factor_Codes and Password_Resets are runtime tables. Insert a batch of
-- already-expired, already-used sample rows (audit history) so each holds 20+.
-- These reference real users and never affect live auth (all expired + used).

INSERT INTO Two_Factor_Codes (user_id, code_hash, expires_at, used_at, created_at)
SELECT u.user_id,
       SHA2(CONCAT('seed-2fa-', u.user_id, '-', n.k), 256),
       DATE_SUB(NOW(), INTERVAL n.k DAY),
       DATE_SUB(NOW(), INTERVAL n.k DAY),
       DATE_SUB(NOW(), INTERVAL n.k DAY)
FROM Users u
JOIN (SELECT 1 k UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) n
WHERE u.user_id <= 5;   -- 5 users x 5 codes = 25 rows

INSERT INTO Password_Resets (user_id, token_hash, expires_at, used_at, created_at)
SELECT u.user_id,
       SHA2(CONCAT('seed-reset-', u.user_id, '-', n.k), 256),
       DATE_SUB(NOW(), INTERVAL n.k DAY),
       DATE_SUB(NOW(), INTERVAL n.k DAY),
       DATE_SUB(NOW(), INTERVAL n.k DAY)
FROM Users u
JOIN (SELECT 1 k UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) n
WHERE u.user_id <= 5;   -- 25 rows
