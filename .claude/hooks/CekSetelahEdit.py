#!/usr/bin/env python3
"""Hook PostToolUse (Edit|Write|MultiEdit): format file & cek konvensi; pelanggaran dikirim balik ke agent."""
import json
import os
import shutil
import subprocess
import sys

sys.path.insert(0, os.path.dirname(__file__))
from Bersama import AkarRepo, BacaMasukan, JadikanRelatif  # noqa: E402

Input = BacaMasukan().get("tool_input", {}) or {}
PathAsli = Input.get("file_path") or ""
PathRelatif = JadikanRelatif(PathAsli)
# File di worktree subagent (.claude/worktrees/<nama>/...) diformat & dicek di worktree itu sendiri, bukan di checkout
# utama yang kebetulan punya jalur relatif sama.
AbsolutAsli = os.path.abspath(PathAsli if os.path.isabs(PathAsli) else os.path.join(AkarRepo, PathAsli))
AwalanWorktree = os.path.join(AkarRepo, ".claude", "worktrees") + os.sep
Akar = AkarRepo
if AbsolutAsli.startswith(AwalanWorktree):
    Akar = os.path.join(AwalanWorktree, AbsolutAsli[len(AwalanWorktree):].split(os.sep, 1)[0])
if not PathRelatif or PathRelatif.startswith("..") or not os.path.isfile(os.path.join(Akar, PathRelatif)):
    sys.exit(0)


def FormatJikaTersedia(Path):
    """Formatter dijalankan hanya jika sudah terpasang; diam jika belum."""
    Absolut = os.path.join(Akar, Path)
    if Path.endswith(".php") and Path.startswith("Aplikasi/Web/"):
        Pint = os.path.join(Akar, "Aplikasi", "Web", "vendor", "bin", "pint")
        if os.path.isfile(Pint):
            subprocess.run([Pint, Absolut], cwd=os.path.join(Akar, "Aplikasi", "Web"), capture_output=True, check=False)
    elif Path.endswith(".dart") and shutil.which("dart"):
        subprocess.run(["dart", "format", Absolut], capture_output=True, check=False)
    elif Path.endswith((".ts", ".tsx", ".css")) and Path.startswith("Aplikasi/Web/"):
        Prettier = os.path.join(Akar, "Aplikasi", "Web", "node_modules", ".bin", "prettier")
        if os.path.isfile(Prettier):
            subprocess.run([Prettier, "--write", Absolut], cwd=os.path.join(Akar, "Aplikasi", "Web"),
                           capture_output=True, check=False)


FormatJikaTersedia(PathRelatif)
Hasil = subprocess.run([sys.executable, os.path.join(Akar, "Alat", "CekKonvensi.py"), "--file", PathRelatif],
                       cwd=Akar, capture_output=True, text=True, check=False)
Kode, Keluaran = Hasil.returncode, (Hasil.stdout + Hasil.stderr).strip()
if Kode != 0:
    print(json.dumps({
        "decision": "block",
        "reason": "Pengecek konvensi menemukan pelanggaran pada file yang baru diubah. Perbaiki sekarang "
                  "(jangan ubah pengecek/pengecualian):\n" + Keluaran,
    }))
sys.exit(0)
