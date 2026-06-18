# 三德子 OA 系统 — Phase 3 设备管理模块完成报告

**文件版本：** v1.0  
**生成日期：** 2026-06-18  
**最新 Commit：** `aa9cd14`  
**模块范围：** Phase 3.0 ～ Phase 3.3（设备台账、借用归还、维修报废、报表导出）

---

## 一、各子阶段完成内容与 Commit

### Phase 3.0 — 设备台账（`2848b1e`）

**新增文件：** `devices.php`  
**修改文件：** `header.php`、`deploy_production.sql`

**功能：**
- 设备台账 CRUD：新增、编辑、删除设备
- 字段：设备编号、资产编号、名称、类别、品牌、型号、序列号、所属部门、负责人、状态
- 设备状态：空闲 / 使用中 / 维修中 / 报废（彩色徽章显示）
- 关键词搜索（名称/编号/品牌/型号/序列号/负责人）+ 类别筛选 + 状态筛选
- 分页 20 条，搜索条件分页保留
- 权限：Admin/Manager 可 CRUD；Employee 只读（不显示操作列和新增表单）
- `device_code` 数据库层 UNIQUE 约束 + PHP 层前置重复检查

---

### Phase 3.1 — 设备借用与归还（`7428c74`）

**新增文件：** `device_borrow.php`、`device_borrow_manage.php`  
**修改文件：** `header.php`、`deploy_production.sql`

**员工端（`device_borrow.php`）：**
- 所有角色均可提交借用申请
- 下拉只显示状态为「空闲」的设备
- 填写用途、借用开始日期、预计归还日期
- 日期重叠冲突检测：同一设备「待审批」或「已批准」记录不可重叠
- 可撤销自己的「待审批」申请
- Admin/Manager 查看全部记录；Employee 只看自己

**管理端（`device_borrow_manage.php`）：**
- 批准：Transaction 同步 `device_borrows.status='已批准'` + `devices.status='使用中'`
- 拒绝：`device_borrows.status='已拒绝'`，设备维持「空闲」
- 归还：弹出 Modal 填写备注，Transaction 同步 `status='已归还'` + `devices.status='空闲'`
- 二次冲突防护：审批时再次检查是否已有其他批准记录
- 支持状态筛选 + 关键词搜索 + 分页

---

### Phase 3.2 — 设备维修与报废（`be5dd4c`）

**新增文件：** `device_maintenance.php`  
**修改文件：** `header.php`、`deploy_production.sql`

**功能：**
- 所有角色可提交报修（排除已报废设备）
- 填写问题标题、问题描述（选填）
- Admin/Manager 通过 Modal 更新状态、维修费用、处理备注
- 状态与设备同步（Transaction 保障原子性）：

| 维修状态 | 设备状态变更 |
|---|---|
| 待处理 | 不变 |
| 维修中 | → 维修中 |
| 已完成 | → 空闲 |
| 已报废 | → 报废 |

- 已完结记录（已完成/已报废）隐藏操作按钮
- Admin/Manager 查看全部；Employee 只看自己提交的
- 支持状态筛选 + 关键词搜索 + 分页

---

### Phase 3.3 — 设备报表导出（`aa9cd14`）

**修改文件：** `reports.php`

**新增三种设备 CSV 导出（在原有排班/请假报表基础上扩展）：**

| 报表 | 日期筛选字段 | 状态筛选 | 字段数 |
|---|---|---|---|
| 设备台账 | `created_at` | ✓ | 11 |
| 设备借用记录 | `borrow_start/end` 区间重叠 | — | 15 |
| 设备维修记录 | `created_at` | ✓ | 13 |

**页面设计：**
- 分区展示：`📋 HR 报表` 和 `📦 设备报表`
- 设备状态筛选下拉，仅影响台账和维修导出
- 无记录时导出按钮自动禁用
- 文件名含日期范围，台账含状态标记（如 `设备台账_维修中_...csv`）

---

## 二、数据库结构

