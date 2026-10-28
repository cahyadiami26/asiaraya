-- =====================================================
-- Web HRIS PT Asia Raya Group
-- MySQL Schema (native PHP + MySQL)
-- Jalankan: mysql -u root -p < database/schema.sql
-- =====================================================

CREATE DATABASE IF NOT EXISTS hris_asia_raya CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hris_asia_raya;

-- ---------- USERS & ROLES ----------
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NULL,
  username VARCHAR(100) UNIQUE NULL,
  password_hash VARCHAR(255) NULL,
  google_sub VARCHAR(191) UNIQUE NULL,
  email VARCHAR(191) UNIQUE NULL,
  role ENUM('EMPLOYEE','HR','FIN') NOT NULL DEFAULT 'EMPLOYEE',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- COMPANY SETTINGS ----------
CREATE TABLE company_settings (
  id INT PRIMARY KEY DEFAULT 1,
  name VARCHAR(200) NOT NULL DEFAULT 'PT Asia Raya Group',
  address TEXT,
  phone VARCHAR(50),
  email VARCHAR(191),
  website VARCHAR(191),
  tax_number VARCHAR(50),
  logo_path VARCHAR(255),
  currency VARCHAR(10) NOT NULL DEFAULT 'IDR',
  timezone VARCHAR(60) NOT NULL DEFAULT 'Asia/Jakarta',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE id_formats (
  id INT PRIMARY KEY DEFAULT 1,
  prefix VARCHAR(20) NOT NULL DEFAULT 'ARG',
  separator VARCHAR(3) NOT NULL DEFAULT '-',
  year_part TINYINT(1) NOT NULL DEFAULT 1,
  number_length INT NOT NULL DEFAULT 4,
  next_number BIGINT NOT NULL DEFAULT 1,
  sample VARCHAR(50) DEFAULT 'ARG-2026-0001'
) ENGINE=InnoDB;

-- ---------- ORGANIZATION UNITS ----------
CREATE TABLE branches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) UNIQUE,
  name VARCHAR(150) NOT NULL,
  address TEXT,
  city VARCHAR(100),
  phone VARCHAR(50),
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  radius_meter INT NOT NULL DEFAULT 300,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE departments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  branch_id INT NULL,
  name VARCHAR(150) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE positions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  department_id INT NULL,
  name VARCHAR(150) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- EMPLOYEES ----------
CREATE TABLE employees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_code VARCHAR(50) UNIQUE NOT NULL,
  full_name VARCHAR(200) NOT NULL,
  address TEXT,
  phone VARCHAR(30),
  email VARCHAR(191),
  direct_manager_id INT NULL,
  branch_id INT NULL,
  department_id INT NULL,
  position_id INT NULL,
  base_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
  employment_status VARCHAR(30) NOT NULL DEFAULT 'Permanent',
  contract_start DATE NULL,
  contract_end DATE NULL,
  flag ENUM('active','resign') NOT NULL DEFAULT 'active',
  photo_path VARCHAR(255),
  hire_date DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (direct_manager_id) REFERENCES employees(id) ON DELETE SET NULL,
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
  FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE users ADD CONSTRAINT fk_users_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL;

-- ---------- SHIFTS / SCHEDULES ----------
CREATE TABLE shifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  break_time TIME NOT NULL DEFAULT '01:00:00',
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shift_id INT NOT NULL,
  branch_id INT NOT NULL,
  employee_id INT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  check_in_time TIME NOT NULL,
  check_out_time TIME NOT NULL,
  break_time TIME NOT NULL DEFAULT '01:00:00',
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE CASCADE,
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  INDEX idx_sched_emp (employee_id, start_date, end_date),
  INDEX idx_sched_branch (branch_id, start_date, end_date)
) ENGINE=InnoDB;

