#!/usr/bin/env python3
"""Memecah PRD.md menjadi dokumen kecil di folder Dokumen/ agar mudah dibaca AI agent.

PRD.md tetap SATU-SATUNYA sumber kebenaran. Dokumen/ adalah hasil generate:
jangan diedit langsung. Setelah mengubah PRD.md, jalankan:

    python3 Alat/PecahPrd.py          # tulis ulang Dokumen/
    python3 Alat/PecahPrd.py --cek    # CI: gagal jika Dokumen/ tidak sinkron dengan PRD.md
"""
import os
import re
import sys

AkarRepo = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
PathPrd = os.path.join(AkarRepo, "PRD.md")
FolderDokumen = os.path.join(AkarRepo, "Dokumen")
Kepala = "<!-- DIBUAT OTOMATIS dari PRD.md oleh Alat/PecahPrd.py. JANGAN DIEDIT LANGSUNG: ubah PRD.md lalu jalankan ulang skrip. -->\n\n"


def JadikanPascal(Teks, MaksKata=7):
    Teks = Teks.replace("&", " ")
    Kata = re.findall(r"[A-Za-z0-9]+", Teks)[:MaksKata]
    return "".join(K[:1].upper() + K[1:] for K in Kata)


def PecahBagian(Isi, PolaJudul):
    """Kembalikan daftar (judul, isi) untuk setiap heading yang cocok PolaJudul."""
    Posisi = [(M.start(), M.group(1)) for M in re.finditer(PolaJudul, Isi, re.M)]
    Hasil = []
    for Indeks, (Mulai, Judul) in enumerate(Posisi):
        Selesai = Posisi[Indeks + 1][0] if Indeks + 1 < len(Posisi) else len(Isi)
        Hasil.append((Judul.strip(), Isi[Mulai:Selesai].rstrip().rstrip("-").rstrip() + "\n"))
    return Hasil


def BuatDokumen():
    Isi = open(PathPrd, encoding="utf-8").read()
    Keluaran = {}
    Indeks = ["# Indeks Dokumen\n", "Potongan PRD.md untuk dibaca sesuai kebutuhan. Sumber kebenaran tetap `PRD.md`.\n",
              "\n## Bagian PRD\n"]

    for Judul, IsiBagian in PecahBagian(Isi, r"^## (\d+\..*)$"):
        Nomor = Judul.split(".")[0].zfill(2)
        NamaFile = f"Prd/Bagian{Nomor}{JadikanPascal(Judul.split('.', 1)[1])}.md"
        if Nomor == "08":
            # Bagian 8 berisi semua flow: cukup pengantar + daftar, detail ada di Dokumen/Flow/
            Pengantar = IsiBagian.split("### P-01")[0]
            IsiBagian = Pengantar + "\nDetail setiap flow ada di folder `Dokumen/Flow/` (lihat Indeks.md).\n"
        Keluaran[NamaFile] = IsiBagian
        Indeks.append(f"- [{Judul}]({NamaFile})\n")

    Indeks.append("\n## Flow (P = Platform Pengelola, F = Tenant)\n")
    for Judul, IsiFlow in PecahBagian(Isi, r"^### ([PF]-\d\d · .*)$"):
        # Flow berakhir di heading level 2/3 berikutnya (bukan hanya di flow berikutnya)
        Batas = re.search(r"^#{2,3} ", IsiFlow[4:], re.M)
        if Batas:
            IsiFlow = IsiFlow[:Batas.start() + 4].rstrip().rstrip("-").rstrip() + "\n"
        Kode, Nama = Judul.split(" · ", 1)
        NamaFile = f"Flow/{Kode.replace('-', '')}{JadikanPascal(Nama)}.md"
        Keluaran[NamaFile] = IsiFlow
        Indeks.append(f"- [{Judul}]({NamaFile})\n")

    CocokKeputusan = re.search(r"^### 25\.1 .*?(?=^## |\Z)", Isi, re.M | re.S)
    if CocokKeputusan:
        Keluaran["Keputusan.md"] = CocokKeputusan.group(0).rstrip().rstrip("-").rstrip() + "\n"
        Indeks.insert(3, "- [Keputusan yang sudah diambil (D-xx)](Keputusan.md)\n")

    Keluaran["Indeks.md"] = "".join(Indeks)
    return {Nama: Kepala + Teks for Nama, Teks in Keluaran.items()}


def BacaDokumenLama():
    Lama = {}
    for Akar, _, DaftarFile in os.walk(FolderDokumen):
        for NamaFile in DaftarFile:
            PathPenuh = os.path.join(Akar, NamaFile)
            Lama[os.path.relpath(PathPenuh, FolderDokumen).replace(os.sep, "/")] = open(PathPenuh, encoding="utf-8").read()
    return Lama


def Utama(Argumen):
    Baru = BuatDokumen()
    if Argumen[:1] == ["--cek"]:
        if BacaDokumenLama() != Baru:
            print("Dokumen/ tidak sinkron dengan PRD.md. Jalankan: python3 Alat/PecahPrd.py")
            return 1
        print(f"Dokumen/ sinkron dengan PRD.md ({len(Baru)} file).")
        return 0
    for NamaLama in BacaDokumenLama():
        if NamaLama not in Baru:
            os.remove(os.path.join(FolderDokumen, NamaLama))
    for NamaFile, Teks in Baru.items():
        PathPenuh = os.path.join(FolderDokumen, NamaFile)
        os.makedirs(os.path.dirname(PathPenuh), exist_ok=True)
        open(PathPenuh, "w", encoding="utf-8").write(Teks)
    print(f"Dokumen/ ditulis ulang: {len(Baru)} file.")
    return 0


if __name__ == "__main__":
    sys.exit(Utama(sys.argv[1:]))
