=== MCUPR-S.A.T.U.R.N ===
Contributors: kittiphunr
Author: Kittiphun Ruttanakorn
Author URI: https://github.com/kittiphunr
Requires at least: 5.9
Tested up to: 6.8
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: accessibility-ready, custom-logo, custom-menu, featured-images, translation-ready, news, education

Accessible, four-language (Thai / English / Chinese / Japanese) theme for government and university websites. Standalone: needs no plugins.

== Description ==

* WCAG 2.2 AA, Thai government website guidelines, 240px to 4K / TV mode
* Visitor-selectable menu position, colours, text size, dark mode, high-contrast modes
* Thai Buddhist Era dates, three news layouts, text-to-speech "listen" tool
* Widget areas, shortcode slots and a few shortcodes: [mcupr_tools], [mcupr_news], [mcupr_button]

== Installation ==

1. Appearance → Themes → Add New → Upload Theme, choose mcupr-x.y.z.zip, Activate.
2. Customize → set logo, organisation name, colours and menus (one main menu per language).

== Copyright ==

MCUPR theme, (C) kittiphunr, GPLv2 or later.
Sarabun font: SIL Open Font License 1.1 (assets/fonts/OFL.txt).
SweetAlert2: MIT License.

== Changelog ==


= 1.72.0 =
* Phones (below 1100 px): a bottom bar with Home, News, a big round button that opens the menu drawer, Events and Search, in every menu layout and colour mode (light, dark, high contrast); the page and the floating buttons keep clear of it; switch it off in Customizer → Animation, news & reading

= 1.71.0 =
* Post head redesigned: the category above the title, a lighter title, and date / reading time / views on one calm line without boxes; on Cover posts the text and every block under it sit in one centred column with equal space on both sides

= 1.70.1 =
* Cover posts: the text now starts at the same side as the blocks under it (gallery, attachments, tags, share) instead of being centred in the wide panel, and the head, the side rail and the panel line up

= 1.70.0 =
* Customizer and control center re-ordered by how they are really used: notice bar, home page and menus first; then what is set once (identity, logos, organisation, colours, footer); then reading, loading screen, print, policy and cookies; the rarely used last. The long organisation and animation sections are split (footer map and social links, website credit, loading screen) and each long section follows the order of work. The control center has menus for organisation, languages, footer, loading screen and credit, tools are ordered by use, and "Colours" now opens the colour section.

= 1.69.0 =
* Loading screen: stays on every page by default and can still be switched off (or limited to the first page of a visit); it can now have its own logo (and one for dark mode) and its own text, or no text at all (Customizer → Animation)

= 1.68.1 =
* Post head: a calmer, more beautiful title (bold, balanced lines, accent bar), the category and date / reading time / views as pill chips, and on the Cover layout a glass breadcrumb, a stronger gradient and a taller picture with the content overlapping its bottom edge

= 1.68.0 =
* Cookie centre: visitors choose which cookies and stored settings the website may keep (necessary / your settings / anonymous statistics / content from other websites) in a banner with three equal buttons and a settings dialog that can be opened again from the footer; nothing but the necessary is kept for long before a yes; embedded videos, maps and Google documents are shown at once only with a yes; the cookie policy page lists what is kept from the theme's own list; Customizer: switch it off, choose the kinds, raise the version to ask everyone again

= 1.67.0 =
* Elementor pages: tested with 60 templates made by the real Elementor (1,171 widgets). Added what was missing: Elementor 4 blocks and widgets (e-div-block, e-flexbox, e-heading, e-paragraph, e-button, e-image, e-svg with their typed settings and styles), the new Tabs and Accordion (nested-tabs, nested-accordion), the testimonial carousel, Rating, Audio (a link, not a player) and Text path (as text)

= 1.66.3 =
* Sidebar menu mode on phones too: the menu button (three bars) is in the header below 1100 px, as in the top menu; the round button in the corner is gone

