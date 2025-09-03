CREATE DATABASE IF NOT EXISTS evergreen_lis CHARACTER SET utf8mb4 COLLATE utf8mb4_inicode_ci;
USE evergreen_lis;

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS tests;
DROP TABLE IF EXISTS patients;

--PATIENT--
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    dob DATE NULL,
    sex ENUM('M', 'F') NOT NULL DEFAULT 'M',
    phone VARCHAR(30),
    email VARCHAR(200),
    address VARCHAR(300),
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)ENGINE=InnoDB;

--TEST CATALOG--
CREATE TABLE IF NOT EXISTS test(
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    department VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    tat_hours INT NOT NULL DEFAULT 0,
    active TINYINT NOT NULL DEFAULT 1
)ENGINE=InnoDB;

--ORDERS--
CREATE TABLE orders(
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    order_date DATETIME NOT NULL,
    status ENUM('pending_collection', 'in_processing', 'completed', 'cancelled') NOT NULL DEFAULT 'pending_collection',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patient(id)
)ENGINE=InnoDB;

--ORDER ITEMS--
CREATE TABLE order_items(
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    test_id INT NOT NULL,
    status ENUM('pending_collection', 'in_processing', 'completed', 'cancelled') NOT NULL DEFAULT 'pending_collection',
    result_value VARCHAR(269) NULL,
    result_unit VARCHAR(50) NULL,
    reference_range VARCHAR(100) NULL,
    abnormal_flag TINYINT NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (test_id) REFERENCES test(id),
    INDEX(order_id), INDEX(test_id), INDEX(status), INDEX(updated_at)
)ENGINE=InnoDB;

--SEED TEST--
INSERT INTO test(code,name, department, price,tat_hours) VALUES
('CBC','Complete Blood Count','Hematology',350.00,24),
('URINALYSIS','Urinalysis','Urinalysis',240.00,12),
('GLUCF','Glucose (Fasting)', 'Chemistry',585.00,6);


--SEED PATIENTS--
INSERT INTO patients(first_name, last_name,dob,sex,phone,email,address) VALUES
('Sergs Rafael', 'Oriel', '2005-4-29','M','09079512428','oriel.sergsrafael@gmail.com','San Andres Balungao ', '')
('Dianne', 'Ramirez', '2004-06-17','F','09069512428','oriel.dianne@gmail.com','Malimpec Malasique ', '');


--SEED EXAMPLE ORDERS/ITEMS/RESULTS--
INSERT INTO orders(patient_id, order_date, status) VALUES
(2, NOW() , 'completed'),
(1, NOW() , 'completed');

INSERT INTO order_items(order_id, test_id, status, result_value, result_unit, reference_range, abnormal_flag, updated_at, completed_at) VALUES
(1, (SELECT id FROM test WHERE code='GLUCF'), 'completed', '90','mg/dl', '50-315 mg/dl', 0 NOW(), NOW()),
(2, (SELECT id FROM test WHERE code='CBC'), 'completed', 'WBC: 5.5 x10^9/L, RBC: 4.7 x10^12/L, HGB: 14 g/dL, HCT: 42%, MCV: 90 fL, MCH: 30 pg, MCHC: 33 g/dL, PLT: 250 x10^9/L','', 'WBC: 4.0-11.0 x10^9/L, RBC: 4.5-5.9 x10^12/L, HGB: 13-17 g/dL, HCT: 38-50%, MCV: 80-100 fL, MCH: 27-33 pg, MCHC: 32-36 g/dL, PLT: 150-450 x10^9/L', 0, NOW(), NOW());
(2, (SELECT id FROM test WHERE code='URINALYSIS'), 'completed', 'Color: Yellow, Appearance: Clear, pH: 6.0, Specific Gravity: 1.020, Glucose: Negative, Protein: Negative, Ketones: Negative, Blood: Negative, Leukocytes: Negative, Nitrites: Negative','', 'Color: Yellow, Appearance: Clear, pH: 4.5-8.0, Specific Gravity: 1.005-1.030, Glucose: Negative, Protein: Negative, Ketones: Negative, Blood: Negative, Leukocytes: Negative, Nitrites: Negative', 0, NOW(), NOW());