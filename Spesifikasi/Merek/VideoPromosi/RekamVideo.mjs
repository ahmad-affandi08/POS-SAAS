// Merekam VideoPromosi30Detik.html menjadi MP4 1920x1080, bingkai demi bingkai (hasil deterministik).
// Butuh: Playwright (Chromium) dan ffmpeg. Jalankan dari akar repo:
//   node Spesifikasi/Merek/VideoPromosi/RekamVideo.mjs [keluaran.mp4] [fps]
// Path ffmpeg bisa diatur lewat variabel lingkungan FFMPEG.
import { spawn } from 'node:child_process';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

const folderIni = dirname(fileURLToPath(import.meta.url));
const pathKeluaran = resolve(process.argv[2] ?? resolve(folderIni, 'VideoPromosi30Detik.mp4'));
const fps = Number(process.argv[3] ?? 30);
const alamatHalaman = pathToFileURL(resolve(folderIni, 'VideoPromosi30Detik.html')).href + '?rekam=1';

async function RekamVideo() {
  const peramban = await chromium.launch();
  const halaman = await peramban.newPage({ viewport: { width: 1920, height: 1080 }, ignoreHTTPSErrors: true });
  await halaman.goto(alamatHalaman);
  await halaman.waitForFunction(() => document.body.dataset.siap === '1', null, { timeout: 20000 });
  const durasiDetik = await halaman.evaluate(() => window.DurasiDetik);
  const jumlahBingkai = Math.round(durasiDetik * fps);

  const ffmpeg = spawn(process.env.FFMPEG ?? 'ffmpeg', [
    '-y', '-loglevel', 'error', '-f', 'image2pipe', '-framerate', String(fps), '-i', '-',
    '-c:v', 'libx264', '-preset', 'slow', '-crf', '16', '-pix_fmt', 'yuv420p', '-movflags', '+faststart',
    pathKeluaran,
  ], { stdio: ['pipe', 'inherit', 'inherit'] });
  const selesai = new Promise((lanjut, gagal) => ffmpeg.on('close', (kode) => (kode === 0 ? lanjut() : gagal(new Error(`ffmpeg keluar dengan kode ${kode}`)))));

  for (let urutan = 0; urutan < jumlahBingkai; urutan += 1) {
    await halaman.evaluate((detik) => window.AturWaktu(detik), urutan / fps);
    const gambar = await halaman.screenshot({ type: 'png' });
    if (!ffmpeg.stdin.write(gambar)) await new Promise((lanjut) => ffmpeg.stdin.once('drain', lanjut));
    if (urutan % fps === 0) process.stdout.write(`\rMerekam ${Math.round(urutan / fps)}/${durasiDetik} detik`);
  }
  ffmpeg.stdin.end();
  await selesai;
  await peramban.close();
  process.stdout.write(`\nSelesai: ${pathKeluaran}\n`);
}

await RekamVideo();
