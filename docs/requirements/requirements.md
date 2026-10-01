> Converted from `Custograde_Requirements_Document.docx` (same folder).
> The .docx is the source of truth; this .md is a searchable mirror.

**REQUIREMENTS SPECIFICATION DOCUMENT**

**CUSTOGRADE**

**Intelligent Handwritten Examination Marking and Archiving System**

Web and Desktop Solution for Universities, Examination Bodies and Similar Academic Institutions

**by Custospark**

| Item | Detail |
| --- | --- |
| Document version | 1.1 (baseline for review) |
| Date | 1 October 2026 |
| Prepared by | Custospark Team |
| Status | Draft for stakeholder review and management approval |
| Source documents | Project Concept Note; Custograde Business Plan and ICT Innovation Proposal (September 2026) |
| Delivery model | Web application and desktop application sharing one Laravel API |
| Fixed technical stack | Backend: Laravel (PHP). Frontend: React with TypeScript (web, and packaged for desktop). AI service: Python, built as an external service |

# Document Control
| Version | Date | Author | Description of change |
| --- | --- | --- | --- |
| 0.1 | September 2026 | Custospark Team | Concept Note and Business Plan prepared (source documents). |
| 1.0 | 1 October 2026 | Custospark Team | First complete requirements baseline covering functional, interface, data and non-functional requirements, implementation mapping and traceability. |
| 1.1 | 1 October 2026 | Custospark Team | Document issued under the Custospark name; delivery as web and desktop solution added (Desktop Application module DSK and related changes). |

This document is controlled. Changes after baseline shall be raised as change requests, assessed for impact using the traceability matrix in Section 10, and recorded in this table.

**CONTENTS**

**Document Control****	2**

**1. Introduction****	5**

1.1 Purpose	5

1.2 Scope	5

1.3 Intended audience	5

1.4 Source documents and baseline	5

1.5 Document conventions	6

**2. Overall Description****	7**

2.1 Product perspective	7

2.2 Product functions	7

2.3 Users and stakeholders	7

2.4 Operating environment	8

2.5 Design and implementation constraints	8

2.6 Assumptions and dependencies	9

2.7 Out of scope	9

**3. System Context and Architecture****	10**

3.1 System context	10

3.2 Logical components	10

3.3 Mapping principles	10

**4. Business Rules and Success Measures****	11**

4.1 Business rules	11

4.2 Success measures	11

**5. Functional Requirements****	13**

5.1 Authentication, Authorisation and Tenancy	13

5.2 Institution and Academic Structure	14

5.3 Student and Candidate Records	15

5.4 Examination Setup and Marking Guides	16

5.5 Personalised Answer Sheet Generation	18

5.6 Script Capture and Ingestion	19

5.7 Image Pre-processing and Student Identification	20

5.8 Objective Marking (OMR)	21

5.9 Handwriting Recognition (OCR)	21

5.10 AI-Assisted Short Answer Grading	22

5.11 Teacher Review and Moderation	24

5.12 Mark Compilation and Results	25

5.13 Digital Archive	26

5.14 Re-marking and Appeals	28

5.15 Reporting, Analytics and Export	29

5.16 Optional AI Assistant	30

5.17 Integration with Existing Academic Information Systems	30

5.18 Notifications	32

5.19 Subscription, Billing and Usage	32

5.20 Administration, Audit and Support	33

5.21 Desktop Application	34

**6. External Interface Requirements****	37**

6.1 User Interface Requirements	37

6.2 Hardware Interface Requirements	37

6.3 Software Interface Requirements	38

6.4 Communications Interface Requirements	38

**7. Data Requirements****	39**

7.1 Logical data entities	39

7.2 Data management requirements	40

**8. Non-Functional Requirements****	41**

8.1 Performance	41

8.2 Scalability and Capacity	41

8.3 Availability, Reliability and Recoverability	42

8.4 Accuracy and Effectiveness	42

8.5 Security	43

8.6 Privacy and Legal Compliance	44

8.7 Usability, Accessibility and Localisation	45

8.8 Maintainability and Portability	45

8.9 Responsible AI Governance	46

**9. Technical Realisation****	48**

9.1 Laravel backend modules	48

9.2 React and TypeScript feature modules	48

9.3 Laravel to Python service contract	49

9.4 Core processing flow	50

9.5 Integration architecture	50

9.6 Deployment	50

**10. Traceability****	52**

10.1 Approach	52

10.2 Source coverage	52

10.3 Layer and priority summary by module	54

10.4 Requirement to stack and verification matrix	55

**11. Verification, Acceptance and Release Plan****	63**

11.1 Verification approach	63

11.2 Acceptance criteria	63

11.3 Release plan	63

**12. Risks, Open Issues and Interpretations****	65**

12.1 Risks to the requirements	65

12.2 Open issues	65

12.3 Interpretations made	66

**Appendix A. Glossary****	67**

**Appendix B. Principal Use Cases****	68**

# 1. Introduction
## 1.1 Purpose
This document specifies the requirements for Custograde, an intelligent system, delivered as a web application and as a desktop application, that digitises handwritten examination scripts, identifies each student automatically, marks objective questions, assists with short-answer marking, and keeps a permanent, searchable archive of every marked script. It is the baseline for design, implementation, testing, acceptance and, later, integration with existing academic information systems.

Each requirement is stated so that it can be verified, is traced to the source document or analysis that justifies it, and is mapped to the part of the fixed technical stack that will implement it.

## 1.2 Scope
The system covers the full life cycle of a handwritten examination: examination and roster set-up; personalised answer sheet generation; scanning and smartphone capture; automatic student identification; objective (OMR) marking; handwriting recognition; AI-assisted short-answer grading; teacher review and moderation; mark compilation; permanent archiving; re-marking and appeals; reporting and analytics; and integration. Subscription management and an optional AI assistant are included because the Business Plan and Concept Note describe them. The desktop application extends the web application with direct scanner acquisition, watched-folder import, an encrypted offline queue and silent printing (DSK).

The intended users are universities, examination bodies and similar academic institutions. The Business Plan targets secondary schools and tertiary institutions first and lists examination bodies as a long-term segment; this document widens the functional scope to the larger institutions named in the project brief, for example through multi-level academic structures, moderation, weighted course results and examination-body roles.

## 1.3 Intended audience
- Developers and testers, who implement and verify requirements against the stack.

- Custospark management and quality reviewers, who check completeness and traceability.

- Institutional stakeholders (teachers, lecturers, examination officers, registrars, auditors), who confirm that the system meets their needs.

- Integrators and information-systems staff of partner institutions, who will connect existing academic information systems.

## 1.4 Source documents and baseline
| Code | Document | Use in this specification |
| --- | --- | --- |
| CN | Project Concept Note: Design and Development of an Intelligent Handwritten Examination Marking and Archiving System | Primary authority for functional scope, objectives (4.1 to 4.6) and the human-in-the-loop principle. |
| BP | Custograde Business Plan and ICT Innovation Proposal | Primary authority for features, workflow, customer needs, commercial model, architecture, technology stack, risks and timeline. |
| UB | Project brief and direction for this requirements document | Defines the target institutions (UB-1), the later integration with academic information systems (UB-2), the fixed technology stack (UB-3) and delivery as a web and desktop solution (UB-4). |
| D | Requirements analysis | Requirements derived to close gaps that a large-scale education system of this kind needs but the source documents do not state explicitly. Every derived requirement is marked D so that it can be challenged. |

Where the source documents differ in emphasis, the Concept Note governs functional scope and the Business Plan governs commercial, operational and technical detail. The Business Plan already names Flask for the Python service, MySQL for the database, encrypted object storage and Docker deployment; these are carried into this document as consistent with the fixed stack. Section 12 records the points where interpretation was needed.

## 1.5 Document conventions
**Requirement identifiers.** Each requirement has an identifier of the form PREFIX-NN, where the prefix names its module (for example IDN-02 is the second identification requirement). Identifiers are never reused.

**Modal verbs.** 'Shall' marks a mandatory statement, 'should' a recommendation, and 'may' an option.

| Convention | Meaning |
| --- | --- |
| Priority (Pri) | M = Must (required for acceptance of the complete system); S = Should (important, may be deferred without invalidating the system); C = Could (desirable, included if capacity allows). Release timing is set in Section 11. |
| Source | Where the requirement comes from: CN-n (Concept Note section or objective), BP-n.n (Business Plan section), UB-n (project brief), D (derived). Codes are explained in Section 10.2. |
| Layer tags (Section 10.4) | L = Laravel backend; R = React with TypeScript frontend; P = Python AI service; D = MySQL database; S = object storage; O = DevOps and infrastructure. |
| Verification method | T = Test; I = Inspection or review; D = Demonstration; A = Analysis or evaluation study. |

# 2. Overall Description
## 2.1 Product perspective
Custograde is a new, cloud-hosted software service with two clients: a web application and a desktop application. It replaces manual tallying, paper storage and spreadsheet entry with an integrated digital workflow and works with the scanners, photocopiers and phones that institutions already own (BP-3.4). It is multi-tenant: each university, school or examination body is a separate tenant. It is designed to sit beside, not replace, an institution's academic information system: that system remains the master of student and course data, while Custograde becomes the master of examination scripts, marks and the script archive.

## 2.2 Product functions
The functional modules and the number of requirements in each are listed below. Counts by priority are given in Section 10.3.

| Section | Module | Prefix | Requirements |
| --- | --- | --- | --- |
| 5.1 | Authentication, Authorisation and Tenancy | AUT | 11 |
| 5.2 | Institution and Academic Structure | ACD | 7 |
| 5.3 | Student and Candidate Records | STU | 8 |
| 5.4 | Examination Setup and Marking Guides | EXM | 10 |
| 5.5 | Personalised Answer Sheet Generation | SHT | 9 |
| 5.6 | Script Capture and Ingestion | CAP | 11 |
| 5.7 | Image Pre-processing and Student Identification | IDN | 10 |
| 5.8 | Objective Marking (OMR) | OMR | 7 |
| 5.9 | Handwriting Recognition (OCR) | OCR | 8 |
| 5.10 | AI-Assisted Short Answer Grading | AIG | 13 |
| 5.11 | Teacher Review and Moderation | REV | 15 |
| 5.12 | Mark Compilation and Results | MRK | 10 |
| 5.13 | Digital Archive | ARC | 12 |
| 5.14 | Re-marking and Appeals | APL | 6 |
| 5.15 | Reporting, Analytics and Export | RPT | 10 |
| 5.16 | Optional AI Assistant | AST | 5 |
| 5.17 | Integration with Existing Academic Information Systems | INT | 13 |
| 5.18 | Notifications | NOT | 4 |
| 5.19 | Subscription, Billing and Usage | BIL | 6 |
| 5.20 | Administration, Audit and Support | ADM | 7 |
| 5.21 | Desktop Application | DSK | 13 |

## 2.3 Users and stakeholders
| Role | Description | Principal needs |
| --- | --- | --- |
| Teacher / lecturer (marker) | Sets and marks examinations for assigned course units or classes. | Fast review of OMR and AI suggestions, annotation, feedback, full control of final marks (CN-4.4). |
| Examination officer / director of studies / head of department | Organises examinations and compiles results for an institution or department. | Set-up, sheet generation, exception handling, marking progress, finalisation, reports (BP-2.2). |
| Institution administrator | Manages users, structure, settings, integrations and subscription for a tenant. | Configuration, roles, integration console, data export. |
| Moderator / second marker / chief examiner | Checks marking quality and arbitrates differences. | Second-marking queues, discrepancy reports, adjustment tools (CN-5). |
| Scanning operator | Scans or photographs scripts on behalf of the institution or bureau. | Batch upload, offline capture, quality feedback (BP-7.4). |
| Student / candidate | Sits examinations; later may view released results and feedback. | Timely results, feedback, fair re-marking (BP-2.2). |
| Registrar / academic registry (system owner of the academic information system) | Owns student, programme and course master data and official results. | Reliable synchronisation of rosters in and results out (UB-2). |
| Auditor / quality assurer | Independently checks marking, access and archive integrity. | Read-only access, audit logs, tamper evidence (CN-4.3). |
| Examination body (for example UNEB) | Runs large-volume, high-integrity examinations. | Panels, blind and second marking, security, scale (BP-2.2, CN-5). |
| System administrator (service provider) | Operates the platform and tenants. | Tenant management, monitoring, billing, support (BP-7.3). |
| Integration client (software) | An external system calling the Custograde API. | Stable, authenticated, versioned API and events (UB-2). |

Parents and guardians are indirect stakeholders who benefit from faster, more reliable results but have no direct access in this version (BP-2.2).

## 2.4 Operating environment
- Cloud-hosted containers on Linux servers, with a managed or self-managed MySQL database and S3-compatible encrypted object storage (BP-8.2, BP-7.4).

- Users reach the system through current web browsers on laptops, desktops, tablets and smartphones, or through the desktop application on Windows (and, as a Should, macOS and Ubuntu); capture devices are document scanners, photocopiers with scan-to-file, and phone cameras (BP-3.1, UB-4).

- Connectivity may be slow or intermittent in some schools; capture must work offline first (BP-9.1).

- Power and hardware resources are limited in many institutions, so no specialised hardware may be required (BP-2.4, BP-5.2).

## 2.5 Design and implementation constraints
| ID | Constraint | Source |
| --- | --- | --- |
| CON-01 | The backend shall be built with Laravel (PHP) and shall be the single entry point for all business logic and data access. | UB-3, BP-8.1 |
| CON-02 | The frontend shall be a React application written in TypeScript. The desktop application shall be built from the same code base and packaged with a desktop shell. | UB-3, UB-4, BP-8.1 |
| CON-03 | AI, OCR, OMR and image-processing functions shall be built in Python as an external service called by the Laravel backend, not embedded in it. | UB-3, BP-8.1 |
| CON-04 | Structured data shall be stored in MySQL and scanned images in encrypted object storage; components shall be containerised with Docker. | BP-8.1, BP-8.2 |
| CON-05 | A teacher or authorised human shall make every final mark decision; automation only suggests or pre-scores. | CN-4.4, BP-9.1 |
| CON-06 | The system shall run with ordinary paper, scanners and phones and shall not require proprietary scanning hardware. The desktop application may use standard scanner drivers when present, but file-based and phone capture shall always remain available. | BP-3.4, BP-5.2, UB-4 |
| CON-07 | The system shall comply with the Uganda Data Protection and Privacy Act, 2019. | BP-9.1 |
| CON-08 | Pricing and currency shall be in Uganda Shillings with termly subscription tiers (Standard and Premium). | BP-6.1 |
| CON-09 | The system shall expose integration points that allow existing academic information systems to be connected later without redesign. | UB-2 |
| CON-10 | The desktop application shall call the same Laravel API as the web application and shall contain no business rules beyond local device functions (scanning, folders, local storage, printing, updating). | UB-4, UB-3 |

## 2.6 Assumptions and dependencies
- Institutions can print A4 sheets and scan or photograph scripts at 200 dpi or better (BP-3.1).

- Students write in the boxed answer regions of the personalised sheets (BP-9.1); handwriting quality varies and is handled by confidence flags and teacher review.

- Marking guides are available to the teacher before marking starts (CN-3).

- Target academic information systems expose an API, or at least a file export and import; the exact systems are not yet known (see open issue OI-01).

- Cloud hosting, SMS or email gateways and, if used, external language-model services are available under suitable contracts (BP-6.2).

- The pilot period (BP-9.2) is used to confirm the proposed accuracy and performance targets that the source documents do not quantify.

## 2.7 Out of scope
- Online (on-screen) examinations and question-paper authoring beyond the structure needed for marking (BP-2.4 positions Custograde for physical, handwritten examinations).

- Replacement of an institution's academic information system, fee management, timetabling or learning management system.

- Parent portals and general messaging (BP-2.2).

- Fully automatic grading without teacher approval, which is excluded by design (CN-4.4).

- Supply of scanners or printers, other than the optional scanning-bureau service described in BP-7.4.

# 3. System Context and Architecture
## 3.1 System context
Users interact with the React application, either in a web browser or inside the desktop application, over HTTPS. The React application talks only to the Laravel API; the desktop shell adds local device access (scanners, folders, local storage, printing) but no business logic. Laravel owns the business rules, the MySQL database and the object storage, and calls the Python AI service over a private, authenticated, versioned REST interface when image processing, recognition or grading is needed. External academic information systems, identity providers, email and SMS gateways, and (optionally) a language-model provider sit outside the platform boundary and are reached through Laravel (and, for the language-model provider only, through the Python service).

## 3.2 Logical components
| Component | Technology | Responsible for | Not responsible for |
| --- | --- | --- | --- |
| Client tier | React 18 with TypeScript; web app (progressive web app) | All screens; capture on phones; offline queue; review and annotation interface; presenting server-validated data | Authoritative authorisation or business rules |
| Desktop shell | Electron (TypeScript main process) hosting the same React interface | Scanner acquisition (WIA, TWAIN, ICA, SANE); watched folders; encrypted offline queue and cache; OS credential storage; silent printing; signed auto-update | Business rules, authoritative checks, direct access to the database or object storage |
| Core backend | Laravel (PHP) REST API, queues, scheduler | Authentication and RBAC; tenancy; exam, student, mark, result and archive logic; orchestration of the processing pipeline; integration, notifications, billing, audit | Pixel-level image processing, handwriting recognition, language-model grading |
| AI and image service | Python (Flask) with OpenCV, OCR and transformer models; external, independently deployed | Pre-processing; code decoding; region cropping; OMR; OCR; grading suggestions; clustering; assistant query planning | Storing tenant master data; making final decisions; user authentication |
| Structured data store | MySQL 8 | Transactional records, marks, mark events, audit logs, archive index | Image storage |
| Object storage | Encrypted S3-compatible storage | Original and derived images, generated PDFs, archive manifests, exports | Searchable metadata |
| Platform services | Docker, reverse proxy with TLS, queue broker, backups, monitoring | Deployment, scaling, certificates, backups, logs and metrics | Application logic |

## 3.3 Mapping principles
The following rules make the mapping from requirement to stack consistent and keep traceability meaningful.

- **Laravel is authoritative.** Every rule that affects security, marks or records is enforced in Laravel; React checks are only for user convenience.

- **Python is stateless and advisory.** The Python service receives references to images (short-lived signed links) and parameters, returns structured results with a model version, and never holds master data or makes final decisions.

- **React is a client of the API only,** in the browser and in the desktop application. It never reaches the database, object storage (except through signed links issued by Laravel) or the Python service; the desktop shell exposes device functions to it only through a small, allow-listed bridge.

