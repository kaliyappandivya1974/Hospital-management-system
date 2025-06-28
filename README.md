# Hospital-management-system


The **Akira Hospital Management System** is a comprehensive web-based application designed to efficiently manage hospital operations such as appointments, patient records, billing, doctor schedules, and laboratory orders. Built using PHP and integrated with a MySQL backend, this system is ideal for clinics or hospitals looking to streamline administrative and medical workflows.

🔧 Features

 👨‍⚕️ Doctor Management: Add, update, and manage doctors’ details and schedules.
 📅 Appointments: Book, view, and manage patient appointments.
 🛏️ Bed Management: Track availability and allocation of hospital beds.
 💳 Billing System: Generate bills and manage payment processing with XAMPP-compatible fixes.
 🧪 Lab Orders: Add and manage laboratory test orders for patients.
 🗃️ Database Tools: Backup, restore, and maintain the hospital database using built-in tools.
 📊 Dashboard: Centralized access to key modules and data insights.
 🔐 Authentication: Includes login and access control for admin.
📦 Modular Design: Separated logic for easy scaling and maintenance.

 🗂️ Folder Structure

 add_lab_order.php: Module for entering new lab orders.
 appointments.php: Appointment scheduling and management interface.
 beds.php & beds_fixed.php: Bed availability and allocation.
 billing.php, billing_fix_for_xampp.php: Billing features and XAMPP compatibility.
 db_connect.php, db_backup.php, db_restore.php: Database connectivity and maintenance.
 doctors.php: Manage doctor profiles and availability.

 🧰 Technologies Used
Backend: PHP
Database: MySQL
Server: XAMPP (local)
UI: HTML, CSS (custom design)


 🚀 How to Run
1. Clone or download the repository.
2. Set up XAMPP and place the project folder in `htdocs`.
3. Import the SQL file (if provided) into phpMyAdmin.
4. Open your browser and navigate to `http://localhost/<project-folder>/dashboard.php`.