CREATE TABLE schedule_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  schedule_id INT NULL,
  requested_start DATE NOT NULL,
  requested_end DATE NOT NULL,
  requested_check_in TIME NOT NULL,
  requested_check_out TIME NOT NULL,
  requested_break TIME NOT NULL DEFAULT '01:00:00',
  reason TEXT,
  status ENUM('PENDING_MGR','PENDING_HR','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING_MGR',
  manager_note TEXT, manager_action_at DATETIME NULL,
  hr_note TEXT, hr_action_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- ATTENDANCE ----------
CREATE TABLE attendance_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  branch_id INT NULL,
  log_date DATE NOT NULL,
  check_in_at DATETIME NULL,
  check_out_at DATETIME NULL,
  check_in_lat DECIMAL(10,7) NULL, check_in_lng DECIMAL(10,7) NULL,
  check_out_lat DECIMAL(10,7) NULL, check_out_lng DECIMAL(10,7) NULL,
  check_in_distance INT NULL, check_out_distance INT NULL,
  check_in_photo VARCHAR(255) NULL,
  check_out_photo VARCHAR(255) NULL,
  late_minutes INT NOT NULL DEFAULT 0,
  overtime_hours DECIMAL(5,2) NOT NULL DEFAULT 0,
  work_hours DECIMAL(5,2) NOT NULL DEFAULT 0,
  source ENUM('PWA','MANUAL','WEB') NOT NULL DEFAULT 'PWA',
  notes VARCHAR(255) NULL,
  UNIQUE KEY uq_att_daily (employee_id, log_date),
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  INDEX idx_att_date (log_date),
  INDEX idx_att_branch (branch_id, log_date)
) ENGINE=InnoDB;

CREATE TABLE attendance_settings (
  id INT PRIMARY KEY DEFAULT 1,
  grace_minutes INT NOT NULL DEFAULT 5 COMMENT 'toleransi keterlambatan (menit)',
  checkin_start TIME NOT NULL DEFAULT '06:00:00',
  checkin_deadline TIME NOT NULL DEFAULT '10:00:00',
  selfie_max_kb INT NOT NULL DEFAULT 200 COMMENT 'ukuran maks foto setelah kompresi',
  selfie_quality INT NOT NULL DEFAULT 70 COMMENT 'kualitas jpeg kompresi client',
  selfie_resize INT NOT NULL DEFAULT 640 COMMENT 'dimensi maks sisi terpanjang (px)',
  lat_cut_per_minute DECIMAL(10,2) NOT NULL DEFAULT 1000 COMMENT 'potongan gaji per menit terlambat',
  lat_cut_max_per_day DECIMAL(12,2) NOT NULL DEFAULT 50000 COMMENT 'maks potongan harian',
  absence_deduction_type ENUM('salary','leave') NOT NULL DEFAULT 'salary' COMMENT 'sumber potongan ketidak hadiran',
  absence_cut_amount DECIMAL(12,2) NOT NULL DEFAULT 150000 COMMENT 'nominal potong gaji per hari tidak hadir',
  absence_deduct_leave_quota TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'kurangi jatah cuti jika tipe=leave'
) ENGINE=InnoDB;

