import 'dart:convert';

import 'package:klien_api/KlienApi.dart';
import 'package:mesin_kasir/MesinKasir.dart';

import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Data/RepositoriPelanggan.dart';
import '../GalatKasir.dart';
import '../Penjualan/Keranjang.dart';
import '../Sesi/StafLokal.dart';

/// Hasil cari pelanggan: [online] false = hasil dari cache perangkat (offline).
class HasilCariPelanggan {
  const HasilCariPelanggan({required this.pelanggan, required this.online});

  final List<PelangganTerpilih> pelanggan;
  final bool online;
}

/// Pelanggan di aplikasi POS (Rincian F-16a, CRM-01), berlaku offline:
/// - cari online (nama/nomor HP, min. 3 karakter); offline → cari di pelanggan yang pernah dipakai perangkat ini;
/// - pelanggan baru (nama + nomor HP) langsung dipakai: outbox `Pelanggan.Buat` dikirim sebelum penjualannya (FIFO);
///   nomor HP dinormalisasi seperti server (`0812…` → `62812…`, 10–15 digit) dan hanya disimpan tersamar di perangkat;
/// - kasir cukup ber-izin `penjualan.buat`.
class LayananPelanggan {
  LayananPelanggan({required this.klien, required this.repositori, PembuatUlid? ulid, DateTime Function()? jam})
    : _ulid = ulid ?? PembuatUlid(),
      _jam = jam ?? DateTime.now;

  static const String jenisOutbox = 'Pelanggan.Buat';
  static const int panjangNamaMaksimal = 150;
  static const int panjangKataMinimal = 3;

  final KlienPos klien;
  final RepositoriPelanggan repositori;
  final PembuatUlid _ulid;
  final DateTime Function() _jam;

  /// Sama dengan `NomorHp::Normalisasi` server; null = tidak valid.
  static String? NormalisasiNoHp(String masukan) {
    final rapi = masukan.trim();
    if (!RegExp(r'^[0-9+() .-]+$').hasMatch(rapi)) {
      return null;
    }
    var angka = rapi.replaceAll(RegExp(r'\D'), '');
    if (angka.startsWith('0')) {
      angka = '62${angka.substring(1)}';
    } else if (angka.startsWith('8')) {
      angka = '62$angka';
    }
    return angka.length < 10 || angka.length > 15 ? null : angka;
  }

  /// Sama dengan `NomorHp::Samarkan` server: `6281234567890` → `0812****7890`.
  static String SamarkanNoHp(String nomor) {
    final lokal = nomor.startsWith('62') ? '0${nomor.substring(2)}' : '+$nomor';
    final bintang = lokal.length - 8 > 0 ? '*' * (lokal.length - 8) : '';
    return '${lokal.substring(0, 4)}$bintang${lokal.substring(lokal.length - 4)}';
  }

  Future<List<PelangganTerpilih>> AmbilTerakhir() async => [
    for (final b in await repositori.AmbilTerakhir()) DariCache(b),
  ];

  static PelangganTerpilih DariCache(BarisPelangganLokal b) => PelangganTerpilih(
    uuid: b.Uuid,
    nama: b.Nama,
    noHpSamar: b.NoHpSamar,
    kodeTier: b.KodeTier,
    namaTier: b.NamaTier,
    limitKredit: b.LimitKredit,
    sisaPiutang: b.SisaPiutang,
    hariLewatJatuhTempo: b.HariLewatJatuhTempo,
    hariLahir: b.HariLahir,
    jumlahTransaksi: b.JumlahTransaksi,
    pemakaianPromo: b.PemakaianPromo == null ? const {} : KodekPemakaianPromo.DariJson(jsonDecode(b.PemakaianPromo!)),
    pemakaianPada: b.PemakaianPada,
  );

  Future<HasilCariPelanggan> Cari(String kata) async {
    if (kata.trim().length < panjangKataMinimal) {
      return const HasilCariPelanggan(pelanggan: [], online: true);
    }
    try {
      final hasil = await klien.CariPelanggan(kata);
      return HasilCariPelanggan(
        pelanggan: [
          for (final p in hasil)
            PelangganTerpilih(
              uuid: p.uuid,
              nama: p.nama,
              noHpSamar: p.noHpSamar,
              kodeTier: p.kodeTier,
              namaTier: p.namaTier,
              saldoPoin: p.saldoPoin,
              limitKredit: p.limitKredit,
              sisaPiutang: p.sisaPiutang,
              hariLewatJatuhTempo: p.hariLewatJatuhTempo,
              hariLahir: p.hariLahir,
              jumlahTransaksi: p.jumlahTransaksi,
              pemakaianPromo: {
                for (final e in p.pemakaianPromo.entries)
                  e.key: PemakaianPromoPelanggan(hari: e.value.hari, promo: e.value.promo),
              },
              pemakaianPada: p.pemakaianPada,
            ),
        ],
        online: true,
      );
    } on GalatJaringan {
      return HasilCariPelanggan(pelanggan: [for (final b in await repositori.Cari(kata)) DariCache(b)], online: false);
    } on GalatApi catch (galat) {
      throw GalatKasir(galat.kode, galat.pesan);
    }
  }

