Hospital Appointment System - Project Master Plan
1. Project Overview

Goal: A web-based application for managing hospital appointments.


Institution: Çukurova University, Computer Engineering Department.

Target Users: Patients, Doctors, and Administrators.


Language: English (Code and Reports).

2. Technical Stack & Rules

Frontend: HTML, CSS (external files), Bootstrap (Responsive).
+1


Backend: PHP using MySQLi API.
+2


Database: MySQL (3rd Normal Form).
+1


Environment: Apache Server (XAMPP).

3. Mandatory Requirements Checklist
[x] Separation of CSS: No inline styles; use external .css files or Bootstrap.

[x] Form Elements: Use at least 5 types (Text, Checkbox, Radio, Drop-down, etc.) .

[x] Server-side Validation: Use Regular Expressions (RegEx) in PHP for input validation.

[x] Database Logic:

[x] Full CRUD (Insert, Manipulate, Retrieve, Remove).

[x] At least one JOIN operation.

[x] At least one Trigger.

[x] At least one Stored Procedure.

[x] Responsiveness: Fully functional on Mobile, Tablet, and PC via Bootstrap.

4. Development Steps (Implementation Order)
Phase 1: Database Foundation
Create a 3NF MySQL schema.

Define Patients, Doctors, Departments, and Appointments tables.

Implement the Trigger and Stored Procedure.

Phase 2: Core Infrastructure
Setup folder structure: /css, /js, /includes, /actions.

Create db_connection.php using MySQLi.

Phase 3: Authentication & Validation
Build Login/Registration forms using at least 5 different web form elements.

Implement PHP RegEx validation for email and user inputs.

Phase 4: Main Features (The CRUD)

Patient Dashboard: View and Book appointments (Insert/Retrieve).


Doctor/Admin Dashboard: Manage (Edit/Delete) appointments and records (Manipulate/Remove).

Implement SQL JOINs to display appointment details with doctor and patient names.

Phase 5: Final Polish
Ensure all links are fully implemented.

Final responsive check with Bootstrap