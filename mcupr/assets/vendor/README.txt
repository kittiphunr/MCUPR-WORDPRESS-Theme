Third-party code shipped inside the theme (loaded only when an e-book is opened):
  pdfjs/      PDF.js 4.10.38 (Apache License 2.0, Mozilla) – pdf.min.js, pdf.worker.min.js (the upstream .mjs files, renamed .js: many older web servers send .mjs with the wrong type and the browser then refuses to load the module), cmaps/, standard_fonts/
  pageflip/   StPageFlip (page-flip) 2.0.7 (MIT, Nodlik) – page-flip.browser.js
  epub/       epub.js 0.3.93 (BSD-2-Clause, FuturePress) – epub.min.js; JSZip 3.10.1 (MIT or GPL-3.0, Stuart Knightley et al.) – jszip.min.js (loaded only for EPUB books and the editor's "cover of the EPUB" button)
  (page-flip/ is fetched only when a reader chooses the "Classic" turning; CSS 3D and WebGL turning are the theme's own code in assets/js/book-engines.js)
  pdf/        jsPDF 4.2.1 (MIT, James Hall and yWorks) – jspdf.umd.min.js; html2canvas 1.4.1 (MIT, Niklas von Hertzen) – html2canvas.min.js (loaded only when a reader presses "Download PDF"; the file is made in the browser, see assets/js/pdf-export.js)
