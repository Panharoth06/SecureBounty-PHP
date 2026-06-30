# SecureBounty — Project Scope

## Introduction

SecureBounty is a web-based bug bounty and vulnerability disclosure platform built with PHP (MVC architecture). The platform connects organizations (Program Owners) who want to secure their digital assets with security researchers who identify and report vulnerabilities. An Admin role oversees platform operations. The system manages bug bounty programs, vulnerability reports, reward policies, and communication between stakeholders.

## Glossary

- **Platform**: The SecureBounty web application as a whole
- **Admin**: A platform administrator responsible for managing users, programs, and platform-wide settings
- **Program_Owner**: A user who creates and manages bug bounty programs on behalf of an organization
- **Researcher**: A security researcher who discovers and reports vulnerabilities in programs
- **Program**: A bug bounty program created by a Program_Owner, defining scope, rules, and reward policies
- **Report**: A vulnerability submission created by a Researcher against a specific Program
- **Attachment**: A file (screenshot, proof-of-concept, log) associated with a Report
- **Reward_Policy**: A set of rules defining payment amounts for different vulnerability severity levels within a Program
- **Comment**: A message exchanged between a Program_Owner and a Researcher on a Report
- **Activity_Log**: A record of user actions on the platform for audit purposes
- **Saved_Program**: A bookmark created by a Researcher to track Programs of interest
- **User_Program**: An enrollment record linking a Researcher to a Program they have joined

## Data Model

### Entities and Relationships

| Entity | Description |
|--------|-------------|
| Users | Platform users with role-based access |
| Roles | Admin, Program_Owner, Researcher |
| Programs | Bug bounty programs created by Program Owners |
| Reports | Vulnerability submissions by Researchers |
| Attachments | Files attached to Reports |
| Reward_Policies | Severity-based reward definitions per Program |
| Comments | Messages between Program_Owner and Researcher on Reports |
| Activity_Logs | Audit trail of user actions |
| User_Programs | Junction: Researcher enrollment in Programs |
| Saved_Programs | Junction: Researcher bookmarks for Programs |

### Relationships

- Users → Roles (N:1 — each user has one role, each role applies to many users)
- Users → Programs (M:N via User_Programs — enrollment)
- Users → Programs (M:N via Saved_Programs — bookmarks)
- Users → Activity_Logs (1:N)
- Users → Comments (1:N)
- Programs → Comments (1:N)
- Programs → Reward_Policies (1:N)
- Programs → Reports (1:N)
- Reports → Attachments (1:N)

---

## Requirements

### Requirement 1: User Registration

**User Story:** As a visitor, I want to register an account with a selected role, so that I can participate in the platform as either a Program Owner or a Researcher.

#### Acceptance Criteria

1. WHEN a visitor submits a valid registration form, THE Platform SHALL create a new user account with the selected role (Program_Owner or Researcher)
2. WHEN a visitor submits a registration form with an email already in use, THE Platform SHALL display an error message indicating the email is already registered
3. WHEN a visitor submits a registration form with invalid data, THE Platform SHALL display specific validation error messages for each invalid field
4. THE Platform SHALL hash user passwords using a secure hashing algorithm before storing them in the database
5. WHEN a user account is created, THE Platform SHALL create an Activity_Log entry recording the registration event

### Requirement 2: User Authentication

**User Story:** As a registered user, I want to log in and log out securely, so that I can access the platform features assigned to my role.

#### Acceptance Criteria

1. WHEN a user submits valid credentials, THE Platform SHALL create an authenticated session and redirect the user to their role-specific dashboard
2. WHEN a user submits invalid credentials, THE Platform SHALL display a generic error message without revealing whether the email or password was incorrect
3. WHEN an authenticated user requests logout, THE Platform SHALL destroy the session and redirect to the login page
4. WHILE a user session is active, THE Platform SHALL enforce role-based access control on all protected routes
5. IF a session expires or is invalidated, THEN THE Platform SHALL redirect the user to the login page with an appropriate notification

### Requirement 3: Role-Based Access Control

**User Story:** As a platform operator, I want users restricted to actions allowed by their role, so that the platform remains secure and responsibilities are properly separated.

#### Acceptance Criteria

1. THE Platform SHALL enforce three distinct roles: Admin, Program_Owner, and Researcher
2. WHILE a user is authenticated as Admin, THE Platform SHALL grant access to user management, program oversight, and platform configuration features
3. WHILE a user is authenticated as Program_Owner, THE Platform SHALL grant access to program creation, program management, report review, and comment features for owned programs
4. WHILE a user is authenticated as Researcher, THE Platform SHALL grant access to program browsing, program enrollment, report submission, and comment features for enrolled programs
5. IF an authenticated user attempts to access a route outside their role permissions, THEN THE Platform SHALL return an HTTP 403 response and display an access denied message