= 1.66.2 =
* E-books (WebGL page turn): a page at rest is drawn at full brightness whatever the graphics card makes of its surface direction; only a page being turned is lit from the side (both open pages are now equally bright)

= 1.66.1 =
* Sidebar menu mode on a tablet (700-1099 px): the menu has a button in the header again (only a lone round button in the corner was there)
* E-books (WebGL page turn): a page lifted towards the reader is no longer cut off at the edge of the picture; pages at rest are never shaded dark (a safer light, high-precision shading)

= 1.66.0 =
* Replace with the latest starter, tested on a lived-in site: pages whose template no longer exists can be replaced; pages built with Elementor show the new text; the copy of a page keeps its description and translated titles; "Put the previous text back" per page; the settings copies (last 5) can be put back exactly, and overwriting twice no longer throws away the first copy

= 1.65.1 =
* News layouts Cover and Sidebar: the frame is as wide as the footer frame (the text column inside stays readable)

= 1.65.0 =
* PDF reader in posts: a search box that finds and marks the words (with previous / next result), and a text layer over every page so the words can be selected, copied and read by screen readers; scanned files say they have no text

= 1.64.0 =
* The translation box of posts and pages has a tab for each language (the default language points to the main editor)

= 1.63.1 =
* The frame of a news post is as wide as the footer frame (the text column inside stays readable)

= 1.63.0 =
* Works and awards get a menu of their own in the sidebar, and notices a card in the control center

= 1.62.0 =
* Notices: number, method, budget, closing date, status, winner and price, with a status filter

= 1.61.0 =
* Loading screen with the logo, featured news cards not clipped on hover, Google Drive and Google Photos in a post

= 1.60.0 =
* Notices and documents: a procurement-style register with the newest on the home page

= 1.59.0 =
* News: activity photo gallery and PDF reader inside a post

= 1.58.0 =
* SEO: schema.org data (university, website, breadcrumb) and a search description for each starter page

= 1.57.1 =
* Tab title choices in the Customizer: number beside the slider, taller slider, controls that do not apply are hidden

= 1.57.0 =
* Import over what is there: replace starter pages and settings on request

= 1.56.2 =
* Starter data: the campus's real history, vision, programmes, strategy and address

= 1.56.1 =
* Welcome & maintenance page: one tab per language

= 1.56.0 =
* ชื่อบนแท็บเบราว์เซอร์พิมพ์ขึ้นทีละตัว และเลื่อนเมื่อยาวเกินแท็บ ตั้งค่าได้ใน Customizer

= 1.55.3 =
* แก้สิทธิ์บทบาท 3 ข้อ: บทบาทที่สร้างเองแก้งานที่เผยแพร่แล้วได้ เจ้าหน้าที่แก้หน้าเว็บของคนอื่นได้ และเจ้าหน้าที่ไม่ได้สิทธิ์เกินมาตรฐาน

= 1.55.2 =
* ย้ายหน้าสิทธิ์ผู้ใช้ไปอยู่ใต้เมนูผู้ใช้ของ WordPress เปลี่ยนชื่อ Subscriber เป็นผู้รับข่าวสาร และตั้งเป็นบทบาทเริ่มต้นของบัญชีใหม่

= 1.55.1 =
* แก้ข้อความ อ่านต่อ ในการ์ดข่าวให้กดได้จริง

= 1.55.0 =
* ระบบจัดการสิทธิ์ผู้ใช้: บทบาทมาตรฐานของมหาวิทยาลัย สิทธิ์เฉพาะของแต่ละหน้าจัดการ และตารางเดียวสำหรับติ๊กว่าใครทำอะไรได้

= 1.54.77 =
* จัดระเบียบฐานข้อมูล: หยุด autoload ตัวเลือก 7 ตัว ย้าย 2 แคชไปเป็น transient ลบแท็กของไฟล์ที่ไม่มีอยู่ และผูกเครื่องหมายโหวตกับวันปิดโพล

