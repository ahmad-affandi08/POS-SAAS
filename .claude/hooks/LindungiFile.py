#!/usr/bin/env python3
"""Hook PreToolUse (Edit|Write|MultiEdit|NotebookEdit): melindungi file penjaga & file terlarang."""
import os
import sys

sys.path.insert(0, os.path.dirname(__file__))
from Bersama import (BacaMasukan, CariAlasanTerlarang, Cocok, JadikanRelatif, KeputusanPreToolUse,  # noqa: E402
                     MigrasiSudahDiMerge, PolaPenjaga)

Masukan = BacaMasukan()
Input = Masukan.get("tool_input", {}) or {}
PathRelatif = JadikanRelatif(Input.get("file_path") or Input.get("notebook_path") or "")
if not PathRelatif or PathRelatif.startswith(".."):
    sys.exit(0)

Alasan = CariAlasanTerlarang(PathRelatif)
if Alasan:
    KeputusanPreToolUse("deny", f"DIBLOKIR ({PathRelatif}): {Alasan}")

if MigrasiSudahDiMerge(PathRelatif):
    KeputusanPreToolUse("deny", f"DIBLOKIR ({PathRelatif}): migrasi yang sudah ada di main tidak boleh diubah. "
                                "Buat migrasi baru dengan pola expand → contract (CLAUDE.md aturan #15).")

if Cocok(PathRelatif, PolaPenjaga):
    # D-17: pemilik produk mengizinkan agent mengubah file penjaga tanpa konfirmasi. Tetap tidak boleh
    # melemahkan test/lint/CI (CLAUDE.md #19); perubahan dicatat di laporan & PRD.
    KeputusanPreToolUse("allow", f"{PathRelatif} adalah file penjaga; diubah atas mandat D-17 (tanpa konfirmasi).")

sys.exit(0)
