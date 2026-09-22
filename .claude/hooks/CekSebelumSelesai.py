#!/usr/bin/env python3
"""Hook Stop: agent tidak boleh menyatakan selesai selama konvensi/Dokumen masih melanggar.

Batas 3 kali blokir per sesi agar tidak terjadi putaran tanpa akhir; setelah itu agent
wajib melaporkan pelanggaran yang tersisa ke pengguna.
"""
import json
import os
import subprocess
import sys
import tempfile

sys.path.insert(0, os.path.dirname(__file__))
from Bersama import AkarRepo, BacaMasukan, JalankanPengecek  # noqa: E402

Masukan = BacaMasukan()
IdSesi = Masukan.get("session_id", "tanpa-sesi")
FilePenghitung = os.path.join(tempfile.gettempdir(), f"claude-cek-selesai-{IdSesi}")

Masalah = []
Kode, Keluaran = JalankanPengecek("--berubah")
if Kode != 0:
    Masalah.append(Keluaran)
Sinkron = subprocess.run([sys.executable, os.path.join(AkarRepo, "Alat", "PecahPrd.py"), "--cek"],
                         cwd=AkarRepo, capture_output=True, text=True, check=False)
if Sinkron.returncode != 0:
    Masalah.append(Sinkron.stdout.strip() + " (PRD.md berubah: minta manusia menjalankan skrip tersebut atau jalankan jika sudah disetujui.)")

if not Masalah:
    if os.path.exists(FilePenghitung):
        os.remove(FilePenghitung)
    sys.exit(0)

Jumlah = int(open(FilePenghitung).read() or 0) if os.path.exists(FilePenghitung) else 0
if Jumlah >= 3:
    # Sudah 3 kali: izinkan berhenti, tapi agent wajib melaporkan (disampaikan lewat stderr ke transkrip).
    print("Pelanggaran belum selesai setelah 3 percobaan. Laporkan ke pengguna apa adanya:\n" + "\n".join(Masalah), file=sys.stderr)
    sys.exit(0)
open(FilePenghitung, "w").write(str(Jumlah + 1))
print(json.dumps({
    "decision": "block",
    "reason": "Belum boleh selesai. Perbaiki pelanggaran berikut (jangan ubah pengecek), lalu jalankan /cek-dod:\n" + "\n".join(Masalah),
}))
sys.exit(0)