= 1.54.76 =
* ขยายเครื่องมือ PDPA ให้ครอบคลุมคำตอบแบบฟอร์ม คำร้อง ข้อความ และกระทู้ และบันทึกการนำข้อมูลส่วนบุคคลออกไว้ใน audit log

= 1.54.75 =
* เกราะชั้นสอง: เก็บไฟล์แนบไว้นอก web root ปิดการส่งออกชนิดที่มีข้อมูลส่วนบุคคล และเพิ่ม security header ให้ทุกหน้า

= 1.54.74 =
* แก้สิทธิ์: contributor/author ไม่นับเป็นเจ้าหน้าที่สำหรับเนื้อหาเฉพาะนักศึกษา เติม map_meta_cap ให้โปรไฟล์บุคลากรและผังองค์กร และกันผังองค์กรจากผู้แก้ไขส่วนงาน

= 1.54.73 =
* แยกสิทธิ์ดูคำร้องออกจากสิทธิ์แก้ไขหน้า ปิด REST ของโปรไฟล์บุคลากรที่ซ่อนไว้ จำกัดการส่งออกรายชื่อบุคลากร และใส่การจำกัดอัตราให้แบบฟอร์ม Elementor

= 1.54.72 =
* Home page: a one-press way to give the quick links their groups, so the 'I am…' picker shows on a site built before it existed

= 1.54.71 =
* Elementor pages without the plugin: the theme prints what Elementor saved

= 1.54.70 =
* Inner pages: Thai-safe line heights on every title and button, a flowing date line on posts, notes for empty sections, pill calendar links

= 1.54.69 =
* Languages: one Customizer tab per language, add and remove languages

= 1.54.68 =
* PHP security check: a notice and a Site Health test for the October 2026 PHP remote-code-execution flaws (CVE-2026-109618, CVE-2026-109619)

= 1.54.67 =
* File manager trash: an item whose id is sixteen digits only is no longer lost from the list

= 1.54.63 =
* Home hero: Thai headline lines no longer touch; roomier quick-link tiles

= 1.54.62 =
* Home hero: an 'I am…' picker over the quick links, and nicer tiles

= 1.54.61 =
* Media library: tidy folder levels and one bar for filters, Bulk select and search

= 1.54.60 =
* Page links: one bar with Previous / numbers / Next and a 'page X of Y' line on every list

= 1.54.59 =
* Animated icons (Lordicon): an icon of the theme can be swapped for a moving one, chosen in the Customizer

= 1.54.56 =
* Media library page looks like the file manager.

= 1.54.54 =
* File manager: a trash (put back, undo, empties itself after 30 days).

= 1.54.53 =
* File manager for the uploads folder: Finder-like screen, colour tags, safe by construction.

= 1.54.52 =
* Print and PDF are made from one A4 document with only the content and the letterhead.

= 1.54.51 =
* Customizer first screen: groups, icons and a line of help; no repeated MCUPR: prefix.

= 1.54.50 =
* Round arrow buttons: arrows drawn in the exact centre.

= 1.54.49 =
* Demo pack rebuilt: links point to the theme's real features; links typed in pages work in a sub-folder and by page name.

= 1.54.48 =
* Footer: identity band, contact cards, pill map links, link rows.

= 1.54.47 =
* Calendar: long event titles wrap and show in full.

= 1.54.46 =
* Control center: statistics menu added; media folders, PDF and office news described.

= 1.54.45 =
* Maintenance page: caution-tape look, animated repair scene, fits 13-inch laptop screens.

= 1.54.44 =
* News inside an office: a short head in the office colour, the News tab marked, the office news list as the site's news window.

= 1.54.43 =
* Compact page bar (listen, speed, print, PDF, views); download as PDF made in the browser.

= 1.54.42 =
* Media library folders (virtual): panel, drag to move, filters in the grid and the pop-up.

