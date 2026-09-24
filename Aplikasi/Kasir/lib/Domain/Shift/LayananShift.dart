import 'dart:convert';

import 'package:drift/drift.dart' show Value;
import 'package:inti/Inti.dart';
import 'package:klien_api/KlienApi.dart';

import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Data/RepositoriKasir.dart';
import '../GalatKasir.dart';
import '../Sesi/StafLokal.dart';

/// Pecahan uang Rupiah untuk hitung kas (kertas & logam yang beredar).
const List<int> daftarPecahanRupiah = [100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100];

/// Jenis mutasi kas (sama dengan server).
abstract final class JenisMutasi {
  static const String masuk = 'Masuk';
  static const String keluar = 'Keluar';
  static const String setoran = 'Setoran';
}

/// Satu baris hitungan pecahan kas awal.
class BarisPecahan {
  const BarisPecahan(this.nominal, this.jumlah);

  final int nominal;
  final int jumlah;

  Uang AmbilTotal() => Uang.DariBulat(nominal).Kali(Decimal.fromInt(jumlah));
}

/// Buka shift & kas masuk/keluar/setoran di perangkat (F-06), berlaku offline (BR-06.3). Aturan sama dengan server
/// agar kasir langsung tahu bila ditolak: BR-06.1 satu shift terbuka per perangkat, kas awal ≥ 0 & sama dengan hitungan
/// pecahan, kategori wajib untuk masuk/keluar, BR-06.2 hanya pemilik shift/supervisor di shift bukan bersama, BR-06.4
/// kas keluar di atas batas butuh supervisor berizin `kas.keluar.setujui` (PIN diperiksa `LayananMasuk`). Dokumen &
/// entri outbox disimpan dalam satu transaksi SQLite.
class LayananShift {
  LayananShift({required this.repositori, PembuatUlid? ulid, DateTime Function()? jam})
    : _ulid = ulid ?? PembuatUlid(),
      _jam = jam ?? DateTime.now;

  final RepositoriKasir repositori;
  final PembuatUlid _ulid;
  final DateTime Function() _jam;

  Future<Uang> AmbilBatasKasKeluar() async =>
      Uang.Dari(await repositori.AmbilPengaturan(KunciPengaturan.batasKasKeluar) ?? '200000.00');

  Future<bool> CekShiftBersama() async => await repositori.AmbilPengaturan(KunciPengaturan.shiftBersama) == '1';

  Future<BarisShift> BukaShift({required StafLokal kasir, required Uang kasAwal, List<BarisPecahan>? pecahan}) async {
    if (!kasir.PunyaIzin(IzinKasir.penjualanBuat)) {
      throw GalatKasir('TanpaIzin', '${kasir.nama} tidak punya izin berjualan dan membuka shift.');
    }

    if (kasAwal.BernilaiNegatif()) {
      throw const GalatKasir('KasAwalTidakValid', 'Kas awal tidak boleh minus.');
    }

    final pecahanTerisi = pecahan?.where((p) => p.jumlah > 0).toList();
    if (pecahanTerisi != null && pecahanTerisi.isNotEmpty) {
      final total = pecahanTerisi.fold(Uang.Nol(), (jumlah, p) => jumlah.Tambah(p.AmbilTotal()));
      if (!total.SamaDengan(kasAwal)) {
        throw GalatKasir(
          'PecahanTidakSesuai',
          'Jumlah hitungan pecahan (${total.FormatRupiah()}) tidak sama dengan kas awal (${kasAwal.FormatRupiah()}).',
        );
      }
    }

    if (await repositori.AmbilShiftAktif() != null) {
      throw const GalatKasir('ShiftSudahTerbuka', 'Perangkat ini masih punya shift terbuka. Tutup shift itu dulu.');
    }

    final sekarang = _jam().toUtc();
    final uuid = _ulid.Buat();
    final bersama = await CekShiftBersama();
    final dataPecahan = pecahanTerisi == null || pecahanTerisi.isEmpty
        ? null
        : [
            for (final p in pecahanTerisi) {'Nominal': '${p.nominal}', 'Jumlah': p.jumlah},
          ];

    await repositori.SimpanShiftBaru(
      ShiftCompanion.insert(
        Uuid: uuid,
        DibukaOleh: kasir.uuid,
        NamaKasir: kasir.nama,
        DibukaPada: sekarang,
        KasAwal: kasAwal.KeString(),
        PecahanKasAwal: Value(dataPecahan == null ? null : jsonEncode(dataPecahan)),
        Bersama: bersama,
        Status: StatusShiftLokal.terbuka,
      ),
      ItemOutbox(
        jenis: 'Shift.Buka',
        uuid: uuid,
        data: {
          'UuidPengguna': kasir.uuid,
          'DibukaPada': sekarang.toIso8601String(),
          'KasAwal': kasAwal.KeString(),
          'Pecahan': ?dataPecahan,
          'Bersama': bersama,
        },
      ),
      sekarang,
    );

    return (await repositori.AmbilShiftAktif())!;
  }

