# 🚀 IT System Access Request System
**โรงแรม:** Katathani Collection  
**Tech Stack:** PHP 8.2 + MySQL (MariaDB) + Tailwind CSS + HTML5 Canvas Signature + Notification Engine + PDF Print Engine

---

## 📖 คู่มือการนำระบบขึ้น Plesk Hosting (Deployment Guide)
ระบบได้รับการตั้งค่าเพื่อรองรับการนำขึ้น **Plesk Web Hosting** เรียบร้อยแล้ว โดยแยกไฟล์เชื่อมต่อฐานข้อมูลออกจากโค้ดหลัก เพื่อความปลอดภัยและความสะดวก:
- รายละเอียดขั้นตอนแบบจับมือทำ กรุณาอ่านในไฟล์: **[readem.md](file:///c:/xampp/htdocs/Request%20From/readem.md)**
- สคริปต์เชื่อมต่อฐานข้อมูล `dashboard_member`: **[database_requests_link.sql](file:///c:/xampp/htdocs/Request%20From/database_requests_link.sql)**
- ไฟล์ฐานข้อมูลรวมทั้งหมด: **[database.sql](file:///c:/xampp/htdocs/Request%20From/database.sql)**
- ไฟล์ตั้งค่าสำหรับ Plesk: **[config/db_config.example.php](file:///c:/xampp/htdocs/Request%20From/config/db_config.example.php)** (คัดลอกเป็น `config/db_config.php`)

---

## 🚀 วิธีเข้าใช้งานระบบบนเครื่อง Local (How to Access)

1. เปิดเบราว์เซอร์แล้วเข้าสู่ URL:
   ```
   http://localhost/Request%20From/
   ```
2. หรือเข้าหน้ายื่นคำขอใหม่โดยตรง:
   ```
   http://localhost/Request%20From/new_request.php
   ```

---

## 👥 บัญชีผู้ใช้ตัวอย่างในระบบ (Demo Accounts)

ทุกบัญชีใช้รหัสผ่านเดียวกัน: **`password123`**  
*(หรือสามารถกดปุ่ม **1-Click Role Switcher** ที่มุมขวาบนของหน้าเว็บเพื่อสลับตำแหน่งทดสอบได้ทันที)*

| บทบาท (Role) | Username | ชื่อ-สกุล / ตำแหน่ง | หน้าที่ในระบบ |
| :--- | :--- | :--- | :--- |
| **Requester** | `requester` | Autthaporn Najit (F/B Supervisor) | ผู้ยื่นคำขอ กรอกข้อมูล และเซ็นชื่อ |
| **Dept Head** | `depthead` | K. Supaphan Hinsonngnern (F/B Head) | **ผู้อนุมัติคนที่ 1:** ตรวจสอบและลงลายเซ็น |
| **Hotel Manager** | `hotelmgr` | General Manager (Hotel) | **ผู้อนุมัติคนที่ 2:** ลงลายเซ็นอนุมัติระดับโรงแรม |
| **IT Staff** | `itstaff` | IT Support Officer | **ผู้ดำเนินการ:** สร้างบัญชี, กำหนดวันเริ่มงาน & วันหมดอายุ |
| **IT Manager** | `itmgr` | K. Somchai IT Manager | **ผู้อนุมัติคนที่ 3:** ลงลายเซ็นอนุมัติระดับไอที |
| **HR Staff** | `hrstaff` | HR Officer (Personnel) | บันทึก **Filing Date** จัดเก็บเอกสารเข้าแฟ้มประวัติ |