- **Every requirement names its implementing layers.** The tag letters in Section 10.4 are generated from the same data as the requirement tables, so a change to one is visible in the other.

- **Integration is by contract.** Both the Laravel-Python interface (Section 9.3) and the academic-information-system interface (INT) are defined as versioned contracts with tests.

# 4. Business Rules and Success Measures
## 4.1 Business rules
| ID | Business rule | Source |
| --- | --- | --- |
| BR-01 | A script belongs to exactly one student (or anonymous candidate) and one examination; a page belongs to exactly one script. | CN-4.2 |
| BR-02 | Unambiguous OMR scores are applied automatically, but no script is finalised until an authorised human approves it; AI suggestions are never final until a teacher accepts or adjusts them. | CN-4.4, BP-9.1 |
| BR-03 | Original scans are never altered or overwritten; annotations and corrections are stored as separate layers or versions. | CN-4.3, BP-3.4 |
| BR-04 | Finalised results are immutable; corrections create a new version with a recorded reason. | CN-4.3 |
| BR-05 | Every mark has a recorded source (OMR, AI-accepted, manual, moderated, re-marked) and a full change history. | CN-4.3, BP-8.1 |
| BR-06 | The academic information system is authoritative for student, programme and course master data; Custograde is authoritative for scripts, marks and the archive. | UB-2 |
| BR-07 | Records are retained by default; deletion requires an authorised request, approval and an audit entry, and is blocked by legal hold. | CN-4.3, BP-9.1 |
| BR-08 | Only students enrolled for the examination receive personalised sheets, except where the fallback sheet procedure is invoked and logged. | CN-4.2 |
| BR-09 | Personal identity data is not sent to the AI service; only pseudonymous answer identifiers and answer content are. | BP-9.1 |
| BR-10 | A marker may not approve an appeal against that marker's own marking. | CN-4.3, D |
| BR-11 | Features beyond the Standard tier (AI grading, advanced analytics) are available only to tenants on the Premium tier. | BP-6.1 |

## 4.2 Success measures
These measures turn the business goals into acceptance evidence. Items marked 'proposed' are not quantified in the source documents and are to be confirmed during the pilot.

| Measure | Target | Source | Evidence |
| --- | --- | --- | --- |
| Marking time reduction | At least 60 percent for pilot teachers (aspiration 70 percent) | BP-1.4, BP-1.1 | Time study against each teacher's baseline (ACC-05) |
| Objective scoring accuracy | At least 99.5 percent of bubbles correct, uncertain marks flagged (proposed; source says near 100 percent) | BP-3.2 | Test set of at least 2,000 sheets (ACC-01) |
| Automatic identification | At least 99 percent of pages on compliant scans (proposed) | CN-4.2 | Test batches (ACC-02) |
| AI suggestion agreement | At least 80 percent within one mark of final (proposed) | CN-4.5 | Pilot evaluation (ACC-04, GOV-01) |
| Pilot scale | Two pilot schools, then 20 paying schools and about 10,000 students by end of Year 1 | BP-1.4 | Subscription records (BIL-01) |
| Archive retrieval | First page displayed in under 3 seconds | CN-4.3 | Performance test (ARC-04) |
| User satisfaction | System Usability Scale of 70 or more (proposed) | CN-4.5 | Usability test (USA-03) |
| Compilation errors | Zero manual arithmetic errors in compiled totals | BP-4.2 | Reconciliation of totals (MRK-01) |

# 5. Functional Requirements
Each table gives the requirement, its priority and source, and the implementation approach in terms of the fixed stack. Layer tags and verification methods for every requirement appear in Section 10.4.

## 5.1 Authentication, Authorisation and Tenancy
These requirements govern who may use the system, what each person may do, and how the data of one institution is kept apart from another. They underpin the security commitments in Business Plan Section 9.1.

| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| AUT-01 | The system shall authenticate each user with a unique identifier (email address or staff number) and a password, and shall store passwords only as salted adaptive hashes. | M | BP-9.1, D | Laravel authentication with Sanctum (session cookie for the React SPA, tokens for API clients); Hash facade using Argon2id or bcrypt; users table in MySQL; React login view with typed form validation. |
| AUT-02 | The system shall support time-based one-time-password multi-factor authentication and shall make it mandatory for institution administrators, examination officers, auditors and examination-body roles. | S | BP-9.1, D | Laravel Fortify two-factor module; recovery codes stored hashed; React enrolment screen showing the QR secret; policy flag per role in MySQL. |
| AUT-03 | The system shall support single sign-on through OpenID Connect and SAML 2.0 with an institution's existing identity provider, mapping asserted attributes to roles. | S | UB-2, CN-4.6 | Laravel Socialite plus a SAML package; per-tenant identity provider settings in a tenant_idp table; attribute-to-role mapping rules; React 'Sign in with your institution' button. |
| AUT-04 | The system shall enforce role-based access control in which permissions are assigned to roles, and roles are granted to users per institution and, where relevant, per examination or course unit. | M | BP-9.1, CN-4.4 | Laravel Policies and Gates backed by roles, permissions and role_user tables; authorisation middleware on every API route. React route guards and conditional rendering are conveniences only; server-side checks are authoritative. |
| AUT-05 | The system shall provide, at minimum, the roles listed in Section 2.3 (system administrator, institution administrator, examination officer, teacher/lecturer, moderator, scanning operator, auditor, student, integration client). | M | BP-2.2, D | Role catalogue seeded by Laravel migrations; role-permission matrix kept under version control and reviewed at every release. |
| AUT-06 | The system shall isolate the data of each institution (tenant) so that no user or API client can read or modify another tenant's records or files. | M | BP-8.1, UB-1 | Laravel global query scope adding tenant_id to all tenant-owned models; tenant-prefixed object-storage keys; automated cross-tenant denial tests; Python service receives only short-lived, tenant-scoped pre-signed URLs. |
| AUT-07 | The system shall restrict a teacher or marker to the scripts, questions and examinations assigned to that person. | M | CN-4.4, D | marker_assignments table (user, exam, section/question); Laravel policy filters script queries by assignment; React work queue shows only assigned items. |
| AUT-08 | The system shall expire idle sessions after a configurable period (default 30 minutes) and shall allow users and administrators to revoke active sessions. | M | BP-9.1, D | Sanctum token expiry and revocation endpoint; React idle timer that calls logout and clears client caches; sessions table. |
| AUT-09 | The system shall provide an account lifecycle of invitation by email, activation, deactivation and password reset using time-limited, single-use links, and shall retain deactivated users' records for audit. | M | D | Laravel mail notifications, password broker and signed URLs; soft deletes on users; React activation and reset views. |
| AUT-10 | The system shall lock an account temporarily after five consecutive failed sign-ins and shall rate-limit authentication and API calls. | M | BP-9.1, D | Laravel RateLimiter on login and API middleware groups; lockout events written to audit_logs. |
| AUT-11 | The system shall enforce a configurable password policy (minimum length of 10, complexity and rejection of known breached passwords). | S | D | Laravel Password validation rule with uncompromised() check; policy text and strength meter in React. |

## 5.2 Institution and Academic Structure
Universities, secondary schools and examination bodies organise academic work differently. One configurable structure serves all of them and prepares the system for later synchronisation with existing academic information systems.

| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| ACD-01 | The system shall allow each institution to maintain a profile (name, type, logo, contact details, time zone, currency, locale), with defaults of Africa/Kampala, UGX and English. | M | BP-1.1, UB-1 | institutions table with a validated JSON settings column (Laravel Form Requests); logo in object storage; React settings screen. |
| ACD-02 | The system shall support a configurable academic hierarchy (faculty or school, department, programme, course unit or subject, class or stream) so that both secondary-school and tertiary structures can be represented. | M | UB-1, BP-4.1 | Typed adjacency-list table org_units shared by all institution types; Laravel resource controllers; React tree-view manager with drag-and-drop ordering. |
| ACD-03 | The system shall maintain an academic calendar of academic years, terms or semesters and assessment windows, and shall associate every examination with one term. | M | BP-1.4, D | academic_years and terms tables; term status drives examination, archive and billing logic; React calendar form. |
| ACD-04 | The system shall maintain a course unit or subject catalogue (code, title, credit units, level, responsible lecturers or teachers). | M | D | course_units table with unique (tenant_id, code); lecturer assignment pivot; React CRUD screens with search. |
| ACD-05 | The system shall support configurable grading schemes (grade boundaries, grade points, pass mark, divisions or aggregates) with effective dates, selectable per programme or examination. | M | BP-3.2, D | grading_schemes and grade_bands tables; Laravel GradeCalculator domain service; React boundary editor that rejects overlaps and gaps. |
| ACD-06 | The system shall support an examination-body mode with centres, candidate numbers, paper codes and marking-panel roles (examiner, team leader, chief examiner). | S | CN-5, BP-4.1, UB-1 | Additional org_unit types and a marking_panels hierarchy table; tenant-type feature flag; role mapping in RBAC tables; React panel management screen. |
| ACD-07 | The system shall import the academic structure and course unit catalogue from CSV or Excel files. | S | D | Laravel Excel import in a queued job with row-level validation and downloadable error report; React upload wizard with preview. |

## 5.3 Student and Candidate Records
Reliable student identification (Concept Note Objective 4.2) depends on an accurate roster. These requirements cover creating, importing and protecting student and candidate records.

| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| STU-01 | The system shall create, view, update and deactivate student or candidate records holding a registration number unique within the institution, names, programme or class, status and optional contact details. | M | CN-4.2, BP-3.3 | students table with unique index (tenant_id, reg_no); Laravel resource controller and Form Request validation; React list and detail forms. |
| STU-02 | The system shall bulk-import rosters from CSV or Excel with a preview, row-level validation, an error report and idempotent updates keyed on registration number. | M | BP-3.3, CN-4.2 | Laravel Excel import in a queued job using upsert on (tenant_id, reg_no); React import wizard with column mapping and error download. |
| STU-03 | The system shall enrol students into course units or classes for a given term, individually and in bulk. | M | BP-3.3, D | enrolments table (student, course unit, term); bulk endpoint; React multi-select enrolment screen. |
| STU-04 | The system shall search, filter, sort and paginate students by registration number, name, programme, class and status, returning results within 2 seconds for 100,000 records. | M | D | Indexed MySQL columns with keyset pagination in Laravel; debounced React search component. |
| STU-05 | The system shall track student status (active, deferred, withdrawn, graduated) and shall preserve the status and identity details that applied when archived work was produced. | S | CN-4.3, D | Status history table with effective dates; archive records store an immutable snapshot of identity fields. |
| STU-06 | The system shall warn when a new or imported record may duplicate an existing one (same registration number, or similar name and date of birth). | S | D | Laravel service using normalised-name comparison and unique indexes; React warning dialog with merge or ignore options. |
| STU-07 | The system shall provide an optional blind-marking mode in which markers see only an anonymous script code and never the student's identity. | S | CN-2, D | Per-examination flag; Laravel API resources omit identity fields for the marker role; React hides identity components; identity re-linked only at finalisation. |
| STU-08 | The system shall allow students, when enabled by the institution, to view their own released results, feedback and marked scripts. | C | CN-5, BP-2.2 | Student role with read-only policies restricted to own records and released results; React student view; disabled by default. |

## 5.4 Examination Setup and Marking Guides
Examination setup corresponds to Step 1 of the user workflow in Business Plan Section 3.3 and supplies the marking guide against which scripts are scored.

| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| EXM-01 | The system shall create an examination record with title, type (continuous assessment test, mid-term, mock, end-of-semester, national), course unit, class or programme, date, duration, total marks and grading scheme. | M | BP-3.3, CN-3 | exams table; Laravel resource controller and Form Request; React multi-step creation form. |
| EXM-02 | The system shall define the paper structure as sections and questions, each with type (multiple choice, short answer, numeric, structured/essay), maximum mark and, for multiple choice, the options. | M | BP-3.3, CN-3 | exam_sections and exam_questions tables; JSON column for type-specific settings; React paper builder with reorder and validation that question marks sum to the paper total. |
| EXM-03 | The system shall accept a marking guide as an uploaded document (PDF, DOCX or text) and as structured entries (model answer, key points, keywords, acceptable alternatives, marks per point) for each question. | M | CN-3, BP-3.2 | Upload to object storage plus a marking_points table; text extraction of uploaded guides by the Python service for retrieval indexing; React structured guide editor. |
| EXM-04 | The system shall support configurable multiple-choice keys, including more than one correct option, partial credit and negative marking. | M | BP-3.2, D | answer_keys table and scoring_rules JSON evaluated by Laravel ScoringService; the same rules supplied to the Python OMR endpoint; React key grid editor. |
| EXM-05 | The system shall support rubrics (criteria with performance levels and marks) for structured and essay questions. | S | CN-4.5, D | rubrics, rubric_criteria and rubric_levels tables; rubric serialised into the AI grading request; React rubric builder. |
| EXM-06 | The system shall manage an examination lifecycle of Draft, Ready, Sat, Scanning, Marking, Moderation, Finalised and Archived, and shall block transitions whose preconditions are unmet. | M | BP-3.3, D | Laravel state-machine service with guard conditions and audit entries; React status stepper that mirrors allowed transitions. |
| EXM-07 | The system shall version marking guides and keys and, if a guide changes after marking has begun, shall identify affected marks and offer re-evaluation without losing earlier results. | S | CN-4.3, D | Version column and history table; Laravel job recomputes OMR scores and re-requests AI suggestions; teacher-approved marks are never overwritten automatically. |
| EXM-08 | The system shall assign markers to sections or questions and shall distribute scripts among markers by rule (equal split, by section, manual). | M | CN-4.4, D | marker_assignments table; Laravel allocation service; React allocation board with workload indicators. |
| EXM-09 | The system shall support multiple paper versions (for example Version A and B) with separate keys within one examination. | S | BP-3.2, D | paper_versions table; version code printed on the sheet and read during identification; key lookup by version. |
| EXM-10 | The system shall allow an examination to be duplicated as a template for reuse. | C | D | Deep-copy service in Laravel excluding scripts and marks; React 'Duplicate' action. |

## 5.5 Personalised Answer Sheet Generation
Personalised, machine-readable answer sheets are the basis for batch processing at scale (Concept Note Section 6) and must work with ordinary printers and paper (Business Plan Section 3.1).

| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| SHT-01 | The system shall generate a printable answer sheet for each student carrying the student's name and registration number, examination details and a unique QR code and Code 128 barcode. | M | CN-3, BP-3.2 | Laravel PDF generation (Dompdf or Snappy) from a Blade template; QR and barcode libraries; per-student script record created at generation time. |
| SHT-02 | The system shall encode in each code only an opaque script identifier with a check value and signature, and shall not embed personal data in the code. | M | BP-9.1, D | Opaque UUID plus HMAC-SHA256 signature using a server-held key; Python service decodes, Laravel verifies the signature. |
| SHT-03 | The system shall print a code on every page that identifies the script and gives the page number and total pages, so that separated pages can be re-associated. | M | CN-4.2, CN-3 | Page-level payload (script id, page n of N) in each QR code; reassembly logic in the Python service and Laravel ingestion pipeline. |
| SHT-04 | The system shall lay out sheets with boxed answer regions, multiple-choice bubble grids and fiducial alignment marks, and shall record region coordinates per question for later cropping. | M | BP-9.1, CN-3 | Template engine producing both the PDF and a JSON layout (page, question, bounding box) stored in MySQL; the JSON is consumed by the Python service. |
| SHT-05 | The system shall generate sheets for a whole class or roster in one asynchronous batch, with progress reporting and a single downloadable PDF or ZIP. | M | BP-3.3 | Laravel queued job writing output to object storage; progress polled by React; signed download URL. |
| SHT-06 | The system shall reissue a replacement sheet for a student, invalidating the earlier code and recording the reason. | S | D | Script versioning with status 'voided'; ingestion rejects voided codes; audit entry. |
| SHT-07 | The system shall provide a fallback sheet with a registration-number bubble grid for candidates without a personalised sheet. | S | CN-4.2, D | Generic template with ID grid; Python service decodes bubbles; Laravel matches to roster or sends to exception queue. |
| SHT-08 | The system shall produce sheets that are legible and machine-readable when printed in monochrome on standard A4 paper. | M | BP-3.1, BP-5.2 | Black-and-white vector layout, minimum code module size and contrast defined in the template; print-and-scan acceptance test at 200 dpi. |
| SHT-09 | The system shall let authorised staff select and configure a sheet template per examination without developer involvement. | M | BP-3.3 | Template options stored per exam; React template selector with live preview. |

## 5.6 Script Capture and Ingestion
Capture must work with whatever scanner, photocopier or phone the institution already owns (Business Plan Section 3.4) and must tolerate unreliable connectivity (Business Plan Section 9.1).

| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| CAP-01 | The system shall accept scanned or photographed pages in PDF, JPEG and PNG formats from document scanners, multi-function printers and smartphone cameras. | M | BP-3.2, CN-3 | Laravel upload endpoint with MIME and magic-byte validation; React drag-and-drop uploader; PDF split into page images by the Python service. |
| CAP-02 | The system shall support multi-file and multi-page batch uploads, with a configurable maximum file size and a target of at least 1,000 pages per batch. | M | BP-3.3, UB-1 | Chunked, resumable upload API in Laravel; React uploader with parallel chunks; batch record in MySQL. |
| CAP-03 | The system shall allow smartphone capture within the web application, with live edge guidance and multi-page capture. | M | CN-3, BP-3.1 | React progressive web app using getUserMedia or the native camera file input; client-side cropping guidance; responsive layout. |
| CAP-04 | The system shall queue captured pages locally when offline and upload them automatically, with resume, when connectivity returns. | M | BP-9.1 | Web: service worker and IndexedDB queue in React. Desktop: encrypted local queue (DSK-05). Idempotent chunk endpoints in Laravel keyed by client-generated upload ID. |
| CAP-05 | The system shall warn immediately about poor captures (blur, low light, low resolution, cut-off edges) and offer a retake. | S | BP-9.1, CN-3 | Client-side quality heuristics in TypeScript; authoritative server-side check in the Python service returning a quality score. |
| CAP-06 | The system shall validate uploaded files for type, size and resolution (minimum 150 dpi equivalent) and shall scan them for malware before processing. | M | BP-9.1, D | Laravel validation rules; ClamAV scan in a queued job; Python service reports resolution; rejected files listed with reasons. |
| CAP-07 | The system shall store original images immutably in encrypted object storage and keep derived images (corrected, cropped, annotated) separately. | M | BP-3.2, CN-4.3 | Object storage with server-side encryption and versioning or object lock; originals written once; DB rows reference object keys and hashes. |
| CAP-08 | The system shall record each ingestion batch (batch ID, uploader, source, page count, status, timestamps) and shall show its progress. | M | D | ingestion_batches and ingested_pages tables; React batch status table updated by polling or broadcasting. |
| CAP-09 | The system shall process ingested pages asynchronously with retries and a dead-letter list, so that failures do not block other pages. | M | BP-8.1 | Laravel queue workers (database or Redis driver) call the Python service; exponential back-off; failed_jobs surfaced in the administration console. |
| CAP-10 | The system shall detect duplicate page uploads by content hash and report them. | S | D | SHA-256 per page stored with unique index per script and page; duplicate reported in batch summary. |
| CAP-11 | The system shall allow a scanning operator to upload on behalf of an institution, with every upload attributed to the operator. | C | BP-7.4 | Scanning-operator role with scoped tenant access; uploaded_by recorded in batch. |

