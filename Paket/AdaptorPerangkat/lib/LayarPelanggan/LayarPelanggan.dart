import 'dart:convert';

import '../Printer/TransportPrinter.dart';

/// Satu baris di layar pelanggan: nama item (atau label ringkasan), rincian opsional, dan nilai rupiah yang sudah
/// diformat aplikasi.
class BarisLayarPelanggan {
  const BarisLayarPelanggan({required this.nama, required this.nilai, this.rincian});

  final String nama;
  final String? rincian;
  final String nilai;

  Map<String, Object?> KeJson() => {'Nama': nama, 'Rincian': rincian, 'Nilai': nilai};
}

/// Keadaan layar pelanggan (PRD §17.2.5a `PortLayar`, v2.01).
enum KeadaanLayarPelanggan { Siaga, Keranjang, Bayar, Selesai }

/// Isi layar pelanggan yang siap tampil (teks sudah diformat; adaptor tidak menghitung apa pun). Dipakai adaptor
/// layar kedua (Android *presentation display*) dan layar VFD 2×20 karakter.
class IsiLayarPelanggan {
  const IsiLayarPelanggan({
    required this.keadaan,
    required this.namaToko,
    this.baris = const [],
    this.ringkasan = const [],
    this.labelTotal = 'Total',
    this.total,
    this.pesan,
    this.dataQr,
  });

  const IsiLayarPelanggan.Siaga(String namaToko, {String pesan = 'Selamat datang'})
    : this(keadaan: KeadaanLayarPelanggan.Siaga, namaToko: namaToko, pesan: pesan);

  final KeadaanLayarPelanggan keadaan;
  final String namaToko;
  final List<BarisLayarPelanggan> baris;
  final List<BarisLayarPelanggan> ringkasan;
  final String labelTotal;
  final String? total;
  final String? pesan;

  /// Isi QR (mis. QRIS) yang ditampilkan untuk dipindai pelanggan; null = tanpa QR.
  final String? dataQr;

  Map<String, Object?> KeJson() => {
    'Keadaan': keadaan.name,
    'NamaToko': namaToko,
    'Baris': [for (final b in baris) b.KeJson()],
    'Ringkasan': [for (final r in ringkasan) r.KeJson()],
    'LabelTotal': labelTotal,
    'Total': total,
    'Pesan': pesan,
    'DataQr': dataQr,
  };

  /// Kunci pembanding agar adaptor tidak mengirim ulang isi yang sama.
  String AmbilSidik() => jsonEncode(KeJson());

  /// Dua baris untuk layar VFD (kolom = [lebar]): baris atas item terakhir / nama toko / pesan, baris bawah total.
  (String, String) SusunVfd({int lebar = 20}) {
    String Kiri(String teks) => _Potong(teks, lebar).padRight(lebar);
    String Rata(String kiri, String kanan) {
      final k = _Potong(kanan, lebar);
      final sisa = lebar - k.length - 1;
      return sisa <= 0 ? k.padLeft(lebar) : '${_Potong(kiri, sisa).padRight(sisa)} $k';
    }

    return switch (keadaan) {
      KeadaanLayarPelanggan.Siaga => (Kiri(pesan ?? ''), Kiri(namaToko)),
      KeadaanLayarPelanggan.Keranjang when baris.isNotEmpty => (
        Rata(baris.last.nama, baris.last.nilai),
        Rata(labelTotal, total ?? ''),
      ),
      KeadaanLayarPelanggan.Selesai => (Kiri(pesan ?? 'Terima kasih'), Rata(labelTotal, total ?? '')),
      _ => (Kiri(pesan ?? ''), Rata(labelTotal, total ?? '')),
    };
  }

  static String _Potong(String teks, int lebar) {
    // VFD hanya ASCII: ganti karakter non-ASCII yang umum lalu buang sisanya.
    final ascii = teks
        .replaceAll('×', 'x')
        .replaceAll('·', '-')
        .replaceAll('…', '...')
        .replaceAll(RegExp(r'[^\x20-\x7E]'), '');
    return ascii.length <= lebar ? ascii : ascii.substring(0, lebar);
  }
}

/// Layar pelanggan (PRD §17.2.5a `LayarPelanggan: PortLayar`).
abstract interface class PortLayarPelanggan {
  /// Nama untuk ditampilkan di pengaturan (mis. "Layar kedua (HDMI)").
  String get nama;

  Future<bool> CekTersedia();

  Future<void> Tampilkan(IsiLayarPelanggan isi);

  /// Matikan/lepaskan layar pelanggan.
  Future<void> Tutup();
}

/// Tanpa layar pelanggan (bawaan).
class LayarPelangganTidakAda implements PortLayarPelanggan {
  const LayarPelangganTidakAda();

  @override
  String get nama => 'Tidak ada';

  @override
  Future<bool> CekTersedia() async => false;

  @override
  Future<void> Tampilkan(IsiLayarPelanggan isi) async {}

  @override
  Future<void> Tutup() async {}
}

/// Pengode layar VFD pelanggan 2×20 (perintah CD5220, dipakai kebanyakan *pole display* di Indonesia): bersihkan layar,
/// tulis baris atas (`ESC Q A … CR`) dan baris bawah (`ESC Q B … CR`).
abstract final class PengodeVfd {
  static const int _esc = 0x1B;

  static List<int> Kodekan(String atas, String bawah) => [
    _esc, 0x40, // inisialisasi
    0x0C, // bersihkan layar
    _esc, 0x51, 0x41, ...ascii.encode(atas), 0x0D,
    _esc, 0x51, 0x42, ...ascii.encode(bawah), 0x0D,
  ];
}

/// Layar VFD pelanggan lewat transport byte (COM port Windows, USB, atau Bluetooth serial).
class LayarPelangganVfd implements PortLayarPelanggan {
  LayarPelangganVfd(this.transport, {this.nama = 'Layar VFD pelanggan', this.lebar = 20});

  final TransportPrinter transport;
  final int lebar;

  @override
  final String nama;

  String? _terakhir;

  @override
  Future<bool> CekTersedia() async => true;

  @override
  Future<void> Tampilkan(IsiLayarPelanggan isi) async {
    final (atas, bawah) = isi.SusunVfd(lebar: lebar);
    final sidik = '$atas|$bawah';
    if (sidik == _terakhir) {
      return;
    }
    await transport.Kirim(PengodeVfd.Kodekan(atas, bawah));
    _terakhir = sidik;
  }

  @override
  Future<void> Tutup() async {
    _terakhir = null;
    await transport.Kirim(const [0x1B, 0x40, 0x0C]);
  }
}
