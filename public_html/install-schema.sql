-- ============================================================
-- JSD Construction Pty Ltd — MySQL schema
-- Import via cPanel > phpMyAdmin > (your database) > Import
-- Compatible with MySQL 5.7+ / MariaDB 10.3+ (GoDaddy defaults)
-- ============================================================

SET NAMES utf8mb4;

-- Customer enquiries from enquiry.html (Enquiry tab)
CREATE TABLE IF NOT EXISTS enquiries (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name            VARCHAR(120)  NOT NULL,
  mobile          VARCHAR(30)   NOT NULL,
  email           VARCHAR(190)  NOT NULL,
  purpose         VARCHAR(120)  NOT NULL,
  area            VARCHAR(190)  NOT NULL,          -- suburb / project area
  contact_number  VARCHAR(30)   NULL,              -- optional alternate number
  message         TEXT          NOT NULL,
  status          ENUM('new','contacted','quoted','won','closed') NOT NULL DEFAULT 'new',
  ip_address      VARCHAR(45)   NULL,
  created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status (status),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contact-page messages (routed to a JSD mailbox alias)
CREATE TABLE IF NOT EXISTS contact_messages (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(120)  NOT NULL,
  email       VARCHAR(190)  NOT NULL,
  mobile      VARCHAR(30)   NOT NULL,
  topic       VARCHAR(40)   NOT NULL DEFAULT 'general',
  message     TEXT          NOT NULL,
  routed_to   VARCHAR(190)  NOT NULL,              -- mailbox the message was forwarded to
  status      ENUM('new','replied','closed') NOT NULL DEFAULT 'new',
  ip_address  VARCHAR(45)   NULL,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cost-estimator leads
CREATE TABLE IF NOT EXISTS estimates (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name            VARCHAR(120)  NOT NULL,
  email           VARCHAR(190)  NOT NULL,
  mobile          VARCHAR(30)   NOT NULL,
  build_type      VARCHAR(40)   NOT NULL,          -- highset | lowset | split | commercial
  spec_level      VARCHAR(40)   NOT NULL,          -- standard | premium | luxury
  floor_area      DECIMAL(8,1)  NOT NULL,
  slope           VARCHAR(20)   NOT NULL DEFAULT 'flat',
  estimate_range  VARCHAR(80)   NULL,              -- range shown to the customer
  status          ENUM('new','quoted','won','closed') NOT NULL DEFAULT 'new',
  ip_address      VARCHAR(45)   NULL,
  created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Projects shown on projects.html (managed in admin panel)
CREATE TABLE IF NOT EXISTS projects (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title         VARCHAR(190)  NOT NULL,
  category      ENUM('highset','lowset','split','commercial') NOT NULL,
  location      VARCHAR(190)  NULL,
  description   TEXT          NULL,
  cover_image   VARCHAR(255)  NULL,                -- fallback if no gallery images
  is_published  TINYINT(1)    NOT NULL DEFAULT 1,
  sort_order    INT           NOT NULL DEFAULT 0,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pub (is_published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Uploaded photos, cropped server-side to 3:2 (1500x1000)
CREATE TABLE IF NOT EXISTS project_images (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id  INT UNSIGNED NOT NULL,
  file_path   VARCHAR(255)  NOT NULL,              -- e.g. uploads/projects/proj-000012-01.jpg
  sort_order  INT           NOT NULL DEFAULT 0,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_project (project_id),
  CONSTRAINT fk_pi_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Client testimonials (managed in admin panel, shown on home page)
CREATE TABLE IF NOT EXISTS testimonials (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(120)  NOT NULL,
  project       VARCHAR(190)  NULL,                -- e.g. "High-Set Build, Rochedale"
  rating        TINYINT       NOT NULL DEFAULT 5,
  quote         TEXT          NOT NULL,
  is_published  TINYINT(1)    NOT NULL DEFAULT 1,
  sort_order    INT           NOT NULL DEFAULT 0,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed data so the site has content on first deploy -------------

INSERT INTO projects (title, category, location, description, cover_image, sort_order) VALUES
('Rochedale High-Set Residence', 'highset',    'Rochedale, QLD',        'Elevated family home maximising airflow and under-house living on a sloping block.', 'assets/img/projects/proj-highset-01-01.svg', 1),
('Springwood Low-Set Home',      'lowset',     'Springwood, QLD',       'Open-plan single-level build with strong street presence on a flat allotment.',      'assets/img/projects/proj-lowset-02-01.svg', 2),
('Eight Mile Plains Split-Level','split',      'Eight Mile Plains, QLD','Split-level design following the natural terrain with tiered outdoor living.',       'assets/img/projects/proj-split-03-01.svg', 3),
('Underwood Retail Development', 'commercial', 'Underwood, QLD',        'Low-rise retail and mixed-use space delivered end-to-end under QLD low-rise licence.','assets/img/projects/proj-commercial-04-01.svg', 4),
('Calamvale High-Set Build',     'highset',    'Calamvale, QLD',        'Contemporary high-set with premium timber framing and energy-efficient design.',     'assets/img/projects/proj-highset-01-02.svg', 5),
('Ipswich Commercial Fit-Out',   'commercial', 'Ipswich, QLD',          'Low-rise commercial project with full in-house project management.',                 'assets/img/projects/proj-commercial-04-02.svg', 6);

INSERT INTO testimonials (name, project, rating, quote, sort_order) VALUES
('Priya & Daniel M.', 'High-Set Build, Rochedale',    5, 'Jagdeep oversaw every stage himself. The timber detailing and finish are beyond what we expected — and we moved in on schedule.', 1),
('Robert K.',         'Commercial Fit-Out, Underwood',5, 'End-to-end management meant one point of contact from council approvals to handover. Professional from day one.', 2),
('Sandeep & Aman G.', 'Split-Level Home, Calamvale',  5, 'Our sloping block scared off other builders. JSD turned it into the best feature of the house.', 3);
