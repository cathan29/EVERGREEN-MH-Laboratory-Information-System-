-- Evergreen LIS schema (with middle_name + suffix)
CREATE DATABASE IF NOT EXISTS evergreen_lis
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE evergreen_lis;

-- Patients
CREATE TABLE IF NOT EXISTS patients (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  first_name  VARCHAR(80)  NOT NULL,
  middle_name VARCHAR(80)  NULL,
  last_name   VARCHAR(80)  NOT NULL,
  suffix      VARCHAR(50)  NULL,
  sex         ENUM('M','F','U') DEFAULT 'U',
  dob         DATE         NULL,
  phone       VARCHAR(40)  NULL,
  email       VARCHAR(120) NULL,
  address     VARCHAR(255) NULL,
  notes       TEXT         NULL,
  mrn         VARCHAR(64)  NULL,
  created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tests (catalog)
CREATE TABLE IF NOT EXISTS tests (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  code       VARCHAR(32)  NOT NULL UNIQUE,
  name       VARCHAR(160) NOT NULL,
  department VARCHAR(80)  NOT NULL,
  price      DECIMAL(10,2) DEFAULT 0,
  tat_hours  INT           DEFAULT 0,
  active     TINYINT(1)    DEFAULT 1,
  loinc_code VARCHAR(32)   NULL
) ENGINE=InnoDB;

-- Orders (header)
CREATE TABLE IF NOT EXISTS orders (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  patient_id      INT NOT NULL,
  ordering_doctor VARCHAR(120) NULL,
  order_date      DATETIME NOT NULL,
  status          VARCHAR(32) DEFAULT 'pending_collection',
  created_at      DATETIME    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_orders_patient (patient_id),
  CONSTRAINT fk_orders_patient FOREIGN KEY (patient_id)
    REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Order items (each test in an order)
CREATE TABLE IF NOT EXISTS order_items (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  order_id         INT NOT NULL,
  test_id          INT NOT NULL,
  result_value     TEXT        NULL,
  result_unit      VARCHAR(32) NULL,
  reference_range  VARCHAR(128) NULL,
  status           VARCHAR(32)  DEFAULT 'pending_collection',  -- align with app
  abnormal_flag    TINYINT(1)   DEFAULT 0,
  updated_at       DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  completed_at     DATETIME     NULL,
  INDEX idx_oi_status_updated (status, updated_at),
  INDEX idx_oi_orderid (order_id),
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_test  FOREIGN KEY (test_id)  REFERENCES tests(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- Sample tests
INSERT INTO tests(code,name,department,price,tat_hours,active) VALUES
('GLUCF','Glucose (Fasting)','Chemistry',150.00,2,1),
('CREAT','Creatinine','Chemistry',200.00,2,1),
('CBC','Complete Blood Count','Hematology',300.00,4,1)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- UA panel and components (optional)
INSERT INTO tests (code,name,department,price,tat_hours,active) VALUES
('URINA','Urinalysis (Routine)','Microscopy',200,6,1)
ON DUPLICATE KEY UPDATE name=VALUES(name), department=VALUES(department),
                        price=VALUES(price), tat_hours=VALUES(tat_hours), active=VALUES(active);

INSERT INTO tests (code,name,department,price,tat_hours,active) VALUES
('UA-COLOR','Urinalysis — Color','Microscopy',0,0,1),
('UA-APPR','Urinalysis — Clarity/Appearance','Microscopy',0,0,1),
('UA-SG',   'Urinalysis — Specific Gravity','Chemistry',0,0,1),
('UA-pH',   'Urinalysis — pH','Chemistry',0,0,1),
('UA-GLU',  'Urinalysis — Glucose (strip)','Chemistry',0,0,1),
('UA-KET',  'Urinalysis — Ketones (strip)','Chemistry',0,0,1),
('UA-PRO',  'Urinalysis — Protein (strip)','Chemistry',0,0,1),
('UA-BLD',  'Urinalysis — Blood (strip)','Chemistry',0,0,1),
('UA-BIL',  'Urinalysis — Bilirubin','Chemistry',0,0,1),
('UA-UBG',  'Urinalysis — Urobilinogen','Chemistry',0,0,1),
('UA-NIT',  'Urinalysis — Nitrite','Chemistry',0,0,1),
('UA-LEU',  'Urinalysis — Leukocyte Esterase','Chemistry',0,0,1),
('UA-RBC',  'Urinalysis — RBC /HPF','Microscopy',0,0,1),
('UA-WBC',  'Urinalysis — WBC /HPF','Microscopy',0,0,1),
('UA-EPI',  'Urinalysis — Epithelial Cells /LPF','Microscopy',0,0,1),
('UA-BACT', 'Urinalysis — Bacteria (semi-quant)','Microscopy',0,0,1),
('UA-CAST', 'Urinalysis — Casts /LPF','Microscopy',0,0,1)
ON DUPLICATE KEY UPDATE name=VALUES(name);

CREATE INDEX IF NOT EXISTS idx_patients_name ON patients(last_name, first_name, middle_name, suffix);
CREATE INDEX IF NOT EXISTS idx_patients_mrn  ON patients(mrn);
CREATE INDEX IF NOT EXISTS idx_orders_date   ON orders(order_date);
CREATE INDEX IF NOT EXISTS idx_t_department  ON tests(department);


