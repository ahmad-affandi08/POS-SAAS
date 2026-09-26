import 'UraiJson.dart';

/// Tagihan QRIS dinamis dari gerbang pembayaran aktif (F-08, v2.05). [isiQr] = string QRIS untuk digambar sebagai QR;
/// bila [halamanBayar] true isinya URL halaman bayar penyedia (misal DOKU Checkout).
class TagihanQrisPos {
  const TagihanQrisPos({
    required this.uuid,
    required this.nomorPesanan,
    required this.isiQr,
    required this.halamanBayar,
    required this.kedaluwarsaPada,
    required this.status,
    required this.jumlah,
  });

  static const String menunggu = 'Menunggu';
  static const String lunas = 'Lunas';
  static const String kedaluwarsa = 'Kedaluwarsa';
  static const String gagal = 'Gagal';
  static const String dibatalkan = 'Dibatalkan';

  final String uuid;
  final String nomorPesanan;
  final String isiQr;
  final bool halamanBayar;
  final DateTime? kedaluwarsaPada;
  final String status;
  final String jumlah;

  static TagihanQrisPos DariJson(Map<String, Object?> json) => TagihanQrisPos(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    nomorPesanan: UraiJson.AmbilTeks(json['NomorPesanan']),
    isiQr: UraiJson.AmbilTeks(json['IsiQr']),
    halamanBayar: UraiJson.AmbilBenar(json['HalamanBayar']),
    kedaluwarsaPada: DateTime.tryParse(UraiJson.AmbilTeks(json['KedaluwarsaPada']))?.toUtc(),
    status: UraiJson.AmbilTeks(json['Status'], menunggu),
    jumlah: UraiJson.AmbilDesimal(json['Jumlah']),
  );
}

/// Status tagihan QRIS dinamis (polling kasir).
class StatusQrisPos {
  const StatusQrisPos({required this.uuid, required this.status, required this.lunasPada});

  final String uuid;
  final String status;
  final DateTime? lunasPada;

  bool get lunas => status == TagihanQrisPos.lunas;

  /// Tagihan tidak bisa dibayar lagi (kedaluwarsa, gagal, dibatalkan).
  bool get selesaiTanpaBayar =>
      status == TagihanQrisPos.kedaluwarsa || status == TagihanQrisPos.gagal || status == TagihanQrisPos.dibatalkan;

  static StatusQrisPos DariJson(Map<String, Object?> json) => StatusQrisPos(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    status: UraiJson.AmbilTeks(json['Status'], TagihanQrisPos.menunggu),
    lunasPada: DateTime.tryParse(UraiJson.AmbilTeks(json['LunasPada']))?.toUtc(),
  );
}

/// Pesan keluar (struk digital lewat WhatsApp/email, v2.05).
class PesanKeluarPos {
  const PesanKeluarPos({required this.uuid, required this.status, required this.pesanGalat});

  static const String diantrekan = 'Diantrekan';
  static const String terkirim = 'Terkirim';
  static const String gagal = 'Gagal';

  final String uuid;
  final String status;
  final String? pesanGalat;

  static PesanKeluarPos DariJson(Map<String, Object?> json) => PesanKeluarPos(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    status: UraiJson.AmbilTeks(json['Status'], diantrekan),
    pesanGalat: UraiJson.AmbilTeksAtauNull(json['PesanGalat']),
  );
}
