# Personal Productivity and Time Tracking System

## 1. Project Overview & Purpose

A centralized, relational-database-driven web application to track daily routines, log time spent on various activities, and monitor daily habits. The primary goal is to demonstrate complex SQL querying (JOINs, aggregates) while delivering a functional, real-world time-management tool based on a "healthy routine/incomplete task" motif.

## 2. Technology Stack

As per the project requirements, this application is built exclusively using:

- **PHP** (Backend logic and database connection)
- **HTML + CSS** (Markup and styling, vanilla only)
- **TypeScript** (Compiled to Vanilla JS for frontend interactivity)
- **MySQL / Docker** (Database and local development environment)

**Note:** No external frameworks or libraries (like React, Tailwind, Bootstrap, jQuery, etc.) are used in this project.

## 3. Database Schema

The database strictly follows the provided E.R. diagram.

- **`users`**
  - `id` (CHAR(36) - UUID equivalent) [PK]
  - `email` (VARCHAR) [NN]
  - `username` (VARCHAR) [NN]
  - `password_hash` (TEXT) [NN]
  - `created_at` (TIMESTAMP) [NN]
  - `updated_at` (TIMESTAMP) [NN]
- **`activities`**
  - `id` (INT AUTO_INCREMENT) [PK]
  - `user_id` (CHAR(36)) [FK -> users.id] [NN]
  - `name` (TEXT) [NN]
  - `created_at` (TIMESTAMP) [NN]
- **`activity_subtypes`**
  - `id` (INT AUTO_INCREMENT) [PK]
  - `activity_id` (INT) [FK -> activities.id] [NN]
  - `name` (TEXT) [NN]
  - `created_at` (TIMESTAMP) [NN]
- **`daily_logs`**
  - `id` (INT AUTO_INCREMENT) [PK]
  - `user_id` (CHAR(36)) [FK -> users.id] [NN]
  - `date` (DATE) [NN]
  - `wake_time` (TIME)
  - `sleep_time` (TIME)
  - `created_at` (TIMESTAMP) [NN]
- **`checklist_items`**
  - `id` (INT AUTO_INCREMENT) [PK]
  - `user_id` (CHAR(36)) [FK -> users.id] [NN]
  - `activity_id` (INT) [FK -> activities.id] [NN]
  - `activity_subtype_id` (INT) [FK -> activity_subtypes.id] [NN]
  - `min_duration_minutes` (INT)
  - `target_duration_minutes` (INT)
  - `created_at` (TIMESTAMP) [NN]
  - `updated_at` (TIMESTAMP) [NN]
- **`daily_completions`**
  - `id` (INT AUTO_INCREMENT) [PK]
  - `user_id` (CHAR(36)) [FK -> users.id] [NN]
  - `daily_log_id` (INT) [FK -> daily_logs.id] [NN]
  - `checklist_item_id` (INT) [FK -> checklist_items.id] [NN]
  - `is_completed` (BOOLEAN) [NN]
  - `updated_at` (TIMESTAMP) [NN]
- **`time_entries`**
  - `id` (INT AUTO_INCREMENT) [PK]
  - `daily_log_id` (INT) [FK -> daily_logs.id] [NN]
  - `activity_id` (INT) [FK -> activities.id]
  - `activity_subtype_id` (INT) [FK -> activity_subtypes.id]
  - `start` (TIMESTAMP) [NN]
  - `end` (TIMESTAMP)
  - `state` (ENUM('running', 'paused', 'completed')) [NN]
  - `notes` (TEXT)
  - `created_at` (TIMESTAMP) [NN]
  - `updated_at` (TIMESTAMP) [NN]

## 4. Core Features

The application is built using a procedural PHP structure.

1.  **Authentication System:** User registration and login to segregate user data via `user_id`.
2.  **Activity Tree Management:** Create and manage `activities` (e.g., "Development", "Health") and their child `activity_subtypes` (e.g., "PHP Project", "Gym").
3.  **Daily Log Initialization:** Create a `daily_logs` entry for the current day to record wake/sleep times and tie all daily data together.
4.  **Habit & Routine Tracking:**
    - Define routines via `checklist_items` with target durations.
    - Check off daily routines via `daily_completions`.
    - Visually highlight incomplete tasks to encourage healthy routines.
5.  **Time Logging Engine:** Start, stop, and log exact timestamp entries into `time_entries` linked to specific activities/subtypes and the current `daily_log_id`.
6.  **Advanced Reporting:**
    - Calculate total time spent per activity/subtype per day/week using SQL `SUM()` and `GROUP BY`.
    - Calculate completion percentages for `checklist_items` using `COUNT()` and `JOIN`s.

## 5. Application Structure

- **`login.php` / `register.php`**: Session management and user creation.
- **`index.php` (Dashboard)**: The main hub. Shows today's `daily_log`, active `time_entries` (where state = 'running'), and a list of today's `checklist_items` (joined with `daily_completions`).
- **`activities.php`**: Manage `activities` and `activity_subtypes` (CRUD operations).
- **`routines.php`**: Manage the configuration of `checklist_items` (setting up the rules, min/target durations).
- **`time_logger.php`**: Interface to manually add past time entries or start/stop the current timer.
- **`reports.php`**: Displays charts or data tables aggregating time spent and habit consistency.

---

## 6. Development Setup

### Quick Start (Docker)

1.  **Install dependencies:**
    ```bash
    npm install
    ```
2.  **Configure Database:**
    Copy the example configuration and set your local credentials.
    ```bash
    cp src/db.example.php src/db.php
    ```
    _(Note: `src/db.php` is gitignored to prevent leaking credentials)_
3.  **Start Containers:**
    ```bash
    docker compose up -d --build
    ```
    The app will be available at **http://localhost:8888**.

### Hot Reload Behavior

- **PHP/HTML/CSS:** Changes are reflected instantly upon browser refresh (mounted as live volumes).
- **TypeScript:** Requires compilation. Run `npm run build` or `npm run watch` to auto-compile on save.

### Backend Tests

Run the backend test harness with:

```bash
npm run test:backend
```

This uses PHPUnit for backend-only tests.

If you are using Docker and do not have PHP installed on your host machine:

Run the backend tests inside Docker with:

```bash
docker compose up -d --build
npm run test:backend:docker
```

This command installs test dependencies with Composer and runs PHPUnit inside the Docker setup.

For this repo, the Docker path is the better default because it uses the same PHP environment as the app.

### XAMPP Setup (Alternative)

If you are not using Docker, you can run the project using XAMPP:

1.  Place the `public/` folder contents into your XAMPP `htdocs/`.
2.  Place the `src/` folder one level **above** `htdocs/` (so the path `../src/db.php` resolves correctly).
3.  Import `database/init.sql` via phpMyAdmin.
4.  Configure `src/db.php` with `host = 'localhost'` and your local XAMPP credentials.
