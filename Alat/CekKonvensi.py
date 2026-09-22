#!/usr/bin/env python3
"""Pengecek konvensi proyek (PRD §13.7, §11, §13.4, §13.8).

Dipakai oleh hook Claude Code, CI, dan developer manusia.

Pemakaian:
    python3 Alat/CekKonvensi.py --semua             # seluruh repo
    python3 Alat/CekKonvensi.py --berubah           # file yang berubah dibanding main + belum di-commit
    python3 Alat/CekKonvensi.py --file A.php B.dart # file tertentu

Keluar dengan kode 1 jika ada pelanggaran.
Pengecualian hanya lewat Alat/KonvensiPengecualian.json (file terlindungi).
"""
import fnmatch
import json
import os
import re
import subprocess
import sys

AkarRepo = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
Pengecualian = json.load(open(os.path.join(AkarRepo, "Alat", "KonvensiPengecualian.json"), encoding="utf-8"))

PolaPascal = re.compile(r"^[A-Z][A-Za-z0-9]*$")
PolaKebab = re.compile(r"^[a-z0-9]+(?:-[a-z0-9]+)*$")
PolaParamRute = re.compile(r"^\{[a-z][A-Za-z0-9]*\??\}$")
# Tanpa membedakan huruf besar/kecil: variabel lokal camelCase (hargaJual, totalBayar) juga harus tertangkap.
KataUang = re.compile(r"(Harga|Total|Uang|Diskon|Pajak|Bayar|Kembalian|Subtotal|Saldo|Hpp|Biaya|Nominal)", re.IGNORECASE)

FolderAbaikan = {".git", "vendor", "node_modules", "build", ".dart_tool", "storage", "bootstrap", "public",
                 ".idea", ".vscode", "coverage", "Pods", ".gradle", "ephemeral"}

# Akar yang nama folder & file-nya wajib PascalCase
AkarPascal = [
    "Backend/app/",
    "Backend/tests/",
    "Backend/resources/js/",
]
PolaAkarDart = re.compile(r"^(Aplikasi|Paket)/[^/]+/(lib|test|integration_test)/")


class Pelanggaran:
    def __init__(self, Path, Baris, Pesan, Rujukan):
        self.Path, self.Baris, self.Pesan, self.Rujukan = Path, Baris, Pesan, Rujukan

    def __str__(self):
        Lokasi = f"{self.Path}:{self.Baris}" if self.Baris else self.Path
        return f"[KONVENSI] {Lokasi} — {self.Pesan} (PRD {self.Rujukan})"


def CocokSalahSatu(Nama, DaftarPola):
    return any(fnmatch.fnmatchcase(Nama, Pola) for Pola in DaftarPola)


def FolderDibebaskan(PathRelatif):
    return any(PathRelatif.startswith(Folder.rstrip("/") + "/") for Folder in Pengecualian["FolderBebas"])


def CekNamaPathPascal(PathRelatif):
    """Folder & file di bawah akar kode wajib PascalCase."""
    Hasil = []
    if FolderDibebaskan(PathRelatif):
        return Hasil
    Akar = next((A for A in AkarPascal if PathRelatif.startswith(A)), None)
    if Akar is None:
        CocokDart = PolaAkarDart.match(PathRelatif)
        if not CocokDart:
            return Hasil
        Akar = CocokDart.group(0)
    Sisa = PathRelatif[len(Akar):].split("/")
    for Folder in Sisa[:-1]:
        if not PolaPascal.match(Folder):
            Hasil.append(Pelanggaran(PathRelatif, 0, f"nama folder '{Folder}' harus PascalCase Bahasa Indonesia", "§13.7.1"))
    NamaFile = Sisa[-1]
    if CocokSalahSatu(NamaFile, Pengecualian["PolaFileBebas"]):
        return Hasil
    Stem = NamaFile.split(".")[0]
    if Stem and not PolaPascal.match(Stem):
        Hasil.append(Pelanggaran(PathRelatif, 0, f"nama file '{NamaFile}' harus PascalCase Bahasa Indonesia", "§13.7.1"))
    return Hasil


def BarisKomentar(Baris):
    Bersih = Baris.strip()
    return Bersih.startswith(("//", "*", "/*", "#"))