  /// Apakah kas keluar sebesar `jumlah` butuh persetujuan supervisor (BR-06.4).
  Future<bool> CekButuhPersetujuan(String jenis, Uang jumlah) async =>
      jenis == JenisMutasi.keluar && jumlah.Bandingkan(await AmbilBatasKasKeluar()) > 0;

  Future<BarisMutasiKas> CatatMutasi({
    required BarisShift shift,
    required String jenis,
    required Uang jumlah,
    required StafLokal pencatat,
    String? uuidKategori,
    String? catatan,
    StafLokal? penyetuju,
  }) async {
    if (jumlah.Bandingkan(Uang.Nol()) <= 0) {
      throw const GalatKasir('JumlahTidakValid', 'Jumlah kas harus lebih dari Rp 0.');
    }

    if (!pencatat.PunyaIzin(IzinKasir.penjualanBuat)) {
      throw GalatKasir('TanpaIzin', '${pencatat.nama} tidak punya izin mencatat kas.');
    }

    if (!shift.Bersama && pencatat.uuid != shift.DibukaOleh && !pencatat.PunyaIzin(IzinKasir.kasKeluarSetujui)) {
      throw const GalatKasir('BukanShiftSendiri', 'Kas hanya bisa dicatat oleh kasir pemilik shift atau supervisor.');
    }

    BarisKategoriKas? kategori;
    if (jenis == JenisMutasi.setoran) {
      uuidKategori = null;
    } else {
      kategori = uuidKategori == null ? null : await repositori.CariKategori(uuidKategori);
      if (kategori == null || kategori.Jenis != jenis) {
        throw const GalatKasir('KategoriTidakValid', 'Pilih kategori kas terlebih dahulu.');
      }
    }

    if (await CekButuhPersetujuan(jenis, jumlah)) {
      if (penyetuju == null) {
        throw GalatKasir(
          'PersetujuanDiperlukan',
          'Kas keluar di atas ${(await AmbilBatasKasKeluar()).FormatRupiah()} wajib disetujui supervisor dengan PIN.',
        );
      }
    }

    if (penyetuju != null && !penyetuju.PunyaIzin(IzinKasir.kasKeluarSetujui)) {
      throw GalatKasir('PenyetujuTidakBerwenang', '${penyetuju.nama} tidak punya izin menyetujui kas keluar.');
    }

    final sekarang = _jam().toUtc();
    final uuid = _ulid.Buat();
    final catatanRapi = catatan?.trim();
    final teksCatatan = catatanRapi == null || catatanRapi.isEmpty ? null : catatanRapi;

    await repositori.SimpanMutasiBaru(
      MutasiKasCompanion.insert(
        Uuid: uuid,
        UuidShift: shift.Uuid,
        Jenis: jenis,
        UuidKategori: Value(kategori?.Uuid),
        NamaKategori: Value(kategori?.Nama),
        Jumlah: jumlah.KeString(),
        Catatan: Value(teksCatatan),
        DicatatOleh: pencatat.uuid,
        DicatatPada: sekarang,
        DisetujuiOleh: Value(penyetuju?.uuid),
      ),
      ItemOutbox(
        jenis: 'MutasiKas.Catat',
        uuid: uuid,
        data: {
          'UuidShift': shift.Uuid,
          'Jenis': jenis,
          'UuidKategori': kategori?.Uuid,
          'Jumlah': jumlah.KeString(),
          'Catatan': teksCatatan,
          'UuidPencatat': pencatat.uuid,
          'DicatatPada': sekarang.toIso8601String(),
          'UuidPenyetuju': penyetuju?.uuid,
        },
      ),
      sekarang,
    );

    return (await repositori.AmbilMutasi(shift.Uuid)).firstWhere((m) => m.Uuid == uuid);
  }

  /// Ringkasan kas non-penjualan shift (kas awal + masuk − keluar − setoran); penjualan tunai ditambah F-07.
  static Uang HitungKasNonPenjualan(BarisShift shift, List<BarisMutasiKas> mutasi) => mutasi.fold(
    Uang.Dari(shift.KasAwal),
    (kas, m) => m.Jenis == JenisMutasi.masuk ? kas.Tambah(Uang.Dari(m.Jumlah)) : kas.Kurangi(Uang.Dari(m.Jumlah)),
  );
}
