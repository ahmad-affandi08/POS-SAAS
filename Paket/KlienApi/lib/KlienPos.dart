import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:http/http.dart' as http;

import 'Galat/GalatApi.dart';
import 'Model/ModelKatalog.dart';
import 'Model/ModelPos.dart';
import 'Model/ModelRetur.dart';

/// Klien `/api/pos/v1` (PRD §16.1, §16.3) dengan device token (`Authorization: Bearer`) dan `X-Versi-Aplikasi`.
/// Galat server → `GalatApi`; server tak terjangkau, waktu habis, atau 5xx → `GalatJaringan` (aman dicoba lagi).
class KlienPos {
  KlienPos({
    required this.alamatDasar,
    required this.versiAplikasi,
    required this.ambilToken,
    http.Client? klien,
    this.batasWaktu = const Duration(seconds: 20),
  }) : _klien = klien ?? http.Client();

  final Uri alamatDasar;
  final String versiAplikasi;
  final FutureOr<String?> Function() ambilToken;
  final Duration batasWaktu;
  final http.Client _klien;

  Future<HasilAktivasi> AktifkanPerangkat({required String kode, required String platform, String? versiOs}) async {
    final json = await _Kirim('POST', 'perangkat/aktivasi', {
      'Kode': kode.trim().toUpperCase(),
      'Platform': platform,
      'VersiAplikasi': versiAplikasi,
      'VersiOs': versiOs,
    }, pakaiToken: false);
    return HasilAktivasi.DariJson(json);
  }

  Future<DataAwal> AmbilDataAwal() async => DataAwal.DariJson(await _Kirim('GET', 'data-awal', null));

  Future<HasilMasukPin> MasukPin({required String uuidPengguna, required String pin}) async =>
      HasilMasukPin.DariJson(await _Kirim('POST', 'kasir/masuk-pin', {'UuidPengguna': uuidPengguna, 'Pin': pin}));

  /// Katalog lengkap (tanpa [kursor]) atau delta sejak [kursor] (F-03 D.3). Kursor rusak/kedaluwarsa → `GalatApi`
  /// ber-kode `KursorTidakValid` (pemanggil lalu mengulang tanpa kursor).
  Future<KatalogPos> AmbilKatalog({String? kursor}) async => KatalogPos.DariJson(
    await _Kirim(
      'GET',
      kursor == null || kursor.isEmpty ? 'katalog' : 'katalog?sejak=${Uri.encodeQueryComponent(kursor)}',
      null,
    ),
  );

  /// Gambar QRIS statis metode pembayaran (F-07b) sebagai bait gambar (PNG/JPEG) untuk ditampilkan di layar Bayar.
  Future<Uint8List> AmbilGambarQris(String uuidMetode) async {
    final respons = await _KirimMentah(
      'GET',
      'metode-pembayaran/${Uri.encodeComponent(uuidMetode)}/gambar-qris',
      null,
      terima: 'image/*',
    );
    if (respons.statusCode >= 400) {
      throw _BuatGalat(respons.statusCode, _UraiJson(respons.body));
    }
    return respons.bodyBytes;
  }

  /// Struk asal untuk retur (F-09 fase 1): penjualan outlet perangkat dengan [nomor] persis, beserta jumlah & nilai yang
  /// masih bisa diretur. Tidak ada → `GalatApi` ber-kode `PenjualanTidakDitemukan` (404); offline → `GalatJaringan`.
  Future<HasilCariPenjualan> CariPenjualan(String nomor) async => HasilCariPenjualan.DariJson(
    await _Kirim('GET', 'penjualan/cari?nomor=${Uri.encodeQueryComponent(nomor.trim())}', null),
  );

  /// Kirim batch outbox (maks. 50) dan kembalikan hasil per item dalam urutan yang sama.
  Future<List<HasilItemSinkron>> KirimSinkron(List<ItemOutbox> item) async {
    final json = await _Kirim('POST', 'sinkron/kirim', {'Item': item.map((i) => i.toJson()).toList()});
    final hasil = json['Hasil'];
    return hasil is List<Object?>
        ? hasil.whereType<Map<String, Object?>>().map(HasilItemSinkron.DariJson).toList()
        : const <HasilItemSinkron>[];
  }