= 1.54.41 =
* Sign in with Google, Facebook, LINE and ThaID for visitors and students.

= 1.54.40 =
* Sign-in page dressed like the theme; a private sign-in address (wp-login.php and wp-admin answer not found).

= 1.54.39 =
* Article tools as two cards (listen / share); the display panel of the Customizer grouped with switches.

= 1.54.38 =
* News browser counts match the list (posts kept off the main site or limited to an audience are not counted).

= 1.54.37 =
* News browser: Finder/Files-style category tree, folders with descriptions, path bar.

= 1.54.36 =
* Round buttons in the all-menu: hover/pressed/contrast states fixed.

= 1.54.35 =
* Work group sample file: all 13 groups filled with marked examples

= 1.54.34 =
* Work group import file: service tiles and figures

= 1.54.33 =
* Work group page: figures, news and people in one row; documents beside the contact

= 1.54.32 =
* All menu: groups rail on the left, the chosen group on the right

= 1.54.31 =
* Work group page: services first as tiles beside the group, figures part

= 1.54.28 =
* All menu: bento safe without the script, measured again after fonts and resize

= 1.54.27 =
* All menu: bento cards, home and contact as round buttons

= 1.54.26 =
* Editor tools card fits a phone screen

= 1.54.25 =
* E-books: manage the pages of a PDF; PDF.js served as .js

= 1.54.22 =
* Editor tools: picture + words, tiles like a start menu

= 1.54.21 =
* Control center: work groups moved to people, menus in order of use

= 1.54.20 =
* Page head title: clear gap between the breadcrumb and the tone marks

= 1.54.19 =
* Page head title: Thai vowels and tone marks are no longer clipped

= 1.54.18 =
* Page head band: straight bottom edge

= 1.54.17 =
* Three levels: university / office / work group with one trail in the office band

= 1.54.16 =
* Work groups: full office band above, light group head

= 1.54.15 =
* Office pages wear the full office band like the office home

= 1.54.14 =
* Office home head: the same 24px inner margin on every side with the side menu

= 1.54.13 =
* Low-risk hardening 2: staff-only forms, complaint access, import folder, 404 write limit

= 1.54.12 =
* RFID last 4 digits: backfill on re-import and a hint for older cards

= 1.54.11 =
* Student card box shows the last 4 digits of the RFID code

= 1.54.10 =
* Central-file box masks the national ID and passport numbers

= 1.54.9 =
* Home hero fills the frame edge to edge with the side menu

= 1.54.8 =
* Office pages share the section head panel; office home restyled

= 1.54.7 =
* Update.

= 1.54.6 =
* Update.

= 1.54.5 =
* Update.

= 1.54.4 =
* Low-risk hardening: book files, admin notices, import files, kept form answers, forum names, masked ID number.

= 1.54.3 =
* Update.

= 1.54.2 =
* Update.

= 1.54.1 =
* Update.

= 1.54.0 =
* Offices: work groups with a landing page of their own (services, news, people, documents, contact).

= 1.53.1 =
* Update.

= 1.53.0 =
* Speed (fewer queries and option writes per page) and Chinese / Japanese dates, welcome texts and portal labels.

= 1.52.6 =
* Update.

= 1.52.5 =
* Update.

= 1.52.4 =
* Update.

= 1.52.3 =
* Update.

= 1.52.2 =
* Update.

= 1.53.0 =
* Update.

= 1.52.1 =
* Update.

= 1.52.0 =
* Control center: the admin home as a Windows-style control panel (8 groups, explanations, guides, start checklist, search, Feather icons).

= 1.51.1 =
* Header tools capsule: same space all round, same button height and gap, round all-menu button.

= 1.51.0 =
* Toolbar for editors: floating icon with a card of the toolbar's links, in groups (or the WordPress bar).

= 1.50.1 =
* Sidebar mode: the content frame keeps the sidebar's gap on every side, also with the WordPress toolbar.

