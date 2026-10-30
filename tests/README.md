# ชุดทดสอบ (End-to-end)

ทดสอบธีมบน **WordPress จริง** (ตัวหลักจาก Packagist + SQLite จึงไม่ต้องมี MySQL และไม่ต้องเข้าถึง wordpress.org) ด้วย **Playwright** และตรวจการเข้าถึงด้วย **axe-core**

## ต้องมี
`php` ≥ 7.4 (sqlite3, gd, mbstring), `composer`, `node` ≥ 18 และ `curl`

## รัน

```bash
cd tests
npm install
npm test                   # สร้างไซต์ทดสอบ (ครั้งแรก) + รันทุกชุด
npm run test:functional    # หรือ layout | content | effects | responsive
npm run check              # ตรวจเร็ว (ไม่ต้องมี WordPress): PHP lint, JS syntax, CSS Tailwind เป็นปัจจุบัน
```

ตัวแปรสภาพแวดล้อม: `PORT` (ค่าเริ่มต้น 8080), `CHROMIUM_PATH` (ใช้ Chromium ที่ติดตั้งไว้แล้วแทน Playwright; ถ้าไม่ตั้ง สคริปต์จะรัน `npx playwright install chromium` ให้)  
ทดสอบบน WordPress รุ่นอื่น: `WP_VERSION=6.2.2 SITE_DIR=.site-6.2 PORT=8383 bash bootstrap.sh` แล้วรัน `WP_DEBUG_DISPLAY=0 SITE_DIR=.site-6.2 PORT=8383 bash run.sh` (ปิดการแสดงคำเตือน deprecated ของ WordPress รุ่นเก่าบน PHP ใหม่)  
สร้างไซต์ใหม่ทั้งหมด: `bash bootstrap.sh --fresh` · ภาพหน้าจอจะอยู่ที่ `tests/screenshots/` (ไม่ถูก commit)

## แต่ละชุดทดสอบ

| ไฟล์ | ตรวจอะไร |
|---|---|
| `php/menus.php` | จัดการเมนู (ไอคอน/Mega menu) กับ WordPress จริง: ช่องใน Appearance → Menus, การบันทึก (สิทธิ์ + กรองค่า), มาร์กอัปหน้าเว็บ |
| `e2e/functional.js` | คุกกี้/PDPA, สลับ 4 ภาษา (กล่องภาษา) + จำค่า, คำแปลเนื้อหา, กล่องตัวช่วยการเข้าถึง (ขนาดอักษร 87.5–200%, สวิตช์คอนทราสต์, โหมดมืด, ตัวเลือกเพิ่มเติม, รีเซ็ต, โฟกัส/Esc), กล่องค้นหาเต็มจอ (ค้นหาล่าสุด/ยอดนิยม/ข้อความเมื่อว่าง), เมนูย่อยด้วยคีย์บอร์ด, drawer มือถือ, skip link และ **axe-core** (WCAG 2.2 AA + best-practice) 4 หน้า × สว่าง/มืด + 3 กล่องโต้ตอบ |
| `e2e/layout.js` | อินเทอร์เฟซแบบ Certify Hub (พื้น slate + การ์ดขาว, ฟอนต์ Sarabun, badge โลโก้), **ตำแหน่งเมนู** บน/ข้าง (แถบซ้ายคงที่ + เลื่อนเนื้อหา, ยุบเป็นไอคอน, hover ขยาย, จำค่า, ค่าเริ่มต้นจาก Customizer vs ผู้เข้าชม, รีเซ็ต, ลิ้นชัก+ปุ่มลอยบนมือถือ), **สีโดยผู้เข้าชม** (ชุดสี, สีเองหลายค่าในกรณีเลวร้ายตรวจคอนทราสต์สว่าง/มืด, ใช้ก่อน paint, โหมดคอนทราสต์ชนะ, คืนค่า), ลวดลายพื้นหลังดอกอโศก (ชั้น SVG mask, สีตามชุดสี, ซ่อนในโหมดคอนทราสต์, สวิตช์ผู้เข้าชม/ค่าเริ่มต้นจาก Customizer), กล่องเกี่ยวกับ/เครดิต และ axe ในโหมดแถบข้าง |
| `e2e/content.js` | วันที่ พ.ศ. (หน้าข่าว/การ์ด/ท้ายเว็บ, ค.ศ. ในภาษาอื่น, `datetime` เป็น ISO), เลย์เอาต์ข่าว 3 แบบ (ค่าเริ่มต้น/override รายข่าว/ค่าจาก Customizer), หน้าเพจ (ในหมวดนี้) และหน้าหมวดข่าว, ชุดสีทั้ง 4 + สีกำหนดเองกรณีเลวร้าย (เหลืองอ่อน/เทากลาง) ตรวจคอนทราสต์ปุ่ม/ลิงก์/ท้ายเว็บทั้งสว่างและมืด, โลโก้ (ปกติ/โหมดมืด/ท้ายเว็บ/ความสูง), สวิตช์แอนิเมชัน + ค่าเริ่มต้นปิดจาก Customizer, และเครื่องมือฟังข่าว (ตัวจำลอง speech: อ่านชื่อเรื่องก่อน, หยุด/เล่นต่อ/หยุด, ความเร็ว, แบ่งท่อน ≤180 ตัวอักษร, ภาษาตามเนื้อหา, เบราว์เซอร์ไม่รองรับ, ปิดได้) + axe-core |
| `e2e/effects.js` | scroll reveal (ไม่กะพริบ), การ์ดเอียง, เคอร์เซอร์โต้ตอบ (จุด/วงแหวน/บับเบิล “ดู”), เลื่อนแบบ ease, progress bar และตรวจว่าเอฟเฟกต์ปิดเมื่อ reduced motion / คอนทราสต์สูง / โหมดทีวี / อุปกรณ์สัมผัส |
| `e2e/responsive.js` | ความกว้าง 240 → 3840px: ไม่มีการเลื่อนแนวนอน, ปุ่ม/ลิงก์ ≥ 24px; โหมดทีวี (UA ของ Tizen): เปิดอัตโนมัติ, ธีมมืด, ตัวอักษรใหญ่, ปุ่มลูกศรเลื่อนโฟกัส, กรอบโฟกัสหนา, ปิดโหมดทีวีได้ |

