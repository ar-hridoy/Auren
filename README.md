# Auren — Setup Guide (Phase 1: Project Foundation)

## What's in this phase

- Folder structure (`config/`, `includes/`, `assets/`, and empty role folders
  ready for Phase 2+)
- `config/database.php` — PDO connection to `auren_db`
- `includes/auth.php` — session/role helper functions used by every later page
- `includes/redirect_by_role.php` — sends a logged-in user to the right dashboard
- `includes/header.php`, `navbar.php`, `footer.php` — shared layout for every page
- `includes/sidebar_employer.php`, `sidebar_seeker.php` — dashboard shells for Phase 3/4
- `assets/css/style.css` — Auren brand styling (navy/gold, matches the project docs)
- `index.php` — placeholder landing page proving the shared layout works
  (gets full visual polish in Phase 7, per the agreed build order)

## Local setup (XAMPP)

1. Copy this entire `auren_app` folder into your XAMPP `htdocs` directory,
   **renamed to `auren`** — i.e. it should live at `htdocs/auren/`.
   (All internal links use absolute paths like `/auren/employer/dashboard.php`,
   so the folder name matters — if you use a different name, update every
   `/auren/` path in `includes/navbar.php`, `sidebar_employer.php`,
   `sidebar_seeker.php`, `auth.php`, and `redirect_by_role.php` accordingly.)

2. Start Apache and MySQL from the XAMPP control panel.

3. Import the database, in order:
   ```
   mysql -u root < 01_schema.sql
   mysql -u root < 02_seed_data.sql
   ```
   or via phpMyAdmin: create nothing manually — `01_schema.sql` creates the
   `auren_db` database itself, then run `02_seed_data.sql` against it.

4. Check `config/database.php` matches your local MySQL credentials.
   Defaults assume the standard XAMPP setup (`root`, no password).

5. Visit `http://localhost/auren/index.php` in your browser.

## Notes for the next phases

- Every future page under `/employer/`, `/seeker/`, `/admin/` should start with:
  ```php
  require_once __DIR__ . '/../includes/auth.php';
  requireRole('employer'); // or 'seeker' / 'admin'
  ```
- Every future page should use the shared layout:
  ```php
  $pageTitle = 'Post a Job';
  require_once __DIR__ . '/../includes/header.php';
  // ... page content ...
  require_once __DIR__ . '/../includes/footer.php';
  ```
- Dashboard pages additionally wrap their content in:
  ```php
  <div class="auren-dashboard-wrap">
      <?php $activePage = 'dashboard'; require_once __DIR__ . '/../includes/sidebar_employer.php'; ?>
      <div class="auren-dashboard-content"> ... </div>
  </div>
  ```
- All database access must go through `$pdo` from `config/database.php`,
  using prepared statements — never raw string concatenation into SQL.