### Requirement 4: Program Management

**User Story:** As a Program Owner, I want to create and manage bug bounty programs, so that researchers can find and report vulnerabilities in my organization's assets.

#### Acceptance Criteria

1. WHEN a Program_Owner submits a valid program creation form, THE Platform SHALL create a new Program with status "draft"
2. WHEN a Program_Owner publishes a draft Program, THE Platform SHALL change the Program status to "active" and make the Program visible to Researchers
3. WHEN a Program_Owner updates an active Program, THE Platform SHALL save the changes and create an Activity_Log entry recording the modification
4. WHEN a Program_Owner closes a Program, THE Platform SHALL change the Program status to "closed" and prevent new Report submissions against the Program
5. THE Platform SHALL require each Program to include a title, description, scope definition, and at least one Reward_Policy before publishing
6. WHEN a Program is created or updated, THE Platform SHALL validate that all required fields contain non-empty values

### Requirement 5: Reward Policy Configuration

**User Story:** As a Program Owner, I want to define reward policies for my programs, so that researchers understand the compensation for different vulnerability severity levels.

#### Acceptance Criteria

1. WHEN a Program_Owner creates a Reward_Policy for a Program, THE Platform SHALL associate the policy with the specified severity level and reward amount
2. THE Platform SHALL support severity levels: Critical, High, Medium, Low, and Informational
3. WHEN a Program_Owner updates a Reward_Policy, THE Platform SHALL save the updated reward amount and create an Activity_Log entry
4. WHEN a Program_Owner deletes a Reward_Policy, THE Platform SHALL remove the policy only if no accepted Reports reference the policy
5. IF a Program_Owner attempts to delete a Reward_Policy referenced by accepted Reports, THEN THE Platform SHALL reject the deletion and display an error message explaining the constraint

### Requirement 6: Program Discovery and Enrollment

**User Story:** As a Researcher, I want to browse and join bug bounty programs, so that I can find targets relevant to my expertise.

#### Acceptance Criteria

1. WHEN a Researcher views the program listing, THE Platform SHALL display all active Programs with their title, description summary, and reward range
2. WHEN a Researcher enrolls in a Program, THE Platform SHALL create a User_Program record and grant the Researcher permission to submit Reports for the Program
3. WHEN a Researcher saves a Program, THE Platform SHALL create a Saved_Program record and display the Program in the Researcher's saved list
4. WHEN a Researcher removes a saved Program, THE Platform SHALL delete the Saved_Program record
5. IF a Researcher attempts to enroll in a Program they are already enrolled in, THEN THE Platform SHALL display a message indicating existing enrollment

### Requirement 7: Vulnerability Report Submission

**User Story:** As a Researcher, I want to submit vulnerability reports for programs I have joined, so that I can disclose security issues responsibly.

#### Acceptance Criteria

1. WHEN an enrolled Researcher submits a valid Report for a Program, THE Platform SHALL create the Report with status "pending" and notify the Program_Owner
2. THE Platform SHALL require each Report to include a title, description, severity level, steps to reproduce, and impact assessment
3. WHEN a Researcher attaches files to a Report, THE Platform SHALL validate file type and size, then store the Attachment linked to the Report
4. THE Platform SHALL restrict allowed attachment file types to: PNG, JPG, GIF, PDF, TXT, and ZIP
5. THE Platform SHALL restrict each Attachment to a maximum file size of 10 MB
6. IF a Researcher attempts to submit a Report for a Program they are not enrolled in, THEN THE Platform SHALL reject the submission and return an HTTP 403 response

### Requirement 8: Report Review and Management

**User Story:** As a Program Owner, I want to review and manage vulnerability reports, so that I can triage, validate, and resolve reported issues.

#### Acceptance Criteria

1. WHEN a Program_Owner views their dashboard, THE Platform SHALL display all Reports for owned Programs grouped by status
2. WHEN a Program_Owner changes a Report status, THE Platform SHALL update the status and create an Activity_Log entry recording the change
3. THE Platform SHALL support Report statuses: pending, triaged, accepted, rejected, and resolved
4. WHEN a Program_Owner accepts a Report, THE Platform SHALL associate the applicable Reward_Policy with the Report based on the severity level
5. WHEN a Report status changes, THE Platform SHALL notify the submitting Researcher of the status update

### Requirement 9: Report Comments and Communication

