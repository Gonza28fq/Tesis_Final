📊 Commercial Management System 2.0
Comprehensive business management system featuring modular architecture, role-based access control (RBAC), and full audit logging.

![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat&logo=php&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat&logo=javascript&logoColor=black)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-7952B3?style=flat&logo=bootstrap&logoColor=white)

🎯 Overview
Enterprise management system developed as a thesis project to automate internal business processes, eliminating manual workflows and fragmented systems. The platform achieved an 85% reduction in administrative errors through data centralization and granular access control.

✨ Key Features
✅ Customer Management: Full CRM module with transaction history.

🛒 Sales Module: Recording, invoicing, and automated tax calculations.

📦 Inventory Control: Real-time stock tracking with automated low-stock alerts.

💰 Procurement Management: Supplier database and purchase order tracking.

👥 User System: Role-based permissions (RBAC) with dynamic UI rendering.

📊 Statistical Reporting: Real-time dashboards for strategic decision-making.

🔒 Comprehensive Auditing: Full traceability of all system operations per user.

🛠️ Tech Stack
Frontend:

HTML5, CSS3, JavaScript (ES6+)

Bootstrap 5 for responsive design.

AJAX for asynchronous interactions.

Backend:

PHP 7.4+

Modular MVC Architecture.

REST API with JSON support.

Stored Procedures for enhanced security and performance.

Database:

MySQL / MariaDB.

Normalized schema with complex relational mapping.

Triggers and procedures for data integrity.

Security:

Password hashing (bcrypt).

Real-time server-side validations.

SQL Injection protection.

Role-Based Access Control (RBAC).

Other Technologies:

Composer (Dependency Management).

Cron Jobs (Scheduled Tasks).

Apache Server.

📋 Requirements
PHP >= 7.4

MySQL >= 5.7 or MariaDB >= 10.3

Apache Server

Composer

🚀 Installation
Clone the repository

Bash
git clone https://github.com/Gonza28fq/Tesis_Final.git
cd Tesis_Final
Install dependencies

Bash
composer install
Database Configuration

Create a new MySQL database.

Import the database.sql file (if provided).

Update config/database.php with your credentials.

Server Configuration

Point your DocumentRoot to the project folder.

Or use XAMPP/WAMP pointing to the directory.

Access the System

http://localhost/Tesis_Final
👤 Demo Credentials
User: admin
Password: [Contact developer]
📸 Screenshots
[Add screenshots here]

🏗️ System Architecture
Core Modules:
📁 modules/
  ├── customers/      # Customer management (CRM)
  ├── sales/          # Sales & Invoicing
  ├── procurement/    # Purchase management
  ├── inventory/      # Stock control
  ├── users/          # User administration
  ├── audit/          # Traceability system
  └── reports/        # Dashboards and analytics
Database Highlights:
15+ Relational tables.

Automated audit triggers.

Stored procedures for critical operations.

Optimized views for reporting.

🔄 Modern Stack Migration (In Progress)
Currently developing version 3.0 featuring:

Frontend: React + TypeScript.

Backend: Node.js + Express.

Database: PostgreSQL.

Improved Security: JWT + Advanced Encryption.

📄 Documentation
A full 240-page technical documentation is available, including:

Requirements Analysis.

Architectural Design.

UML Diagrams (Use Case, Class, Sequence).

User and Technical Manuals.

🤝 Contributions
This is a completed academic project. If you find bugs or have suggestions, feel free to open an issue.

👨‍💻 Author
Gonzalo Iván Pedraza

📧 Email: gonza280797@hotmail.com

💼 LinkedIn: linkedin.com/in/gonzalo-pedraza

📝 License
This project was developed as a final thesis for the Software Development degree at Instituto 9 de Julio (2024).

⭐ If you found this project useful, feel free to give it a star!