### `devices` 表（Phase 3.0）
```sql
CREATE TABLE devices (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    device_code   VARCHAR(50)  NULL UNIQUE,   -- 设备编号（唯一）
    asset_code    VARCHAR(50)  NULL,           -- 资产编号（财务）
    name          VARCHAR(150) NOT NULL,
    category      VARCHAR(100) NOT NULL DEFAULT '',
    brand         VARCHAR(100) NOT NULL DEFAULT '',
    model         VARCHAR(100) NOT NULL DEFAULT '',
    serial_number VARCHAR(100) NULL,
    department_id INT NULL,                    -- FK → departments
    manager       VARCHAR(100) NOT NULL DEFAULT '',
    status        ENUM('空闲','使用中','维修中','报废') DEFAULT '空闲',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### `device_borrows` 表（Phase 3.1）
```sql
CREATE TABLE device_borrows (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    device_id    INT NOT NULL,                 -- FK → devices CASCADE
    employee_id  INT NULL,                     -- FK → employees SET NULL
    purpose      TEXT NOT NULL,
    borrow_start DATE NOT NULL,
    borrow_end   DATE NOT NULL,
    status       ENUM('待审批','已批准','已拒绝','已归还') DEFAULT '待审批',
    approved_by  INT NULL,                     -- FK → users SET NULL
    approved_at  DATETIME NULL,
    returned_at  DATETIME NULL,
    return_note  VARCHAR(500) NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### `device_maintenance` 表（Phase 3.2）
```sql
CREATE TABLE device_maintenance (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    device_id         INT NOT NULL,            -- FK → devices CASCADE
    report_by         INT NULL,                -- FK → employees SET NULL
    issue_title       VARCHAR(200) NOT NULL,
    issue_description TEXT,
    status            ENUM('待处理','维修中','已完成','已报废') DEFAULT '待处理',
    cost              DECIMAL(10,2) NULL,
    handled_by        INT NULL,                -- FK → users SET NULL
    handled_at        DATETIME NULL,
    note              VARCHAR(1000) NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 三、设备完整生命周期流程

```
┌─────────────────────────────────────────────────────────────────┐
│                      设备生命周期                                │
└─────────────────────────────────────────────────────────────────┘

  1. 设备登记（devices.php）
     Admin/Manager 录入设备资料
     初始状态：空闲
         │
         ▼
  2. 借用申请（device_borrow.php）
     员工选择「空闲」设备，填写用途和日期
     → 状态：待审批
     ← 可撤销（员工本人 或 Admin/Manager）
         │
         ▼
  3. 借用审批（device_borrow_manage.php）
     Admin/Manager 审批
     ├── 批准 → 设备状态：使用中
     └── 拒绝 → 设备状态：空闲（归还原位）
         │（批准后）
         ▼
  4. 设备归还（device_borrow_manage.php）
     Admin/Manager 登记归还
     → 借用状态：已归还
     → 设备状态：空闲
         │
         ▼
  5. 设备报修（device_maintenance.php）
     任何角色发现问题可提交报修
     → 维修状态：待处理
         │
         ▼
  6. 维修处理（device_maintenance.php）
     Admin/Manager 更新维修进度
     ├── 维修中   → 设备状态：维修中
     ├── 已完成   → 设备状态：空闲（回到步骤 2 可用）
     └── 已报废   → 设备状态：报废（退出流程）
         │
         ▼
  7. 报表导出（reports.php）
     Admin/Manager 按日期/状态导出
     ├── 设备台账 CSV
     ├── 借用记录 CSV
     └── 维修记录 CSV
```

---

## 四、设备模块功能清单

| 功能 | 文件 | 权限 | 状态 |
|---|---|---|---|
| 设备台账 CRUD | `devices.php` | Admin/Manager（CRUD）Employee（只读）| ✅ |
| 设备搜索 + 分页 | `devices.php` | 全员 | ✅ |
| 设备借用申请 | `device_borrow.php` | 全员 | ✅ |
| 日期冲突检测 | `device_borrow.php` | 系统自动 | ✅ |
| 借用撤销 | `device_borrow.php` | 员工本人 / Admin / Manager | ✅ |
| 借用审批（批准/拒绝） | `device_borrow_manage.php` | Admin / Manager | ✅ |
| 归还登记（含备注 Modal） | `device_borrow_manage.php` | Admin / Manager | ✅ |
| 设备报修提交 | `device_maintenance.php` | 全员 | ✅ |
| 维修状态更新 | `device_maintenance.php` | Admin / Manager | ✅ |
| 报废处理 | `device_maintenance.php` | Admin / Manager | ✅ |
| 设备台账 CSV 导出 | `reports.php` | Admin / Manager | ✅ |
| 借用记录 CSV 导出 | `reports.php` | Admin / Manager | ✅ |
| 维修记录 CSV 导出 | `reports.php` | Admin / Manager | ✅ |
| 设备状态筛选导出 | `reports.php` | Admin / Manager | ✅ |
| 设备状态自动同步 | 借用/维修 Transaction | 系统自动 | ✅ |
| 侧边栏导航 | `header.php` | 按角色显示 | ✅ |
| 生产部署 SQL | `deploy_production.sql` | — | ✅ |

---

## 五、当前完成度评估

```
Phase 3.0 设备台账         ████████████████████  100%
Phase 3.1 借用与归还       ████████████████████  100%
Phase 3.2 维修与报废       ████████████████████  100%
Phase 3.3 报表导出         ████████████████████  100%

设备模块整体完成度                               ≈ 92%
（余 8% 为高级功能，见剩余优化项）
```

**设备模块已具备：**
- ✅ 完整的资产生命周期管理（登记→借用→归还→维修→报废）
- ✅ 状态自动联动（借用/维修操作自动更新设备主状态）
- ✅ Transaction 保障数据一致性
- ✅ 冲突检测防止重复借用
- ✅ 三种 CSV 报表支持管理层数据分析
- ✅ 全站 CSRF + PDO prepared statement + safe() 输出

---

## 六、剩余可优化项目

以下功能属于进阶需求，当前 MVP 阶段不包含，可视业务需要在后续迭代中实现：

### 🔲 QR Code / 条码标签
- 为每台设备生成唯一 QR Code
- 用手机扫描快速跳转设备详情页
- 支持打印标签粘贴到实体设备
- **实现思路：** 使用 PHP `endroid/qr-code` 库或前端 `qrcode.js` 生成

### 🔲 设备图片上传
- 每台设备可上传 1～3 张图片（设备外观、铭牌等）
- 支持查看图片用于核对资产
- **实现思路：** `devices_images` 表 + 服务器本地或 OSS 存储

### 🔲 设备盘点
- 管理员定期发起「盘点任务」
- 各部门负责人确认设备清单（在线/缺失/状态异常）
- 生成盘点差异报告
- **实现思路：** `device_inventories` + `inventory_items` 两张表

### 🔲 设备保养提醒
- 为设备设定保养周期（如每 90 天一次）
- 系统自动计算下次保养日期
- 到期前在 Dashboard 显示提醒
- **实现思路：** `devices` 表加 `next_maintenance_date` 字段 + Dashboard 查询

### 🔲 企业微信审批集成
- 借用申请提交后自动发送企业微信审批通知给 Admin/Manager
- 审批结果通知申请人
- **实现思路：** 企业微信 Webhook / 应用消息 API

---

## 七、老板可看的总结

**Phase 3 设备管理模块，我们做了什么？**

完成了一套**完整的设备资产管理系统**，涵盖从设备入库到最终报废的全流程：

1. **设备台账** — 把公司所有直播设备、电脑、摄影器材等统一录入系统，一目了然
2. **借用管理** — 员工想借设备先提申请，管理员线上审批，告别纸条登记，归还自动恢复可用状态
3. **维修管理** — 设备出问题直接在系统报修，管理员跟进维修进度，报废设备自动标记退出使用
4. **报表导出** — 随时按日期、状态导出设备台账、借用记录、维修记录，财务盘点和运营分析有数据支撑

**现在可以用来做什么？**

✅ 取代 Excel / 纸质登记本管理设备借用  
✅ 追踪每台设备的当前状态（空闲/使用中/维修中/报废）  
✅ 月度/季度生成设备使用报告  
✅ 清楚知道哪台设备被谁借走、何时归还  

**还没有做什么（后续可加）？**

- 手机扫 QR Code 快速查设备（目前需手动搜索）
- 设备图片存档
- 设备定期保养提醒
- 企业微信借用审批通知

这些属于进阶功能，业务跑顺后可按需追加。

---

## 八、Commit 汇总

| Commit | 说明 |
|---|---|
| `2848b1e` | Phase 3.0 — 设备台账 CRUD + 搜索分页 |
| `7428c74` | Phase 3.1 — 设备借用与归还（含冲突检测） |
| `be5dd4c` | Phase 3.2 — 设备维修与报废记录 |
| `aa9cd14` | Phase 3.3 — 设备报表 CSV 导出扩展 |

---

*本报告由开发团队生成，日期：2026-06-18*
