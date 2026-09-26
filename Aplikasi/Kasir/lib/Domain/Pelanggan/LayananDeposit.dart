import 'package:drift/drift.dart' show Value;
import 'package:inti/Inti.dart';
import 'package:klien_api/KlienApi.dart';
import 'package:mesin_kasir/MesinKasir.dart';

import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Data/RepositoriDeposit.dart';
import '../../Data/RepositoriKasir.dart';
import '../GalatKasir.dart';
import '../Penjualan/Keranjang.dart';
import '../Penjualan/KonteksPenjualan.dart';
import '../Sesi/StafLokal.dart';

/// Isi deposit yang tersimpan di perangkat. Data cetak (pelanggan, kasir, metode) untuk bukti isi deposit hanya ada di
/// memori setelah isi deposit dibuat.
class IsiDepositTersimpan {
  const IsiDepositTersimpan({
    required this.uuid,
    required this.nomor,
    required this.jumlah,
    required this.namaPelanggan,
    required this.namaKasir,
    required this.namaMetode,
    required this.tunai,
    required this.dibuatPada,
    this.referensi,
    this.saldoSebelum,
  });

  final String uuid;
  final String nomor;
  final Uang jumlah;
  final String namaPelanggan;
  final String namaKasir;
  final String namaMetode;

  /// Dibayar tunai (laci dibuka pada cetak otomatis pertama).
  final bool tunai;
  final DateTime dibuatPada;
  final String? referensi;

  /// Saldo sebelum isi bila sempat dibaca online; null = tidak diketahui (offline), saldo akhir tidak dicetak.
  final Uang? saldoSebelum;

  Uang? get saldoSesudah => saldoSebelum?.Tambah(jumlah);
}

/// Deposit pelanggan di aplikasi kasir (F-16d bagian 1, CRM-04):
/// - **Isi** (offline): pelanggan terpilih mengisi saldo dengan tunai/QRIS statis/EDC/transfer/e-wallet, Rupiah bulat
///   dalam batas data awal. Nomor `DEP/{KodeOutlet}/{YYMMDD}/{KodePerangkat}-{SEQ4}`; baris lokal (kas shift) + outbox
///   `Deposit.Isi` dalam satu transaksi SQLite. Server menambah saldo & membukukan J-16.1.
/// - **Saldo** (online): dibaca sebelum membayar dengan deposit; offline → `PerluOnline` (saldo bisa dipakai perangkat
///   lain, jadi tidak di-cache).
class LayananDeposit {
  LayananDeposit({
    required this.klien,
    required this.repositori,
    required this.repositoriKasir,
    PembuatUlid? ulid,
    DateTime Function()? jam,
  }) : _ulid = ulid ?? PembuatUlid(),
       _jam = jam ?? DateTime.now;

  static const String jenisOutbox = 'Deposit.Isi';
  static const int panjangReferensiMaksimal = 100;

  final KlienPos klien;
  final RepositoriDeposit repositori;
  final RepositoriKasir repositoriKasir;
  final PembuatUlid _ulid;
  final DateTime Function() _jam;

  /// Saldo deposit terkini pelanggan (wajib online).
  Future<Uang> AmbilSaldo(String uuidPelanggan) async {
    try {
      final hasil = await klien.AmbilSaldoDeposit(uuidPelanggan);
      return Uang.Dari(hasil.saldoDeposit);
    } on GalatJaringan {
      throw const GalatKasir(
        'PerluOnline',
        'Saldo deposit perlu dicek online. Coba lagi saat perangkat terhubung internet.',
      );
    } on GalatApi catch (galat) {
      throw GalatKasir(galat.kode, galat.pesan);
    }
  }

  /// Aturan isi deposit yang bisa diperiksa sebelum disimpan: jumlah Rupiah bulat dalam batas [k].
  static void ValidasiJumlah(Uang jumlah, KonteksPenjualan k) {
    final minimal = Uang.Dari(k.deposit.minimalIsi);
    final maksimal = Uang.Dari(k.deposit.maksimalIsi);
    if (!jumlah.KeString().endsWith('.00') || jumlah.Bandingkan(minimal) < 0 || jumlah.Bandingkan(maksimal) > 0) {
      throw GalatKasir(
        'JumlahTidakValid',
        'Isi deposit harus Rupiah bulat ${minimal.FormatRupiah()} sampai ${maksimal.FormatRupiah()}.',
      );
    }
  }