= 1.50.0 =
* E-book (WebGL): the page is held by a corner and rolled over a slanting fold.

= 1.49.0 =
* E-book: the admin chooses how the pages turn (site and per book); the visitor's effect button is gone.

= 1.48.0 =
* E-book (WebGL): lifting corner, spring paper, book thickness, hard covers, new page sounds.

= 1.47.2 =
* Re-import keeps card code and ID number already set.

= 1.47.1 =
* National ID number box shows and syncs the central number.

= 1.47.0 =
* Student details from the university's central file (86 columns): import as it is, encrypted sensitive fields, codes to names table, labels, the student's own view.

= 1.46.1 =
* Fix: the mega menu of the section you are on shrank to the width of its capsule when hovered or opened (a filter made the capsule the panel's containing box).

= 1.46.0 =
* Import students straight from the registrar's .xls reports (several files at once).

= 1.45.2 =
* Fix: student CSV import said the file was empty on PHP 7.0-7.3 (empty fgetcsv escape character); PHP 7.0 compatibility of two other calls; import shows what it saw.

= 1.45.1 =
* Import students: a clear message when an Excel file is chosen instead of a CSV.

= 1.45.0 =
* Student import from registrar reports; student card (RFID) and national ID number as a second step at sign-in.

= 1.44.0 =
* Students can sign in with the university Google account (Google Workspace, OpenID Connect).

= 1.43.0 =
* Fewer database queries per page: figure counts, options read on every page, featured images of lists.

= 1.42.0 =
* Class schedule and grades in the student portal: CSV import per term, weekly schedule, calendar file, GPA/GPAX, print.

= 1.41.0 =
* E-mail to students in groups: choose who (levels, years, faculties), saved templates, scheduled send, attachment, background sending with an hourly limit, stop-announcements link.

= 1.40.0 =
* Requests: tracking number, status, files, e-mails (2B)

= 1.39.0 =
* E-books: Real3D-style viewer (toolbar, zoom, contents, print, auto-play, lightbox)

= 1.38.0 =
* E-books: lighter page turning with CSS 3D and WebGL engines

= 1.37.0 =
* E-books round 2: EPUB and extras on the pages

= 1.36.0 =
* E-books: bookshelf + 3D flipbook (PDF and pictures)

= 1.35.0 =
* Plain permalinks: every link into the website with a pretty path becomes ?p=123 style (menus, content, demo pages); no notice about permalinks

= 1.34.0 =
* MCUPR admin menu: only the overview and five main groups; every page is one click from the control page

= 1.33.2 =
* Top menu: the More pill is not a capsule (it drew a double outline); high-contrast outline for More

= 1.33.1 =
* Top menu: an item with a sub-menu is one capsule (link and arrow no longer overlap)

= 1.33.0 =
* Student-only content limited by year of study, faculty and major; two fixes found while testing

= 1.32.1 =
* Office menu folds into one button on small screens

= 1.32.0 =
* Student data care: activity log, e-mail test and link without e-mail, link-rule check, high-contrast fixes

= 1.31.3 =
* Office band reaches the top of the page (top menu and sidebar), gradient and seal

= 1.31.2 =
* Office pages: header no longer covered by the page band; empty person profile

= 1.31.1 =
* Student portal and office links work with plain permalinks

= 1.31.0 =
* Students: private student database, CSV bulk import, login accounts and portal, student-only content, registrar role; control page reaches every function; fewer database queries

= 1.30.5 =
* Maintenance and welcome pages redesigned (glass card, badge, countdown, contact chips)

= 1.30.4 =
* Work / award page: badges and facts redesigned as cards

= 1.30.3 =
* 404 page redesigned: big 404, centred search with a guess from the address, buttons, menu pages, latest news

= 1.30.2 =
* Control page (MCUPR overview) redesigned: banner, status chips, quick actions, section cards, tools, office view

= 1.30.1 =
* Offices: prettier pages (cover header, contact chips, floating tabs, figure tiles, icons, card covers)

= 1.30.0 =
* Offices: mini-sites inside the website (own news, events, people, pages, colour and staff accounts) with a demo

= 1.29.3 =
* More dropdown: back to the ordinary dropdown; long text wraps, no scroll bar

= 1.29.2 =
* Top menu: the More dropdown is a card grid with every group open and no scroll bars

= 1.29.1 =
* Info button shows the special mode that is on (editors only); orange maintenance strip removed

= 1.29.0 =
* New menu layout: one bar (logo, menu and tools in a single capsule)

= 1.28.10 =
* Maintenance bar fixed to the top, full width; header and side bar start below it

= 1.28.9 =
* Full-screen menu: pressing the button while it is closing opens it again

= 1.28.8 =
* Key numbers (students / teachers / faculties) section restored in the home page panel

= 1.28.7 =
* Narrow side rail: logo centred

= 1.28.6 =
* Side bar: smooth open/close of the card, labels, corners and page; softer phone drawer

= 1.28.5 =
* Full-screen menu: grows out of its button, parts arrive in turn, shrinks back on close

= 1.28.4 =
* High-contrast mode: on/off switches visible

= 1.28.3 =
* Removed the Numbers (students / teachers / faculties) block

= 1.28.2 =
* Theme credit: author name with link to profile

= 1.28.1 =
* Header tools capsule compact and quieter; weather pill no longer overflows at 320 px

= 1.28.0 =
* Home page demo (news, events, video, albums, quick links, all home settings)

= 1.27.0 =
* Pictures: JPEG/PNG uploads saved as WebP

= 1.26.1 =
* Header capsules fully round (100%)

= 1.26.0 =
* Import / export settings: starter settings file, fill-only, redirects, export

= 1.25.2 =
* Home blocks framed like the other cards (weather bar, figures, about)

= 1.25.1 =
* Theme renamed MCUPR-S.A.T.U.R.N with a new screenshot

= 1.25.0 =
* Home blocks moved from widgets to the Customizer (MCUPR: Home page sections)

= 1.24.1 =
* Weather source link underlined (axe link-in-text-block)

= 1.24.0 =
* Speed check page in the admin

= 1.23.1 =
* Home: events card and video card polished, video title bar

= 1.23.0 =
* Home blocks: audience cards, figures strip, link cards, about block, weather bar; 4 new sample pages

= 1.22.0 =
* Numbers block: students, teachers, faculties, students per level

= 1.21.1 =
* Category browser: clear tree for sub-categories; space above the pager

= 1.21.0 =
* Form builder, add-ons starter file, page-by-page audit, small fixes

= 1.20.0 =
* Calendar: import .ics files and sync with a calendar address

= 1.19.0 =
* All theme admin menus grouped under one MCUPR menu with an overview

= 1.18.8 =
* Top menu: the More button is a pill again

= 1.18.7 =
* Menu drop-down buttons are true circles

= 1.18.6 =
* Search overlay: close button inside the corner

= 1.18.5 =
* Search overlay redesigned with example searches, tested from 320 to 1920 px

= 1.18.4 =
* Sidebar: active item keeps its icon on hover

= 1.18.3 =
* Welcome and maintenance pages stay centred with the sidebar menu layout

= 1.18.2 =
* Sidebar menu: long names wrap

= 1.18.1 =
* Starter file: welcome page samples page

= 1.18.0 =
* Home page re-arranged (sections can be switched off, events/video/albums), welcome page, maintenance mode, mourning

= 1.17.2 =
* Starter file: Photo albums and Videos in the News menu

= 1.17.1 =
* Back-to-top button is a circle

= 1.17.0 =
* Videos from other sites (YouTube/Vimeo) with click-to-play player, redesigned news cards

= 1.16.4 =
* Logo capsule same height as the menu; wide-logo option

= 1.16.3 =
* Top menu items spread over the whole capsule

= 1.16.2 =
* Fix: eye icon in the view cursor, tooltips clipped by the dialog body

= 1.16.1 =
* UI polish: tooltips, page lead, back-to-top quick links, eye cursor

= 1.16.0 =
* Forum, FAQ, photo albums with lightbox, polls and satisfaction surveys

= 1.16.0 =
* Discussion board: boards, topics, replies, moderation, spam protection, reports.

= 1.15.0 =
* Administration chart: boxes beside a unit (committees), list panels (sections and groups), box colours, wider rows; the sample chart follows the Phrae campus structure.

= 1.14.0 =
* Staff hub: import people from Excel (.xlsx) or CSV with a preview, template download and export.

= 1.13.1 =
* Removed the creator card from the footer (the creator stays in the About dialog).

= 1.13.0 =
* Staff hub: profiles linked to the administration chart, privacy controls, works, education, royal decorations catalogue with editable medals, control centre, roll of honour.

= 1.12.2 =
* Reading speed is a row of buttons instead of a drop-down.

= 1.12.1 =
* Drop-downs (reading speed, filters, forms) are rounded pills with their own arrow.

= 1.12.0 =
* Menu refresh: hybrid layout removed, menu position as icons, clearer hover, quick links tucked into a button, full-screen menu in both modes (one screen, filter, quick links).

= 1.11.1 =
* Accessibility dialog: the extra options are icon tiles with tooltips.

= 1.11.0 =
* Ring around the sidebar expand button: a runner while the page loads, then the scroll progress.

= 1.10.3 =
* Collapsed sidebar expand button is a perfect circle.

= 1.10.2 =
* Collapsed sidebar card is a true pill (full semicircle ends).

= 1.10.1 =
* News browser: sub-category folders are equal squares.

= 1.10.0 =
* Print posts and pages on A4 with an editable letterhead: header and footer graphics on every sheet, background image, text letterhead fallback, per-post switch.

= 1.9.0 =
* Sidebar expand/collapse button instead of hover, no black utility bar, Finder-style news browser (category zones, separate tags, card/list/gallery views), built-in creator credit with photo.

= 1.8.0 =
* Notice bar (announcement / urgent / mourning), hero slider, social links, statistics, people directory, document centre, contact and complaint forms.

= 1.7.0 =
* Thai admin text, richer hero options, footer credit card, view counter, file attachments with download counter / limit, works & awards, administration chart.

= 1.6.0 =
* Layout v2: floating capsules (top menu), sidebar card + content frame (side / hybrid) with the black bar's links in the header, Facebook / LINE share buttons.

= 1.5.0 =
* Side / hybrid menu as a bento layout: separate rounded content frame, sidebar card with margins, round icons when collapsed.

= 1.4.2 =
* Side / hybrid menu: the rail's menu card now runs the full height of the screen.

= 1.4.1 =
* Merged: Recent Posts widget (30-character titles + Read more...) with the footer contact / map.
= 1.4.0 =
* Footer: address, telephone, opening hours and a click-to-load Google Maps card with open / directions links.

= 1.3.1 =
* Recent Posts widget: titles cut to 30 characters with a Read more... button.

= 1.3.0 =
* Events calendar (event post type, month grid, upcoming list, shortcodes, Google Calendar / .ics, schema.org Event).

= 1.2.2 =
* Page band extends up to the top bar under the floating header.

= 1.2.1 =
* Top bar proportions (compact utility bar, tighter main menu, icon-only All menu button below 1600px), icon of the active menu item readable.

= 1.2.0 =
* Full-screen menu (All menu button), one open menu panel at a time, refined mega menu.
= 1.1.0 =
* Starter import (pages + menus), speed improvements, 'More' menu opens inline, duplicate-import clean-up.

= 1.0.0 =
* Initial release.
