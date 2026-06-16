CREATE DATABASE IF NOT EXISTS sandezi_oa_plus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sandezi_oa_plus;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS leaves;
DROP TABLE IF EXISTS schedules;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS shifts;
DROP TABLE IF EXISTS departments;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    department_id INT NULL,
    position VARCHAR(100) NOT NULL,
    phone VARCHAR(50),
    email VARCHAR(150),
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Manager', 'Employee') NOT NULL DEFAULT 'Employee',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
);

CREATE TABLE shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    shift_id INT NOT NULL,
    work_date DATE NOT NULL,
    remark VARCHAR(255),
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_employee_date (employee_id, work_date)
);

CREATE TABLE leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    approve_remark VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO departments (id, name, description) VALUES
(1, '综合管理部', '行政、人事、财务等综合管理'),
(2, '运营中心', '直播运营、中控、主播排班'),
(3, '客服中心', '客服在线与售后支持'),
(4, '内容中心', '编导、拍摄、剪辑相关工作'),
(5, '生产管理部', '生产基地与产品管理');

INSERT INTO employees (id, name, department_id, position, phone, email, status) VALUES
(1, '系统管理员', 1, '系统管理员', '000000', 'admin@sandezi.com', 'active'),
(2, '部门主管', 2, '运营主管', '000000', 'manager@sandezi.com', 'active'),
(3, '普通员工', 3, '客服专员', '000000', 'staff@sandezi.com', 'active'),
(4, '张三', 2, '主播', '13800000001', 'zhangsan@sandezi.com', 'active'),
(5, '李四', 2, '中控', '13800000002', 'lisi@sandezi.com', 'active'),
(6, '王五', 4, '拍剪', '13800000003', 'wangwu@sandezi.com', 'active'),
(7, '赵六', 5, '生产专员', '13800000004', 'zhaoliu@sandezi.com', 'active');

INSERT INTO users (employee_id, name, email, password_hash, role) VALUES
(1, '系统管理员', 'admin@sandezi.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin'),
(2, '部门主管', 'manager@sandezi.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Manager'),
(3, '普通员工', 'staff@sandezi.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Employee');

INSERT INTO shifts (name, start_time, end_time, description) VALUES
('早班', '08:00:00', '17:00:00', '客服/行政常规早班'),
('中班', '12:00:00', '21:00:00', '客服/运营中班'),
('晚班', '16:00:00', '23:59:00', '晚间客服与运营班次'),
('主播早班', '09:00:00', '14:00:00', '直播早班'),
('主播中班', '14:00:00', '19:00:00', '直播中班'),
('主播晚班', '19:00:00', '23:59:00', '直播晚班');

INSERT INTO schedules (employee_id, shift_id, work_date, remark, created_by) VALUES
(2, 1, CURDATE(), '主管早班', 1),
(3, 2, CURDATE(), '客服中班', 1),
(4, 4, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '主播早班', 1),
(5, 4, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '中控配合主播', 1),
(6, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '拍摄任务', 1);

INSERT INTO announcements (title, content, created_by) VALUES
('第一阶段系统测试中', '目前 OA 系统已完成基础架构、登录、员工管理、班次管理和排班管理。', 1),
('请各部门确认员工资料', '请主管检查员工姓名、部门、职位和联系方式是否正确。', 1),
('排班功能测试通知', '本周将开始测试基础排班功能，后续会继续优化请假和调班流程。', 2);
