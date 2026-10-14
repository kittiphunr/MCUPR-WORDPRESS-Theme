// Shared helpers for the end-to-end tests.
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const B = process.env.BASE_URL || 'http://localhost:8080';
let fails = 0;
let passes = 0;

/** Record a check. */
const ok = (cond, msg) => {
  console.log((cond ? 'PASS ' : 'FAIL ') + msg);
  cond ? passes++ : fails++;
};

/** Launch Chromium. Set CHROMIUM_PATH to use an already-installed browser. */
const launch = () => chromium.launch(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {});

/** Screenshot path (tests/screenshots/…, git-ignored). */
const shot = (name) => {
  const dir = path.join(__dirname, '..', 'screenshots');
  fs.mkdirSync(dir, { recursive: true });
  return path.join(dir, name);
};

const axeSource = () => fs.readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');

/** Print the summary and set the exit code. */
const finish = () => {
  console.log(`\n${passes} passed, ${fails} failed`);
  process.exitCode = fails ? 1 : 0;
};

module.exports = { chromium, B, ok, launch, shot, axeSource, finish };
