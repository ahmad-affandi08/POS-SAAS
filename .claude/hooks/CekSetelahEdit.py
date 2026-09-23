#!/usr/bin/env python3
"""Hook PostToolUse (Edit|Write|MultiEdit): format file & cek konvensi; pelanggaran dikirim balik ke agent."""
import json
import os
import shutil
import subprocess
import sys

sys.path.insert(0, os.path.dirname(__file__))
from Bersama import AkarRepo, BacaMasukan, JadikanRelatif, JalankanPengecek  # noqa: E402

Input = BacaMasukan().get("tool_input", {}) or {}
PathRelatif = JadikanRelatif(Input.get("file_path") or "")
if not PathRelatif or PathRelatif.startswith("..") or not os.path.isfile(os.path.join(AkarRepo, PathRelatif)):
    sys.exit(0)


def FormatJikaTersedia(Path):
    """Formatter dijalankan hanya jika sudah terpasang; diam jika belum."""
    Absolut = os.path.join(AkarRepo, Path)
    if Path.endswith(".php") and Path.startswith("Aplikasi/Web/"):
        Pint = os.path.join(AkarRepo, "Aplikasi", "Web", "vendor", "bin", "pint")
        if os.path.isfile(Pint):
            subprocess.run([Pint, Absolut], cwd=os.path.join(AkarRepo, "Aplikasi", "Web"), capture_output=True, check=False)
    elif Path.endswith(".dart") and shutil.which("dart"):
        subprocess.run(["dart", "format", Absolut], capture_output=True, check=False)
    elif Path.endswith((".ts", ".tsx", ".css")) and Path.startswith("Aplikasi/Web/"):
        Prettier = os.path.join(AkarRepo, "Aplikasi", "Web", "node_modules", ".bin", "prettier")
        if os.path.isfile(Prettier):
            subprocess.run([Prettier, "--write", Absolut], capture_output=True, check=False)


FormatJikaTersedia(PathRelatif)
Kode, Keluaran = JalankanPengecek("--file", PathRelatif)
if Kode != 0:
    print(json.dumps({
        "decision": "block",
        "reason": "Pengecek konvensi menemukan pelanggaran pada file yang baru diubah. Perbaiki sekarang "
                  "(jangan ubah pengecek/pengecualian):\n" + Keluaran,
    }))
sys.exit(0)
