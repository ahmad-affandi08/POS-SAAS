import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:http/http.dart' as http;

import 'Galat/GalatApi.dart';
import 'Model/ModelKatalog.dart';
import 'Model/ModelKonfigurasi.dart';
import 'Model/ModelMeja.dart';
import 'Model/ModelPelanggan.dart';
import 'Model/ModelPos.dart';
import 'Model/ModelPreOrder.dart';
import 'Model/ModelPromo.dart';
import 'Model/ModelRetur.dart';
import 'Model/UraiJson.dart';

/// Klien `/api/pos/v1` (PRD §16.1, §16.3) dengan device token (`Authorization: Bearer`), `X-Versi-Aplikasi`, dan
/// `X-Outbox-Tertunda` (P-10 BR-P10.2: jumlah transaksi belum terkirim, bila [ambilJumlahOutbox] diberikan).
/// Galat server → `GalatApi`; server tak terjangkau, waktu habis, atau 5xx → `GalatJaringan` (aman dicoba lagi).
class KlienPos {
  KlienPos({
    required this.alamatDasar,
    required this.versiAplikasi,
    required this.ambilToken,
    http.Client? klien,
    this.batasWaktu = const Duration(seconds: 20),
    this.ambilJumlahOutbox,
  }) : _klien = klien ?? http.Client();

  final Uri alamatDasar;
  final String versiAplikasi;
  final FutureOr<String?> Function() ambilToken;
  final Duration batasWaktu;
  final FutureOr<int?> Function()? ambilJumlahOutbox;
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

  /// Versi terbaru/minimal, catatan rilis, dan flag fitur (§14.6, P-10).
  Future<KonfigurasiAplikasi> AmbilKonfigurasiAplikasi() async =>
      KonfigurasiAplikasi.DariJson(await _Kirim('GET', 'konfigurasi-aplikasi', null));

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

