// Runs only the named blocks of starter.js (the ones marked "// @only name" … "// @end name") against the running test site,
// so a change can be checked in a minute instead of the forty of the whole suite.
//   node e2e/only.js --list
//   node e2e/only.js footer-stats pictures-bulk
// The block gets what starter.js gives it at that point: B, br (browser), ap (an administrator, signed in), ok, axeSource,
// and the file manager's fsF / fmUrl / fmReady. A block that needs more than that is not marked (run the whole suite).
const fs = require('fs');
const path = require('path');
const lib = require('./lib');

const src = fs.readFileSync(path.join(__dirname, 'starter.js'), 'utf8');
const blocks = {};
for (const m of src.matchAll(/^[ \t]*\/\/ @only ([\w-]+)\n([\s\S]*?)^[ \t]*\/\/ @end \1\n/gm)) { blocks[m[1]] = m[2]; }
const want = process.argv.slice(2);
if (!want.length || want.includes('--list')) {
  console.log('Blocks of starter.js that can be run alone:\n  ' + Object.keys(blocks).join('\n  '));
  process.exit(want.length ? 0 : 2);
}
const missing = want.filter((n) => !blocks[n]);
if (missing.length) { console.log('No such block: ' + missing.join(', ') + '\nKnown: ' + Object.keys(blocks).join(', ')); process.exit(2); }

const AsyncFunction = Object.getPrototypeOf(async function () {}).constructor;
(async () => {
  const br = await lib.launch();
  const ctx = await br.newContext({ viewport: { width: 1440, height: 900 } });
  ctx.setDefaultTimeout(90000); ctx.setDefaultNavigationTimeout(90000);
  const ap = await ctx.newPage();
  await ap.goto(lib.B + '/wp-login.php');
  await ap.fill('#user_login', 'e2eadmin'); await ap.fill('#user_pass', 'e2e-Admin-pass-1'); await ap.click('#wp-submit');
  await ap.waitForURL(/wp-admin/);
  const fsF = fs;
  const fmUrl = lib.B + '/wp-admin/admin.php?page=mcupr-files';
  const fmReady = async () => { await ap.waitForSelector('#mcupr-fm .fm-tw', { timeout: 30000 }); await ap.waitForTimeout(600); };
  for (const n of want) {
    console.log('--- ' + n);
    const run = new AsyncFunction('require', '__dirname', 'B', 'br', 'ap', 'ok', 'axeSource', 'fsF', 'fmUrl', 'fmReady', blocks[n]);
    await run(require, __dirname, lib.B, br, ap, lib.ok, lib.axeSource, fsF, fmUrl, fmReady);
  }
  await br.close();
  lib.finish();
})().catch((e) => { console.log('ERROR ' + e.message); process.exit(1); });
