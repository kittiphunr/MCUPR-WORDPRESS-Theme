Third-party code shipped inside the theme (loaded only when an e-book is opened):
  pdfjs/      PDF.js 4.10.38 (Apache License 2.0, Mozilla) – pdf.min.mjs, pdf.worker.min.mjs, cmaps/, standard_fonts/
  pageflip/   StPageFlip (page-flip) 2.0.7 (MIT, Nodlik) – page-flip.browser.js
  epub/       epub.js 0.3.93 (BSD-2-Clause, FuturePress) – epub.min.js; JSZip 3.10.1 (MIT or GPL-3.0, Stuart Knightley et al.) – jszip.min.js (loaded only for EPUB books and the editor's "cover of the EPUB" button)
  (page-flip/ is fetched only when a reader chooses the "Classic" turning; CSS 3D and WebGL turning are the theme's own code in assets/js/book-engines.js)