## ตัวจำลอง (mu-plugins ใน `fixtures/mu-plugins/`)
`mods-stub.php` (cookie `mod_stub` = JSON ของค่า Customizer ที่ต้องการแทนที่ เช่น `{"mcupr_palette":"navy","custom_logo":"ATT"}` โดย `ATT` คือรูปที่ seed ไว้)

## ข้อจำกัดที่ควรรู้
- การอ่านออกเสียงทดสอบด้วยตัวจำลอง speech (headless Chromium ไม่มีเสียง) — **ยังไม่ได้ฟังเสียงจริง** บนอุปกรณ์/เบราว์เซอร์ใดๆ
- ทดสอบเฉพาะ Chromium; ไม่ได้ทดสอบกับ screen reader จริง, ทีวีจริง หรือเบราว์เซอร์ทีวีรุ่นเก่า
- ไซต์ทดสอบปิดการเชื่อมต่อออกภายนอก (`WP_HTTP_BLOCK_EXTERNAL`)

## ข้อมูลทดสอบ (`fixtures/seed.php`)
ข่าวภาษาไทย 10 ข่าว (ข่าวที่ 1 มีคำแปล en/zh) พร้อมรูปที่สร้างด้วย GD, หน้านโยบายความเป็นส่วนตัว, เมนูครบทุกตำแหน่ง และค่า Customizer
- `bash only.sh php <suite…> e2e <block…>` – run only what you changed, in about a minute, on the site the last `starter.sh` left behind: PHP suites (`php/<name>.php`) and blocks of `e2e/starter.js` that are marked `// @only name` … `// @end name` (`bash only.sh --list` shows them). Mark a block that is self-contained (it may use `B`, `br`, `ap` – an administrator already signed in –, `ok`, `axeSource`, `fsF`, `fmUrl`, `fmReady`) to make it runnable alone. This is for working; **before a merge the whole of `starter.sh` still has to end with `EXIT=0`**.
- `bash starter.sh` – imports the starter file with the real WordPress Importer into a clean site and checks pages/menus/footer/axe (needs network once to clone the importer). PHP suites that print a `FAIL` line, a fatal error, or exit with an error are listed together at the end (and just before the browser tests) as `FAILED PHP suites (n of N): …`, and make the run end with `EXIT=1`; with none the line reads `PHP suites: all N passed`.

## Loading screen cost (not part of the automatic run)

`perf/preloader.js` and `perf/preloader-nav.js` measure what the logo loading screen costs: FCP, LCP, CLS, TBT, INP and the time until the
page is really seen (the later of LCP and the moment the screen left, because Chrome counts a picture as painted even when the screen covers it).
Cold loads, median of N, on a slow phone (4× CPU, 1.6 Mbit/s, 150 ms) and without throttling; the three settings off / first page only / every page.

    BASE_URL=http://localhost:8090 CHROMIUM_PATH=/opt/pw-browsers/chromium node perf/preloader.js 7
    BASE_URL=http://localhost:8090 CHROMIUM_PATH=/opt/pw-browsers/chromium node perf/preloader-nav.js 7

Result on 2026-10-28 (the local test site; a real host with real network will differ): FCP, CLS, TBT and INP are the same with the screen on or off;
LCP as Chrome reports it is the same too. What changes is when the visitor sees the page: with "every page" a fast page (LCP 0.2–0.35 s) is seen
after about 1.0–1.4 s, and a click from page to page takes about 1.0 s instead of 0.3 s; on the slow phone, where a page takes 3.4–4.1 s anyway,
the screen adds 0.1–1.0 s. "First page only" costs the same on the first page and nothing afterwards.