def MetodePhpBoleh(Nama, Isi, Posisi):
    if Nama in Pengecualian["MetodeFrameworkPhp"]:
        return True
    if any(re.search(Pola, Nama) for Pola in Pengecualian["PolaMetodeBolehPhp"]):
        return True
    # Accessor Eloquent gaya baru: function namaAtribut(): Attribute
    Sesudah = Isi[Posisi:Posisi + 200]
    if re.match(r"[^)]*\)\s*:\s*\\?(?:Illuminate\\Database\\Eloquent\\Casts\\)?Attribute\b", Sesudah):
        return True
    return False


def CekPhp(PathRelatif, Isi):
    Hasil = []
    DalamApp = PathRelatif.startswith("Backend/app/")
    DalamDomain = PathRelatif.startswith("Backend/app/Domain/")
    DalamPengelola = PathRelatif.startswith("Backend/app/Domain/Pengelola/") or PathRelatif.startswith("Backend/app/Http/Kontroler/Pengelola/")
    for NomorBaris, Baris in enumerate(Isi.splitlines(), 1):
        if BarisKomentar(Baris):
            continue
        for Cocok in re.finditer(r"\bfunction\s+([A-Za-z_]\w*)\s*\(", Baris):
            Nama = Cocok.group(1)
            if PolaPascal.match(Nama):
                continue
            PosisiGlobal = sum(len(B) + 1 for B in Isi.splitlines()[:NomorBaris - 1]) + Cocok.end() - 1
            if not MetodePhpBoleh(Nama, Isi, PosisiGlobal + 1):
                Hasil.append(Pelanggaran(PathRelatif, NomorBaris, f"function '{Nama}' harus PascalCase diawali kata kerja (misal HitungTotal)", "§13.7.1"))
        if DalamApp and re.search(r"\bfloat\b|\(float\)|floatval\(", Baris) and (DalamDomain or KataUang.search(Baris)):
            Hasil.append(Pelanggaran(PathRelatif, NomorBaris, "jangan pakai float untuk uang/kuantitas; pakai value object Uang/Kuantitas (brick/math)", "§8 F-07, §15.1"))
        if DalamApp and not DalamPengelola and re.search(r"withoutGlobalScopes?\s*\(|JalankanLintasTenant\s*\(", Baris):
            Hasil.append(Pelanggaran(PathRelatif, NomorBaris, "melewati scope tenant hanya boleh di Domain/Pengelola lewat KonteksPengelola", "§13.4, §13.8"))
    return Hasil


def CekMigrasi(PathRelatif, Isi):
    Hasil = []
    NamaFile = os.path.basename(PathRelatif)
    Cocok = re.match(r"^\d{4}_\d{2}_\d{2}_\d{6}_([A-Za-z0-9]+)\.php$", NamaFile)
    if not Cocok or not PolaPascal.match(Cocok.group(1)):
        Hasil.append(Pelanggaran(PathRelatif, 0, "nama migrasi harus 'YYYY_MM_DD_HHMMSS_NamaPascalCase.php' (misal ..._BuatTabelPenjualan.php)", "§13.7.1"))
    TabelFramework = set(Pengecualian["TabelFramework"])
    TabelAktif = ""
    for NomorBaris, Baris in enumerate(Isi.splitlines(), 1):
        if BarisKomentar(Baris):
            continue
        for CocokTabel in re.finditer(r"Schema::(?:create|table|drop|dropIfExists)\(\s*'([^']+)'", Baris):
            Tabel = CocokTabel.group(1)
            TabelAktif = Tabel
            if Tabel not in TabelFramework and not PolaPascal.match(Tabel):
                Hasil.append(Pelanggaran(PathRelatif, NomorBaris, f"nama tabel '{Tabel}' harus PascalCase tunggal Bahasa Indonesia", "§15.1"))
        if TabelAktif in TabelFramework:
            # Kolom tabel bawaan framework/paket mengikuti skema aslinya (§13.7.4).
            continue
        for CocokKolom in re.finditer(r"->(?:id|string|char|text|mediumText|longText|integer|bigInteger|unsignedBigInteger|unsignedInteger|tinyInteger|smallInteger|boolean|decimal|date|dateTime|timestamp|time|json|foreignId|foreignUlid|ulid|uuid|enum|binary|year|ipAddress|dropColumn)\(\s*'([^']+)'", Baris):
            Kolom = CocokKolom.group(1)
            if not PolaPascal.match(Kolom):
                Hasil.append(Pelanggaran(PathRelatif, NomorBaris, f"nama kolom '{Kolom}' harus PascalCase (misal IdOutlet, TanggalBisnis)", "§15.1"))
        if re.search(r"->(?:float|double)\(", Baris):
            Hasil.append(Pelanggaran(PathRelatif, NomorBaris, "kolom float/double dilarang; pakai decimal (uang 18,2, HPP 19,6, jumlah 18,4)", "§15.1"))
        if re.search(r"->(?:uuid|ulid|Uuid|Ulid|UUID|ULID)\(\s*\)", Baris):
            Hasil.append(Pelanggaran(PathRelatif, NomorBaris, "->uuid()/->ulid() tanpa nama membuat kolom huruf kecil (nama method PHP tidak case-sensitive); pakai ->UuidPublik()", "§13.7.2"))
        if re.search(r"->id\(\s*\)", Baris):
            Hasil.append(Pelanggaran(PathRelatif, NomorBaris, "gunakan ->id('Id') agar primary key bernama 'Id'", "§15.1"))
        if re.search(r"->(?:timestamps|softDeletes|rememberToken)\(\s*\)", Baris):
            Hasil.append(Pelanggaran(PathRelatif, NomorBaris, "gunakan ->WaktuStandar() / ->softDeletes('DihapusPada') agar kolom waktu berbahasa Indonesia", "§13.7.2"))
    return Hasil