## 5.7 Image Pre-processing and Student Identification
These requirements deliver Concept Note Objective 4.2: every scanned page is linked to the correct student, even in large batches.

| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| IDN-01 | The system shall pre-process each page image (orientation correction, deskew, perspective correction, cropping, denoising, contrast normalisation). | M | BP-8.2, CN-3 | Python service endpoint /v1/preprocess using OpenCV; output stored as derived image in object storage; parameters recorded. |
| IDN-02 | The system shall detect and decode the QR code or barcode on every page and return the script identifier, page number and a confidence value. | M | CN-4.2, CN-3 | Python endpoint /v1/identify using OpenCV QR detector and a barcode library; Laravel verifies HMAC signature and examination membership. |
| IDN-03 | The system shall group pages into scripts, order them by page number and detect missing, duplicate or foreign pages. | M | CN-4.2, BP-3.3 | Laravel PageAssembler service using decoded page numbers; per-script completeness status in MySQL. |
| IDN-04 | The system shall reject codes that belong to another examination or tenant, or that are voided, unsigned or malformed. | M | BP-9.1, D | Signature and scope checks in Laravel IdentificationService; rejected pages sent to the exception queue. |
| IDN-05 | The system shall send unidentified or ambiguous pages to an exception queue where staff can identify them manually with suggested candidates and registration-number lookup. | M | CN-4.2, CN-4.4 | exceptions table; React exception-resolution screen showing the page image and ranked candidates; resolution logged. |
| IDN-06 | The system shall fall back to printed registration-number recognition or the ID bubble grid when a code is damaged. | S | CN-4.2, D | Python endpoint combines OCR of the printed header and ID-grid OMR; result still passes through Laravel roster matching. |
| IDN-07 | The system shall report, per examination, enrolled students with no script (absent or missing) and scripts with missing pages. | M | BP-2.3, D | Laravel query comparing enrolments with identified scripts; React report with export. |
| IDN-08 | The system shall permit authorised staff to reassign a page to a different script, and shall log the change with reason. | M | CN-4.3, D | Reassignment endpoint with policy check; audit_logs entry; React drag or select control. |
| IDN-09 | The system shall crop the answer region of every question from each page using the stored template layout and shall store the crops. | M | CN-3, BP-3.3 | Python endpoint /v1/regions applies layout JSON and alignment marks; crops saved in object storage and linked to question and script. |
| IDN-10 | The system shall record identification method, confidence and reason codes for every page. | S | CN-4.3, D | identification_results table populated from the Python response and Laravel decisions. |

## 5.8 Objective Marking (OMR)
OMR marking supplies the near-100-percent objective scoring promised in Business Plan Section 3.2.

| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| OMR-01 | The system shall detect the multiple-choice grid on each page, read marked options and score them against the examination key. | M | CN-4.1, BP-3.2 | Python endpoint /v1/omr/read using OpenCV (grid registration, thresholding, fill-ratio); Laravel ScoringService applies the key and stores results. |
| OMR-02 | The system shall flag ambiguous responses (multiple marks, faint marks, erasures, blank) for teacher review using configurable thresholds. | M | CN-4.4, BP-3.2 | Fill-ratio thresholds in tenant settings sent with each request; ambiguous items written to the review queue with the cropped bubble image. |
| OMR-03 | The system shall store the detected raw responses so that scores can be recomputed when a key changes, without rescanning. | M | CN-4.3, D | omr_responses table (script, question, detected options, confidence); recompute job in Laravel. |
| OMR-04 | The system shall make objective scores available as soon as processing of a script completes. | M | BP-3.3, BP-3.2 | Event-driven pipeline; React review view updates by polling or broadcast. |
| OMR-05 | The system shall support up to ten options per question, multi-select questions, negative marking and partial credit as configured in the examination. | S | BP-3.2, D | Scoring rules interpreted by ScoringService; grid dimensions defined in the layout JSON. |
| OMR-06 | The system shall store a marked-bubble overlay image per script showing detected responses and correctness. | S | BP-3.2, CN-4.4 | Python renders overlay; stored as derived image; shown in React review. |
| OMR-07 | The system shall read numeric or coded bubble fields (for example paper version, index number digits). | S | CN-4.2, D | Template-defined bubble fields decoded by the same OMR endpoint. |

## 5.9 Handwriting Recognition (OCR)
Handwriting recognition turns cropped answer regions into text for AI-assisted grading and for searching the archive.

| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| OCR-01 | The system shall transcribe handwritten text in each cropped answer region and return the text with word-level and answer-level confidence. | M | CN-3, BP-3.2 | Python endpoint /v1/ocr/transcribe using a fine-tuned handwriting OCR or transformer model; results stored in ocr_results. |
| OCR-02 | The system shall flag low-confidence words and answers for teacher attention. | M | BP-9.1, CN-4.4 | Confidence threshold per tenant; React highlights uncertain words in the transcription. |
| OCR-03 | The system shall recognise handwritten numerals and numeric answers, including handwritten totals where used. | S | BP-3.2 | Dedicated numeric recogniser in the Python service; value range validation in Laravel. |
| OCR-04 | The system shall display the original image beside its transcription and shall let a teacher correct the transcription, retaining both versions. | M | CN-4.4, BP-3.2 | React side-by-side component with editable text; corrected_text stored beside machine text; audit entry. |
| OCR-05 | The system shall record the model name and version used for every transcription. | M | CN-4.5, D | model_version column on ocr_results; version returned by the Python service in each response. |
| OCR-06 | The system shall use teacher corrections to improve recognition only for institutions that have consented to this use. | S | BP-8.2, BP-9.1 | Consent flag per tenant; export job to an access-controlled training store; retraining offline in the Python service. |
| OCR-07 | The system shall detect content that cannot be transcribed (diagrams, graphs, heavy strike-outs) and route it to manual marking. | S | CN-4.4, D | Content-type classifier in the Python service; items flagged 'non-text' in the review queue. |
| OCR-08 | The system shall support English handwriting in the first release and shall allow further languages to be added without changing the Laravel or React code. | C | CN-6, BP-1.2 | Language code in the request contract; model selection inside the Python service. |

## 5.10 AI-Assisted Short Answer Grading
These requirements realise Concept Note Objective 4.5 and the Premium-tier grading module in Business Plan Section 6.1. The teacher always decides the final mark.

| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| AIG-01 | The system shall propose, for each short answer, a suggested mark, a confidence value and a rationale stating which marking-guide points were matched or missed. | M | CN-4.1, CN-3, BP-3.2 | Python endpoint /v1/grade/suggest receives question, guide points, rubric, transcription and maximum mark; returns structured JSON; Laravel stores in ai_suggestions. |
| AIG-02 | The system shall support keyword matching, semantic similarity and retrieval-augmented generation as selectable grading strategies, configurable per question. | M | CN-4.5 | Strategy plug-ins in the Python service (keyword and fuzzy matching, sentence embeddings, retrieval over guide text with a language model); strategy field in exam_questions. |
| AIG-03 | The system shall treat every AI suggestion as provisional and shall never finalise a mark without explicit teacher approval. | M | CN-4.4, BP-9.1 | Status machine (suggested, accepted, adjusted, rejected) enforced in Laravel; finalisation requires an authenticated human action. |
| AIG-04 | The system shall prioritise or flag low-confidence suggestions for review according to configurable thresholds. | M | BP-3.3, CN-4.4 | Threshold in tenant and exam settings; React queue sorting and badges. |
| AIG-05 | The system shall cap suggestions at the question maximum and shall respect the configured mark granularity (for example whole or half marks). | M | D | Validation in the Python response schema and again in Laravel. |
| AIG-06 | The system shall show matched key points and keywords highlighted in the student's answer to explain each suggestion. | S | CN-4.5, CN-4.4 | Python returns character spans for matches; React highlights them. |
| AIG-07 | The system shall record, for every question, the AI suggestion, the final teacher mark and the difference, and shall report agreement rate, mean absolute error and review time. | M | CN-4.5 | ai_suggestions and mark_events tables; Laravel analytics query; React evaluation dashboard. |
| AIG-08 | The system shall version models, prompts and strategy parameters, and shall make suggestions reproducible for a given version. | M | CN-4.5, D | model_version and config_hash stored per suggestion; fixed random seeds or zero temperature; Python service configuration under version control. |
| AIG-09 | The system shall restrict AI grading to tenants on the Premium tier and shall allow it to be switched off per examination. | S | BP-6.1, CN-4.4 | Feature flags in tenant subscription and exam settings; Laravel middleware and React conditional UI. |
| AIG-10 | The system shall send the AI service only the data needed for grading (pseudonymous answer ID and answer text) and, where an external language-model provider is used, only with tenant opt-in and a data-processing agreement. | M | BP-9.1, D | Request schema without student identity; outbound provider calls in Python only when the tenant flag is set; DPA recorded in tenant record. |
| AIG-11 | The system shall continue to work for manual and OMR marking when the AI service is unavailable. | M | BP-8.1, D | Laravel circuit breaker and timeouts; unavailable suggestions shown as 'pending'; manual marking unaffected. |
| AIG-12 | The system shall monitor suggestion accuracy across handwriting quality, subject and language style to detect systematic bias. | S | CN-4.5, D | Scheduled Laravel report slicing agreement metrics by OCR confidence band and subject; review by the quality owner. |
| AIG-13 | The system shall allow similar answers to be grouped so that a teacher can review and mark a group together. | C | CN-4.1, CN-4.5 | Embedding clusters computed by the Python service (/v1/cluster); React group view with bulk accept. |

## 5.11 Teacher Review and Moderation
The review interface keeps teachers in full control (Concept Note Objective 4.4) and corresponds to Step 4 of the user workflow.

| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| REV-01 | The system shall present each answer's image, transcription, marking guide, current mark and suggested mark together, with one-click accept and a quick adjust control. | M | BP-3.2, CN-4.4 | React review workspace (TypeScript components) fed by Laravel API resources; image URLs signed by Laravel. |
| REV-02 | The system shall provide work queues and filters by question, confidence, status, marker, student and flagged state. | M | CN-4.4, BP-3.3 | Server-side filtered, paginated endpoints; indexed columns; React filter bar. |
| REV-03 | The system shall allow a teacher to mark any question manually, whether or not OMR or AI results exist. | M | CN-4.4 | Manual mark endpoint writing a mark_event with source 'manual'; React numeric input with validation. |
| REV-04 | The system shall allow inline annotation (ticks, crosses, highlights, comments) on the scanned script, stored as an overlay without altering the original image. | M | BP-3.2, CN-4.4 | Annotation layer stored as JSON vector data per page; React canvas or SVG overlay; flattened copy rendered on demand for export. |
| REV-05 | The system shall let a teacher attach feedback comments to a question or script. | M | CN-3, CN-5 | feedback table linked to question marks; React comment panel; included in student feedback report. |
| REV-06 | The system shall support keyboard shortcuts and a teacher-initiated bulk accept of suggestions above a chosen confidence, with confirmation and logging. | S | CN-4.1, BP-3.2 | React hotkeys; Laravel bulk endpoint creating individual mark_events flagged as bulk. |
| REV-07 | The system shall require or invite a reason when a final mark differs from the suggestion by more than a configurable amount. | S | CN-4.5, D | Variance rule in exam settings; reason field enforced by Form Request. |
| REV-08 | The system shall allow marks for a script to be approved and locked, and shall allow unlocking only by an authorised role with a recorded reason. | M | CN-4.4, CN-4.3 | Script status 'locked'; Laravel policy; audit entry; React lock control. |
| REV-09 | The system shall support second marking and moderation, including blind second marking, discrepancy thresholds and arbitration by a moderator. | S | CN-5, BP-4.1, UB-1 | second_marks table; discrepancy rule evaluated by Laravel; React moderator queue; mandatory for examination-body mode. |
| REV-10 | The system shall prevent two users from editing the same script at once and shall prevent silent overwrites. | M | D | Optimistic locking (version column) and short-lived edit leases in Laravel; React shows lock owner and conflict messages. |
| REV-11 | The system shall autosave work and recover it after a connection loss or browser crash. | M | BP-9.1, D | Debounced autosave to API; local draft in IndexedDB until acknowledged. |
| REV-12 | The system shall show marking progress per examination, marker and question. | M | BP-1.4, D | Aggregate endpoint in Laravel; React progress bars. |
| REV-13 | The system shall record every mark change with user, time, previous value, new value and source (OMR, AI, manual, bulk, moderation). | M | CN-4.3, BP-8.1 | mark_events append-only table written in the same DB transaction as the mark change; no update or delete permitted to the application user. |
| REV-14 | The system shall allow a script to be flagged for investigation (for example suspected malpractice) with a note and restricted visibility. | C | CN-5, D | script_flags table; policy limits visibility to examination officers; React flag action. |
| REV-15 | The review interface shall be usable on tablets and on laptops with a 1366 by 768 display. | M | BP-3.1, CN-3 | Responsive React layouts with breakpoint tests; touch-friendly controls. |

## 5.12 Mark Compilation and Results
Automatic compilation removes manual arithmetic, which Business Plan Section 4.2 identifies as a leading pain point.

| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| MRK-01 | The system shall aggregate question marks into section and script totals automatically, with no manual arithmetic. | M | BP-1.1, BP-3.2 | Laravel ScoreAggregator service run in a database transaction when marks change; totals stored with a computed version. |
| MRK-02 | The system shall validate before finalisation that all questions are marked or explicitly zero-scored, that no total exceeds the maximum, and shall list any incomplete scripts. | M | CN-4.3, D | Pre-finalisation validator returning a structured issue list; React checklist dialog. |
| MRK-03 | The system shall compute class statistics (mean, median, standard deviation, minimum, maximum, pass rate and grade distribution). | M | BP-3.2 | SQL aggregates and Laravel statistics service; React charts. |
| MRK-04 | The system shall convert totals to grades, grade points or divisions using the configured grading scheme. | M | BP-3.2, D | GradeCalculator service; scheme version recorded with the result. |
| MRK-05 | The system shall combine multiple assessments with configurable weights (for example continuous assessment plus final examination) into a course result. | S | UB-1, BP-4.1 | assessment_components and weight tables; Laravel CourseResultService; React weighting editor. |
| MRK-06 | The system shall support result statuses for absent, incomplete, malpractice and deferred candidates. | S | D | Enumerated result_status with rules in aggregator; shown on mark sheets. |
| MRK-07 | The system shall allow an authorised moderator to apply a documented scaling or adjustment to a set of marks, preserving the original marks. | C | CN-5, UB-1 | adjustments table with parameters and reason; original marks retained; React adjustment wizard with preview. |
| MRK-08 | The system shall finalise results as an immutable, versioned record and shall apply later corrections as new versions. | M | CN-4.3, BP-3.4 | results table with version and status; finalise action locks scripts; corrections create version n+1 with audit entry. |
| MRK-09 | The system shall control when results become visible to students or are released to external systems. | S | CN-5, UB-2 | release_status per examination; Laravel policy gates student and integration access. |
| MRK-10 | The system shall recompute totals, grades and statistics when a re-mark or correction is accepted. | M | CN-4.3, D | Event listener on mark_event triggers aggregation job; downstream outbound-sync event raised. |

## 5.13 Digital Archive
The permanent archive is a first-class feature (Business Plan Section 3.4) and delivers Concept Note Objective 4.3.

| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| ARC-01 | The system shall commit every finalised script to the archive, with original images, annotated images, transcriptions, marks, feedback and audit history. | M | CN-4.3, BP-3.2 | Laravel archive job copies references and writes an archive manifest (JSON) to object storage; archive_records row in MySQL. |
| ARC-02 | The system shall index archived scripts by registration number, name, academic year, term, class or programme, course unit and examination type. | M | BP-3.2, CN-4.3 | Indexed archive_records columns; optional MySQL full-text index; React advanced-search form. |
| ARC-03 | The system shall provide full-text search over transcribed answers, restricted to roles permitted to see them. | S | CN-4.3, CN-6 | MySQL FULLTEXT on ocr text, filtered by policy; React result list with highlighting. |
| ARC-04 | The system shall retrieve and display an archived script (all pages, annotations, marks) with zoom and navigation, showing the first page in under 3 seconds under normal load. | M | CN-4.3, BP-1.3 | Signed, short-lived object URLs; pre-generated thumbnails and web-sized derivatives; React image viewer. |
| ARC-05 | The system shall provide tamper evidence by storing a SHA-256 hash for each stored file and a chained hash for each script manifest, and shall verify them on a schedule and on retrieval. | M | BP-3.4, CN-4.3 | Hash columns in MySQL; scheduled Laravel verification job; object-lock or versioning on the bucket; mismatch alerts to administrators. |
| ARC-06 | The system shall apply configurable retention periods by examination type, support legal holds, and require approval and an audit entry for any deletion. | M | CN-4.3, BP-9.1 | retention_policies and legal_holds tables; scheduled Laravel retention job proposing deletions for approval; policy defaults to retain. |
| ARC-07 | The system shall back up all data and files automatically every day and shall allow a restore to be performed and tested. | M | BP-7.4, BP-8.2 | Scheduled MySQL dumps and bucket replication or versioning managed by the DevOps pipeline; quarterly restore drill documented. |
| ARC-08 | The system shall export a script bundle (annotated PDF with marks and audit extract) for appeals, audits or transfer. | M | CN-4.3, BP-1.3 | Laravel export job compositing derived images into PDF (PDF/A where feasible); signed download URL. |
| ARC-09 | The system shall log every archive retrieval and export with user, time and purpose. | M | CN-4.3, BP-9.1 | access_logs table written by archive controller; viewable by auditors. |
| ARC-10 | The system shall archive the examination artefacts (paper structure, marking guide versions, sheet template, grading scheme) together with the scripts. | M | CN-4.3, D | Manifest includes versioned artefact references; immutable once archived. |
| ARC-11 | The system shall allow an institution to export all of its archived data in open formats (PDF, JSON, CSV) on request or at contract end. | M | UB-2, D | Laravel bulk export job with manifest and checksums; large exports delivered through object storage. |
| ARC-12 | The system shall preserve archived images and documents in long-term readable formats and migrate formats if standards change. | S | CN-4.3, BP-1.2 | Standard formats (PNG/JPEG/PDF); format registry and migration job; periodic readability check. |

