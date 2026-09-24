#!/usr/bin/env python3
"""Hook PreToolUse (Bash): mencegat perintah berbahaya & penulisan ke file penjaga lewat shell."""
import os
import re
import sys

sys.path.insert(0, os.path.dirname(__file__))
from Bersama import BacaMasukan, BuangAwalanWorktree, KeputusanPreToolUse  # noqa: E402

Perintah = (BacaMasukan().get("tool_input", {}) or {}).get("command", "") or ""


def BuangIsiHeredoc(Teks):
    """Isi heredoc adalah data (misal isi file yang ditulis), bukan perintah: jangan ikut diperiksa."""
    Hasil, Penutup = [], None
    for Baris in Teks.splitlines():
        if Penutup is not None:
            if Baris.strip() == Penutup:
                Penutup = None
            continue
        Hasil.append(Baris)
        Cocok = re.search(r"<<-?\s*['\"]?(\w+)['\"]?", Baris)
        if Cocok:
            Penutup = Cocok.group(1)
    return "\n".join(Hasil)


Perintah = BuangIsiHeredoc(Perintah)
Datar = " ".join(Perintah.split())

Terlarang = [
    (r"\bgit\s+push\b.*(--force\b|--force-with-lease\b|\s-f\b)", "force push dilarang."),
    (r"--no-verify\b", "melewati git hook (--no-verify) dilarang."),
    (r"\bgit\s+commit\b.*\s-n\b", "melewati git hook (commit -n) dilarang."),
    (r"\bgit\s+config\b.*core\.hooksPath", "mengubah lokasi git hook dilarang."),
    (r"\bgit\s+(reset\s+--hard|clean\s+-[a-z]*f)", "menghapus perubahan secara paksa harus dilakukan manusia."),
    (r"\brm\s+-[a-zA-Z]*[rR][a-zA-Z]*\s+(/|~/?|\*|\./?|\.\./?|\$HOME/?)(\s|$)", "penghapusan rekursif di lokasi berbahaya dilarang."),
    (r"(cat|less|more|head|tail|grep|sed|awk|cp|mv|source|\.)\s+(?:[^|;&]*[/\s])?\.env(?!\.example)(\.|\s|$)", "membaca/menyalin .env dilarang."),
    (r"\b(migrate:fresh|migrate:refresh|migrate:reset|db:wipe)\b(?!.*--env=testing)", "perintah penghapus database hanya boleh dengan --env=testing."),
    (r"\bmarkTestSkipped\b|\bsed\b.*->skip\(", "melemahkan/men-skip test dilarang."),
]
for Pola, Alasan in Terlarang:
    if re.search(Pola, Datar):
        KeputusanPreToolUse("deny", f"DIBLOKIR: {Alasan} (CLAUDE.md, bagian 'Yang dilarang keras')")

PathPenjaga = r"(CLAUDE\.md|\.claude/|Alat/|\.github/|PRD\.md|Dokumen/|tests/Arsitektur|phpstan|pint\.json|rector\.php|phpunit\.xml|eslint\.config|analysis_options\.yaml|dart_test\.yaml|melos\.yaml)"
PolaTulis = r"(>|>>|\btee\b|\bsed\s+-i|\bperl\s+-[a-z]*i|\bcp\b|\bmv\b|\brm\b|\btruncate\b|\bchmod\b|\bln\b|\bpython3?\s+-c|\bnode\s+-e|\bgit\s+(checkout|restore)\b)"
# Jalur worktree subagent dinilai relatif terhadap akar worktree; pengalihan ke /dev/null bukan penulisan file.
DatarPenjaga = re.sub(r"\d?&?>>?\s*/dev/null|\d>&\d", " ", BuangAwalanWorktree(Datar))
if re.search(PathPenjaga, DatarPenjaga) and re.search(PolaTulis, DatarPenjaga):
    # Pengecualian: regenerasi Dokumen/ lewat skripnya sendiri.
    if not re.fullmatch(r"python3?\s+Alat/PecahPrd\.py(\s+--cek)?", Datar.strip()):
        KeputusanPreToolUse("ask", "Perintah ini tampaknya menulis ke file penjaga aturan proyek. "
                                   "Hanya lanjutkan jika pengguna menyetujui perubahan aturan tersebut.")

sys.exit(0)