  Future<IsiDepositTersimpan> Isi({
    required PelangganTerpilih pelanggan,
    required Uang jumlah,
    required BarisMetodePembayaran metode,
    required StafLokal kasir,
    required KonteksPenjualan k,
    String? referensi,
    Uang? saldoSebelum,
  }) async {
    if (!k.deposit.berlaku) {
      throw const GalatKasir('FiturTidakAktif', 'Paket usaha ini belum termasuk deposit pelanggan.');
    }
    final shift = await repositoriKasir.AmbilShiftAktif();
    if (shift == null) {
      throw const GalatKasir(
        'ShiftTidakDitemukan',
        'Belum ada shift terbuka. Buka shift dulu sebelum menerima deposit.',
      );
    }
    if (!kasir.PunyaIzin(IzinKasir.penjualanBuat)) {
      throw GalatKasir('TanpaIzin', '${kasir.nama} tidak punya izin menerima pembayaran.');
    }
    if (!JenisMetodeBayar.bolehIsiDeposit.contains(metode.Jenis)) {
      throw GalatKasir('MetodeBayarBelumDidukung', 'Deposit tidak bisa diisi dengan ${metode.Nama}.');
    }
    ValidasiJumlah(jumlah, k);
    final rapiReferensi = referensi?.trim();
    if ((rapiReferensi?.runes.length ?? 0) > panjangReferensiMaksimal) {
      throw const GalatKasir('ReferensiTerlaluPanjang', 'Referensi paling panjang 100 karakter.');
    }
    final kodeOutlet = k.kodeOutlet ?? '';
    final kodePerangkat = k.kodePerangkat ?? '';
    if (kodeOutlet.isEmpty || kodePerangkat.isEmpty) {
      throw const GalatKasir(
        'DataAwalBelumLengkap',
        'Kode outlet atau perangkat belum ada di perangkat ini. Sambungkan ke internet agar data terbaru terunduh.',
      );
    }

    final sekarang = _jam().toUtc();
    final t = k.HitungTanggalBisnis(sekarang);
    final yymmdd = '${t.substring(2, 4)}${t.substring(5, 7)}${t.substring(8, 10)}';
    final uuid = _ulid.Buat();
    final ref = rapiReferensi == null || rapiReferensi.isEmpty ? null : rapiReferensi;
    late String nomor;
    await repositori.Simpan(
      kodePerangkat: kodePerangkat,
      tanggal: yymmdd,
      sekarang: sekarang,
      susun: (urut) {
        nomor = 'DEP/$kodeOutlet/$yymmdd/$kodePerangkat-${urut.toString().padLeft(4, '0')}';
        return (
          isi: IsiDepositLokalCompanion.insert(
            Uuid: uuid,
            UuidShift: shift.Uuid,
            Nomor: nomor,
            UuidPelanggan: pelanggan.uuid,
            NamaPelanggan: pelanggan.nama,
            Jumlah: jumlah.KeString(),
            UuidMetodePembayaran: metode.Uuid,
            JenisMetode: metode.Jenis,
            NamaMetode: metode.Nama,
            Referensi: Value(ref),
            DibuatPada: sekarang,
          ),
          outbox: ItemOutbox(
            jenis: jenisOutbox,
            uuid: uuid,
            data: {
              'UuidPelanggan': pelanggan.uuid,
              'Jumlah': jumlah.KeString(),
              'UuidMetodePembayaran': metode.Uuid,
              'Referensi': ref,
              'UuidShift': shift.Uuid,
              'UuidPengguna': kasir.uuid,
              'Nomor': nomor,
              'DibuatPada': sekarang.toIso8601String(),
            },
          ),
        );
      },
    );
    return IsiDepositTersimpan(
      uuid: uuid,
      nomor: nomor,
      jumlah: jumlah,
      namaPelanggan: pelanggan.nama,
      namaKasir: kasir.nama,
      namaMetode: metode.Nama,
      tunai: metode.Jenis == JenisMetodeBayar.tunai,
      dibuatPada: sekarang,
      referensi: ref,
      saldoSebelum: saldoSebelum,
    );
  }
}
