"""Fungsi bersama untuk hook Claude Code proyek ini."""
import fnmatch
import json
import os
import subprocess
import sys

AkarRepo = os.environ.get("CLAUDE_PROJECT_DIR") or os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))

# File penjaga: agent boleh mengusulkan, tapi manusia yang menyetujui (permissionDecision "ask").
PolaPenjaga = [
    "CLAUDE.md", ".claude/*", "Alat/*", ".github/*", "PRD.md",
    "Spesifikasi/VektorUjiKalkulasi/*", "*/tests/Arsitektur/*",
    "*phpstan*.neon*", "*pint.json", "*rector.php", "*phpunit.xml*", "*eslint.config.*",
    "*.prettierrc*", "*analysis_options.yaml", "*dart_test.yaml", "*melos.yaml",
]
# File yang tidak boleh diedit tangan sama sekali (deny).
PolaTerlarang = {
    "Dokumen/*": "Dokumen/ adalah hasil generate. Ubah PRD.md (dengan persetujuan manusia) lalu jalankan: python3 Alat/PecahPrd.py",
    "*composer.lock": "Lockfile diperbarui lewat composer, bukan diedit tangan.",
    "*package-lock.json": "Lockfile diperbarui lewat npm, bukan diedit tangan.",
    "*pubspec.lock": "Lockfile diperbarui lewat dart/flutter pub, bukan diedit tangan.",
    ".env": "File .env berisi rahasia dan tidak boleh disentuh agent.",
    ".env.*": "File .env berisi rahasia dan tidak boleh disentuh agent.",
    "*/.env": "File .env berisi rahasia dan tidak boleh disentuh agent.",
    "*/.env.*": "File .env berisi rahasia dan tidak boleh disentuh agent.",
}


def BacaMasukan():
    try:
        return json.load(sys.stdin)
    except (json.JSONDecodeError, ValueError):
        return {}


def JadikanRelatif(Path):
    if not Path:
        return ""
    PathAbsolut = os.path.abspath(os.path.join(AkarRepo, Path)) if not os.path.isabs(Path) else os.path.abspath(Path)
    return os.path.relpath(PathAbsolut, AkarRepo).replace(os.sep, "/")


def Cocok(PathRelatif, DaftarPola):
    return any(fnmatch.fnmatch(PathRelatif, Pola) for Pola in DaftarPola)


def CariAlasanTerlarang(PathRelatif):
    if PathRelatif.endswith(".env.example"):
        return None
    for Pola, Alasan in PolaTerlarang.items():
        if fnmatch.fnmatch(PathRelatif, Pola):
            return Alasan
    return None


def MigrasiSudahDiMerge(PathRelatif):
    if not PathRelatif.startswith("Aplikasi/Web/database/migrations/"):
        return False
    # D-13: sebelum pindah, folder ini bernama Backend/. Cabang main lama masih memakai jalur itu.
    JalurLama = "Backend/" + PathRelatif[len("Aplikasi/Web/"):]
    for Cabang in ("origin/main", "main"):
        for Jalur in (PathRelatif, JalurLama):
            Hasil = subprocess.run(["git", "cat-file", "-e", f"{Cabang}:{Jalur}"], cwd=AkarRepo,
                                   capture_output=True, check=False)
            if Hasil.returncode == 0:
                return True
    return False


def KeputusanPreToolUse(Keputusan, Alasan):
    print(json.dumps({"hookSpecificOutput": {
        "hookEventName": "PreToolUse",
        "permissionDecision": Keputusan,
        "permissionDecisionReason": Alasan,
    }}))
    sys.exit(0)


def JalankanPengecek(*Argumen):
    Hasil = subprocess.run([sys.executable, os.path.join(AkarRepo, "Alat", "CekKonvensi.py"), *Argumen],
                           cwd=AkarRepo, capture_output=True, text=True, check=False)
    return Hasil.returncode, (Hasil.stdout + Hasil.stderr).strip()