## 5.14 Re-marking and Appeals
Re-marking, appeals and audit are named in Concept Note Objective 4.3 and in the dispute scenarios of Business Plan Section 2.

| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| APL-01 | The system shall record a re-mark or appeal request for a script, with requester, reason, date and fee status if applicable. | M | CN-4.3, BP-2.3 | appeals table; Laravel endpoint restricted to authorised requesters; React request form. |
| APL-02 | The system shall manage appeals through the states Submitted, Assigned, Re-marked, Decided and Communicated, with configurable target times. | S | CN-4.3, D | Appeal state machine and due-date fields; React appeals board; overdue indicator. |
| APL-03 | The system shall let an appeal be re-marked by a different marker, optionally without sight of the original marks, while retaining all earlier mark versions. | M | CN-4.3, CN-4.4 | Re-mark creates a new mark set linked to the appeal; blind option uses blind-marking view; history retained in mark_events. |
| APL-04 | The system shall apply the institution's configured outcome rule (for example highest mark, re-mark mark, or moderated mark) and shall issue a revised result version. | S | CN-4.3, D | outcome_rule setting; MRK finalisation service creates version n+1; notification sent. |
| APL-05 | The system shall retrieve the archived script for an appeal within the response time set in ARC requirements and shall log that access. | M | CN-4.3, BP-1.3 | Direct link from appeal to archive viewer; access log entry. |
| APL-06 | The system shall produce a decision letter or notice from a template, for sending by the institution. | C | D | Blade-to-PDF template; stored in the appeal record. |

## 5.15 Reporting, Analytics and Export
Reporting delivers Concept Note Objective 4.6 and the analytics benefit described in Concept Note Section 6.

| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| RPT-01 | The system shall provide standard reports: class mark sheet, student report card or transcript, course unit summary, marker progress and exception reports. | M | CN-4.6, BP-3.2 | Laravel report classes querying MySQL; React report viewer with filters. |
| RPT-02 | The system shall export reports and mark sheets to CSV, Excel (XLSX) and PDF. | M | CN-4.6, BP-3.2 | Laravel Excel for CSV and XLSX; Dompdf for PDF; downloads as signed URLs. |
| RPT-03 | The system shall provide item analysis per question (difficulty index, discrimination index, option distribution, most-missed items). | S | CN-6, BP-1.3 | SQL and Laravel analytics service over omr_responses and mark_events; React charts. |
| RPT-04 | The system shall analyse marking consistency (differences between markers, between streams, and between AI suggestions and teacher marks). | S | CN-6, BP-2.1 | Statistical comparison queries; summary tables refreshed by scheduled jobs; React comparison dashboards. |
| RPT-05 | The system shall show performance trends across terms, years and cohorts. | S | CN-6, BP-2.3 | Materialised summary tables by term and cohort; React trend charts. |
| RPT-06 | The system shall provide role-appropriate dashboards for teachers, directors of studies or heads of department, administrators and examination-body staff. | M | BP-1.3, UB-1 | Role-specific React dashboard layouts populated from permission-filtered API endpoints. |
| RPT-07 | The system shall generate large reports asynchronously and, optionally, on a schedule. | C | D | Queued Laravel report jobs and scheduler; notification on completion. |
| RPT-08 | The system shall brand reports with the institution's logo and a configurable signature or stamp block. | S | BP-1.1, D | Blade report templates using tenant settings and stored image assets. |
| RPT-09 | The system shall produce a per-student feedback report with question marks, comments and class comparison where permitted. | S | CN-5, CN-3 | Laravel feedback report template; privacy settings control comparison data. |
| RPT-10 | The system shall log all exports (who, what, when) and shall let administrators restrict export rights. | S | BP-9.1, D | export_logs table; export permissions in RBAC. |

## 5.16 Optional AI Assistant
Concept Note Section 3 describes an optional AI assistant for report generation and performance queries. These requirements apply only if the feature is enabled.

| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| AST-01 | The system shall let authorised users ask natural-language questions about performance data and receive answers computed from permitted data only. | C | CN-3 | React chat panel; Laravel endpoint sends the question to the Python service, which returns a structured query plan; Laravel executes it under the caller's permissions. |
| AST-02 | The system shall draft narrative summaries for reports (for example class performance commentary) for the user to edit before use. | C | CN-3, CN-4.6 | Python service generates text from computed statistics supplied by Laravel; React editor with explicit 'draft' labelling. |
| AST-03 | The assistant shall be read-only, shall inherit the caller's access rights, and shall show the data and query behind every answer. | C | BP-9.1, D | Assistant executes only whitelisted, parameterised report queries; no direct database access from the AI service; React shows source table. |
| AST-04 | The assistant shall decline requests outside its scope and shall not present unverified figures as fact. | C | D | Answer composition only from returned query results; refusal templates; automated regression prompts. |
| AST-05 | The system shall log assistant interactions and shall allow an institution to disable the assistant. | C | BP-9.1, D | assistant_logs table; tenant feature flag. |

