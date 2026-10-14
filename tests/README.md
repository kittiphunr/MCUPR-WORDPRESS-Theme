# ชุดทดสอบ (End-to-end)

ทดสอบธีมบน **WordPress จริง** (ตัวหลักจาก Packagist + SQLite จึงไม่ต้องมี MySQL และไม่ต้องเข้าถึง wordpress.org) ด้วย **Playwright** และตรวจการเข้าถึงด้วย **axe-core**

## ต้องมี
`php` ≥ 7.4 (sqlite3, gd, mbstring), `composer`, `node` ≥ 18 และ `curl`

## รัน

```bash
cd tests
npm install
npm test                   # สร้างไซต์ทดสอบ (ครั้งแรก) + รันทุกชุด
npm run test:functional    # หรือ responsive | elementor | polylang
npm run check              # ตรวจเร็ว (ไม่ต้องมี WordPress): PHP lint, JS syntax, CSS Tailwind เป็นปัจจุบัน
```

ตัวแปรสภาพแวดล้อม: `PORT` (ค่าเริ่มต้น 8080), `CHROMIUM_PATH` (ใช้ Chromium ที่ติดตั้งไว้แล้วแทน Playwright; ถ้าไม่ตั้ง สคริปต์จะรัน `npx playwright install chromium` ให้)  
สร้างไซต์ใหม่ทั้งหมด: `bash bootstrap.sh --fresh` · ภาพหน้าจอจะอยู่ที่ `tests/screenshots/` (ไม่ถูก commit)

## แต่ละชุดทดสอบ

| ไฟล์ | ตรวจอะไร |
|---|---|
| `e2e/functional.js` | สลับ 4 ภาษา + จำค่า, คำแปลเนื้อหา/แจ้งเมื่อไม่มีคำแปล, ขนาดอักษร (87.5–200%), โหมดมืด/ตามระบบ, โหมดคอนทราสต์/สีเทา/ระยะห่าง, แผนการเข้าถึง (โฟกัส, Esc), SweetAlert2 (คุกกี้/PDPA, ยืนยันรีเซ็ต, toast, เตือนค้นหาว่าง), เมนูย่อยด้วยคีย์บอร์ด, skip link, มือถือ และ **axe-core** (WCAG 2.2 AA + best-practice) 4 หน้า × สว่าง/มืด |
| `e2e/responsive.js` | ความกว้าง 240 → 3840px: ไม่มีการเลื่อนแนวนอน, ปุ่ม/ลิงก์ ≥ 24px; โหมดทีวี (UA ของ Tizen): เปิดอัตโนมัติ, ธีมมืด, ตัวอักษรใหญ่, ปุ่มลูกศรเลื่อนโฟกัส, กรอบโฟกัสหนา, ปิดโหมดทีวีได้ |
| `e2e/elementor.js` | Theme Builder location (header/footer/single/archive) ผ่านตัวจำลอง, แถบเครื่องมือยังทำงานเมื่อใช้ Header ของ Elementor, หน้าที่สร้างด้วย Elementor แสดงเต็มความกว้าง |
| `e2e/polylang.js` | โหมด Polylang ผ่านตัวจำลอง: ภาษา/URL/สลับภาษา, ไม่มี hreflang/คุกกี้ของธีม, ภาษาที่ไม่มี UI ใช้อังกฤษ |

## ข้อจำกัดที่ควรรู้
- **Elementor Pro และ Polylang ใช้ตัวจำลอง** (`fixtures/mu-plugins/*-stub.php`) ที่เลียนแบบฟังก์ชันสาธารณะที่ธีมเรียก — ยืนยันว่าตรรกะของธีมถูกต้อง แต่ **ไม่ได้ทดสอบกับปลั๊กอินจริง** ควรทดสอบมือบนไซต์ที่ติดตั้งปลั๊กอินจริงด้วย
- ทดสอบเฉพาะ Chromium; ไม่ได้ทดสอบกับ screen reader จริง, ทีวีจริง หรือเบราว์เซอร์ทีวีรุ่นเก่า
- ไซต์ทดสอบปิดการเชื่อมต่อออกภายนอก (`WP_HTTP_BLOCK_EXTERNAL`)

## ข้อมูลทดสอบ (`fixtures/seed.php`)
ข่าวภาษาไทย 10 ข่าว (ข่าวที่ 1 มีคำแปล en/zh) พร้อมรูปที่สร้างด้วย GD, หน้านโยบายความเป็นส่วนตัว, หน้า `el-page` (ตั้ง meta ว่าสร้างด้วย Elementor), เมนูครบทุกตำแหน่ง และค่า Customizer