def CekRute(PathRelatif, Isi):
    Hasil = []
    for NomorBaris, Baris in enumerate(Isi.splitlines(), 1):
        if BarisKomentar(Baris):
            continue
        for Cocok in re.finditer(r"(?:Route::|->)(?:get|post|put|patch|delete|any|match|resource|apiResource|prefix|view|redirect)\(\s*(?:\[[^\]]*\]\s*,\s*)?'([^']*)'", Baris):
            for Segmen in Cocok.group(1).strip("/").split("/"):
                if not Segmen or PolaKebab.match(Segmen) or PolaParamRute.match(Segmen) or re.match(r"^v\d+$", Segmen):
                    continue
                Hasil.append(Pelanggaran(PathRelatif, NomorBaris, f"segmen URL '{Segmen}' harus Bahasa Indonesia huruf kecil kebab-case, parameter {{camelCase}}", "§13.7.1 (D-06)"))
        for Cocok in re.finditer(r"->name\(\s*'([^']+)'", Baris):
            if not all(PolaKebab.match(Bagian) for Bagian in Cocok.group(1).split(".")):
                Hasil.append(Pelanggaran(PathRelatif, NomorBaris, f"nama route '{Cocok.group(1)}' harus titik + kebab-case (misal kelola.produk.daftar)", "§13.7.1"))
    return Hasil


def CekTypeScript(PathRelatif, Isi):
    Hasil = []
    for NomorBaris, Baris in enumerate(Isi.splitlines(), 1):
        if BarisKomentar(Baris):
            continue
        Nama = []
        Nama += re.findall(r"\bfunction\s+([A-Za-z_]\w*)\s*[<(]", Baris)
        Nama += re.findall(r"\b(?:const|let)\s+([A-Za-z_]\w*)\s*(?::[^=]+)?=\s*(?:async\s*)?(?:\([^)]*\)|[A-Za-z_]\w*)\s*(?::[^=]+)?=>", Baris)
        for N in Nama:
            if PolaPascal.match(N) or re.match(r"^use[A-Z]", N):
                continue
            Hasil.append(Pelanggaran(PathRelatif, NomorBaris, f"function '{N}' harus PascalCase (hook React boleh diawali 'use')", "§13.7.1"))
    return Hasil


PolaDeklarasiDart = re.compile(
    r"^\s*(?:@override\s+)?(?:static\s+|external\s+)?(?:Future|FutureOr|Stream|Iterable|List|Map|Set|void|int|double|bool|String|num|dynamic|Object|[A-Z]\w*)"
    r"(?:<[^>{}()=;]*>)?\??\s+([a-zA-Z_]\w*)\s*(?:<[^>]*>)?\s*\("
)