**User Story:** As a Program Owner or Researcher, I want to communicate through comments on reports, so that I can clarify details and coordinate resolution.

#### Acceptance Criteria

1. WHEN an authorized user submits a Comment on a Report, THE Platform SHALL store the Comment with the author, timestamp, and Report reference
2. THE Platform SHALL restrict Comment access to the Report submitter (Researcher) and the Program_Owner of the associated Program
3. WHEN a new Comment is added to a Report, THE Platform SHALL notify the other participant in the conversation
4. THE Platform SHALL display Comments in chronological order within the Report detail view
5. IF an unauthorized user attempts to view or add Comments on a Report, THEN THE Platform SHALL return an HTTP 403 response

### Requirement 10: Admin User Management

**User Story:** As an Admin, I want to manage user accounts, so that I can maintain platform integrity and handle account issues.

#### Acceptance Criteria

1. WHEN an Admin views the user management panel, THE Platform SHALL display a paginated list of all registered users with their role, status, and registration date
2. WHEN an Admin deactivates a user account, THE Platform SHALL prevent the user from authenticating and create an Activity_Log entry
3. WHEN an Admin reactivates a user account, THE Platform SHALL restore authentication capability and create an Activity_Log entry
4. WHEN an Admin changes a user's role, THE Platform SHALL update the role and create an Activity_Log entry recording the previous and new role
5. THE Platform SHALL prevent an Admin from deactivating their own account

### Requirement 11: Admin Program Oversight

**User Story:** As an Admin, I want to oversee all programs on the platform, so that I can ensure compliance with platform policies.

#### Acceptance Criteria

1. WHEN an Admin views the program oversight panel, THE Platform SHALL display all Programs regardless of status with filtering options by status and owner
2. WHEN an Admin suspends a Program, THE Platform SHALL change the Program status to "suspended" and prevent new enrollments and Report submissions
3. WHEN an Admin reinstates a suspended Program, THE Platform SHALL change the Program status back to "active"
4. WHEN an Admin suspends or reinstates a Program, THE Platform SHALL create an Activity_Log entry and notify the Program_Owner

### Requirement 12: Activity Logging

**User Story:** As an Admin, I want comprehensive activity logs, so that I can audit user actions and investigate incidents.

#### Acceptance Criteria

1. THE Platform SHALL create an Activity_Log entry for every state-changing action performed by any user
2. THE Platform SHALL record the following fields in each Activity_Log entry: user identifier, action type, target entity, timestamp, and IP address
3. WHEN an Admin views the activity log panel, THE Platform SHALL display log entries in reverse chronological order with pagination
4. WHEN an Admin filters activity logs by user, action type, or date range, THE Platform SHALL return only matching entries
5. THE Platform SHALL retain Activity_Log entries indefinitely and prevent deletion by any user role

### Requirement 13: Dashboard Views

**User Story:** As a user, I want a role-specific dashboard, so that I can quickly access relevant information and actions.

#### Acceptance Criteria

1. WHILE a user is authenticated as Admin, THE Platform SHALL display a dashboard showing total users, total programs, recent activity, and pending reports count
2. WHILE a user is authenticated as Program_Owner, THE Platform SHALL display a dashboard showing owned programs, pending reports, and recent comments
3. WHILE a user is authenticated as Researcher, THE Platform SHALL display a dashboard showing enrolled programs, submitted reports, saved programs, and recent notifications
4. WHEN a user accesses the dashboard, THE Platform SHALL load data specific to the authenticated user without exposing data belonging to other users

### Requirement 14: Input Validation and Security

**User Story:** As a platform operator, I want all user inputs validated and sanitized, so that the platform is protected against injection and cross-site scripting attacks.

#### Acceptance Criteria

1. THE Platform SHALL validate and sanitize all user-submitted input before processing or storing
2. THE Platform SHALL use parameterized queries for all database operations to prevent SQL injection
3. THE Platform SHALL encode output data rendered in HTML views to prevent cross-site scripting
4. THE Platform SHALL implement CSRF token validation on all state-changing form submissions
5. IF a request fails CSRF validation, THEN THE Platform SHALL reject the request and return an HTTP 403 response

---

## Technical Context

- **Language:** PHP
- **Architecture:** MVC (Model-View-Controller)
- **Database:** MySQL (via MySQLi, utf8mb4)
- **Routing:** Front controller pattern (index.php with query parameter dispatch)
- **Session Management:** PHP native sessions
- **Middleware:** Role-based middleware classes (AdminMiddleware, AuthMiddleware, ProgramOwnerMiddleware, ResearcherMiddleware)