## 5.17 Integration with Existing Academic Information Systems
The system will later integrate with existing academic information systems (student information systems, registrars' systems, school management systems). Integration is therefore a design driver now and is realised through a stable API and a connector pattern.

| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| INT-01 | The system shall expose a versioned REST API, documented in OpenAPI 3, for students, programmes, course units, enrolments, examinations, results and archive metadata. | M | UB-2, CN-4.6, BP-8.1 | Laravel API resources under /api/v1 with generated OpenAPI documentation; semantic versioning of endpoints. |
| INT-02 | The system shall authenticate integration clients with OAuth 2.0 client credentials or scoped API keys, per tenant, with rate limits and revocation. | M | UB-2, BP-9.1 | Laravel Passport or Sanctum token abilities; integration_clients table; throttle middleware; React administration screen for keys. |
| INT-03 | The system shall import student, programme, course unit, enrolment and academic calendar data from an academic information system by scheduled API pull, push, or file exchange (CSV or SFTP) as a fallback. | M | UB-2, CN-4.6 | Laravel scheduled import jobs through a connector interface; SFTP and CSV adapters; mapping tables; same validation as manual import. |
| INT-04 | The system shall export finalised marks, grades and result status to an academic information system, on request or automatically on release, using configurable field mappings. | M | UB-2, BP-1.4, CN-4.6 | Outbound ResultExporter per connector; field mapping stored per tenant; React mapping editor; idempotent updates keyed by external IDs. |
| INT-05 | The system shall implement each external system as an adapter behind a common connector interface so that new systems can be added without changing core modules. | M | UB-2 | Laravel IntegrationConnector interface and service-provider registration; adapter-specific configuration encrypted in MySQL; contract tests per adapter. |
| INT-06 | The system shall store external identifiers for students, programmes, course units and examinations and shall reconcile records, reporting unmatched and conflicting items. | M | UB-2, CN-4.2 | external_ids table (system, entity, internal id, external id); reconciliation report in React. |
| INT-07 | The system shall treat the academic information system as authoritative for student master data and itself as authoritative for marks, scripts and archive data, and shall apply this rule in all synchronisation. | M | UB-2, D | Conflict policy encoded in sync services; overwrites of master data allowed only from the connector. |
| INT-08 | The system shall publish signed webhook events (for example exam finalised, results released, script archived) with retry and delivery log. | S | UB-2, BP-8.1 | Laravel events dispatched to a webhook queue; HMAC signature header; delivery attempts table. |
| INT-09 | The system shall log every synchronisation run (counts, errors, duration), alert administrators to failures and allow retry. | M | UB-2, D | sync_runs table; Laravel notifications; React integration console with retry button. |
| INT-10 | The system shall provide field mappings or templates compatible with common interchange standards (for example IMS OneRoster for rosters and results) where the target system supports them. | C | UB-2 | Mapping profiles in the connector layer; CSV templates generated by Laravel Excel. |
| INT-11 | The system shall provide a sandbox tenant and developer documentation for integration testing. | S | UB-2, D | Separate tenant with synthetic data; docs generated from OpenAPI; Docker-based staging environment. |
| INT-12 | The Laravel backend shall call the Python AI service only through an internal, versioned, authenticated REST contract with timeouts, retries and idempotency keys. | M | BP-8.1, UB-3 | Laravel HTTP client with service token or mutual TLS; Python service (Flask) not exposed publicly; contract defined in Section 9.3 and tested with contract tests. |
| INT-13 | The system shall support mobile-network SMS and email as outbound channels through replaceable gateway adapters. | S | BP-6.2, D | Laravel notification channels implementing gateway interface; configuration per tenant. |

## 5.18 Notifications
| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| NOT-01 | The system shall notify users in the application and by email of relevant events (batch processed, exceptions raised, marking assigned, marking overdue, results released, appeal updates). | M | BP-3.3, D | Laravel notifications (database and mail channels); React notification centre. |
| NOT-02 | The system shall support optional SMS notifications through a gateway. | C | BP-6.2 | Custom Laravel SMS channel; tenant-level enablement. |
| NOT-03 | The system shall let users set notification preferences, except for mandatory security notices. | C | D | notification_preferences table; React settings page. |
| NOT-04 | The system shall render notification text from editable templates. | C | D | Blade or database templates with placeholders; i18n-ready. |

## 5.19 Subscription, Billing and Usage
These requirements support the commercial model in Business Plan Section 6 (Standard and Premium tiers, onboarding fee, optional pay-per-scan). They are lower priority for institutional deployments that are licensed under separate contracts.

| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| BIL-01 | The system shall manage tenant subscription tiers (Standard and Premium) and use them to enable or disable features such as AI grading and advanced analytics. | S | BP-6.1 | subscriptions table; Laravel feature-flag service (middleware and policy checks); React plan page. |
| BIL-02 | The system shall compute termly charges per active enrolled student in UGX and shall generate invoices. | S | BP-6.1, BP-8.5 | Laravel billing job per term using enrolment counts; Blade-to-PDF invoice; invoices table. |
| BIL-03 | The system shall record one-off onboarding and training fees and pilot discounts. | C | BP-6.1, BP-7.2 | Invoice line items with type and discount fields. |
| BIL-04 | The system shall meter scans and storage per tenant and support an optional pay-per-scan plan. | C | BP-6.1 | usage_events table incremented in the ingestion pipeline; monthly aggregation. |
| BIL-05 | The system shall provide a billing console for administrators of the service provider, showing invoices, payments and usage. | S | BP-6.1 | Laravel admin routes (system administrator role); React billing screens. |
| BIL-06 | The system shall allow payments to be recorded manually and may integrate with mobile-money or bank gateways. | C | D | payments table; gateway adapter interface. |

## 5.20 Administration, Audit and Support
| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| ADM-01 | The system shall provide an administration console for configurable settings (thresholds, retention, templates, integrations, feature flags). | M | BP-3.3, D | Settings service with validated schemas; React admin screens; changes audited. |
| ADM-02 | The system shall record security-relevant and data-changing events in an append-only audit log that can be searched and exported by auditors. | M | CN-4.3, BP-8.1 | audit_logs table (actor, action, entity, before and after, IP, time) with no update or delete permission; React audit viewer. |
| ADM-03 | The system shall provide an operations dashboard showing queue depth, processing times, AI service health, storage use and error rates. | S | BP-8.2, D | Laravel Horizon or queue metrics, health-check endpoints on all services, container metrics; React or Grafana view. |
| ADM-04 | The system shall provide an onboarding wizard that guides an institution from roster import to the first examination. | S | BP-7.2, BP-9.1 | React stepper using existing import and exam APIs; progress stored per tenant. |
| ADM-05 | The system shall provide in-application help, short training guides and a way to send feedback or request support. | S | BP-7.2, CN-4.5 | React help panel with static content; Laravel support-request endpoint and mail forwarding. |
| ADM-06 | The system shall collect user satisfaction ratings and time-saved estimates at the end of a marking cycle. | S | CN-4.5, BP-1.4 | In-app survey stored in survey_responses; summary in evaluation dashboard. |
| ADM-07 | The system shall allow support staff to view a tenant's configuration and diagnostics only with that tenant's authorisation, and shall log such access. | S | BP-9.1, D | Time-limited support grant created by tenant administrator; access logged. |

## 5.21 Desktop Application
Custograde is delivered as a web application and as a desktop application. The desktop application uses the same React and TypeScript interface and the same Laravel API, and adds device-level capabilities that a browser cannot offer reliably: direct scanner control, folder watching, encrypted offline storage and silent printing. Electron is the reference desktop shell because it reuses the React and TypeScript code base; the choice is recorded as open issue OI-09.

| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| DSK-01 | The system shall be delivered as a web application and as a desktop application for Windows 10 and 11 (64-bit), and, as a Should, for macOS and Ubuntu LTS, offering the same functions from one React and TypeScript code base. | M | UB-4, UB-3 | Shared React and TypeScript UI package; Electron shell (TypeScript main process) loading the bundled build; no business logic in the shell; Laravel API unchanged; CI builds web and desktop artefacts from the same commit. |
| DSK-02 | The desktop application shall authenticate against the same Laravel API and institutional identity providers, using the system browser and a PKCE redirect, and shall keep tokens in the operating system credential store. | M | UB-4, BP-9.1 | Laravel Passport or Sanctum with OIDC/SAML flows (AUT-03); Electron safeStorage or keytar (Windows Credential Manager, macOS Keychain, libsecret); no tokens in plain files. |
| DSK-03 | The desktop application shall acquire pages directly from locally connected scanners (including automatic document feeders and duplex), with selectable resolution (200 dpi minimum) and colour mode, and shall place the pages in the upload queue. | M | UB-4, BP-3.2, BP-7.4 | Scanner helper process using WIA and TWAIN on Windows, ICA on macOS and SANE on Linux, called from the Electron main process over an allow-listed IPC bridge; React capture screen; images pass through the same ingestion pipeline (CAP). |
| DSK-04 | The desktop application shall watch configured folders (for example scan-to-folder or network shares) and ingest new files automatically, without ingesting incomplete or duplicate files. | S | UB-4, BP-3.2 | File watcher (for example chokidar) in the main process with file-stability checks; SHA-256 de-duplication (CAP-10); batches recorded through the standard batch API. |
| DSK-05 | The desktop application shall store captured pages in an encrypted local queue when offline and shall synchronise them automatically, with resume, when connectivity returns. | M | UB-4, BP-9.1 | Local store (SQLite with encryption, or encrypted files) keyed through the OS credential store; reuse of the chunked, idempotent upload API; queue status shown in React and in the system tray. |
| DSK-06 | The desktop application should cache rosters, sheet layouts and marking guides so that review and manual marking can continue offline, and shall report any conflict with server changes when it reconnects. | S | UB-4, BP-9.1, CN-4.4 | Local cache of permitted data only; version column and optimistic locking in Laravel (REV-10); conflict responses rendered by a React resolution dialog; cache cleared on sign-out. |
| DSK-07 | The desktop application shall update itself from signed releases, support staged rollout and rollback, and the API shall be able to require a minimum client version. | M | UB-4, D | Code-signed installers; electron-updater with release artefacts in object storage; Laravel middleware that reads the X-Client-Version header and returns an upgrade-required response; CI release pipeline. |
| DSK-08 | The desktop application shall follow secure-shell practice: context isolation, no Node access from the interface, a strict content security policy, an allow-listed IPC bridge, loading only bundled code or the institution's HTTPS origin, and local data wipe when the account is deactivated or signed out. | M | UB-4, BP-9.1 | Electron security configuration enforced by lint rules and tests; signed IPC schemas in TypeScript; Laravel session-revocation signal consumed by the client. |
| DSK-09 | The desktop application shall print answer sheet batches to a selected printer with preview and duplex options, and shall save generated files to a chosen folder. | S | UB-4, BP-3.2 | Print service in the main process using the PDF generated by Laravel (SHT-05); React print dialog; folder picker through the IPC bridge. |
| DSK-10 | The desktop application shall upload large batches in the background with pause, resume and bandwidth limits, and shall continue while the window is minimised. | S | UB-4, BP-9.1 | Parallel chunk worker in the main process; throttle setting in tenant and user preferences; system tray indicator. |
| DSK-11 | The desktop application shall install per user without administrator rights where possible, shall provide an MSI or equivalent package for silent institutional deployment, and shall accept a managed configuration (server address, tenant) from a file or policy. | M | UB-4, BP-7.2 | electron-builder (NSIS and MSI targets); managed JSON configuration read at start; deployment guide for institutional IT staff. |
| DSK-12 | The desktop application shall provide opt-in crash reporting and a diagnostic bundle that contains logs with correlation identifiers but no personal data. | S | UB-4, D | Crash reporter with explicit consent flag; log redaction in the shell; bundle export through the IPC bridge. |
| DSK-13 | The desktop application shall start within 5 seconds and process a 1,000-page batch on a computer with a dual-core processor and 4 GB of memory. | S | UB-4, BP-2.4 | Lazy-loaded modules; streamed file reads; memory profiling in the release checklist. |

# 6. External Interface Requirements
## 6.1 User Interface Requirements
| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| UIF-01 | The user interface shall be a single-page React application written in TypeScript, served over HTTPS for use in a browser without installation, and packaged from the same code base as the interface of the desktop application. | M | BP-3.1, UB-3, UB-4 | React 18 with TypeScript strict mode, Vite or equivalent build, React Router; static assets served by the web tier; shared UI package consumed by the web build and the desktop shell (DSK-01). |
| UIF-02 | The interface shall be responsive from 360 px wide phones to desktop displays, with touch-friendly controls for capture and review. | M | BP-3.1, CN-3 | Mobile-first CSS (utility classes or CSS modules), breakpoint tests in the component test suite. |
| UIF-03 | The interface shall use one consistent component library and design tokens, and shall present clear status, progress and error messages for long-running operations. | S | BP-9.1, D | Shared React component library; centralised API error handling and toast pattern. |
| UIF-04 | The interface shall provide a smartphone capture mode as part of the same application (progressive web app). | M | CN-3, BP-3.2 | Web app manifest, service worker, camera access via browser APIs. |

## 6.2 Hardware Interface Requirements
| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| HWI-01 | The system shall accept images from any scanner, photocopier or camera that can produce PDF, JPEG or PNG files at 200 dpi or higher (300 dpi recommended), without proprietary software; in addition, the desktop application shall acquire images directly from scanners that provide standard drivers (DSK-03). | M | BP-3.4, BP-5.2, UB-4 | File-based ingestion is always available; resolution checks in the Python service; device access is confined to the desktop shell and never required. |
| HWI-02 | The system shall produce sheets that print correctly on standard A4 paper using ordinary monochrome laser or inkjet printers. | M | BP-3.1, BP-2.4 | Vector PDF templates; print test on representative printers. |
| HWI-03 | The system shall work with smartphone cameras of 8 megapixels or more under ordinary classroom lighting. | M | CN-3, BP-3.1 | Capture guidance and quality scoring; acceptance test on three representative phones. |

## 6.3 Software Interface Requirements
| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| SWI-01 | The Laravel backend shall use MySQL 8 (InnoDB, utf8mb4) as the relational store and shall access it only through the Laravel database layer. | M | BP-8.1, UB-3 | Eloquent models, migrations under version control, foreign-key constraints. |
| SWI-02 | The platform shall store scanned images and archives in S3-compatible, encrypted object storage, accessed through Laravel's filesystem abstraction and, for the Python service, through short-lived pre-signed URLs. | M | BP-8.1, BP-8.2 | Flysystem S3 driver; bucket policies; server-side encryption and versioning. |
| SWI-03 | The Python AI service shall be an independently deployable external service (Flask, as in the Business Plan) exposing the REST contract in Section 9.3 and holding no tenant master data. | M | UB-3, BP-8.1 | Flask application with gunicorn, containerised separately; stateless workers; OpenAPI description; contract tests. |
| SWI-04 | The system shall integrate with outbound email (SMTP or API) and optional SMS gateways, and with external academic information systems through the connector interface in INT. | S | BP-6.2, UB-2 | Laravel mail and notification channels; connector adapters. |

## 6.4 Communications Interface Requirements
| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| COM-01 | All client-to-server traffic shall use HTTPS with TLS 1.2 or higher and HTTP Strict Transport Security. | M | BP-7.4, BP-8.2 | Automated certificate issuance and renewal; reverse-proxy TLS configuration. |
| COM-02 | The system shall remain usable on connections of 2 Mbps, compressing images on the client before upload where this does not reduce recognition quality. | S | BP-9.1, BP-2.4 | Client-side image resizing to a defined target; chunked upload; bundle splitting. |
| COM-03 | Traffic between Laravel, the Python service, the database and object storage shall use a private network segment and encrypted connections. | M | BP-7.4, D | Docker network isolation, security groups, TLS or mutual TLS between services. |

# 7. Data Requirements
## 7.1 Logical data entities
The following entities form the logical data model. Detailed schemas are a design deliverable; this table fixes the scope and the storage location of each entity.

| Entity group | Principal entities and key attributes | Store | Retention |
| --- | --- | --- | --- |
| Tenancy and access | institutions, users, roles, permissions, role assignments, sessions, identity-provider settings, integration clients | MySQL | Life of contract plus audit period |
| Academic structure | org units, programmes, course units, academic years, terms, grading schemes and bands | MySQL | Permanent |
| Students | students (registration number, names, status), enrolments, status history, external identifiers | MySQL | Permanent while records archived |
| Examination set-up | exams, sections, questions, answer keys, marking points, rubrics, paper versions, sheet templates and layouts, marker assignments | MySQL; documents in object storage | Retained with archive |
| Scripts and capture | scripts, pages, ingestion batches, page hashes, identification results, exceptions, derived images and crops | MySQL; images in object storage | Originals permanent; derivatives reproducible |
| Recognition and marking | OMR responses, OCR results, AI suggestions (model version, confidence, rationale), marks, mark events, annotations, feedback, second marks, flags | MySQL; overlays in object storage | Retained with archive |
| Results | results (versioned), course results, assessment weights, adjustments, release status | MySQL | Permanent |
| Archive | archive records, manifests, file hashes, retention policies, legal holds, access logs, export logs | MySQL; manifests and bundles in object storage | Per retention policy |
| Appeals | appeals, decisions, notices | MySQL; notices in object storage | Retained with archive |
| Integration | connectors, field mappings, external IDs, sync runs, webhook deliveries | MySQL | Operational log retention |
| Commercial | subscriptions, invoices, payments, usage events | MySQL; invoices in object storage | Statutory financial retention |
| Governance | audit logs, notification records, settings, model registry entries, survey responses | MySQL | At least as long as the records described |

## 7.2 Data management requirements
| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| DAT-01 | The system shall use a normalised relational schema with primary keys, foreign keys and unique constraints, created and evolved only through version-controlled, reversible migrations. | M | BP-8.1, UB-3 | Laravel migrations and Eloquent models; schema reviewed in code review; migration run in CI against a clean database. |
| DAT-02 | The system shall validate data at every entry point (forms, imports, API, synchronisation) for required fields, formats, ranges and referential integrity before storing it. | M | CN-4.2, D | Laravel Form Requests and import validators; TypeScript types and client validation in React as a first line only. |
| DAT-03 | Every business record shall carry creation and update timestamps and the acting user, and business entities shall use soft deletion so that history is preserved. | M | CN-4.3, D | Eloquent timestamps and trait for created_by, updated_by, deleted_at. |
| DAT-04 | The system shall classify data (personal, examination-confidential, operational) and shall apply access, encryption and retention rules according to the class. | S | BP-9.1, D | Classification field in the data inventory; policies and storage settings derived from it. |
| DAT-05 | The system shall import legacy results held in spreadsheets so that earlier assessments can be searched and analysed with new ones. | S | BP-2.1, CN-2 | Laravel Excel import profile mapping spreadsheet columns to result records flagged as 'legacy'; React import wizard. |
| DAT-06 | The system shall store all timestamps in UTC and present them in the institution's time zone. | M | D | Laravel timezone configuration; React date formatting using tenant locale. |

# 8. Non-Functional Requirements
## 8.1 Performance
Values marked as proposed targets are to be confirmed during the pilot (Business Plan Section 9.2, Months 3 to 4).

| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| PRF-01 | At least 95 percent of interactive API requests (excluding file transfer and report generation) shall complete within 1 second at 500 concurrent users, and main screens shall be usable within 3 seconds on a 2 Mbps connection. | M | BP-3.1, D | Indexed queries, eager loading, response caching, code-split React bundles, compression; load tests with k6 against staging. |
| PRF-02 | The Python service shall average no more than 5 seconds per page for OMR scoring and 3 seconds per page for identification on baseline hardware. | M | CN-4.1, D | Optimised OpenCV pipelines; benchmark suite in CI; horizontal worker scaling. |
| PRF-03 | OCR and AI suggestions for a page of up to ten answers shall be delivered within 60 seconds without blocking other work. | S | CN-4.1, D | Asynchronous queue; model warm-up; React shows pending state. |
| PRF-04 | A batch of 1,000 pages shall be ingested, identified and OMR-scored within 60 minutes on the baseline production deployment, with throughput increasing by adding Python workers. | M | CN-4.1, UB-1, BP-1.1 | Parallel queue workers; chunked upload; worker autoscaling by queue depth. |
| PRF-05 | Archive search and filtering shall return within 3 seconds for an archive of 1,000,000 scripts. | S | CN-4.3, D | Composite indexes, pagination, partitioning of archive tables by year. |

## 8.2 Scalability and Capacity
| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| SCL-01 | The system shall support 10,000 active students in Year 1, 100 institutions in Year 3 and at least 100,000 active students by Year 3 without architectural change. | M | BP-1.4, BP-4.3 | Stateless Laravel containers behind a load balancer; independent queue and Python workers; read replicas and partitioning when needed. |
| SCL-02 | Object storage shall be sized for at least 1 TB of new scripts per year at Year 1 scale (planning estimate: 10,000 students, 3 terms, 6 papers, 8 pages, 0.4 MB per page, about 0.58 TB), with monitoring and alerts at 70 percent use. | M | BP-4.3, D | Capacity model maintained in operations documentation; bucket metrics and alerts; lifecycle rules for cold storage. |
| SCL-03 | The Python AI service shall scale independently of the Laravel backend and shall tolerate surges of at least three times average load at the end of each term. | M | BP-7.3, BP-9.1 | Separate container group; queue-based load levelling; autoscaling rules. |

## 8.3 Availability, Reliability and Recoverability
| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| AVL-01 | The system shall achieve at least 99.5 percent monthly availability during announced examination and marking windows, excluding planned maintenance. | S | BP-7.4, D | Redundant application containers, health checks and automatic restart, managed MySQL, monitored uptime. |
| AVL-02 | Recovery shall meet a recovery point objective of 24 hours and a recovery time objective of 8 hours, supported by automated daily backups. | M | BP-7.4, BP-8.2 | Daily MySQL dumps, object-storage versioning and replication, documented and rehearsed restore procedure. |
| AVL-03 | Once the system acknowledges receipt of a page, it shall not lose it; acknowledgement shall follow a durable write to object storage and a committed database record. | M | BP-2.1, CN-1 | Two-phase write order in the upload controller; checksum verification; reconciliation job comparing records to objects. |
| AVL-04 | Failure of a non-core component (AI service, notifications, analytics) shall not prevent users from viewing, marking or finalising work. | S | BP-8.1, D | Circuit breakers, timeouts, queue isolation, degraded-mode messaging in React. |
| AVL-05 | Mark and result changes shall be transactional and consistent, enforced by database constraints. | M | CN-4.3, D | InnoDB transactions, foreign keys, unique constraints, optimistic locking. |

## 8.4 Accuracy and Effectiveness
These measurable targets make the business objectives testable. Values other than those stated in the source documents are proposed targets to be confirmed in the pilot.

| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| ACC-01 | OMR scoring shall read at least 99.5 percent of bubbles correctly on compliant scans, shall flag uncertain marks rather than guess, and shall be verified on a test set of at least 2,000 sheets. | M | BP-3.2, CN-4.1 | Labelled test set; automated regression run for every OMR model or threshold change; results archived. |
| ACC-02 | At least 99 percent of pages shall be identified automatically on compliant scans and no more than 0.1 percent shall be assigned to the wrong script before human review. | M | CN-4.2 | Test batches of at least 5,000 pages; confusion report; exceptions routed to manual identification. |
| ACC-03 | Handwriting recognition quality shall be measured as word error rate on a pilot sample of local handwriting, with a baseline target of 15 percent or lower on legible writing and with calibrated confidence values. | S | BP-9.1, CN-4.5 | Pilot sample collected with consent; evaluation script in the Python repository; calibration plot reported. |
| ACC-04 | For each subject, at least 80 percent of AI short-answer suggestions shall fall within one mark (or 10 percent of the question maximum) of the teacher's final mark during pilot evaluation. | S | CN-4.5, BP-1.4 | Agreement analysis from ai_suggestions and mark_events; reported in the evaluation dashboard. |
| ACC-05 | Pilot teachers shall achieve an average marking-time reduction of at least 60 percent against their own baseline, with 70 percent as the aspiration. | M | BP-1.4, BP-1.1 | Baseline and post-pilot time study using in-app time stamps and survey; reported to management. |

## 8.5 Security
| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| SEC-01 | The system shall encrypt data at rest (database volumes, object storage, backups) with AES-256 or equivalent and shall manage keys with rotation and restricted access. | M | BP-7.4, BP-9.1 | Encrypted volumes, server-side object encryption, cloud key management; key rotation procedure. |
| SEC-02 | The application shall meet OWASP ASVS Level 2 controls, including input validation, output encoding, CSRF and XSS protection, SQL injection prevention and secure HTTP headers with a content security policy. | M | BP-9.1, D | Laravel built-in protections and Form Requests; React escaping and CSP; security tests in CI. |
| SEC-03 | The system shall undergo an independent penetration test before the first production pilot and annually, and shall scan dependencies and container images in the build pipeline. | S | BP-9.1, D | Scheduled test; Composer, npm and pip audits; image scanning in CI. |
| SEC-04 | Secrets and credentials shall never be stored in source control and shall be supplied through a secret manager or protected environment variables. | M | BP-8.2, D | CI secret scanning; deployment configuration with injected secrets. |
| SEC-05 | The Python AI service and the database shall not be reachable from the public internet. | M | BP-8.1, BP-7.4 | Private networking, firewall rules, only the reverse proxy exposed. |
| SEC-06 | Audit logs shall be protected from alteration by the application and retained at least as long as the records they describe. | M | CN-4.3, BP-9.1 | Append-only tables, separate database privileges, periodic export to write-once storage. |
| SEC-07 | The system shall apply separation of duties: for example, a marker shall not approve an appeal on that marker's own script, and a person who generates sheets shall not be able to alter finalised marks. | S | CN-4.3, UB-1 | Policy rules in Laravel; role-permission matrix review. |

## 8.6 Privacy and Legal Compliance
Legal obligations should be confirmed by counsel before launch.

| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| PRV-01 | The system shall comply with the Uganda Data Protection and Privacy Act, 2019, including lawful basis, purpose limitation, data minimisation, security safeguards, data subject rights and breach notification, and the operator shall complete any required registration. | M | BP-9.1 | Privacy-by-design checklist; data inventory; breach response runbook; DPIA before pilot; consent and notice texts in React. |
| PRV-02 | The system shall support access, correction and (subject to retention duties) deletion requests for personal data. | S | BP-9.1, D | Administrative workflow and export tooling; retention rules prevent deletion of records that must be kept. |
| PRV-03 | The system shall apply heightened protection to the data of minors, with institutions acting as controller and the operator as processor under written agreements. | M | BP-9.1, BP-4.1 | Data processing agreement template; stricter default access rules; no marketing use of student data. |
| PRV-04 | The operator shall document the hosting location and any cross-border transfer of personal data and shall put in place lawful transfer safeguards. | M | BP-8.2, BP-9.1 | Hosting region selection; sub-processor register; contractual clauses. |
| PRV-05 | The system shall maintain electronic records in a form that supports their use as evidence in disputes, subject to legal confirmation under applicable law on electronic transactions and signatures. | C | CN-4.3, BP-3.4 | Hash chain, audit logs, timestamps and export bundles (see ARC); legal review of admissibility. |

## 8.7 Usability, Accessibility and Localisation
| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| USA-01 | The system shall run on the current and previous two major versions of Chrome, Edge, Firefox and Safari and on Chrome for Android. | M | BP-3.1, CN-3 | Cross-browser automated tests; browser support policy published. |
| USA-02 | Core workflows shall conform to WCAG 2.1 Level AA. | S | D | Accessible component library; automated checks (axe) and manual keyboard and screen-reader review. |
| USA-03 | A trained teacher shall be able to complete a first marking cycle after a half-day training session, and usability testing with at least five users shall achieve a System Usability Scale score of 70 or more. | S | BP-7.2, BP-9.1 | Usability test plan; onboarding wizard; in-app help. |
| USA-04 | The system shall display UGX currency, Africa/Kampala time and day-month-year dates by default, and shall be internationalised so that other languages can be added. | S | BP-1.2, CN-6 | Laravel localisation and react-i18next; locale settings per tenant. |

## 8.8 Maintainability and Portability
| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| MNT-01 | Code shall follow published standards (PSR-12 for PHP, strict TypeScript with ESLint, PEP 8 for Python) and pass static analysis (PHPStan or Larastan, mypy) in the build pipeline. | M | UB-3, D | CI lint and static-analysis jobs that block merges on failure. |
| MNT-02 | Automated tests shall cover at least 80 percent of backend domain services, with component tests for React, unit tests for Python, contract tests for the Laravel-Python interface and end-to-end tests for the main workflow. | M | UB-3, D | PHPUnit or Pest, Jest and Testing Library, pytest, Playwright; coverage gate in CI. |
| MNT-03 | The system shall be modular: Laravel domain modules, React feature folders and Python grading-strategy plug-ins shall be independently changeable. | M | BP-8.1, CN-4.5 | Module boundaries described in Section 9; dependency rules enforced by static analysis. |
| MNT-04 | All components shall run as Docker containers on any suitable Linux cloud server, configured by environment variables, with no dependency on a single cloud vendor's proprietary services. | M | BP-8.2, BP-7.4 | Dockerfiles and compose or orchestration manifests; infrastructure as code; S3-compatible storage only. |
| MNT-05 | The project shall maintain OpenAPI documentation, architecture notes, runbooks and a changelog. | S | UB-2, D | Generated API docs; documentation reviewed at each release. |
| MNT-06 | The platform shall provide centralised structured logs with correlation identifiers across React, Laravel and the Python service, plus metrics and alerts on errors, queue depth and latency. | S | BP-8.2, D | JSON logging, request-ID propagation, metrics exporters and alert rules. |

## 8.9 Responsible AI Governance
| ID | Requirement | Pri | Source | Implementation approach (stack mapping) |
| --- | --- | --- | --- | --- |
| GOV-01 | Before AI grading is enabled for a subject, it shall be validated against a sample of at least 200 answers marked by a subject teacher panel, and the results shall be documented. | M | BP-1.4, CN-4.5 | Validation protocol and report template; evaluation scripts in the Python repository; sign-off recorded in the tenant record. |
| GOV-02 | The system shall publish a model card for each OCR and grading model describing intended use, data, known limits and measured performance. | S | CN-4.5, D | Model-card template stored with model versions; link shown in the administration console. |
| GOV-03 | AI grading shall not use student identity attributes (name, sex, school, registration number) as inputs. | M | CN-4.5, BP-9.1 | Request schema excludes identity fields; schema tests. |
| GOV-04 | Administrators shall be able to disable a model version immediately and roll back to the previous version. | S | CN-4.4, D | Model registry with active-version flag; configuration reload without redeploy. |

# 9. Technical Realisation
This section consolidates the implementation approach given requirement by requirement in Sections 5 to 8 and shows how the pieces fit into the three fixed technology layers.

## 9.1 Laravel backend modules
| Laravel module | Responsibilities | Requirement groups |
| --- | --- | --- |
| Identity and Access | Authentication, MFA, SSO, RBAC policies, tenancy scope, sessions | AUT |
| Academic Structure | Org units, calendar, course units, grading schemes | ACD |
| Student Registry | Students, enrolments, imports, duplicate checks, blind-marking mapping | STU |
| Examination Management | Exams, paper structure, guides, keys, rubrics, assignments, lifecycle state machine | EXM |
| Sheet Generation | Templates, layout JSON, signed codes, batch PDF generation | SHT |
| Ingestion and Pipeline | Upload API, chunking, batches, queue orchestration, calls to the Python service, page assembly, identification decisions, exceptions | CAP, IDN |
| Marking | OMR scoring rules, OCR and AI result storage, mark events, review APIs, locking, second marking | OMR, OCR, AIG, REV |
| Results | Aggregation, grading, statistics, finalisation, versions, release | MRK |
| Archive and Appeals | Manifests, hashing, retention, retrieval, export, appeal workflow | ARC, APL |
| Reporting | Reports, exports, analytics, assistant orchestration | RPT, AST |
| Integration | Versioned API, OAuth clients, connectors, webhooks, sync runs | INT |
| Communication | Notification channels and templates | NOT |
| Billing | Subscriptions, feature flags, invoices, usage | BIL |
| Administration and Audit | Settings, audit and access logs, support grants, health | ADM, DAT |

## 9.2 React and TypeScript feature modules
| Feature module | Principal screens and components | Requirement groups |
| --- | --- | --- |
| Authentication and profile | Sign-in, MFA, SSO, password reset, session handling | AUT |
| Administration | Institution settings, structure tree, grading scheme editor, users and roles, integration console, audit viewer | ACD, ADM, INT |
| Student registry | Student list and detail, import wizard, enrolment, duplicate warnings | STU |
| Examination builder | Paper structure editor, marking guide and key editors, rubric builder, template selector, assignment board | EXM, SHT |
| Capture | Batch uploader, smartphone camera capture, offline queue, quality warnings, batch status | CAP |
| Exceptions | Unidentified page resolution, page reassignment, missing-script report | IDN |
| Review workspace | Side-by-side image, transcription, guide and suggestion; annotation canvas; queues and filters; shortcuts; locking | OMR, OCR, AIG, REV |
| Moderation and appeals | Second-marking queue, discrepancy view, appeals board | REV, APL |
| Results and reports | Compilation checks, statistics, dashboards, report and export centre, evaluation dashboard | MRK, RPT |
| Archive | Search, script viewer, export bundle, retention and hold management | ARC |
| Assistant and help | Chat panel, help panel, onboarding wizard, survey | AST, ADM |
| Commercial | Plan, invoices, usage | BIL |
| Desktop shell | Scanner acquisition screen, watched-folder settings, local queue view, print dialog, update prompts, managed configuration | DSK |

## 9.3 Laravel to Python service contract
All calls are internal, over HTTPS inside a private network, authenticated with a service token (or mutual TLS), and carry an X-Request-ID, an opaque tenant reference and an Idempotency-Key. Responses use one envelope containing status, data, model_version and warnings. Synchronous calls have a 30-second timeout; longer work returns 202 with a job identifier and is reported back to a signed Laravel callback. Laravel retries with exponential back-off and opens a circuit breaker on repeated failure (AIG-11, AVL-04). The Python service reads and writes images only through short-lived signed URLs supplied by Laravel.

| Endpoint | Purpose | Main inputs | Main outputs | Requirements |
| --- | --- | --- | --- | --- |
| GET /v1/health | Liveness and loaded model versions | None | Status, versions | ADM-03 |
| POST /v1/preprocess | Correct, clean and score the quality of a page | Image reference, options | Derived image reference, quality score, resolution | IDN-01, CAP-05, CAP-06 |
| POST /v1/identify | Decode QR or barcode and fallback identifiers | Image reference | Script ID, page number, version code, confidence | IDN-02, IDN-06, SHT-02 |
| POST /v1/regions | Crop answer regions using the layout | Image reference, layout JSON | Crop references per question | IDN-09, SHT-04 |
| POST /v1/omr/read | Read bubble grids and ID fields | Image reference, grid layout, thresholds | Detected options, confidence, overlay reference | OMR-01 to 07 |
| POST /v1/ocr/transcribe | Transcribe handwriting | Crop reference, language | Text, word confidences, content type, model version | OCR-01 to 08 |
| POST /v1/grade/suggest | Suggest a mark for a short answer | Question, guide points, rubric, answer text, maximum mark, strategy | Suggested mark, confidence, rationale, matched spans | AIG-01 to 13 |
| POST /v1/cluster | Group similar answers | List of answer texts and IDs | Groups with similarity scores | AIG-13 |
| POST /v1/guide/index | Extract and index a marking guide for retrieval | Document reference | Index identifier, extracted points | EXM-03, AIG-02 |
| POST /v1/assistant/plan | Plan a read-only query for a natural-language question | Question, permitted schema description | Structured query plan or refusal | AST-01 to 04 |

## 9.4 Core processing flow
**Step 1, set-up.** Laravel stores the exam, paper structure, guide, key and template; it generates signed, personalised sheets (SHT, EXM).

**Step 2, capture.** React uploads pages (online or from the offline queue) in chunks; Laravel validates them, writes originals to object storage and records the batch (CAP).

**Step 3, pre-processing and identification.** A queue worker calls /v1/preprocess and /v1/identify; Laravel verifies the signature and roster membership, assembles scripts and sends problems to the exception queue (IDN).

**Step 4, objective marking.** Laravel calls /v1/regions and /v1/omr/read; ScoringService applies the key and stores responses and scores, flagging ambiguous marks (OMR).

**Step 5, recognition and suggestion.** For written answers, Laravel calls /v1/ocr/transcribe and then /v1/grade/suggest (Premium tier); results are stored as provisional suggestions with model versions (OCR, AIG).

**Step 6, review.** Teachers review in React, accept or adjust, annotate and approve; every change becomes a mark event (REV).

**Step 7, compilation.** Laravel aggregates marks, applies the grading scheme, computes statistics and finalises a versioned result (MRK).

**Step 8, archive and release.** Laravel writes the archive manifest and hashes, releases results to students or external systems, and raises integration events (ARC, INT).

## 9.5 Integration architecture
Integration with existing academic information systems follows the adapter pattern. A common connector interface in Laravel defines operations for importing students, programmes, course units, enrolments and calendars, and exporting results. Each target system is implemented as one adapter that uses an API where available and CSV or SFTP otherwise. External identifiers are held in a mapping table so that synchronisation is idempotent, and conflicts are resolved by the rule in INT-07. All synchronisation runs are logged and retryable (INT-09). Because adapters are configured per tenant and isolated from the core modules, adding a new system later does not alter exam, marking or archive logic. Outbound events (INT-08) let target systems react to finalisation without polling.

## 9.6 Deployment
- Containers: web (Nginx with PHP-FPM serving Laravel and the built React assets), queue workers, scheduler, Python AI service workers (Flask behind gunicorn), and a queue broker if a Redis driver is used.

- Data services: MySQL with automated backups and encrypted storage; S3-compatible object storage with versioning or object lock.

- Edge: reverse proxy with automated TLS certificates; only the proxy is public (SEC-05, COM-01).

- Desktop distribution: signed Windows installers (per-user EXE and MSI for institutional deployment) and, as a Should, macOS and Ubuntu packages, built by the same CI pipeline, published to object storage and delivered through the auto-update channel (DSK-07, DSK-11).

- Delivery: CI pipeline running linting, static analysis, tests, dependency and image scanning, then deployment to staging and production (MNT-01, MNT-02).

- Scaling: Laravel and Python containers scale independently; Python workers scale on queue depth (SCL-01, SCL-03).

# 10. Traceability
## 10.1 Approach
Traceability runs in three directions. Backward: every requirement names its source (CN, BP, UB or D). Forward to design: every requirement names its implementation approach and the stack layers involved. Forward to test: every requirement names a verification method. The tables below are generated from the same data as the requirement tables in Sections 5 to 8, so they cannot drift apart.

## 10.2 Source coverage
This table shows, for each source section or objective, the requirements that realise it. Concept Note Objectives 4.1 to 4.6 therefore trace directly to requirements, and every source used appears at least once.

| Code | Source element | Count | Requirements |
| --- | --- | --- | --- |
| CN-1 | Concept Note Section 1, Background | 1 | AVL-03 |
| CN-2 | Concept Note Section 2, Problem Statement | 2 | STU-07, DAT-05 |
| CN-3 | Concept Note Section 3, Proposed Solution | 23 | EXM-01 to 03, SHT-01, SHT-03, SHT-04, CAP-01, CAP-03, CAP-05, IDN-01, IDN-02, IDN-09, OCR-01, AIG-01, REV-05, REV-15, RPT-09, AST-01, AST-02, UIF-02, UIF-04, HWI-03, USA-01 |
| CN-4.1 | Objective: reduce marking time through automated objective scoring and suggested short-answer marks | 8 | OMR-01, AIG-01, AIG-13, REV-06, PRF-02 to 04, ACC-01 |
| CN-4.2 | Objective: reliable student identification linking every page to the correct student | 12 | STU-01, STU-02, SHT-03, SHT-07, IDN-02, IDN-03, IDN-05, IDN-06, OMR-07, INT-06, DAT-02, ACC-02 |
| CN-4.3 | Objective: digital archive for preservation, retrieval, re-marking, appeals and audit | 33 | STU-05, EXM-07, CAP-07, IDN-08, IDN-10, OMR-03, REV-08, REV-13, MRK-02, MRK-08, MRK-10, ARC-01 to 06, ARC-08 to 10, ARC-12, APL-01 to 05, ADM-02, DAT-03, PRF-05, AVL-05, SEC-06, SEC-07, PRV-05 |
| CN-4.4 | Objective: teacher-friendly review interface with teachers in full control | 21 | AUT-04, AUT-07, EXM-08, IDN-05, OMR-02, OMR-06, OCR-02, OCR-04, OCR-07, AIG-03, AIG-04, AIG-06, AIG-09, REV-01 to 04, REV-08, APL-03, DSK-06, GOV-04 |
| CN-4.5 | Objective: explore and evaluate NLP/AI grading (accuracy, time saved, satisfaction) | 17 | EXM-05, OCR-05, AIG-02, AIG-06 to 08, AIG-12, AIG-13, REV-07, ADM-05, ADM-06, ACC-03, ACC-04, MNT-03, GOV-01 to 03 |
| CN-4.6 | Objective: reporting, export and integration with student information systems | 7 | AUT-03, RPT-01, RPT-02, AST-02, INT-01, INT-03, INT-04 |
| CN-5 | Concept Note Section 5, Expected Impact | 8 | ACD-06, STU-08, REV-05, REV-09, REV-14, MRK-07, MRK-09, RPT-09 |
| CN-6 | Concept Note Section 6, Proposed System Innovation | 6 | OCR-08, ARC-03, RPT-03 to 05, USA-04 |
| BP-1.1 | Business Plan 1.1, Business idea | 5 | ACD-01, MRK-01, RPT-08, PRF-04, ACC-05 |
| BP-1.2 | Business Plan 1.2, Mission and vision | 3 | OCR-08, ARC-12, USA-04 |
| BP-1.3 | Business Plan 1.3, Core value proposition | 5 | ARC-04, ARC-08, APL-05, RPT-03, RPT-06 |
| BP-1.4 | Business Plan 1.4, Business goals and objectives | 8 | ACD-03, REV-12, INT-04, ADM-06, SCL-01, ACC-04, ACC-05, GOV-01 |
| BP-2.1 | Business Plan 2.1, The problem | 3 | RPT-04, DAT-05, AVL-03 |
| BP-2.2 | Business Plan 2.2, Who experiences the problem | 2 | AUT-05, STU-08 |
| BP-2.3 | Business Plan 2.3, Why the problem matters | 3 | IDN-07, APL-01, RPT-05 |
| BP-2.4 | Business Plan 2.4, The business opportunity | 3 | DSK-13, HWI-02, COM-02 |
| BP-3.1 | Business Plan 3.1, Description of the solution | 9 | SHT-08, CAP-03, REV-15, UIF-01, UIF-02, HWI-02, HWI-03, PRF-01, USA-01 |
| BP-3.2 | Business Plan 3.2, Key features | 31 | ACD-05, EXM-03, EXM-04, EXM-09, SHT-01, CAP-01, CAP-07, OMR-01, OMR-02, OMR-04 to 06, OCR-01, OCR-03, OCR-04, AIG-01, REV-01, REV-04, REV-06, MRK-01, MRK-03, MRK-04, ARC-01, ARC-02, RPT-01, RPT-02, DSK-03, DSK-04, DSK-09, UIF-04, ACC-01 |
| BP-3.3 | Business Plan 3.3, User workflow | 16 | STU-01 to 03, EXM-01, EXM-02, EXM-06, SHT-05, SHT-09, CAP-02, IDN-03, IDN-09, OMR-04, AIG-04, REV-02, NOT-01, ADM-01 |
| BP-3.4 | Business Plan 3.4, Uniqueness and innovation | 4 | MRK-08, ARC-05, HWI-01, PRV-05 |
| BP-4.1 | Business Plan 4.1, Customer segments | 5 | ACD-02, ACD-06, REV-09, MRK-05, PRV-03 |
| BP-4.3 | Business Plan 4.3, Market size and growth | 2 | SCL-01, SCL-02 |
| BP-5.2 | Business Plan 5.2, Competitive advantage | 2 | SHT-08, HWI-01 |
| BP-6.1 | Business Plan 6.1, Revenue model | 6 | AIG-09, BIL-01 to 05 |
| BP-6.2 | Business Plan 6.2, Business model canvas (partners) | 3 | INT-13, NOT-02, SWI-04 |
| BP-7.2 | Business Plan 7.2, Sales and onboarding | 5 | BIL-03, ADM-04, ADM-05, DSK-11, USA-03 |
| BP-7.3 | Business Plan 7.3, Operational plan | 1 | SCL-03 |
| BP-7.4 | Business Plan 7.4, Infrastructure | 10 | CAP-11, ARC-07, DSK-03, COM-01, COM-03, AVL-01, AVL-02, SEC-01, SEC-05, MNT-04 |
| BP-8.1 | Business Plan 8.1, System architecture | 15 | AUT-06, CAP-09, AIG-11, REV-13, INT-01, INT-08, INT-12, ADM-02, SWI-01 to 03, DAT-01, AVL-04, SEC-05, MNT-03 |
| BP-8.2 | Business Plan 8.2, Technology stack | 11 | IDN-01, OCR-06, ARC-07, ADM-03, SWI-02, COM-01, AVL-02, SEC-04, PRV-04, MNT-04, MNT-06 |
| BP-8.5 | Business Plan 8.5, Revenue projections | 1 | BIL-02 |
| BP-9.1 | Business Plan 9.1, Risks and mitigation | 44 | AUT-01, AUT-02, AUT-04, AUT-08, AUT-10, SHT-02, SHT-04, CAP-04 to 06, IDN-04, OCR-02, OCR-06, AIG-03, AIG-10, REV-11, ARC-06, ARC-09, RPT-10, AST-03, AST-05, INT-02, ADM-04, ADM-07, DSK-02, DSK-05, DSK-06, DSK-08, DSK-10, UIF-03, COM-02, DAT-04, SCL-03, ACC-03, SEC-01 to 03, SEC-06, PRV-01 to 04, USA-03, GOV-03 |
| UB-1 | Project brief: system serves universities, examination bodies and similar institutions | 11 | AUT-06, ACD-01, ACD-02, ACD-06, CAP-02, REV-09, MRK-05, MRK-07, RPT-06, PRF-04, SEC-07 |
| UB-2 | Project brief: later integration with existing academic information systems | 16 | AUT-03, MRK-09, ARC-11, INT-01 to 11, SWI-04, MNT-05 |
| UB-3 | Project brief: fixed stack (Laravel, React with TypeScript, external Python AI service) | 8 | INT-12, DSK-01, UIF-01, SWI-01, SWI-03, DAT-01, MNT-01, MNT-02 |
| UB-4 | Project direction: delivered as a web and desktop solution | 15 | DSK-01 to 13, UIF-01, HWI-01 |
| D | Derived by requirements analysis to close gaps (good practice, completeness, enterprise education systems) | 103 | AUT-01, AUT-02, AUT-05, AUT-07 to 11, ACD-03 to 05, ACD-07, STU-03 to 07, EXM-04 to 10, SHT-02, SHT-06, SHT-07, CAP-06, CAP-08, CAP-10, IDN-04, IDN-06 to 08, IDN-10, OMR-03, OMR-05, OMR-07, OCR-05, OCR-07, AIG-05, AIG-08, AIG-10 to 12, REV-07, REV-10 to 12, REV-14, MRK-02, MRK-04, MRK-06, MRK-10, ARC-10, ARC-11, APL-02, APL-04, APL-06, RPT-07, RPT-08, RPT-10, AST-03 to 05, INT-07, INT-09, INT-11, INT-13, NOT-01, NOT-03, NOT-04, BIL-06, ADM-01, ADM-03, ADM-07, DSK-07, DSK-12, UIF-03, COM-03, DAT-02 to 04, DAT-06, PRF-01 to 03, PRF-05, SCL-02, AVL-01, AVL-04, AVL-05, SEC-02 to 04, PRV-02, USA-02, MNT-01, MNT-02, MNT-05, MNT-06, GOV-02, GOV-04 |

## 10.3 Layer and priority summary by module
Columns M, S and C count priorities. Columns L, R, P, D, S and O count requirements that involve each layer (a requirement can involve several layers).

| Module | Total | M | S | C | L | R | P | D | S | O |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| AUT | 11 | 8 | 3 | 0 | 11 | 8 | 1 | 10 | 1 | 0 |
| ACD | 7 | 5 | 2 | 0 | 7 | 7 | 0 | 7 | 1 | 0 |
| STU | 8 | 4 | 3 | 1 | 8 | 7 | 0 | 8 | 0 | 0 |
| EXM | 10 | 6 | 3 | 1 | 10 | 8 | 5 | 9 | 1 | 0 |
| SHT | 9 | 7 | 2 | 0 | 9 | 2 | 4 | 5 | 1 | 0 |
| CAP | 11 | 8 | 2 | 1 | 9 | 6 | 3 | 6 | 3 | 1 |
| IDN | 10 | 8 | 2 | 0 | 9 | 3 | 5 | 7 | 2 | 0 |
| OMR | 7 | 4 | 3 | 0 | 6 | 2 | 6 | 3 | 1 | 0 |
| OCR | 8 | 4 | 3 | 1 | 8 | 2 | 7 | 4 | 1 | 0 |
| AIG | 13 | 9 | 3 | 1 | 12 | 8 | 9 | 9 | 0 | 0 |
| REV | 15 | 11 | 3 | 1 | 14 | 14 | 0 | 13 | 2 | 0 |
| MRK | 10 | 6 | 3 | 1 | 10 | 5 | 0 | 10 | 0 | 0 |
| ARC | 12 | 10 | 2 | 0 | 11 | 5 | 0 | 9 | 8 | 3 |
| APL | 6 | 3 | 2 | 1 | 6 | 4 | 0 | 6 | 1 | 0 |
| RPT | 10 | 3 | 6 | 1 | 10 | 7 | 0 | 7 | 2 | 1 |
| AST | 5 | 0 | 0 | 5 | 4 | 3 | 4 | 1 | 0 | 0 |
| INT | 13 | 9 | 3 | 1 | 13 | 4 | 1 | 12 | 0 | 3 |
| NOT | 4 | 1 | 0 | 3 | 4 | 2 | 0 | 4 | 0 | 0 |
| BIL | 6 | 0 | 3 | 3 | 6 | 2 | 0 | 6 | 1 | 0 |
| ADM | 7 | 2 | 5 | 0 | 7 | 6 | 1 | 5 | 0 | 1 |
| DSK | 13 | 7 | 6 | 0 | 11 | 13 | 0 | 2 | 1 | 5 |
| UIF | 4 | 3 | 1 | 0 | 0 | 4 | 0 | 0 | 0 | 1 |
| HWI | 3 | 3 | 0 | 0 | 2 | 1 | 2 | 0 | 0 | 0 |
| SWI | 4 | 3 | 1 | 0 | 3 | 0 | 2 | 1 | 1 | 1 |
| COM | 3 | 2 | 1 | 0 | 1 | 1 | 1 | 1 | 0 | 2 |
| DAT | 6 | 4 | 2 | 0 | 6 | 3 | 0 | 6 | 1 | 0 |
| PRF | 5 | 3 | 2 | 0 | 4 | 2 | 3 | 2 | 0 | 3 |
| SCL | 3 | 3 | 0 | 0 | 1 | 0 | 2 | 1 | 1 | 3 |
| AVL | 5 | 3 | 2 | 0 | 3 | 1 | 1 | 3 | 2 | 2 |
| ACC | 5 | 3 | 2 | 0 | 4 | 1 | 4 | 2 | 0 | 0 |
| SEC | 7 | 5 | 2 | 0 | 4 | 2 | 2 | 4 | 2 | 6 |
| PRV | 5 | 3 | 1 | 1 | 4 | 2 | 0 | 4 | 1 | 2 |
| USA | 4 | 1 | 3 | 0 | 1 | 4 | 0 | 0 | 0 | 0 |
| MNT | 6 | 4 | 2 | 0 | 5 | 4 | 5 | 0 | 0 | 5 |
| GOV | 4 | 2 | 2 | 0 | 3 | 0 | 4 | 1 | 0 | 2 |
| Total | 259 | 157 | 80 | 22 | 226 | 143 | 72 | 168 | 34 | 41 |

## 10.4 Requirement to stack and verification matrix
An X shows that the layer takes part in implementing the requirement. Layers: L = Laravel, R = React with TypeScript, P = Python AI service, D = MySQL, S = object storage, O = DevOps and infrastructure. Verification: T = test, I = inspection, D = demonstration, A = analysis.

| ID | Pri | Source | L | R | P | D | S | O | Ver |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| AUT-01 | M | BP-9.1, D | X | X |  | X |  |  | T |
| AUT-02 | S | BP-9.1, D | X | X |  | X |  |  | T |
| AUT-03 | S | UB-2, CN-4.6 | X | X |  | X |  |  | T |
| AUT-04 | M | BP-9.1, CN-4.4 | X | X |  | X |  |  | T |
| AUT-05 | M | BP-2.2, D | X |  |  | X |  |  | I |
| AUT-06 | M | BP-8.1, UB-1 | X |  | X | X | X |  | T |
| AUT-07 | M | CN-4.4, D | X | X |  | X |  |  | T |
| AUT-08 | M | BP-9.1, D | X | X |  | X |  |  | T |
| AUT-09 | M | D | X | X |  | X |  |  | T |
| AUT-10 | M | BP-9.1, D | X |  |  | X |  |  | T |
| AUT-11 | S | D | X | X |  |  |  |  | T |
| ACD-01 | M | BP-1.1, UB-1 | X | X |  | X | X |  | T |
| ACD-02 | M | UB-1, BP-4.1 | X | X |  | X |  |  | D |
| ACD-03 | M | BP-1.4, D | X | X |  | X |  |  | T |
| ACD-04 | M | D | X | X |  | X |  |  | T |
| ACD-05 | M | BP-3.2, D | X | X |  | X |  |  | T |
| ACD-06 | S | CN-5, BP-4.1, UB-1 | X | X |  | X |  |  | D |
| ACD-07 | S | D | X | X |  | X |  |  | T |
| STU-01 | M | CN-4.2, BP-3.3 | X | X |  | X |  |  | T |
| STU-02 | M | BP-3.3, CN-4.2 | X | X |  | X |  |  | T |
| STU-03 | M | BP-3.3, D | X | X |  | X |  |  | T |
| STU-04 | M | D | X | X |  | X |  |  | T |
| STU-05 | S | CN-4.3, D | X |  |  | X |  |  | T |
| STU-06 | S | D | X | X |  | X |  |  | T |
| STU-07 | S | CN-2, D | X | X |  | X |  |  | T |
| STU-08 | C | CN-5, BP-2.2 | X | X |  | X |  |  | T |
| EXM-01 | M | BP-3.3, CN-3 | X | X |  | X |  |  | T |
| EXM-02 | M | BP-3.3, CN-3 | X | X |  | X |  |  | T |
| EXM-03 | M | CN-3, BP-3.2 | X | X | X | X | X |  | T |
| EXM-04 | M | BP-3.2, D | X | X | X | X |  |  | T |
| EXM-05 | S | CN-4.5, D | X | X | X | X |  |  | D |
| EXM-06 | M | BP-3.3, D | X | X |  | X |  |  | T |
| EXM-07 | S | CN-4.3, D | X |  | X | X |  |  | T |
| EXM-08 | M | CN-4.4, D | X | X |  | X |  |  | T |
| EXM-09 | S | BP-3.2, D | X |  | X | X |  |  | T |
| EXM-10 | C | D | X | X |  |  |  |  | D |
| SHT-01 | M | CN-3, BP-3.2 | X |  |  | X |  |  | T |
| SHT-02 | M | BP-9.1, D | X |  | X |  |  |  | T |
| SHT-03 | M | CN-4.2, CN-3 | X |  | X |  |  |  | T |
| SHT-04 | M | BP-9.1, CN-3 | X |  | X | X |  |  | T |
| SHT-05 | M | BP-3.3 | X | X |  |  | X |  | T |
| SHT-06 | S | D | X |  |  | X |  |  | T |
| SHT-07 | S | CN-4.2, D | X |  | X | X |  |  | T |
| SHT-08 | M | BP-3.1, BP-5.2 | X |  |  |  |  |  | T |
| SHT-09 | M | BP-3.3 | X | X |  | X |  |  | D |
| CAP-01 | M | BP-3.2, CN-3 | X | X | X |  | X |  | T |
| CAP-02 | M | BP-3.3, UB-1 | X | X |  | X | X |  | T |
| CAP-03 | M | CN-3, BP-3.1 |  | X |  |  |  |  | D |
| CAP-04 | M | BP-9.1 | X | X |  |  |  |  | T |
| CAP-05 | S | BP-9.1, CN-3 |  | X | X |  |  |  | T |
| CAP-06 | M | BP-9.1, D | X |  | X |  |  |  | T |
| CAP-07 | M | BP-3.2, CN-4.3 | X |  |  | X | X |  | I |
| CAP-08 | M | D | X | X |  | X |  |  | T |
| CAP-09 | M | BP-8.1 | X |  |  | X |  | X | T |
| CAP-10 | S | D | X |  |  | X |  |  | T |
| CAP-11 | C | BP-7.4 | X |  |  | X |  |  | T |
| IDN-01 | M | BP-8.2, CN-3 |  |  | X |  | X |  | T |
| IDN-02 | M | CN-4.2, CN-3 | X |  | X |  |  |  | T |
| IDN-03 | M | CN-4.2, BP-3.3 | X |  |  | X |  |  | T |
| IDN-04 | M | BP-9.1, D | X |  |  | X |  |  | T |
| IDN-05 | M | CN-4.2, CN-4.4 | X | X |  | X |  |  | T |
| IDN-06 | S | CN-4.2, D | X |  | X |  |  |  | T |
| IDN-07 | M | BP-2.3, D | X | X |  | X |  |  | T |
| IDN-08 | M | CN-4.3, D | X | X |  | X |  |  | T |
| IDN-09 | M | CN-3, BP-3.3 | X |  | X | X | X |  | T |
| IDN-10 | S | CN-4.3, D | X |  | X | X |  |  | I |
| OMR-01 | M | CN-4.1, BP-3.2 | X |  | X | X |  |  | T |
| OMR-02 | M | CN-4.4, BP-3.2 | X |  | X | X |  |  | T |
| OMR-03 | M | CN-4.3, D | X |  |  | X |  |  | T |
| OMR-04 | M | BP-3.3, BP-3.2 | X | X | X |  |  |  | D |
| OMR-05 | S | BP-3.2, D | X |  | X |  |  |  | T |
| OMR-06 | S | BP-3.2, CN-4.4 |  | X | X |  | X |  | I |
| OMR-07 | S | CN-4.2, D | X |  | X |  |  |  | T |
| OCR-01 | M | CN-3, BP-3.2 | X |  | X | X |  |  | T |
| OCR-02 | M | BP-9.1, CN-4.4 | X | X | X |  |  |  | T |
| OCR-03 | S | BP-3.2 | X |  | X |  |  |  | T |
| OCR-04 | M | CN-4.4, BP-3.2 | X | X |  | X |  |  | T |
| OCR-05 | M | CN-4.5, D | X |  | X | X |  |  | I |
| OCR-06 | S | BP-8.2, BP-9.1 | X |  | X | X | X |  | I |
| OCR-07 | S | CN-4.4, D | X |  | X |  |  |  | T |
| OCR-08 | C | CN-6, BP-1.2 | X |  | X |  |  |  | A |
| AIG-01 | M | CN-4.1, CN-3, BP-3.2 | X |  | X | X |  |  | T |
| AIG-02 | M | CN-4.5 | X | X | X | X |  |  | T |
| AIG-03 | M | CN-4.4, BP-9.1 | X | X |  | X |  |  | T |
| AIG-04 | M | BP-3.3, CN-4.4 | X | X |  | X |  |  | T |
| AIG-05 | M | D | X |  | X |  |  |  | T |
| AIG-06 | S | CN-4.5, CN-4.4 |  | X | X |  |  |  | D |
| AIG-07 | M | CN-4.5 | X | X |  | X |  |  | T |
| AIG-08 | M | CN-4.5, D | X |  | X | X |  |  | I |
| AIG-09 | S | BP-6.1, CN-4.4 | X | X |  | X |  |  | T |
| AIG-10 | M | BP-9.1, D | X |  | X | X |  |  | I |
| AIG-11 | M | BP-8.1, D | X | X | X |  |  |  | T |
| AIG-12 | S | CN-4.5, D | X |  | X | X |  |  | A |
| AIG-13 | C | CN-4.1, CN-4.5 | X | X | X |  |  |  | D |
| REV-01 | M | BP-3.2, CN-4.4 | X | X |  | X | X |  | D |
| REV-02 | M | CN-4.4, BP-3.3 | X | X |  | X |  |  | T |
| REV-03 | M | CN-4.4 | X | X |  | X |  |  | T |
| REV-04 | M | BP-3.2, CN-4.4 | X | X |  | X | X |  | D |
| REV-05 | M | CN-3, CN-5 | X | X |  | X |  |  | T |
| REV-06 | S | CN-4.1, BP-3.2 | X | X |  | X |  |  | T |
| REV-07 | S | CN-4.5, D | X | X |  | X |  |  | T |
| REV-08 | M | CN-4.4, CN-4.3 | X | X |  | X |  |  | T |
| REV-09 | S | CN-5, BP-4.1, UB-1 | X | X |  | X |  |  | T |
| REV-10 | M | D | X | X |  | X |  |  | T |
| REV-11 | M | BP-9.1, D | X | X |  |  |  |  | T |
| REV-12 | M | BP-1.4, D | X | X |  | X |  |  | T |
| REV-13 | M | CN-4.3, BP-8.1 | X |  |  | X |  |  | T |
| REV-14 | C | CN-5, D | X | X |  | X |  |  | T |
| REV-15 | M | BP-3.1, CN-3 |  | X |  |  |  |  | D |
| MRK-01 | M | BP-1.1, BP-3.2 | X |  |  | X |  |  | T |
| MRK-02 | M | CN-4.3, D | X | X |  | X |  |  | T |
| MRK-03 | M | BP-3.2 | X | X |  | X |  |  | T |
| MRK-04 | M | BP-3.2, D | X |  |  | X |  |  | T |
| MRK-05 | S | UB-1, BP-4.1 | X | X |  | X |  |  | T |
| MRK-06 | S | D | X |  |  | X |  |  | T |
| MRK-07 | C | CN-5, UB-1 | X | X |  | X |  |  | T |
| MRK-08 | M | CN-4.3, BP-3.4 | X |  |  | X |  |  | T |
| MRK-09 | S | CN-5, UB-2 | X | X |  | X |  |  | T |
| MRK-10 | M | CN-4.3, D | X |  |  | X |  |  | T |
| ARC-01 | M | CN-4.3, BP-3.2 | X |  |  | X | X |  | T |
| ARC-02 | M | BP-3.2, CN-4.3 | X | X |  | X |  |  | T |
| ARC-03 | S | CN-4.3, CN-6 | X | X |  | X |  |  | T |
| ARC-04 | M | CN-4.3, BP-1.3 | X | X |  |  | X |  | T |
| ARC-05 | M | BP-3.4, CN-4.3 | X |  |  | X | X | X | T |
| ARC-06 | M | CN-4.3, BP-9.1 | X | X |  | X |  |  | T |
| ARC-07 | M | BP-7.4, BP-8.2 |  |  |  | X | X | X | D |
| ARC-08 | M | CN-4.3, BP-1.3 | X | X |  |  | X |  | T |
| ARC-09 | M | CN-4.3, BP-9.1 | X |  |  | X |  |  | T |
| ARC-10 | M | CN-4.3, D | X |  |  | X | X |  | I |
| ARC-11 | M | UB-2, D | X |  |  | X | X |  | D |
| ARC-12 | S | CN-4.3, BP-1.2 | X |  |  |  | X | X | A |
| APL-01 | M | CN-4.3, BP-2.3 | X | X |  | X |  |  | T |
| APL-02 | S | CN-4.3, D | X | X |  | X |  |  | T |
| APL-03 | M | CN-4.3, CN-4.4 | X | X |  | X |  |  | T |
| APL-04 | S | CN-4.3, D | X |  |  | X |  |  | T |
| APL-05 | M | CN-4.3, BP-1.3 | X | X |  | X |  |  | T |
| APL-06 | C | D | X |  |  | X | X |  | D |
| RPT-01 | M | CN-4.6, BP-3.2 | X | X |  | X |  |  | T |
| RPT-02 | M | CN-4.6, BP-3.2 | X | X |  |  | X |  | T |
| RPT-03 | S | CN-6, BP-1.3 | X | X |  | X |  |  | T |
| RPT-04 | S | CN-6, BP-2.1 | X | X |  | X |  |  | T |
| RPT-05 | S | CN-6, BP-2.3 | X | X |  | X |  |  | T |
| RPT-06 | M | BP-1.3, UB-1 | X | X |  |  |  |  | D |
| RPT-07 | C | D | X |  |  | X |  | X | T |
| RPT-08 | S | BP-1.1, D | X |  |  |  | X |  | I |
| RPT-09 | S | CN-5, CN-3 | X | X |  | X |  |  | T |
| RPT-10 | S | BP-9.1, D | X |  |  | X |  |  | T |
| AST-01 | C | CN-3 | X | X | X |  |  |  | T |
| AST-02 | C | CN-3, CN-4.6 | X | X | X |  |  |  | D |
| AST-03 | C | BP-9.1, D | X | X | X |  |  |  | T |
| AST-04 | C | D |  |  | X |  |  |  | T |
| AST-05 | C | BP-9.1, D | X |  |  | X |  |  | I |
| INT-01 | M | UB-2, CN-4.6, BP-8.1 | X |  |  | X |  |  | I |
| INT-02 | M | UB-2, BP-9.1 | X | X |  | X |  |  | T |
| INT-03 | M | UB-2, CN-4.6 | X |  |  | X |  | X | T |
| INT-04 | M | UB-2, BP-1.4, CN-4.6 | X | X |  | X |  |  | T |
| INT-05 | M | UB-2 | X |  |  | X |  |  | I |
| INT-06 | M | UB-2, CN-4.2 | X | X |  | X |  |  | T |
| INT-07 | M | UB-2, D | X |  |  | X |  |  | I |
| INT-08 | S | UB-2, BP-8.1 | X |  |  | X |  |  | T |
| INT-09 | M | UB-2, D | X | X |  | X |  |  | T |
| INT-10 | C | UB-2 | X |  |  | X |  |  | A |
| INT-11 | S | UB-2, D | X |  |  | X |  | X | D |
| INT-12 | M | BP-8.1, UB-3 | X |  | X |  |  | X | T |
| INT-13 | S | BP-6.2, D | X |  |  | X |  |  | T |
| NOT-01 | M | BP-3.3, D | X | X |  | X |  |  | T |
| NOT-02 | C | BP-6.2 | X |  |  | X |  |  | T |
| NOT-03 | C | D | X | X |  | X |  |  | T |
| NOT-04 | C | D | X |  |  | X |  |  | I |
| BIL-01 | S | BP-6.1 | X | X |  | X |  |  | T |
| BIL-02 | S | BP-6.1, BP-8.5 | X |  |  | X | X |  | T |
| BIL-03 | C | BP-6.1, BP-7.2 | X |  |  | X |  |  | T |
| BIL-04 | C | BP-6.1 | X |  |  | X |  |  | T |
| BIL-05 | S | BP-6.1 | X | X |  | X |  |  | D |
| BIL-06 | C | D | X |  |  | X |  |  | D |
| ADM-01 | M | BP-3.3, D | X | X |  | X |  |  | D |
| ADM-02 | M | CN-4.3, BP-8.1 | X | X |  | X |  |  | T |
| ADM-03 | S | BP-8.2, D | X |  | X |  |  | X | D |
| ADM-04 | S | BP-7.2, BP-9.1 | X | X |  | X |  |  | D |
| ADM-05 | S | BP-7.2, CN-4.5 | X | X |  |  |  |  | I |
| ADM-06 | S | CN-4.5, BP-1.4 | X | X |  | X |  |  | T |
| ADM-07 | S | BP-9.1, D | X | X |  | X |  |  | T |
| DSK-01 | M | UB-4, UB-3 | X | X |  |  |  | X | D |
| DSK-02 | M | UB-4, BP-9.1 | X | X |  | X |  |  | T |
| DSK-03 | M | UB-4, BP-3.2, BP-7.4 | X | X |  |  |  |  | T |
| DSK-04 | S | UB-4, BP-3.2 | X | X |  |  |  |  | T |
| DSK-05 | M | UB-4, BP-9.1 | X | X |  |  |  |  | T |
| DSK-06 | S | UB-4, BP-9.1, CN-4.4 | X | X |  | X |  |  | T |
| DSK-07 | M | UB-4, D | X | X |  |  | X | X | T |
| DSK-08 | M | UB-4, BP-9.1 | X | X |  |  |  | X | I |
| DSK-09 | S | UB-4, BP-3.2 | X | X |  |  |  |  | D |
| DSK-10 | S | UB-4, BP-9.1 | X | X |  |  |  |  | T |
| DSK-11 | M | UB-4, BP-7.2 |  | X |  |  |  | X | I |
| DSK-12 | S | UB-4, D | X | X |  |  |  | X | I |
| DSK-13 | S | UB-4, BP-2.4 |  | X |  |  |  |  | T |
| UIF-01 | M | BP-3.1, UB-3, UB-4 |  | X |  |  |  | X | I |
| UIF-02 | M | BP-3.1, CN-3 |  | X |  |  |  |  | D |
| UIF-03 | S | BP-9.1, D |  | X |  |  |  |  | I |
| UIF-04 | M | CN-3, BP-3.2 |  | X |  |  |  |  | D |
| HWI-01 | M | BP-3.4, BP-5.2, UB-4 | X |  | X |  |  |  | T |
| HWI-02 | M | BP-3.1, BP-2.4 | X |  |  |  |  |  | T |
| HWI-03 | M | CN-3, BP-3.1 |  | X | X |  |  |  | T |
| SWI-01 | M | BP-8.1, UB-3 | X |  |  | X |  |  | I |
| SWI-02 | M | BP-8.1, BP-8.2 | X |  | X |  | X |  | I |
| SWI-03 | M | UB-3, BP-8.1 |  |  | X |  |  | X | I |
| SWI-04 | S | BP-6.2, UB-2 | X |  |  |  |  |  | T |
| COM-01 | M | BP-7.4, BP-8.2 |  |  |  |  |  | X | T |
| COM-02 | S | BP-9.1, BP-2.4 |  | X |  |  |  |  | T |
| COM-03 | M | BP-7.4, D | X |  | X | X |  | X | I |
| DAT-01 | M | BP-8.1, UB-3 | X |  |  | X |  |  | I |
| DAT-02 | M | CN-4.2, D | X | X |  | X |  |  | T |
| DAT-03 | M | CN-4.3, D | X |  |  | X |  |  | I |
| DAT-04 | S | BP-9.1, D | X |  |  | X | X |  | I |
| DAT-05 | S | BP-2.1, CN-2 | X | X |  | X |  |  | T |
| DAT-06 | M | D | X | X |  | X |  |  | T |
| PRF-01 | M | BP-3.1, D | X | X |  | X |  | X | T |
| PRF-02 | M | CN-4.1, D |  |  | X |  |  | X | T |
| PRF-03 | S | CN-4.1, D | X | X | X |  |  |  | T |
| PRF-04 | M | CN-4.1, UB-1, BP-1.1 | X |  | X |  |  | X | T |
| PRF-05 | S | CN-4.3, D | X |  |  | X |  |  | T |
| SCL-01 | M | BP-1.4, BP-4.3 | X |  | X | X |  | X | A |
| SCL-02 | M | BP-4.3, D |  |  |  |  | X | X | A |
| SCL-03 | M | BP-7.3, BP-9.1 |  |  | X |  |  | X | T |
| AVL-01 | S | BP-7.4, D |  |  |  |  |  | X | A |
| AVL-02 | M | BP-7.4, BP-8.2 |  |  |  | X | X | X | D |
| AVL-03 | M | BP-2.1, CN-1 | X |  |  | X | X |  | T |
| AVL-04 | S | BP-8.1, D | X | X | X |  |  |  | T |
| AVL-05 | M | CN-4.3, D | X |  |  | X |  |  | T |
| ACC-01 | M | BP-3.2, CN-4.1 | X |  | X |  |  |  | T |
| ACC-02 | M | CN-4.2 | X |  | X |  |  |  | T |
| ACC-03 | S | BP-9.1, CN-4.5 |  |  | X |  |  |  | A |
| ACC-04 | S | CN-4.5, BP-1.4 | X |  | X | X |  |  | A |
| ACC-05 | M | BP-1.4, BP-1.1 | X | X |  | X |  |  | A |
| SEC-01 | M | BP-7.4, BP-9.1 |  |  |  | X | X | X | I |
| SEC-02 | M | BP-9.1, D | X | X |  |  |  | X | T |
| SEC-03 | S | BP-9.1, D | X | X | X |  |  | X | I |
| SEC-04 | M | BP-8.2, D |  |  |  |  |  | X | I |
| SEC-05 | M | BP-8.1, BP-7.4 |  |  | X | X |  | X | I |
| SEC-06 | M | CN-4.3, BP-9.1 | X |  |  | X | X | X | I |
| SEC-07 | S | CN-4.3, UB-1 | X |  |  | X |  |  | T |
| PRV-01 | M | BP-9.1 | X | X |  | X |  | X | I |
| PRV-02 | S | BP-9.1, D | X | X |  | X |  |  | D |
| PRV-03 | M | BP-9.1, BP-4.1 | X |  |  | X |  |  | I |
| PRV-04 | M | BP-8.2, BP-9.1 |  |  |  |  |  | X | I |
| PRV-05 | C | CN-4.3, BP-3.4 | X |  |  | X | X |  | A |
| USA-01 | M | BP-3.1, CN-3 |  | X |  |  |  |  | T |
| USA-02 | S | D |  | X |  |  |  |  | T |
| USA-03 | S | BP-7.2, BP-9.1 |  | X |  |  |  |  | A |
| USA-04 | S | BP-1.2, CN-6 | X | X |  |  |  |  | I |
| MNT-01 | M | UB-3, D | X | X | X |  |  | X | I |
| MNT-02 | M | UB-3, D | X | X | X |  |  | X | T |
| MNT-03 | M | BP-8.1, CN-4.5 | X | X | X |  |  |  | I |
| MNT-04 | M | BP-8.2, BP-7.4 |  |  |  |  |  | X | I |
| MNT-05 | S | UB-2, D | X |  | X |  |  | X | I |
| MNT-06 | S | BP-8.2, D | X | X | X |  |  | X | D |
| GOV-01 | M | BP-1.4, CN-4.5 | X |  | X | X |  |  | A |
| GOV-02 | S | CN-4.5, D |  |  | X |  |  | X | I |
| GOV-03 | M | CN-4.5, BP-9.1 | X |  | X |  |  |  | T |
| GOV-04 | S | CN-4.4, D | X |  | X |  |  | X | D |

# 11. Verification, Acceptance and Release Plan
## 11.1 Verification approach
- **Unit and component tests** by layer: PHPUnit or Pest for Laravel, Jest and Testing Library for React, pytest for Python (MNT-02).

- **Contract tests** for the Laravel-Python interface (INT-12) and for every academic-information-system adapter (INT-05).

- **Pipeline tests** with labelled sheet and handwriting sets for OMR, identification and OCR accuracy (ACC-01 to ACC-03).

- **Security tests**: static and dependency scanning, penetration test, tenant-isolation tests (SEC-02, SEC-03, AUT-06).

- **Desktop tests**: automated end-to-end tests of the packaged application (Playwright for Electron), a scanner test matrix (at least one WIA, one TWAIN and one network scan-to-folder device), installer and update tests on Windows 10 and 11 (DSK).

- **Performance and resilience tests**: load tests, batch throughput tests, restore drills, failure injection for the AI service (PRF, AVL).

- **Pilot evaluation**: time study, AI agreement analysis, usability test and satisfaction survey with pilot teachers (ACC-04, ACC-05, USA-03, ADM-06).

## 11.2 Acceptance criteria
- All Must requirements for the release under test are verified as passed, with no open critical or high-severity defect.

- The measurable targets in Section 4.2 are met in the pilot, or a documented variation is approved.

- Traceability in Section 10 is complete: every requirement has a source, a stack mapping and a verification method, and every verification record refers to a requirement identifier.

## 11.3 Release plan
The Business Plan schedules a three-month initial build, a pilot, an AI module and integrations over twelve months (BP-9.2). The breadth of this specification exceeds a first build, so delivery is phased. Priority (Must, Should, Could) says how important a requirement is to the complete system; the release says when it is delivered.

| Release | Indicative timing (BP-9.2) | Modules and focus |
| --- | --- | --- |
| 1. Core marking and archive (MVP) | Months 1 to 4 (MVP then first pilot) | Web application and Windows desktop core (DSK-01 to 03, DSK-05, DSK-07, DSK-08, DSK-11), AUT, ACD, STU, EXM (including MCQ keys), SHT, CAP, IDN, OMR, REV (manual and OMR review), MRK, ARC, basic RPT, UIF, HWI, SWI, COM, DAT, essential NFR (security, backup, performance), versioned API (INT-01, INT-02), NOT (basic), ADM (audit, settings) |
| 2. Recognition and AI assistance | Months 5 to 9 | Remaining desktop features (DSK-04, DSK-06, DSK-09, DSK-10, DSK-12, DSK-13), OCR, AIG, annotation and feedback, APL, advanced RPT (item analysis, consistency), BIL tiers and feature flags, GOV, ACC validation |
| 3. Enterprise integration and scale | Months 10 to 12 and Year 2 | INT connectors, synchronisation and webhooks (SIS export per BP-9.2), SSO, examination-body mode, macOS and Ubuntu desktop builds, second marking and moderation at scale, AST, SMS, billing automation, operations dashboard |

The integration interface (INT-01, INT-02, INT-12) and the identifier-mapping tables are built in Release 1 so that connectors can be added later without changing the data model.

# 12. Risks, Open Issues and Interpretations
## 12.1 Risks to the requirements
| Risk | Effect | Mitigating requirements |
| --- | --- | --- |
| Teacher resistance or low adoption (BP-9.1) | Tool unused; benefits not realised | REV-01 to 07, USA-03, ADM-04, ADM-05, CON-05 |
| Unreliable connectivity (BP-9.1) | Lost or delayed uploads | CAP-03, CAP-04, REV-11, AVL-03, COM-02 |
| Variable handwriting quality (BP-9.1) | OCR errors and poor AI suggestions | OCR-02, OCR-04, OCR-07, SHT-04, ACC-03, GOV-01 |
| Privacy and examination security (BP-9.1) | Breach, legal exposure, loss of trust | AUT-04 to 06, SEC-01 to 07, PRV-01 to 05, AIG-10 |
| AI mis-grading or bias | Unfair marks, appeals | AIG-03, AIG-07, AIG-12, GOV-01 to 04, REV-07 |
| Diverse or unknown target information systems | Integration cost and delay | INT-03 to 07, INT-10, INT-11, CON-09 |
| Scale of examination-body volumes (CN-5, BP-2.2) | Throughput and storage shortfalls | PRF-04, SCL-01 to 03, CAP-02, CAP-09 |
| Competition from school-management systems adding grading (BP-9.1) | Loss of market position | ARC-01 to 12, OCR-01, INT-01 to 12 |
| Desktop support burden: many operating systems, scanner models and drivers | Support cost, failed scans, inconsistent behaviour | DSK-01, DSK-03, DSK-07, DSK-11, DSK-12, HWI-01, CAP-01 |
| Scope larger than the first build (Section 11.3) | Schedule overrun | Release plan; Must and Should priorities |

## 12.2 Open issues
| ID | Open issue | Impact | Suggested owner and timing |
| --- | --- | --- | --- |
| OI-01 | Which academic information systems will be integrated first, and do they offer APIs, file exchange or both? | INT connectors, field mappings | Product owner with partner institutions; before Release 3 design |
| OI-02 | Choice and hosting of OCR and language models, and whether an external language-model provider is permitted by institutions and by law. | OCR, AIG, AST, PRV-04, AIG-10 | AI lead; model evaluation in Release 2 planning |
| OI-03 | Specific security, secrecy and moderation rules of examination bodies such as UNEB. | Examination-body mode, SEC, REV-09 | Business development; before any examination-body pilot |
| OI-04 | Retention periods for scripts and marks by institution and examination type. | ARC-06 | Institutional policy owners; before first archive |
| OI-05 | Legal confirmation of the evidential status of electronic records and of Data Protection and Privacy Act obligations (registration, cross-border transfer). | PRV-01, PRV-04, PRV-05 | Legal counsel; before pilot |
| OI-06 | Proposed quantitative targets (accuracy, throughput, availability) need confirmation against pilot data. | ACC, PRF, AVL | Quality lead; end of pilot |
| OI-08 | Which operating systems and scanner models must be supported first by the desktop application, and which institutions will deploy it through managed IT. | DSK-01, DSK-03, DSK-11 | Product owner with pilot institutions; before Release 1 desktop build |
| OI-09 | Confirmation of the desktop shell: Electron is assumed because it reuses the React and TypeScript code base; Tauri could be chosen if a smaller installer and memory footprint outweigh the cost of a second toolchain. | DSK-01, DSK-13, MNT-01 | Engineering lead; before desktop build starts |
| OI-07 | Whether billing and payment features are needed in deployments that are licensed institutionally rather than by subscription. | BIL | Product owner |

## 12.3 Interpretations made
- The Business Plan uses 'SMS' for both School Management Systems and text messages. This document uses 'academic information system' (including student information and school management systems) for the former and 'SMS notification' for the latter.

- The Business Plan refers to end-to-end encryption of stored scripts. This document specifies encryption in transit and at rest with managed keys (SEC-01, COM-01), which is the form that remains compatible with server-side OCR and AI processing.

- The Business Plan describes OMR accuracy as 'near 100 percent'. A measurable threshold of 99.5 percent with mandatory flagging of uncertain marks is proposed (ACC-01).

- The Business Plan describes access through a browser and capture through scanners, photocopiers and phones. The later direction to deliver a desktop application as well (UB-4) is added as the DSK module; the hardware-agnostic principle is kept because the desktop scanner support is additional and never mandatory (CON-06).

- The Concept Note states that the optional assistant 'may' be included; it is therefore specified at Could priority.

# Appendix A. Glossary
| Term | Meaning |
| --- | --- |
| Academic information system | Existing institutional system holding student, programme, course and official result data (student information system, registry system, school management system). |
| Answer region | A boxed area of the answer sheet in which a student writes the answer to one question. |
| API | Application programming interface. |
| ASVS | OWASP Application Security Verification Standard. |
| Blind marking | Marking in which the marker cannot see the student's identity. |
| DPIA | Data protection impact assessment. |
| Exception queue | List of pages or scripts that automatic processing could not resolve and that need human action. |
| Desktop shell | The wrapper (for example Electron) that runs the React interface as an installed desktop application and provides local device functions. |
| Fiducial mark | Printed alignment mark used by the software to correct page position and scale. |
| HMAC | Keyed hash used to sign the identifiers printed in codes. |
| Human in the loop | Design principle that a person reviews and decides final marks. |
| Marking guide | Model answers, key points and mark allocations used to mark a paper. |
| MFA | Multi-factor authentication. |
| Moderation | Quality-control process, including second marking and arbitration, to ensure consistent marks. |
| NLP | Natural language processing. |
| OCR | Optical character recognition; here, handwriting recognition. |
| OIDC / SAML | Standards for single sign-on with an institution's identity provider. |
| OMR | Optical mark recognition of ticked or shaded bubbles. |
| PWA | Progressive web application, a web app that can be installed and work offline. |
| RAG | Retrieval-augmented generation, in which a language model answers using retrieved reference text such as the marking guide. |
| RBAC | Role-based access control. |
| Script | The full set of pages written by one student for one examination. |
| Tenant | One institution's isolated space within the platform. |
| TWAIN, WIA, ICA, SANE | Standard interfaces through which software controls scanners on Windows (TWAIN, WIA), macOS (ICA) and Linux (SANE). |
| UNEB | Uganda National Examinations Board. |
| WCAG | Web Content Accessibility Guidelines. |
| WER | Word error rate, a measure of recognition accuracy. |

# Appendix B. Principal Use Cases
| ID | Use case | Primary actor | Related requirements |
| --- | --- | --- | --- |
| UC-01 | Set up an examination | Examination officer | ACD-01 to 05, STU-01 to 03, EXM-01 to 04, EXM-06, EXM-08 |
| UC-02 | Generate and print answer sheets | Examination officer | SHT-01 to 09 |
| UC-03 | Scan and upload scripts | Scanning operator or teacher | CAP-01, CAP-02, CAP-06 to 09, IDN-01 to 04 |
| UC-04 | Capture scripts with a phone while offline | Teacher | CAP-03 to 05, UIF-04, REV-11 |
| UC-05 | Resolve identification exceptions | Examination officer | IDN-05 to 08 |
| UC-06 | Review OMR results | Teacher | OMR-01 to 06, REV-01, REV-02 |
| UC-07 | Review AI-suggested answers | Teacher | OCR-01 to 04, AIG-01 to 07, REV-01 to 07 |
| UC-08 | Moderate and second-mark | Moderator | REV-08, REV-09, MRK-07 |
| UC-09 | Finalise and release results | Examination officer | MRK-01 to 10, RPT-01, RPT-02 |
| UC-10 | Retrieve an archived script | Authorised staff or auditor | ARC-02 to 05, ARC-08, ARC-09 |
| UC-11 | Handle an appeal | Examination officer | APL-01 to 06 |
| UC-12 | Synchronise with an academic information system | Administrator or integration client | INT-01 to 12 |
| UC-13 | Analyse performance | Head of department | RPT-03 to 06, AST-01 |
| UC-14 | Manage subscription and usage | System administrator | BIL-01 to 06 |
| UC-15 | Scan directly with the desktop application | Scanning operator | DSK-01 to 05, DSK-09, DSK-10 |

**End of Requirements Specification Document**