def CekDart(PathRelatif, Isi):
    Hasil = []
    Engine = PathRelatif.startswith("Paket/MesinKasir/")
    BarisBaris = Isi.splitlines()
    for NomorBaris, Baris in enumerate(BarisBaris, 1):
        if BarisKomentar(Baris):
            continue
        Cocok = PolaDeklarasiDart.match(Baris)
        if Cocok:
            Nama = Cocok.group(1)
            NamaInti = Nama.lstrip("_")
            SebelumnyaOverride = NomorBaris > 1 and BarisBaris[NomorBaris - 2].strip() == "@override"
            if not (PolaPascal.match(NamaInti) or Nama in Pengecualian["MetodeFrameworkDart"] or SebelumnyaOverride
                    or Nama in ("if", "for", "while", "switch", "return", "catch")):
                Hasil.append(Pelanggaran(PathRelatif, NomorBaris, f"function '{Nama}' harus PascalCase diawali kata kerja", "§13.7.1"))
        if re.search(r"\bdouble\b", Baris) and (Engine or KataUang.search(Baris)):
            Hasil.append(Pelanggaran(PathRelatif, NomorBaris, "jangan pakai double untuk uang/kuantitas; pakai Uang/Kuantitas (paket decimal)", "§13.1 B, §8 F-07"))
    return Hasil


def CekFile(PathRelatif):
    PathRelatif = PathRelatif.replace("\\", "/")
    PathAbsolut = os.path.join(AkarRepo, PathRelatif)
    if any(Bagian in FolderAbaikan for Bagian in PathRelatif.split("/")[:-1]):
        return []
    if not os.path.isfile(PathAbsolut):
        return []
    Hasil = CekNamaPathPascal(PathRelatif)
    NamaFile = os.path.basename(PathRelatif)
    if CocokSalahSatu(NamaFile, ["*.g.dart", "*.freezed.dart", "*.gr.dart", "*.mocks.dart", "*.d.ts"]):
        return Hasil
    try:
        Isi = open(PathAbsolut, encoding="utf-8").read()
    except (UnicodeDecodeError, OSError):
        return Hasil
    if PathRelatif.startswith(("Backend/database/migrations/", "Backend/tests/Pendukung/Migrasi/")) and NamaFile.endswith(".php"):
        Hasil += CekMigrasi(PathRelatif, Isi)
    elif PathRelatif.startswith("Backend/routes/") and NamaFile.endswith(".php"):
        Hasil += CekRute(PathRelatif, Isi)
    if NamaFile.endswith(".php") and PathRelatif.startswith(("Backend/app/", "Backend/tests/")):
        Hasil += CekPhp(PathRelatif, Isi)
    elif NamaFile.endswith((".ts", ".tsx")) and PathRelatif.startswith("Backend/resources/js/") and not FolderDibebaskan(PathRelatif):
        Hasil += CekTypeScript(PathRelatif, Isi)
    elif NamaFile.endswith(".dart") and PolaAkarDart.match(PathRelatif):
        Hasil += CekDart(PathRelatif, Isi)
    return Hasil


def JalankanGit(*Argumen):
    try:
        return subprocess.run(["git", *Argumen], cwd=AkarRepo, capture_output=True, text=True, check=False).stdout
    except FileNotFoundError:
        return ""


def AmbilFileBerubah():
    Dasar = ""
    for Kandidat in ("origin/main", "main"):
        Dasar = JalankanGit("merge-base", "HEAD", Kandidat).strip()
        if Dasar:
            break
    Daftar = set()
    if Dasar:
        Daftar.update(JalankanGit("diff", "--name-only", Dasar, "HEAD").split())
    Daftar.update(JalankanGit("diff", "--name-only").split())
    Daftar.update(JalankanGit("diff", "--name-only", "--cached").split())
    Daftar.update(JalankanGit("ls-files", "--others", "--exclude-standard").split())
    return sorted(Daftar)


def AmbilSemuaFile():
    Daftar = JalankanGit("ls-files").split() + JalankanGit("ls-files", "--others", "--exclude-standard").split()
    return sorted(set(Daftar))


def Utama(Argumen):
    if not Argumen or Argumen[0] not in ("--semua", "--berubah", "--file"):
        print(__doc__)
        return 2
    if Argumen[0] == "--semua":
        Daftar = AmbilSemuaFile()
    elif Argumen[0] == "--berubah":
        Daftar = AmbilFileBerubah()
    else:
        Daftar = [os.path.relpath(os.path.abspath(F), AkarRepo) for F in Argumen[1:]]
    SemuaPelanggaran = []
    for PathRelatif in Daftar:
        SemuaPelanggaran += CekFile(PathRelatif)
    for P in SemuaPelanggaran:
        print(P)
    if SemuaPelanggaran:
        print(f"\n{len(SemuaPelanggaran)} pelanggaran konvensi. Perbaiki kodenya; jangan mengubah pengecek atau daftar pengecualian.")
        return 1
    print(f"Konvensi OK ({len(Daftar)} file diperiksa).")
    return 0


if __name__ == "__main__":
    sys.exit(Utama(sys.argv[1:]))
