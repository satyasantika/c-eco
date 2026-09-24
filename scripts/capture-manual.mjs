// Memotret halaman C-ECO yang sedang berjalan untuk manual pengguna (/panduan).
//
// Jangan dijalankan langsung tanpa job: berkas job dibuat oleh
// `php artisan manual:capture-screenshots` dan berisi URL, akun demo, serta
// daftar tangkapan dari App\Support\UserManual.
//
//   node scripts/capture-manual.mjs storage/app/manual-capture.json

import { chromium } from 'playwright';
import { mkdir, readFile, rm } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const jobPath = resolve(root, process.argv[2] ?? 'storage/app/manual-capture.json');
const job = JSON.parse(await readFile(jobPath, 'utf8'));
const outDir = resolve(root, job.outDir);

const DESKTOP = { width: 1366, height: 860 };
const PHONE = { width: 390, height: 844 };

const browser = await chromium.launch();
const pages = new Map();
let failures = 0;

async function pageFor(as, mobile) {
    const key = `${as}:${mobile ? 'phone' : 'desk'}`;
    if (pages.has(key)) return pages.get(key);

    const context = await browser.newContext({
        viewport: mobile ? PHONE : DESKTOP,
        deviceScaleFactor: mobile ? 2 : 1,
        isMobile: mobile,
        hasTouch: mobile,
        locale: 'id-ID',
        timezoneId: 'Asia/Jakarta',
    });
    const page = await context.newPage();

    const account = job.accounts[as];
    if (account) {
        await page.goto(job.baseUrl + '/admin/login');
        await page.locator('input[type="email"]').fill(account.email);
        await page.locator('input[type="password"]').fill(account.password);
        await page.locator('button[type="submit"]').click();
        await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 20000 });
    }

    pages.set(key, page);
    return page;
}

async function settle(page) {
    await page.waitForLoadState('load');
    // Livewire/Filament merender ulang sesudah load; polling Monitor membuat networkidle tak pernah datang.
    await page.waitForTimeout(900);
}

async function runStep(page, [action, selector, value]) {
    const target = page.locator(selector).first();
    switch (action) {
        case 'fill': await target.fill(value); break;
        case 'select': await target.selectOption(value); break;
        case 'check': await target.check({ force: true }); break;
        case 'click': await target.click({ force: true }); await settle(page); break;
        case 'waitFor': await target.waitFor({ state: 'visible', timeout: 15000 }); break;
        case 'scrollTo': await target.evaluate((el) => el.scrollIntoView({ block: 'start' })); await page.waitForTimeout(400); break;
        default: throw new Error(`Aksi tidak dikenal: ${action}`);
    }
}

for (const shot of job.shots) {
    const mobile = Boolean(shot.mobile);
    const file = resolve(outDir, shot.file);
    try {
        const page = await pageFor(shot.as, mobile);
        if (shot.path) {
            const response = await page.goto(job.baseUrl + shot.path);
            // Halaman galat (403 karena peran, 500) tidak boleh masuk manual diam-diam.
            if (response && response.status() >= 400) {
                throw new Error(`HTTP ${response.status()} untuk ${shot.path} sebagai ${shot.as}`);
            }
            await settle(page);
        }
        for (const step of shot.steps ?? []) {
            await runStep(page, step);
        }
        await page.waitForTimeout(300);
        await mkdir(dirname(file), { recursive: true });

        const options = {
            path: file,
            animations: 'disabled',
            caret: 'hide',
            mask: shot.mask ? [page.locator(shot.mask)] : [],
            maskColor: '#1a2b22',
        };
        if (shot.clip === 'header') {
            options.clip = { x: 0, y: 0, width: DESKTOP.width, height: 420 };
        } else {
            options.fullPage = mobile;
        }
        await page.screenshot(options);
        console.log(`ok   ${shot.file}  (${shot.as})`);
    } catch (error) {
        failures++;
        console.error(`GAGAL ${shot.file}: ${error.message.split('\n')[0]}`);
        // Potret keadaan saat gagal, di luar folder publik, untuk memperbaiki selektor.
        const page = pages.get(`${shot.as}:${mobile ? 'phone' : 'desk'}`);
        if (page) {
            const debug = resolve(root, 'storage/app/manual-capture-failures', shot.file);
            await mkdir(dirname(debug), { recursive: true });
            await page.screenshot({ path: debug, fullPage: true }).catch(() => {});
        }
    }
}

await browser.close();
// Job memuat sandi akun demo; jangan dibiarkan tergeletak.
await rm(jobPath, { force: true });

console.log(`${job.shots.length - failures}/${job.shots.length} tangkapan tersimpan di ${job.outDir}`);
process.exit(failures === 0 ? 0 : 1);
