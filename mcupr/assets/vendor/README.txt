Third-party code shipped inside the theme (loaded only when an e-book is opened):
  pdfjs/      PDF.js 4.10.38 (Apache License 2.0, Mozilla) – pdf.min.js, pdf.worker.min.js (the upstream .mjs files, renamed .js: many older web servers send .mjs with the wrong type and the browser then refuses to load the module), cmaps/, standard_fonts/
  pageflip/   StPageFlip (page-flip) 2.0.7 (MIT, Nodlik) – page-flip.browser.js
  epub/       epub.js 0.3.93 (BSD-2-Clause, FuturePress) – epub.min.js; JSZip 3.10.1 (MIT or GPL-3.0, Stuart Knightley et al.) – jszip.min.js (loaded only for EPUB books and the editor's "cover of the EPUB" button)
  (page-flip/ is fetched only when a reader chooses the "Classic" turning; CSS 3D and WebGL turning are the theme's own code in assets/js/book-engines.js)
  pdf/        jsPDF 4.2.1 (MIT, James Hall and yWorks) – jspdf.umd.min.js; html2canvas 1.4.1 (MIT, Niklas von Hertzen) – html2canvas.min.js (loaded only when a reader presses "Download PDF"; the file is made in the browser, see assets/js/pdf-export.js)
  fontawesome/ Font Awesome Free 5.15.4 (icons CC BY 4.0, fonts SIL OFL 1.1, code MIT; Fonticons, Inc.) – css/all.min.css (only the woff2 files are named) and webfonts/*.woff2; loaded only on a page that shows Elementor content with Font Awesome icons (assets/css and inc/elementor*.php)
  qrcode/     qrcode-generator 2.0.4 (MIT, Kazuhiko Arase) – qrcode.js; loaded only on a post that has a Google Drive / Google Photos link, to print its QR code on the A4 document (assets/js/print-doc.js)