  /// F-16b: saldo poin & aturan tukar terkini. Tukar poin wajib online (§18.4): offline → `GalatKasir` `PerluOnline`.
  Future<SaldoPoinPos> AmbilSaldoPoin(String uuidPelanggan) async {
    try {
      return await klien.AmbilSaldoPoin(uuidPelanggan);
    } on GalatJaringan {
      throw const GalatKasir('PerluOnline', 'Tukar poin perlu koneksi internet. Coba lagi saat perangkat online.');
    } on GalatApi catch (galat) {
      throw GalatKasir(galat.kode, galat.pesan);
    }
  }

  /// Poin terbanyak yang bisa ditukar: saldo, dibatasi ⌊[sisaTagihan] ÷ [nilaiPerPoin]⌋ agar potongan tidak melebihi
  /// subtotal setelah diskon lain.
  static int HitungMaksimalPoin({required int saldo, required Uang sisaTagihan, required Uang nilaiPerPoin}) {
    if (saldo <= 0 || nilaiPerPoin.KeDesimal() <= Decimal.zero) {
      return 0;
    }
    final batas = (sisaTagihan.KeDesimal() / nilaiPerPoin.KeDesimal()).floor().toInt();
    return batas < saldo ? (batas < 0 ? 0 : batas) : saldo;
  }

  /// Nilai potongan [poin] × [nilaiPerPoin].
  static Uang HitungNilaiTukar(int poin, Uang nilaiPerPoin) => nilaiPerPoin.Kali(Decimal.fromInt(poin));

  /// Pelanggan hasil cari dipilih: disimpan ke cache agar bisa dicari lagi offline.
  Future<void> CatatDipakai(PelangganTerpilih pelanggan) => repositori.Simpan(
    pelanggan.uuid,
    pelanggan.nama,
    pelanggan.noHpSamar,
    _jam(),
    kodeTier: pelanggan.kodeTier,
    namaTier: pelanggan.namaTier,
    kredit: pelanggan.sisaPiutang == null
        ? null
        : (
            limitKredit: pelanggan.limitKredit,
            sisaPiutang: pelanggan.sisaPiutang!,
            hariLewatJatuhTempo: pelanggan.hariLewatJatuhTempo ?? 0,
          ),
    // F-16c bagian 3: hanya dari hasil cari online (jumlah transaksi diketahui); pilihan dari cache tidak menimpa.
    promo: pelanggan.jumlahTransaksi == null
        ? null
        : (
            hariLahir: pelanggan.hariLahir,
            jumlahTransaksi: pelanggan.jumlahTransaksi,
            pemakaianPromo: jsonEncode(KodekPemakaianPromo.KeJson(pelanggan.pemakaianPromo)),
            pemakaianPada: pelanggan.pemakaianPada,
          ),
  );

  Future<PelangganTerpilih> Buat({required String nama, required String noHp, required StafLokal kasir}) async {
    if (!kasir.PunyaIzin(IzinKasir.penjualanBuat)) {
      throw GalatKasir('TanpaIzin', '${kasir.nama} tidak punya izin menambah pelanggan.');
    }
    final rapi = nama.trim();
    if (rapi.isEmpty) {
      throw const GalatKasir('NamaWajib', 'Isi nama pelanggan.');
    }
    if (rapi.runes.length > panjangNamaMaksimal) {
      throw const GalatKasir('NamaTerlaluPanjang', 'Nama pelanggan paling panjang 150 karakter.');
    }
    final nomor = NormalisasiNoHp(noHp);
    if (nomor == null) {
      throw const GalatKasir('NoHpTidakValid', 'Nomor HP tidak valid. Contoh: 0812-3456-7890.');
    }
    final sekarang = _jam().toUtc();
    final uuid = _ulid.Buat();
    final samar = SamarkanNoHp(nomor);
    await repositori.SimpanBaru(
      uuid,
      rapi,
      samar,
      ItemOutbox(
        jenis: jenisOutbox,
        uuid: uuid,
        data: {
          'Nama': rapi,
          'NoHp': nomor,
          'Email': null,
          'UuidPengguna': kasir.uuid,
          'DibuatPada': sekarang.toIso8601String(),
        },
      ),
      sekarang,
    );
    return PelangganTerpilih(uuid: uuid, nama: rapi, noHpSamar: samar);
  }
}