-- ---------- OVERTIME ----------
CREATE TABLE overtime_settings (
  id INT PRIMARY KEY DEFAULT 1,
  rate_hour1 DECIMAL(12,2) NOT NULL DEFAULT 25000,
  rate_hour2 DECIMAL(12,2) NOT NULL DEFAULT 35000,
  rate_hour_more DECIMAL(12,2) NOT NULL DEFAULT 45000,
  max_hours_per_day DECIMAL(5,2) NOT NULL DEFAULT 4,
  max_comp_per_day DECIMAL(14,2) NOT NULL DEFAULT 200000,
  enabled TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE overtime_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  work_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  duration_hours DECIMAL(5,2) NOT NULL DEFAULT 0,
  reason TEXT,
  status ENUM('PENDING_MGR','PENDING_HR','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING_MGR',
  manager_note TEXT, manager_action_at DATETIME NULL,
  hr_note TEXT, hr_action_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  INDEX idx_ot (work_date, status)
) ENGINE=InnoDB;

-- ---------- LEAVE ----------
CREATE TABLE leave_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  is_paid TINYINT(1) NOT NULL DEFAULT 1,
  default_quota_days INT NOT NULL DEFAULT 12,
  requires_attachment TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE leave_quotas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  leave_type_id INT NOT NULL,
  year INT NOT NULL,
  quota_days DECIMAL(6,2) NOT NULL DEFAULT 0,
  used_days DECIMAL(6,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_quota (employee_id, leave_type_id, year),
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE leave_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  leave_type_id INT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  days_count DECIMAL(6,2) NOT NULL DEFAULT 1,
  purpose TEXT,
  attachment_path VARCHAR(255) NULL,
  status ENUM('PENDING_MGR','PENDING_HR','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING_MGR',
  manager_note TEXT, manager_action_at DATETIME NULL,
  hr_note TEXT, hr_action_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- PAYROLL COMPONENTS ----------
CREATE TABLE payroll_components (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  type ENUM('benefit','deduction') NOT NULL,
  value_type ENUM('fix','percent') NOT NULL DEFAULT 'fix',
  amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  percent_of_basic DECIMAL(6,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  include_in_pph TINYINT(1) NOT NULL DEFAULT 0,
  include_in_bpjs TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE employee_components (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  component_id INT NOT NULL,
  override_amount DECIMAL(15,2) NULL,
  UNIQUE KEY uq_empcomp (employee_id, component_id),
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  FOREIGN KEY (component_id) REFERENCES payroll_components(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payroll_settings (
  id INT PRIMARY KEY DEFAULT 1,
  pph_enabled TINYINT(1) NOT NULL DEFAULT 1,
  pph_rate_percent DECIMAL(6,2) NOT NULL DEFAULT 5.00,
  pthp_monthly DECIMAL(15,2) NOT NULL DEFAULT 4500000 COMMENT 'PTKP bulanan',
  bpjs_kesehatan_company_percent DECIMAL(6,2) NOT NULL DEFAULT 4.00,
  bpjs_kesehatan_employee_percent DECIMAL(6,2) NOT NULL DEFAULT 1.00,
  bpjs_kesehatan_max_base DECIMAL(15,2) NOT NULL DEFAULT 12000000,
  bpjs_jht_company_percent DECIMAL(6,2) NOT NULL DEFAULT 2.00,
  bpjs_jht_employee_percent DECIMAL(6,2) NOT NULL DEFAULT 3.00,
  bpjs_jp_company_percent DECIMAL(6,2) NOT NULL DEFAULT 2.00,
  bpjs_jp_employee_percent DECIMAL(6,2) NOT NULL DEFAULT 1.00,
  bpjs_jp_max_base DECIMAL(15,2) NOT NULL DEFAULT 10274000,
  bpjs_enabled TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE payroll_periods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  branch_id INT NOT NULL,
  period_month TINYINT NOT NULL,
  period_year SMALLINT NOT NULL,
  pay_date DATE NULL,
  status ENUM('OPEN','LOCKED','SENT_FINANCE','PAID') NOT NULL DEFAULT 'OPEN',
  UNIQUE KEY uq_period (branch_id, period_month, period_year),
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payslips (
  id INT AUTO_INCREMENT PRIMARY KEY,
  period_id INT NOT NULL,
  employee_id INT NOT NULL,
  basic_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
  present_days INT NOT NULL DEFAULT 0,
  absent_days INT NOT NULL DEFAULT 0,
  late_minutes INT NOT NULL DEFAULT 0,
  late_deduction DECIMAL(15,2) NOT NULL DEFAULT 0,
  absence_deduction DECIMAL(15,2) NOT NULL DEFAULT 0,
  overtime_hours DECIMAL(6,2) NOT NULL DEFAULT 0,
  overtime_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  leave_days_used DECIMAL(6,2) NOT NULL DEFAULT 0,
  unpaid_leave_days DECIMAL(6,2) NOT NULL DEFAULT 0,
  benefits_total DECIMAL(15,2) NOT NULL DEFAULT 0,
  deductions_total DECIMAL(15,2) NOT NULL DEFAULT 0,
  pph_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  bpjs_kes_company DECIMAL(15,2) NOT NULL DEFAULT 0,
  bpjs_kes_employee DECIMAL(15,2) NOT NULL DEFAULT 0,
  bpjs_jht_company DECIMAL(15,2) NOT NULL DEFAULT 0,
  bpjs_jht_employee DECIMAL(15,2) NOT NULL DEFAULT 0,
  bpjs_jp_company DECIMAL(15,2) NOT NULL DEFAULT 0,
  bpjs_jp_employee DECIMAL(15,2) NOT NULL DEFAULT 0,
  gross_pay DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_deduction DECIMAL(15,2) NOT NULL DEFAULT 0,
  net_pay DECIMAL(15,2) NOT NULL DEFAULT 0,
  breakdown_json TEXT,
  status ENUM('DRAFT','FINAL','SENT_FINANCE','PAID') NOT NULL DEFAULT 'DRAFT',
  paid_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payslip (period_id, employee_id),
  FOREIGN KEY (period_id) REFERENCES payroll_periods(id) ON DELETE CASCADE,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(100),
  details TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- SEED DATA
-- Password default semua user: "password"
-- =====================================================
INSERT INTO company_settings (id,name,address,phone,email,website,tax_number,currency,timezone) VALUES
(1,'PT Asia Raya Group','Jl. Raya Protokol Blok A No. 88, Bekasi, Jawa Barat','(021) 8899-1100','hr@asiarayagroup.co.id','https://asiarayagroup.co.id','01.234.567.8-901.000','IDR','Asia/Jakarta');

INSERT INTO id_formats (id,prefix,separator,year_part,number_length,next_number,sample) VALUES
(1,'ARG','-',1,4,11,'ARG-2026-0010');

INSERT INTO branches (id,code,name,city,address,latitude,longitude,radius_meter) VALUES
(1,'HQ','Kantor Pusat Jakarta','Jakarta','Jl. Jend. Sudirman Kav. 1',-6.2087634,106.8455998,300),
(2,'BKS','Cabang Bekasi','Bekasi','Jl. Raya Protokol Blok A No. 88',-6.2383239,106.9756637,250),
(3,'SBY','Cabang Surabaya','Surabaya','Jl. Tunjungan No. 21',-7.2624153,112.7369841,250);

INSERT INTO departments (id,branch_id,name) VALUES
(1,1,'Human Resources'),(2,1,'Finance'),(3,1,'IT'),(4,2,'Operasional'),(5,2,'Produksi'),(6,3,'Sales & Marketing');

INSERT INTO positions (id,department_id,name) VALUES
(1,1,'HR Manager'),(2,1,'HR Staff'),(3,2,'Finance Manager'),(4,2,'Accounting Staff'),
(5,3,'IT Manager'),(6,3,'Programmer'),(7,4,'Supervisor Operasional'),(8,5,'Operator Produksi'),
(9,6,'Sales Executive'),(10,6,'Marketing Staff');

INSERT INTO employees (id,employee_code,full_name,address,phone,email,direct_manager_id,branch_id,department_id,position_id,base_salary,employment_status,contract_start,contract_end,flag,hire_date) VALUES
(1,'ARG-2024-0001','Andi Wijaya','Jl. Melati No. 10, Jakarta','0812-3456-7801','andi.wijaya@asiarayagroup.co.id',NULL,1,1,1,25000000,'Permanent','2024-01-01',NULL,'active','2024-01-01'),
(2,'ARG-2024-0002','Siti Rahma','Jl. Anggrek No. 5, Jakarta','0812-3456-7802','siti.rahma@asiarayagroup.co.id',1,1,1,2,12000000,'Permanent','2024-03-01',NULL,'active','2024-03-01'),
(3,'ARG-2024-0003','Budi Santoso','Jl. Mawar No. 3, Bekasi','0812-3456-7803','budi.santoso@asiarayagroup.co.id',NULL,1,2,3,22000000,'Permanent','2024-01-15',NULL,'active','2024-01-15'),
(4,'ARG-2024-0004','Dewi Lestari','Jl. Kenanga No. 8, Bekasi','0812-3456-7804','dewi.lestari@asiarayagroup.co.id',3,1,2,4,11000000,'Contract','2024-06-01','2026-06-01','active','2024-06-01'),
(5,'ARG-2025-0005','Rizky Pratama','Jl. Bambu No. 21, Jakarta','0812-3456-7805','rizky.pratama@asiarayagroup.co.id',NULL,1,3,5,20000000,'Permanent','2025-01-01',NULL,'active','2025-01-01'),
(6,'ARG-2025-0006','Maya Putri','Jl. Cempaka No. 17, Bekasi','0812-3456-7806','maya.putri@asiarayagroup.co.id',5,2,4,7,13000000,'Permanent','2025-02-01',NULL,'active','2025-02-01'),
(7,'ARG-2025-0007','Joko Susilo','Jl. Flamboyan No. 9, Bekasi','0812-3456-7807','joko.susilo@asiarayagroup.co.id',6,2,5,8,6500000,'Contract','2025-04-01','2027-04-01','active','2025-04-01'),
(8,'ARG-2025-0008','Fitri Handayani','Jl. Dahlia No. 4, Surabaya','0812-3456-7808','fitri.handayani@asiarayagroup.co.id',NULL,3,6,9,9000000,'Permanent','2025-05-01',NULL,'active','2025-05-01'),
(9,'ARG-2026-0009','Agus Salim','Jl. Anyelir No. 12, Surabaya','0812-3456-7809','agus.salim@asiarayagroup.co.id',8,3,6,9,8000000,'Probation','2026-01-05','2026-07-05','active','2026-01-05'),
(10,'ARG-2026-0010','Lina Marlina','Jl. Teratai No. 6, Jakarta','0812-3456-7810','lina.marlina@asiarayagroup.co.id',1,1,1,2,10000000,'Permanent','2026-02-01',NULL,'active','2026-02-01');

-- Users: admin=HR (Andi Wijaya), finance=FIN (Budi Santoso), sisanya EMPLOYEE. Password: password
INSERT INTO users (id,employee_id,username,password_hash,email,role) VALUES
(1,1,'admin','$2b$10$oOTSIhvj5bJEO2TqUNXlRuGveG.fhGZNvQ4LBgj7ID7ZcFhQ6sy1y','andi.wijaya@asiarayagroup.co.id','HR'),
(2,3,'finance','$2b$10$oOTSIhvj5bJEO2TqUNXlRuGveG.fhGZNvQ4LBgj7ID7ZcFhQ6sy1y','budi.santoso@asiarayagroup.co.id','FIN'),
(3,7,'joko','$2b$10$oOTSIhvj5bJEO2TqUNXlRuGveG.fhGZNvQ4LBgj7ID7ZcFhQ6sy1y','joko.susilo@asiarayagroup.co.id','EMPLOYEE'),
(4,2,'siti','$2b$10$oOTSIhvj5bJEO2TqUNXlRuGveG.fhGZNvQ4LBgj7ID7ZcFhQ6sy1y','siti.rahma@asiarayagroup.co.id','EMPLOYEE'),
(5,NULL,'demo','$2b$10$oOTSIhvj5bJEO2TqUNXlRuGveG.fhGZNvQ4LBgj7ID7ZcFhQ6sy1y','demo@asiarayagroup.co.id','EMPLOYEE');

INSERT INTO shifts (id,name,start_time,end_time,break_time) VALUES
(1,'Shift Pagi','07:00:00','15:00:00','01:00:00'),
(2,'Shift Siang','13:00:00','21:00:00','01:00:00'),
(3,'Shift Malam','21:00:00','06:00:00','01:00:00'),
(4,'Regular Kantor','08:00:00','17:00:00','01:00:00');

INSERT INTO attendance_settings (id) VALUES (1);
INSERT INTO overtime_settings (id) VALUES (1);
INSERT INTO payroll_settings (id) VALUES (1);

INSERT INTO leave_types (id,name,is_paid,default_quota_days,requires_attachment) VALUES
(1,'Cuti Tahunan',1,12,0),
(2,'Cuti Sakit',1,14,1),
(3,'Cuti Melahirkan',1,90,1),
(4,'Cuti Penting',1,3,1),
(5,'Cuti Tidak Dibayar (Unpaid Leave)',0,0,1);

INSERT INTO payroll_components (id,name,type,value_type,amount,percent_of_basic,include_in_pph,include_in_bpjs) VALUES
(1,'Tunjangan Makan','benefit','fix',500000,0,0,0),
(2,'Tunjangan Transport','benefit','fix',400000,0,0,0),
(3,'Tunjangan Jabatan','benefit','percent',0,10,1,1),
(4,'Bonus Kinerja','benefit','fix',750000,0,1,0),
(5,'Iuran BPJS Ketenagakerjaan (JHT)','deduction','percent',0,3,0,0),
(6,'Cash Advance','deduction','fix',200000,0,0,0);

INSERT INTO employee_components (employee_id,component_id,override_amount) VALUES
(1,3,NULL),(1,1,NULL),(1,2,NULL),
(2,1,NULL),(2,2,NULL),
(3,3,NULL),(3,4,NULL),
(6,1,NULL),(6,2,NULL),(6,5,NULL),
(7,1,NULL),(7,2,NULL),(7,5,NULL),
(8,2,NULL),(9,2,NULL),(10,1,NULL);

INSERT INTO payroll_periods (id,branch_id,period_month,period_year,status) VALUES
(1,1,10,2026,'OPEN'),(2,2,10,2026,'OPEN'),(3,3,10,2026,'OPEN');

INSERT INTO leave_quotas (employee_id,leave_type_id,year,quota_days) VALUES
(1,1,2026,12),(2,1,2026,12),(3,1,2026,12),(4,1,2026,12),(5,1,2026,12),
(6,1,2026,12),(7,1,2026,12),(8,1,2026,12),(9,1,2026,12),(10,1,2026,12);