  Future<Map<String, Object?>> _Kirim(
    String metode,
    String jalur,
    Map<String, Object?>? isi, {
    bool pakaiToken = true,
  }) async {
    final respons = await _KirimMentah(metode, jalur, isi, pakaiToken: pakaiToken);
    final json = _UraiJson(respons.body);

    if (respons.statusCode >= 400) {
      throw _BuatGalat(respons.statusCode, json);
    }

    return json;
  }

  /// Kirim permintaan dan kembalikan respons apa adanya; gagal jaringan & 5xx → `GalatJaringan`.
  Future<http.Response> _KirimMentah(
    String metode,
    String jalur,
    Map<String, Object?>? isi, {
    bool pakaiToken = true,
    String terima = 'application/json',
  }) async {
    final alamat = alamatDasar.resolve('api/pos/v1/$jalur');
    final permintaan = http.Request(metode, alamat)
      ..headers.addAll({'Accept': terima, 'X-Versi-Aplikasi': versiAplikasi});

    if (pakaiToken) {
      final token = await ambilToken();
      if (token != null && token.isNotEmpty) {
        permintaan.headers['Authorization'] = 'Bearer $token';
      }
    }

    if (isi != null) {
      permintaan.headers['Content-Type'] = 'application/json';
      permintaan.body = jsonEncode(isi);
    }

    final http.Response respons;
    try {
      respons = await http.Response.fromStream(await _klien.send(permintaan).timeout(batasWaktu));
    } on TimeoutException {
      throw const GalatJaringan('Server tidak menjawab. Periksa koneksi internet.');
    } on SocketException {
      throw const GalatJaringan('Tidak ada koneksi ke server.');
    } on http.ClientException catch (galat) {
      throw GalatJaringan(galat.message);
    }

    if (respons.statusCode >= 500) {
      throw GalatJaringan('Server sedang bermasalah (${respons.statusCode}). Data akan dikirim ulang otomatis.');
    }

    return respons;
  }

  static Map<String, Object?> _UraiJson(String isi) {
    if (isi.isEmpty || !isi.trimLeft().startsWith('{')) {
      return const <String, Object?>{};
    }
    try {
      final hasil = jsonDecode(isi);
      return hasil is Map<String, Object?> ? hasil : const <String, Object?>{};
    } on FormatException {
      return const <String, Object?>{};
    }
  }

  static GalatApi _BuatGalat(int status, Map<String, Object?> json) {
    final galat = json['Galat'];
    if (galat is Map<String, Object?>) {
      final detail = galat['Detail'];
      return GalatApi(
        kode: galat['Kode'] is String ? galat['Kode']! as String : 'GalatServer',
        pesan: galat['Pesan'] is String ? galat['Pesan']! as String : 'Permintaan ditolak server.',
        statusHttp: status,
        bidang: galat['Bidang'] is String ? galat['Bidang']! as String : null,
        detail: detail is Map<String, Object?> ? detail : const <String, Object?>{},
      );
    }

    final kesalahan = json['errors'];
    if (kesalahan is Map<String, Object?> && kesalahan.isNotEmpty) {
      final pertama = kesalahan.entries.first;
      final pesan = pertama.value is List<Object?> && (pertama.value! as List<Object?>).isNotEmpty
          ? '${(pertama.value! as List<Object?>).first}'
          : 'Data tidak valid.';
      return GalatApi(kode: 'DataTidakValid', pesan: pesan, statusHttp: status, bidang: pertama.key);
    }

    return GalatApi(
      kode: status == 401 ? 'TokenPerangkatTidakValid' : 'GalatServer',
      pesan: json['message'] is String ? json['message']! as String : 'Permintaan ditolak server ($status).',
      statusHttp: status,
    );
  }
}
