-- ============================================================================
--  PT ASIA RAYA GROUP - HRIS ENTERPRISE
--  Canonical schema (MySQL 8 / MariaDB 10.4+)
--
--  The DDL below is intentionally written in a portable subset so that the
--  bundled preview adapter can translate it to SQLite. Production runs MySQL.
-- ============================================================================

CREATE TABLE IF NOT EXISTS companies (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    legal_name VARCHAR(200) NULL,
    logo VARCHAR(255) NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    province VARCHAR(100) NULL,
    postal_code VARCHAR(10) NULL,
    phone VARCHAR(50) NULL,
    email VARCHAR(150) NULL,
    website VARCHAR(150) NULL,
    npwp VARCHAR(40) NULL,
    timezone VARCHAR(60) NOT NULL DEFAULT 'Asia/Jakarta',
    currency VARCHAR(10) NOT NULL DEFAULT 'IDR',
    created_at DATETIME NULL,
    updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS business_units (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL DEFAULT 1,
    code VARCHAR(10) NOT NULL,
    name VARCHAR(150) NOT NULL,
    type VARCHAR(40) NULL,
    logo VARCHAR(255) NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    phone VARCHAR(50) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    geofence_radius INT NOT NULL DEFAULT 50,
    payroll_cutoff_day INT NOT NULL DEFAULT 25,
    payroll_pay_day INT NOT NULL DEFAULT 28,
    timezone VARCHAR(60) NOT NULL DEFAULT 'Asia/Jakarta',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS departments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    business_unit_id INT UNSIGNED NOT NULL,
    code VARCHAR(10) NOT NULL,
    name VARCHAR(150) NOT NULL,
    head_employee_id INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS positions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    department_id INT UNSIGNED NULL,
    code VARCHAR(15) NULL,
    name VARCHAR(150) NOT NULL,
    level VARCHAR(50) NULL,
    basic_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS employees (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_code VARCHAR(30) NOT NULL,
    username VARCHAR(60) NULL,
    full_name VARCHAR(150) NOT NULL,
    nickname VARCHAR(60) NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NULL,
    address VARCHAR(255) NULL,
    birth_date DATE NULL,
    gender VARCHAR(15) NULL,
    marital_status VARCHAR(20) NULL,
    religion VARCHAR(30) NULL,
    photo VARCHAR(255) NULL,
    avatar VARCHAR(255) NULL,
    password_hash VARCHAR(255) NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'KARYAWAN',
    direct_manager_id INT UNSIGNED NULL,
    business_unit_id INT UNSIGNED NULL,
    department_id INT UNSIGNED NULL,
    position_id INT UNSIGNED NULL,
    basic_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
    employment_status VARCHAR(20) NOT NULL DEFAULT 'PKWT',
    contract_start DATE NULL,
    contract_end DATE NULL,
    join_date DATE NULL,
    resign_date DATE NULL,
    flag VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    bank_name VARCHAR(80) NULL,
    bank_account_name VARCHAR(150) NULL,
    bank_account_number VARCHAR(50) NULL,
    npwp VARCHAR(40) NULL,
    bpjs_kesehatan_no VARCHAR(40) NULL,
    bpjs_tk_no VARCHAR(40) NULL,
    tax_status VARCHAR(10) NOT NULL DEFAULT 'TK/0',
    google_id VARCHAR(120) NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS id_sequences (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    scope_key VARCHAR(80) NOT NULL,
    last_number INT NOT NULL DEFAULT 0,
    updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS work_shifts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    business_unit_id INT UNSIGNED NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL,
    operational_type VARCHAR(15) NOT NULL DEFAULT 'NORMAL',
    check_in_time VARCHAR(8) NOT NULL DEFAULT '08:00',
    check_out_time VARCHAR(8) NOT NULL DEFAULT '17:00',
    break_minutes INT NOT NULL DEFAULT 60,
    late_tolerance_minutes INT NOT NULL DEFAULT 15,
    work_days VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5',
    color VARCHAR(20) NOT NULL DEFAULT '#0d9488',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS schedules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    shift_id INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    source VARCHAR(20) NOT NULL DEFAULT 'ASSIGNED',
    note VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS shift_change_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    current_schedule_id INT UNSIGNED NULL,
    current_shift_id INT UNSIGNED NULL,
    requested_shift_id INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason VARCHAR(255) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    manager_id INT UNSIGNED NULL,
    manager_approved_at DATETIME NULL,
    manager_note VARCHAR(255) NULL,
    hr_id INT UNSIGNED NULL,
    hr_approved_at DATETIME NULL,
    hr_note VARCHAR(255) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS attendance (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    work_date DATE NOT NULL,
    shift_id INT UNSIGNED NULL,
    operational_type VARCHAR(15) NOT NULL DEFAULT 'NORMAL',
    check_in_at DATETIME NULL,
    check_in_lat DECIMAL(10,7) NULL,
    check_in_lng DECIMAL(10,7) NULL,
    check_in_photo VARCHAR(255) NULL,
    check_in_distance INT NULL,
    check_in_accuracy INT NULL,
    check_in_status VARCHAR(20) NULL,
    check_in_note VARCHAR(255) NULL,
    check_out_at DATETIME NULL,
    check_out_lat DECIMAL(10,7) NULL,
    check_out_lng DECIMAL(10,7) NULL,
    check_out_photo VARCHAR(255) NULL,
    check_out_distance INT NULL,
    late_minutes INT NOT NULL DEFAULT 0,
    early_leave_minutes INT NOT NULL DEFAULT 0,
    work_minutes INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'PRESENT',
    source VARCHAR(20) NOT NULL DEFAULT 'WEB',
    ip_address VARCHAR(60) NULL,
    device_info VARCHAR(255) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS overtime_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    department_id INT UNSIGNED NULL,
    work_date DATE NOT NULL,
    planned_start VARCHAR(8) NULL,
    planned_end VARCHAR(8) NULL,
    planned_hours DECIMAL(5,2) NOT NULL DEFAULT 0,
    actual_hours DECIMAL(5,2) NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'REQUEST',
    reason VARCHAR(255) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    compensation_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    manager_id INT UNSIGNED NULL,
    manager_approved_at DATETIME NULL,
    manager_note VARCHAR(255) NULL,
    hr_id INT UNSIGNED NULL,
    hr_approved_at DATETIME NULL,
    hr_note VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS leave_types (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    quota_days INT NOT NULL DEFAULT 12,
    is_paid TINYINT(1) NOT NULL DEFAULT 1,
    max_per_request INT NOT NULL DEFAULT 12,
    min_notice_days INT NOT NULL DEFAULT 1,
    requires_attachment TINYINT(1) NOT NULL DEFAULT 0,
    carry_over TINYINT(1) NOT NULL DEFAULT 0,
    deduct_salary TINYINT(1) NOT NULL DEFAULT 0,
    color VARCHAR(20) NOT NULL DEFAULT '#0d9488',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS leave_balances (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    leave_type_id INT UNSIGNED NOT NULL,
    period_year INT NOT NULL,
    quota DECIMAL(6,1) NOT NULL DEFAULT 0,
    used DECIMAL(6,1) NOT NULL DEFAULT 0,
    carry DECIMAL(6,1) NOT NULL DEFAULT 0,
    updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS leave_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    leave_type_id INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days DECIMAL(5,1) NOT NULL DEFAULT 1,
    reason VARCHAR(255) NULL,
    attachment VARCHAR(255) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    manager_id INT UNSIGNED NULL,
    manager_approved_at DATETIME NULL,
    manager_note VARCHAR(255) NULL,
    hr_id INT UNSIGNED NULL,
    hr_approved_at DATETIME NULL,
    hr_note VARCHAR(255) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS salary_components (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL,
    name VARCHAR(120) NOT NULL,
    type VARCHAR(15) NOT NULL DEFAULT 'BENEFIT',
    calc_type VARCHAR(15) NOT NULL DEFAULT 'FIXED',
    amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    percent_base VARCHAR(20) NOT NULL DEFAULT 'BASIC',
    taxable TINYINT(1) NOT NULL DEFAULT 1,
    bpjs_base TINYINT(1) NOT NULL DEFAULT 0,
    apply_default TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS employee_components (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    component_id INT UNSIGNED NOT NULL,
    amount_override DECIMAL(15,2) NULL,
    effective_date DATE NULL,
    end_date DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS payroll_periods (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    business_unit_id INT UNSIGNED NOT NULL,
    period_year INT NOT NULL,
    period_month INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    cutoff_date DATE NULL,
    pay_date DATE NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    total_employee INT NOT NULL DEFAULT 0,
    total_gross DECIMAL(18,2) NOT NULL DEFAULT 0,
    total_deduction DECIMAL(18,2) NOT NULL DEFAULT 0,
    total_net DECIMAL(18,2) NOT NULL DEFAULT 0,
    total_bpjs_company DECIMAL(18,2) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    processed_by INT UNSIGNED NULL,
    processed_at DATETIME NULL,
    sent_by INT UNSIGNED NULL,
    sent_at DATETIME NULL,
    finance_approved_by INT UNSIGNED NULL,
    finance_approved_at DATETIME NULL,
    paid_by INT UNSIGNED NULL,
    paid_at DATETIME NULL,
    hr_signature_name VARCHAR(150) NULL,
    hr_signature_at DATETIME NULL,
    fin_signature_name VARCHAR(150) NULL,
    fin_signature_at DATETIME NULL,
    notes VARCHAR(255) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS payroll_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    period_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    slip_number VARCHAR(40) NULL,
    employee_code VARCHAR(30) NULL,
    employee_name VARCHAR(150) NULL,
    department_name VARCHAR(150) NULL,
    position_name VARCHAR(150) NULL,
    basic_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
    present_days INT NOT NULL DEFAULT 0,
    absent_days INT NOT NULL DEFAULT 0,
    late_days INT NOT NULL DEFAULT 0,
    late_minutes INT NOT NULL DEFAULT 0,
    late_deduction DECIMAL(15,2) NOT NULL DEFAULT 0,
    unpaid_leave_days DECIMAL(5,1) NOT NULL DEFAULT 0,
    leave_deduction DECIMAL(15,2) NOT NULL DEFAULT 0,
    absent_deduction DECIMAL(15,2) NOT NULL DEFAULT 0,
    overtime_hours DECIMAL(6,2) NOT NULL DEFAULT 0,
    overtime_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    benefit_total DECIMAL(15,2) NOT NULL DEFAULT 0,
    deduction_total DECIMAL(15,2) NOT NULL DEFAULT 0,
    gross_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
    bpjs_kesehatan_employee DECIMAL(15,2) NOT NULL DEFAULT 0,
    bpjs_tk_employee DECIMAL(15,2) NOT NULL DEFAULT 0,
    bpjs_company DECIMAL(15,2) NOT NULL DEFAULT 0,
    pph_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_deduction DECIMAL(15,2) NOT NULL DEFAULT 0,
    net_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
    payment_status VARCHAR(20) NOT NULL DEFAULT 'UNPAID',
    paid_at DATETIME NULL,
    paid_by INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS payroll_item_components (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    item_id INT UNSIGNED NOT NULL,
    code VARCHAR(30) NULL,
    name VARCHAR(120) NOT NULL,
    type VARCHAR(15) NOT NULL DEFAULT 'BENEFIT',
    kind VARCHAR(30) NOT NULL DEFAULT 'COMPONENT',
    amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS holidays (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    holiday_date DATE NOT NULL,
    name VARCHAR(120) NOT NULL,
    is_national TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS review_periods (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'TAHUNAN',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    self_due DATE NULL,
    manager_due DATE NULL,
    business_unit_id INT UNSIGNED NULL,
    self_weight TINYINT NOT NULL DEFAULT 30,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    note VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS review_indicators (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    category VARCHAR(20) NOT NULL DEFAULT 'KOMPETENSI',
    weight DECIMAL(5,2) NOT NULL DEFAULT 10,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS performance_reviews (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    period_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    manager_id INT UNSIGNED NULL,
    business_unit_id INT UNSIGNED NULL,
    department_id INT UNSIGNED NULL,
    position_id INT UNSIGNED NULL,
    self_score DECIMAL(5,2) NULL,
    manager_score DECIMAL(5,2) NULL,
    final_score DECIMAL(5,2) NULL,
    rating VARCHAR(2) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    self_note VARCHAR(500) NULL,
    manager_note VARCHAR(500) NULL,
    calibration_note VARCHAR(500) NULL,
    self_submitted_at DATETIME NULL,
    manager_submitted_at DATETIME NULL,
    calibrated_by INT UNSIGNED NULL,
    calibrated_at DATETIME NULL,
    acknowledged_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS performance_scores (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    review_id INT UNSIGNED NOT NULL,
    indicator_id INT UNSIGNED NOT NULL,
    weight DECIMAL(5,2) NOT NULL DEFAULT 0,
    self_score TINYINT NULL,
    manager_score TINYINT NULL,
    note VARCHAR(255) NULL
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    message VARCHAR(255) NULL,
    link VARCHAR(255) NULL,
    type VARCHAR(30) NOT NULL DEFAULT 'info',
    read_at DATETIME NULL,
    created_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS announcements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    category VARCHAR(30) NOT NULL DEFAULT 'INFO',
    body TEXT NOT NULL,
    audience VARCHAR(20) NOT NULL DEFAULT 'ALL',
    audience_value VARCHAR(120) NULL,
    is_pinned TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    notify TINYINT(1) NOT NULL DEFAULT 1,
    publish_at DATETIME NULL,
    expires_at DATETIME NULL,
    attachment VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS announcement_reads (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    read_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NULL,
    action VARCHAR(60) NOT NULL,
    entity VARCHAR(60) NULL,
    entity_id INT UNSIGNED NULL,
    meta VARCHAR(500) NULL,
    ip_address VARCHAR(60) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(80) NOT NULL,
    setting_value TEXT NULL,
    setting_group VARCHAR(40) NOT NULL DEFAULT 'general',
    updated_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    selector VARCHAR(40) NOT NULL,
    validator_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NULL
);

-- ---------------------------------------------------------------------------
-- Batch 11 — Klaim & Reimbursement
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS expense_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    max_amount DECIMAL(14,2) NULL,
    requires_receipt TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_expense_category_code (code),
    KEY idx_expense_category_active (is_active)
);

CREATE TABLE IF NOT EXISTS expense_claims (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    claim_number VARCHAR(40) NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    claim_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    description VARCHAR(255) NOT NULL,
    receipt VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    manager_id INT UNSIGNED NULL,
    manager_approved_at DATETIME NULL,
    manager_note VARCHAR(255) NULL,
    approver_id INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    approver_note VARCHAR(255) NULL,
    payroll_item_id INT UNSIGNED NULL,
    paid_at DATETIME NULL,
    paid_by INT UNSIGNED NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_expense_claim_number (claim_number),
    KEY idx_expense_claim_employee (employee_id, claim_date),
    KEY idx_expense_claim_status (status),
    KEY idx_expense_claim_category (category_id),
    KEY idx_expense_claim_payroll (payroll_item_id)
);

-- ---------------------------------------------------------------------------
-- Batch 14 — Pelatihan & Pengembangan
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS trainings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(30) NOT NULL DEFAULT 'TEKNIS',
    type VARCHAR(20) NOT NULL DEFAULT 'INTERNAL',
    provider VARCHAR(120) NULL,
    description VARCHAR(500) NULL,
    duration_hours DECIMAL(6,2) NOT NULL DEFAULT 0,
    cost_per_participant DECIMAL(14,2) NOT NULL DEFAULT 0,
    is_mandatory TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_training_code (code),
    KEY idx_training_category (category, is_active)
);

CREATE TABLE IF NOT EXISTS training_sessions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    training_id INT UNSIGNED NOT NULL,
    mode VARCHAR(20) NOT NULL DEFAULT 'OFFLINE',
    location VARCHAR(150) NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    enroll_deadline DATE NULL,
    quota INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    note VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_training_session_training (training_id),
    KEY idx_training_session_status (status, start_date)
);

CREATE TABLE IF NOT EXISTS training_participants (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    source VARCHAR(10) NOT NULL DEFAULT 'HR',
    status VARCHAR(20) NOT NULL DEFAULT 'NOMINATED',
    score DECIMAL(5,2) NULL,
    attended_hours DECIMAL(6,2) NULL,
    certificate VARCHAR(255) NULL,
    certificate_no VARCHAR(60) NULL,
    note VARCHAR(255) NULL,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_training_participant (session_id, employee_id),
    KEY idx_training_participant_employee (employee_id, status),
    KEY idx_training_participant_status (session_id, status)
);

-- ---------------------------------------------------------------------------
-- Batch 15 — Rekrutmen & Onboarding
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS job_vacancies (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL,
    title VARCHAR(150) NOT NULL,
    business_unit_id INT UNSIGNED NULL,
    department_id INT UNSIGNED NULL,
    position_id INT UNSIGNED NULL,
    employment_type VARCHAR(30) NOT NULL DEFAULT 'PKWT',
    work_mode VARCHAR(20) NOT NULL DEFAULT 'ONSITE',
    location VARCHAR(150) NULL,
    headcount INT NOT NULL DEFAULT 1,
    description VARCHAR(1000) NULL,
    requirements VARCHAR(1000) NULL,
    salary_min DECIMAL(14,2) NOT NULL DEFAULT 0,
    salary_max DECIMAL(14,2) NOT NULL DEFAULT 0,
    open_date DATE NULL,
    close_date DATE NULL,
    hiring_manager_id INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    note VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_job_code (code),
    KEY idx_job_status (status, close_date),
    KEY idx_job_unit (business_unit_id)
);

CREATE TABLE IF NOT EXISTS candidates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NULL,
    birth_date DATE NULL,
    gender VARCHAR(15) NULL,
    education VARCHAR(20) NULL,
    experience_years DECIMAL(4,1) NOT NULL DEFAULT 0,
    current_company VARCHAR(150) NULL,
    current_position VARCHAR(120) NULL,
    expected_salary DECIMAL(14,2) NOT NULL DEFAULT 0,
    source VARCHAR(20) NOT NULL DEFAULT 'WEBSITE',
    cv VARCHAR(255) NULL,
    cover_note VARCHAR(500) NULL,
    rating TINYINT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'APPLIED',
    interview_at DATETIME NULL,
    interviewer_id INT UNSIGNED NULL,
    interview_mode VARCHAR(20) NULL,
    note VARCHAR(500) NULL,
    reject_reason VARCHAR(255) NULL,
    employee_id INT UNSIGNED NULL,
    hired_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_candidate_job (job_id, status),
    KEY idx_candidate_status (status),
    KEY idx_candidate_email (email)
);

CREATE TABLE IF NOT EXISTS candidate_stages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT UNSIGNED NOT NULL,
    from_status VARCHAR(20) NULL,
    to_status VARCHAR(20) NOT NULL,
    note VARCHAR(255) NULL,
    actor_id INT UNSIGNED NULL,
    created_at DATETIME NULL,
    KEY idx_candidate_stage (candidate_id, created_at)
);

-- ---------------------------------------------------------------------------
-- Batch 16 — Offboarding & exit management
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS offboarding_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(40) NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'RESIGN',
    reason_category VARCHAR(30) NULL,
    reason VARCHAR(1000) NULL,
    submit_date DATE NULL,
    last_working_date DATE NOT NULL,
    notice_days INT NOT NULL DEFAULT 0,
    handover_to INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'SUBMITTED',
    manager_id INT UNSIGNED NULL,
    manager_approved_by INT UNSIGNED NULL,
    manager_approved_at DATETIME NULL,
    hr_approved_by INT UNSIGNED NULL,
    hr_approved_at DATETIME NULL,
    reject_reason VARCHAR(255) NULL,
    exit_interview_at DATETIME NULL,
    exit_interviewer_id INT UNSIGNED NULL,
    exit_feedback VARCHAR(1500) NULL,
    exit_would_recommend VARCHAR(10) NULL,
    exit_note VARCHAR(500) NULL,
    settlement_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    settlement_note VARCHAR(500) NULL,
    completed_by INT UNSIGNED NULL,
    completed_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_offboarding_number (request_number),
    KEY idx_offboarding_employee (employee_id, status),
    KEY idx_offboarding_status (status, last_working_date)
);

CREATE TABLE IF NOT EXISTS offboarding_clearance (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    item VARCHAR(150) NOT NULL,
    category VARCHAR(20) NOT NULL DEFAULT 'OTHER',
    owner_role VARCHAR(20) NOT NULL DEFAULT 'HR',
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    note VARCHAR(255) NULL,
    done_by INT UNSIGNED NULL,
    done_at DATETIME NULL,
    created_at DATETIME NULL,
    KEY idx_clearance_request (request_id, status)
);

CREATE TABLE IF NOT EXISTS offboarding_stages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    from_status VARCHAR(20) NULL,
    to_status VARCHAR(20) NOT NULL,
    note VARCHAR(255) NULL,
    actor_id INT UNSIGNED NULL,
    created_at DATETIME NULL,
    KEY idx_offboarding_stage (request_id, created_at)
);

-- ---------------------------------------------------------------------------
-- Batch 18 — Email (SMTP) & penjadwal tugas (cron)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_queue (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    recipient_email VARCHAR(150) NOT NULL,
    recipient_name VARCHAR(150) NULL,
    employee_id INT UNSIGNED NULL,
    template VARCHAR(40) NOT NULL DEFAULT 'generic',
    subject VARCHAR(200) NOT NULL,
    body_html TEXT NULL,
    body_text TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    attempts INT NOT NULL DEFAULT 0,
    driver VARCHAR(20) NULL,
    error VARCHAR(500) NULL,
    next_attempt_at DATETIME NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NULL,
    KEY idx_email_queue_status (status, next_attempt_at),
    KEY idx_email_queue_employee (employee_id),
    KEY idx_email_queue_created (created_at)
);

CREATE TABLE IF NOT EXISTS cron_runs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    job VARCHAR(60) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'RUNNING',
    source VARCHAR(20) NOT NULL DEFAULT 'MANUAL',
    processed INT NOT NULL DEFAULT 0,
    message VARCHAR(500) NULL,
    duration_ms INT NOT NULL DEFAULT 0,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    KEY idx_cron_runs_job (job, started_at)
);

-- ---------------------------------------------------------------------------
-- Batch 19 — Dokumen karyawan & riwayat jabatan/posisi
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS employee_documents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    doc_type VARCHAR(30) NOT NULL DEFAULT 'LAINNYA',
    title VARCHAR(160) NOT NULL,
    document_number VARCHAR(80) NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(200) NULL,
    file_size INT NOT NULL DEFAULT 0,
    mime_type VARCHAR(80) NULL,
    issued_at DATE NULL,
    expires_at DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'UNVERIFIED',
    is_confidential TINYINT(1) NOT NULL DEFAULT 0,
    note VARCHAR(255) NULL,
    uploaded_by INT UNSIGNED NULL,
    verified_by INT UNSIGNED NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_employee_doc_employee (employee_id, status),
    KEY idx_employee_doc_expiry (expires_at),
    KEY idx_employee_doc_type (doc_type)
);

CREATE TABLE IF NOT EXISTS employee_job_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    change_type VARCHAR(20) NOT NULL DEFAULT 'MUTATION',
    effective_date DATE NOT NULL,
    end_date DATE NULL,
    business_unit_id INT UNSIGNED NULL,
    department_id INT UNSIGNED NULL,
    position_id INT UNSIGNED NULL,
    unit_name VARCHAR(120) NULL,
    department_name VARCHAR(120) NULL,
    position_name VARCHAR(120) NULL,
    employment_status VARCHAR(30) NULL,
    basic_salary DECIMAL(15,2) NULL,
    previous_salary DECIMAL(15,2) NULL,
    sk_number VARCHAR(80) NULL,
    note VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NULL,
    KEY idx_job_history_employee (employee_id, effective_date),
    KEY idx_job_history_type (change_type, effective_date)
);

-- ---------------------------------------------------------------------------
-- Batch 17.1 — Target pengumuman berlapis (unit, departemen, jabatan, peran,
-- karyawan tertentu, atau kombinasi beberapa sekaligus)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS announcement_targets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT UNSIGNED NOT NULL,
    target_type VARCHAR(20) NOT NULL DEFAULT 'ALL',
    target_value VARCHAR(64) NOT NULL DEFAULT '',
    created_at DATETIME NULL,
    UNIQUE KEY uq_announcement_target (announcement_id, target_type, target_value),
    KEY idx_ann_target_ann (announcement_id),
    KEY idx_ann_target_type (target_type, target_value)
);

-- ---------------------------------------------------------------------------
-- Batch 17 — Register aset offboarding & BAST (berita acara serah terima)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS offboarding_assets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    asset_code VARCHAR(60) NULL,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(20) NOT NULL DEFAULT 'OTHER',
    qty INT NOT NULL DEFAULT 1,
    condition_state VARCHAR(20) NOT NULL DEFAULT 'GOOD',
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    note VARCHAR(255) NULL,
    returned_by INT UNSIGNED NULL,
    returned_at DATETIME NULL,
    created_at DATETIME NULL,
    KEY idx_offboarding_asset (request_id, status)
);

CREATE TABLE IF NOT EXISTS offboarding_bast (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    bast_number VARCHAR(50) NOT NULL,
    issued_at DATETIME NULL,
    issued_by INT UNSIGNED NULL,
    receiver_name VARCHAR(150) NULL,
    receiver_position VARCHAR(120) NULL,
    witness_name VARCHAR(150) NULL,
    note VARCHAR(500) NULL,
    items_snapshot TEXT NULL,
    created_at DATETIME NULL,
    UNIQUE KEY uq_offboarding_bast_number (bast_number),
    KEY idx_offboarding_bast_request (request_id, issued_at)
);