  /// Logo usaha untuk kepala struk (PRD v1.79). Tanpa logo/logo dimatikan → null (404).
  Future<Uint8List?> AmbilLogoStruk() async {
    final respons = await _KirimMentah('GET', 'logo-struk', null, terima: 'image/*');
    if (respons.statusCode == 404) {
      return null;
    }
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

  /// Cari pelanggan aktif tenant (F-16a): nama atau nomor HP, minimal 3 karakter (kurang = daftar kosong tanpa
  /// permintaan). Offline → `GalatJaringan`.
  Future<List<PelangganPos>> CariPelanggan(String kata) async {
    final rapi = kata.trim();
    if (rapi.length < 3) {
      return const [];
    }
    final json = await _Kirim('GET', 'pelanggan?kata=${Uri.encodeQueryComponent(rapi)}', null);
    // F-16c bagian 3: tanggal bisnis yang menjadi acuan hitungan harian `PemakaianPromo` (server lama: tidak ada).
    final tanggalBisnis = UraiJson.AmbilTeksAtauNull(json['TanggalBisnis']);
    return UraiJson.AmbilDaftarPeta(json['Pelanggan'])
        .map((p) => PelangganPos.DariJson(p, tanggalBisnis: tanggalBisnis))
        .toList();
  }

  /// Saldo poin terkini & aturan tukar sebelum kasir menukar poin (F-16b). Pelanggan tidak ada/diarsipkan → `GalatApi`
  /// ber-kode `PelangganTidakDitemukan` (404); offline → `GalatJaringan`.
  Future<SaldoPoinPos> AmbilSaldoPoin(String uuidPelanggan) async =>
      SaldoPoinPos.DariJson(await _Kirim('GET', 'pelanggan/${Uri.encodeComponent(uuidPelanggan)}/poin', null));

  /// Promo aktif tenant + mode resolusi konflik (F-16c); disimpan perangkat agar promo tetap berlaku saat offline.
  /// `?voucher=1`: aplikasi ini mengenal syarat `WajibVoucher` (F-16c bagian 2), jadi promo voucher ikut dikirim.
  /// `&lanjutan=1`: aplikasi ini juga mengenal syarat bagian 3 (metode bayar, ulang tahun, transaksi pertama, batas per
  /// pelanggan).
  Future<DataPromoPos> AmbilPromo() async =>
      DataPromoPos.DariJson(await _Kirim('GET', 'promo?voucher=1&lanjutan=1', null));

  /// Cari pre-order yang siap diambil di outlet perangkat (F-12 bagian 2): nomor atau nama/nomor HP pelanggan, minimal
  /// 3 karakter. Offline → `GalatJaringan`.
  Future<HasilCariPesananPenjualan> CariPesananPenjualan(String kata) async => HasilCariPesananPenjualan.DariJson(
    await _Kirim('GET', 'pesanan-penjualan?kata=${Uri.encodeQueryComponent(kata.trim())}', null),
  );

  /// Periksa & pesan kode voucher untuk penjualan [uuidPenjualan] yang sedang dibuat (F-16c bagian 2, wajib online).
  /// Ditolak → `GalatApi` ber-kode `VoucherTidakDitemukan` (404), `VoucherHabis` (409), `VoucherNonaktif`,
  /// `VoucherKedaluwarsa`, atau `PromoTidakBerlaku` (422); offline → `GalatJaringan`.
  Future<VoucherPos> PesanVoucher(String kode, String uuidPenjualan) async =>
      VoucherPos.DariJson(await _Kirim('POST', 'voucher/pesan', {'Kode': kode, 'UuidPenjualan': uuidPenjualan}));

  /// Lepas pesanan voucher (kasir menghapus voucher atau membatalkan transaksi). Idempoten.
  Future<void> LepasVoucher(String kode, String uuidPenjualan) async {
    await _Kirim('POST', 'voucher/lepas', {'Kode': kode, 'UuidPenjualan': uuidPenjualan});
  }

  /// Data meja outlet perangkat (F-07 mode meja fase 1).
  Future<DataMejaPos> AmbilMeja() async => DataMejaPos.DariJson(await _Kirim('GET', 'meja', null));

  /// Snapshot pesanan terbuka outlet. [etag] sama dengan server → `null` (tidak berubah, 304).
  Future<SnapshotPesananTerbuka?> AmbilPesananTerbuka({String? etag}) async {
    final respons = await _KirimMentah('GET', 'pesanan-terbuka', null, header: {'If-None-Match': ?etag});
    if (respons.statusCode == 304) {
      return null;
    }
    if (respons.statusCode >= 400) {
      throw _BuatGalat(respons.statusCode, _UraiJson(respons.body));
    }
    return SnapshotPesananTerbuka.DariJson(_UraiJson(respons.body), respons.headers['etag']);
  }

  /// Kunci bayar online (berlaku sebentar, diperpanjang saat layar Bayar terbuka). Perangkat lain sedang membayar →
  /// `GalatApi` ber-kode `PesananSedangDibayar` (409).
  Future<DateTime?> KunciBayar(String uuidPesanan) async {
    final json = await _Kirim('POST', 'pesanan-terbuka/${Uri.encodeComponent(uuidPesanan)}/kunci-bayar', null);
    return DateTime.tryParse(UraiJson.AmbilTeks(json['KunciBayarSampai']));
  }

  Future<void> LepasKunciBayar(String uuidPesanan) async {
    await _Kirim('DELETE', 'pesanan-terbuka/${Uri.encodeComponent(uuidPesanan)}/kunci-bayar', null);
  }

  /// Tiket dapur aktif outlet untuk KDS; [stasiun] kosong/null = semua stasiun.
  Future<DaftarTiketDapur> AmbilTiketDapur({List<String> stasiun = const []}) async {
    final kueri = stasiun.map((s) => 'stasiun[]=${Uri.encodeQueryComponent(s)}').join('&');
    return DaftarTiketDapur.DariJson(await _Kirim('GET', kueri.isEmpty ? 'dapur/tiket' : 'dapur/tiket?$kueri', null));
  }

  /// Ubah status tiket satu langkah (Antre → Dimasak → Siap → Disajikan, atau mundur satu langkah).
  Future<String> UbahStatusTiket(String uuidTiket, String status) async {
    final json = await _Kirim('POST', 'dapur/tiket/${Uri.encodeComponent(uuidTiket)}/status', {'Status': status});
    return UraiJson.AmbilTeks(json['Status'], status);
  }

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
    Map<String, String> header = const {},
  }) async {
    final alamat = alamatDasar.resolve('api/pos/v1/$jalur');
    final permintaan = http.Request(metode, alamat)
      ..headers.addAll({'Accept': terima, 'X-Versi-Aplikasi': versiAplikasi, ...header});

    if (pakaiToken) {
      final token = await ambilToken();
      if (token != null && token.isNotEmpty) {
        permintaan.headers['Authorization'] = 'Bearer $token';
      }
      final outbox = await ambilJumlahOutbox?.call();
      if (outbox != null) {
        permintaan.headers['X-Outbox-Tertunda'] = '$outbox';
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
