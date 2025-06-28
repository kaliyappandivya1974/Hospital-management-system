#!/bin/bash

# AKIRA HOSPITAL Laboratory Fix Script
echo "Running the laboratory fix script..."

# Run the SQL commands directly instead of using PHP
cat <<EOL | psql $DATABASE_URL

-- Step 1: Create lab_departments table
CREATE TABLE IF NOT EXISTS lab_departments (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL
);

-- Check if we have departments and insert default ones if needed
DO \$\$
BEGIN
    IF (SELECT COUNT(*) FROM lab_departments) = 0 THEN
        INSERT INTO lab_departments (name, description) VALUES
            ('Hematology', 'Blood testing department'),
            ('Biochemistry', 'Chemical analysis department'),
            ('Radiology', 'Medical imaging department'),
            ('Microbiology', 'Microorganism analysis'),
            ('Pathology', 'Tissue and sample analysis');
    END IF;
END \$\$;

-- Step 2: Create lab_tests table with correct structure
DROP TABLE IF EXISTS lab_tests CASCADE;
CREATE TABLE lab_tests (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    lab_department_id INT,
    cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    description TEXT,
    status VARCHAR(20) DEFAULT 'active',
    FOREIGN KEY (lab_department_id) REFERENCES lab_departments(id)
);

-- Add sample lab tests
INSERT INTO lab_tests (name, lab_department_id, cost, description, status) VALUES
    ('Complete Blood Count (CBC)', 1, 250.00, 'Analyzes different components of blood', 'active'),
    ('Lipid Profile', 2, 500.00, 'Measures cholesterol and triglycerides', 'active'),
    ('MRI Brain', 3, 4000.00, 'Detailed imaging of the brain', 'active'),
    ('Blood Glucose', 2, 300.00, 'Measures blood sugar levels', 'active'),
    ('Liver Function Test', 2, 1200.00, 'Assesses liver function and damage', 'active'),
    ('Kidney Function Test', 2, 1000.00, 'Evaluates kidney function', 'active'),
    ('Urinalysis', 2, 250.00, 'Physical, chemical and microscopic examination of urine', 'active'),
    ('Chest X-Ray', 3, 1500.00, 'Imaging of chest, heart and lungs', 'active');

-- Step 3: Create or update lab_orders table
DROP TABLE IF EXISTS lab_orders CASCADE;
CREATE TABLE lab_orders (
    id SERIAL PRIMARY KEY,
    order_number VARCHAR(20),
    patient_id INT NOT NULL,
    doctor_id INT,
    test_id INT NOT NULL,
    technician_id INT,
    order_date DATE DEFAULT CURRENT_DATE,
    order_time TIME DEFAULT CURRENT_TIME,
    priority VARCHAR(20) DEFAULT 'routine',
    status VARCHAR(20) DEFAULT 'pending',
    results TEXT,
    normal_values TEXT,
    completed_at TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (test_id) REFERENCES lab_tests(id)
);

EOL

# Set executable permissions for the lab files
chmod +x laboratory.php
chmod +x add_lab_order.php

echo "Laboratory database fix completed!"
echo "You can now access the laboratory module at: http://localhost:5000/laboratory.php"