-- ============================================================
-- 三德子 OA 系统 — 生产部署 SQL
-- Phase 1 · 版本 b128be2
-- 生成日期：2026-06-18
-- 用途：首次上线时在生产服务器执行
-- 注意：执行前请先备份现有数据库
-- ============================================================

CREATE DATABASE IF NOT EXISTS sandezi_oa_plus
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sandezi_oa_plus;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS devices;
DROP TABLE IF EXISTS login_logs;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS leaves;
DROP TABLE IF EXISTS schedules;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS shifts;
DROP TABLE IF EXISTS departments;
SET FOREIGN_KEY_CHECKS = 1;

-- 部门
CREATE TABLE departments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 员工
CREATE TABLE employees (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    department_id INT NULL,
    position      VARCHAR(100) NOT NULL,
    phone         VARCHAR(50),
    email         VARCHAR(150),
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- 系统用户
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    employee_id   INT NULL,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('Admin','Manager','Employee') NOT NULL DEFAULT 'Employee',
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
);

-- 班次
CREATE TABLE shifts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    start_time  TIME NOT NULL,
    end_time    TIME NOT NULL,
    description VARCHAR(255),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 排班
CREATE TABLE schedules (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    shift_id    INT NOT NULL,
    work_date   DATE NOT NULL,
    remark      VARCHAR(255),
    created_by  INT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (shift_id)    REFERENCES shifts(id)    ON DELETE CASCADE,
    FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL,
    UNIQUE KEY unique_employee_date (employee_id, work_date)
);

-- 请假
CREATE TABLE leaves (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    employee_id    INT NOT NULL,
    leave_type     VARCHAR(50) NOT NULL,
    start_date     DATE NOT NULL,
    end_date       DATE NOT NULL,
    reason         TEXT,
    status         ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    approve_remark VARCHAR(255),
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- 登录日志
CREATE TABLE login_logs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(150) NOT NULL,
    user_id    INT NULL,
    ip         VARCHAR(45)  NOT NULL DEFAULT '',
    user_agent VARCHAR(512) NOT NULL DEFAULT '',
    status     ENUM('success','failed') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_email    (email),
    INDEX idx_ip       (ip),
    INDEX idx_status   (status),
    INDEX idx_created  (created_at)
);

-- 设备台账
CREATE TABLE devices (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    device_code   VARCHAR(50)  NULL UNIQUE COMMENT '设备编号',
    asset_code    VARCHAR(50)  NULL        COMMENT '资产编号（财务）',
    name          VARCHAR(150) NOT NULL    COMMENT '设备名称',
    category      VARCHAR(100) NOT NULL DEFAULT '' COMMENT '设备类别',
    brand         VARCHAR(100) NOT NULL DEFAULT '' COMMENT '品牌',
    model         VARCHAR(100) NOT NULL DEFAULT '' COMMENT '型号',
    serial_number VARCHAR(100) NULL        COMMENT '序列号/SN',
    department_id INT NULL                 COMMENT '所属部门',
    manager       VARCHAR(100) NOT NULL DEFAULT '' COMMENT '负责人',
    status        ENUM('空闲','使用中','维修中','报废') NOT NULL DEFAULT '空闲',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_category (category),
    INDEX idx_status   (status)
);

-- 公告
CREATE TABLE announcements (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(200) NOT NULL,
    content    TEXT NOT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- 种子数据：基础部门（生产环境请按实际修改）
-- ============================================================
INSERT INTO departments (name, description) VALUES
('综合管理部', '行政、人事、财务等综合管理'),
('运营中心',   '直播运营、中控、主播排班'),
('客服中心',   '客服在线与售后支持'),
('内容中心',   '编导、拍摄、剪辑相关工作'),
('生产管理部', '生产基地与产品管理');

-- 基础班次
INSERT INTO shifts (name, start_time, end_time, description) VALUES
('早班',     '08:00:00', '17:00:00', '客服/行政常规早班'),
('中班',     '12:00:00', '21:00:00', '客服/运营中班'),
('晚班',     '16:00:00', '23:59:00', '晚间客服与运营班次'),
('主播早班', '09:00:00', '14:00:00', '直播早班'),
('主播中班', '14:00:00', '19:00:00', '直播中班'),
('主播晚班', '19:00:00', '23:59:00', '直播晚班');

-- ============================================================
-- 首个 Admin 账号
-- 上线后请立即登录修改密码！
-- 初始密码：SdzAdmin2026!
-- bcrypt hash 由 PHP password_hash('SdzAdmin2026!', PASSWORD_DEFAULT) 生成
-- ============================================================
INSERT INTO employees (id, name, department_id, position, phone, email, status)
VALUES (1, '系统管理员', 1, '系统管理员', '', 'admin@sandezi.com', 'active');

INSERT INTO users (employee_id, name, email, password_hash, role, status)
VALUES (
    1,
    '系统管理员',
    'admin@sandezi.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Admin',
    'active'
);
-- ⚠️ 上线后第一件事：用 Admin 登录 → 修改密码

-- ============================================================
-- 部署完成
-- ============================================================
