const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..', '..');
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'package.json'), 'utf8'));
const main = fs.readFileSync(path.join(root, 'electron', 'main.js'), 'utf8');
const auth = fs.readFileSync(path.join(root, 'core', 'Auth.php'), 'utf8');
const cron = fs.readFileSync(path.join(root, 'cron', 'calendar_reminders.php'), 'utf8');
const htaccess = fs.readFileSync(path.join(root, '.htaccess'), 'utf8');
const build = manifest.build || {};

assert.deepEqual(build.files, ['electron/main.js', 'electron/preload.js'],
  'The desktop package must include only its main process and preload files.');
assert.equal(build.asar, true, 'The desktop package must use ASAR.');
for (const file of build.files) {
  assert.equal(fs.existsSync(path.join(root, file)), true, `Missing packaged entry file: ${file}`);
}
assert.match(main, /nodeIntegration:\s*false/);
assert.match(main, /contextIsolation:\s*true/);
assert.match(main, /sandbox:\s*true/);
assert.match(main, /webSecurity:\s*true/);
assert.match(auth, /SELECT role, status, password_hash FROM users WHERE id = \? LIMIT 1/);
assert.match(auth, /hash_equals\(hash\('sha256', \$passwordHash\), \$sessionFingerprint\)/);
assert.match(auth, /\$_SESSION\['auth_password_fingerprint'\] = hash\('sha256'/);
const cronGuard = cron.indexOf("if (PHP_SAPI !== 'cli')");
const cronBootstrap = cron.indexOf("require __DIR__ . '/../bootstrap.php';");
assert.ok(cronGuard >= 0 && cronBootstrap > cronGuard,
  'The calendar reminder job must reject HTTP before loading application code.');
assert.match(htaccess, /RewriteRule \^\(\?:config\|core\|controllers\|models\|helpers\|services\|views\|database\|cron\|scripts\|tools\|tests\|docs\)/);
assert.match(htaccess, /RewriteRule \^bootstrap\\\.php\$/);
assert.match(htaccess, /RewriteRule \^storage\/\(\?!uploads\(\?:\/\|\$\)\)/);
assert.match(htaccess, /RewriteRule \(\^\|\/\)\\\.\(\?!well-known/);
assert.match(htaccess, /pnpm\(-lock\|-workspace\)\?\\\.yaml/);

console.log('SECURITY_STATIC_REGRESSIONS_OK');
