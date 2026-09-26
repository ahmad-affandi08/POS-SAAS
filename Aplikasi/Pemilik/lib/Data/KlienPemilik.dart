import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;
import 'package:klien_api/KlienApi.dart';

/// Tenant yang boleh diakses pengguna di Aplikasi Owner.
class TenantPemilik {
  const TenantPemilik({required this.uuid, required this.nama, required this.pemilik});

  final String uuid;
  final String nama;
  final bool pemilik;

  static TenantPemilik DariJson(Map<String, Object?> json) => TenantPemilik(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    nama: UraiJson.AmbilTeks(json['Nama']),
    pemilik: UraiJson.AmbilBenar(json['Pemilik']),
  );
}

/// Hasil masuk: token + tenant, atau tantangan dua faktor.
class HasilMasukPemilik {
  const HasilMasukPemilik({this.token, this.namaPengguna = '', this.tenant = const [], this.tokenTantangan});

  final String? token;
  final String namaPengguna;
  final List<TenantPemilik> tenant;

  /// Terisi bila akun memakai 2FA: kirim kode lewat [KlienPemilik.MasukDuaFaktor].
  final String? tokenTantangan;

  bool get perluDuaFaktor => tokenTantangan != null;

  static HasilMasukPemilik DariJson(Map<String, Object?> json) => HasilMasukPemilik(
    token: UraiJson.AmbilTeksAtauNull(json['Token']),
    namaPengguna: UraiJson.AmbilTeks(UraiJson.AmbilPeta(json['Pengguna'])['Nama']),
    tenant: [for (final t in UraiJson.AmbilDaftarPeta(json['Tenant'])) TenantPemilik.DariJson(t)],
    tokenTantangan: UraiJson.AmbilBenar(json['PerluDuaFaktor'])
        ? UraiJson.AmbilTeksAtauNull(json['TokenTantangan'])
        : null,
  );
}

class OutletRingkas {
  const OutletRingkas({required this.uuid, required this.nama});

  final String uuid;
  final String nama;

  static OutletRingkas DariJson(Map<String, Object?> json) =>
      OutletRingkas(uuid: UraiJson.AmbilTeks(json['Uuid']), nama: UraiJson.AmbilTeks(json['Nama']));
}

/// Satu baris nilai (per outlet, per produk, per jam, dll.). Uang & jumlah berupa string desimal.
class BarisNilai {
  const BarisNilai({required this.nama, required this.omzet, this.jumlah, this.transaksi});

  final String nama;
  final String omzet;
  final String? jumlah;
  final int? transaksi;

  static BarisNilai DariJson(Map<String, Object?> json, {String kunciNama = 'Nama'}) => BarisNilai(
    nama: json[kunciNama] is int ? '${json[kunciNama]}' : UraiJson.AmbilTeks(json[kunciNama]),
    omzet: UraiJson.AmbilDesimal(json['Omzet']),
    jumlah: UraiJson.AmbilDesimalAtauNull(json['Jumlah']),
    transaksi: UraiJson.AmbilBulatAtauNull(json['Transaksi']),
  );
}

class HalPerluTindakan {
  const HalPerluTindakan({required this.jenis, required this.judul, required this.keterangan});

  final String jenis;
  final String judul;
  final String keterangan;

  static HalPerluTindakan DariJson(Map<String, Object?> json) => HalPerluTindakan(
    jenis: UraiJson.AmbilTeks(json['Jenis']),
    judul: UraiJson.AmbilTeks(json['Judul']),
    keterangan: UraiJson.AmbilTeks(json['Keterangan']),
  );
}

/// Dasbor OWN-02: satu angka besar (omzet) + perbandingan, lalu hal yang butuh tindakan.
class DasborPemilik {
  const DasborPemilik({
    required this.tanggal,
    required this.outlet,
    required this.omzet,
    required this.labaKotor,
    required this.transaksi,
    required this.rataRata,
    required this.omzetKemarin,
    required this.omzetMingguLalu,
    required this.perOutlet,
    required this.perJam,
    required this.produkTeratas,
    required this.perluTindakan,
  });

  final String tanggal;
  final List<OutletRingkas> outlet;
  final String omzet;

  /// Null bila pengguna tidak berizin melihat laporan keuangan.
  final String? labaKotor;
  final int transaksi;
  final String rataRata;
  final String omzetKemarin;
  final String omzetMingguLalu;
  final List<BarisNilai> perOutlet;
  final List<BarisNilai> perJam;
  final List<BarisNilai> produkTeratas;
  final List<HalPerluTindakan> perluTindakan;

  static DasborPemilik DariJson(Map<String, Object?> json) {
    final r = UraiJson.AmbilPeta(json['Ringkasan']);
    return DasborPemilik(
      tanggal: UraiJson.AmbilTeks(json['Tanggal']),
      outlet: [for (final o in UraiJson.AmbilDaftarPeta(json['Outlet'])) OutletRingkas.DariJson(o)],
      omzet: UraiJson.AmbilDesimal(r['Omzet']),
      labaKotor: UraiJson.AmbilDesimalAtauNull(r['LabaKotor']),
      transaksi: UraiJson.AmbilBulat(r['Transaksi']),
      rataRata: UraiJson.AmbilDesimal(r['RataRata']),
      omzetKemarin: UraiJson.AmbilDesimal(r['OmzetKemarin']),
      omzetMingguLalu: UraiJson.AmbilDesimal(r['OmzetMingguLalu']),
      perOutlet: [for (final o in UraiJson.AmbilDaftarPeta(json['PerOutlet'])) BarisNilai.DariJson(o)],
      perJam: [for (final j in UraiJson.AmbilDaftarPeta(json['PerJam'])) BarisNilai.DariJson(j, kunciNama: 'Jam')],
      produkTeratas: [for (final p in UraiJson.AmbilDaftarPeta(json['ProdukTeratas'])) BarisNilai.DariJson(p)],
      perluTindakan: [for (final h in UraiJson.AmbilDaftarPeta(json['PerluTindakan'])) HalPerluTindakan.DariJson(h)],
    );
  }
}

class LaporanPenjualanPemilik {
  const LaporanPenjualanPemilik({required this.kelompok, required this.baris, required this.totalOmzet});

  final String kelompok;
  final List<BarisNilai> baris;
  final String totalOmzet;

  static LaporanPenjualanPemilik DariJson(Map<String, Object?> json) => LaporanPenjualanPemilik(
    kelompok: UraiJson.AmbilTeks(json['Kelompok']),
    baris: [for (final b in UraiJson.AmbilDaftarPeta(json['Baris'])) BarisNilai.DariJson(b)],
    totalOmzet: UraiJson.AmbilDesimal(UraiJson.AmbilPeta(json['Total'])['Omzet']),
  );
}

class ShiftPemilik {
  const ShiftPemilik({
    required this.uuid,
    required this.outlet,
    required this.kasir,
    required this.dibukaPada,
    required this.ditutupPada,
    required this.status,
    required this.selisih,
  });

  final String uuid;
  final String outlet;
  final String kasir;
  final DateTime? dibukaPada;
  final DateTime? ditutupPada;
  final String status;

  /// Null selama shift belum ditutup.
  final String? selisih;

  static ShiftPemilik DariJson(Map<String, Object?> json) => ShiftPemilik(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    outlet: UraiJson.AmbilTeks(json['Outlet']),
    kasir: UraiJson.AmbilTeks(json['Kasir']),
    dibukaPada: DateTime.tryParse(UraiJson.AmbilTeks(json['DibukaPada']))?.toLocal(),
    ditutupPada: DateTime.tryParse(UraiJson.AmbilTeks(json['DitutupPada']))?.toLocal(),
    status: UraiJson.AmbilTeks(json['Status']),
    selisih: UraiJson.AmbilDesimalAtauNull(json['Selisih']),
  );
}

class PerangkatPemilik {
  const PerangkatPemilik({
    required this.uuid,
    required this.kode,
    required this.nama,
    required this.jenis,
    required this.outlet,
    required this.status,
    required this.terakhirAktifPada,
    required this.outboxTertunda,
    required this.versiAplikasi,
  });

  final String uuid;
  final String kode;
  final String nama;
  final String jenis;
  final String outlet;
  final String status;
  final DateTime? terakhirAktifPada;
  final int outboxTertunda;
  final String? versiAplikasi;

  static PerangkatPemilik DariJson(Map<String, Object?> json) => PerangkatPemilik(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    kode: UraiJson.AmbilTeks(json['Kode']),
    nama: UraiJson.AmbilTeks(json['Nama']),
    jenis: UraiJson.AmbilTeks(json['Jenis']),
    outlet: UraiJson.AmbilTeks(json['Outlet']),
    status: UraiJson.AmbilTeks(json['Status']),
    terakhirAktifPada: DateTime.tryParse(UraiJson.AmbilTeks(json['TerakhirAktifPada']))?.toLocal(),
    outboxTertunda: UraiJson.AmbilBulat(json['JumlahOutboxTertunda']),
    versiAplikasi: UraiJson.AmbilTeksAtauNull(json['VersiAplikasi']),
  );
}

/// Klien `/api/pemilik/v1` (PRD §16, OWN-01..08) dengan user token Sanctum + header `X-Tenant` (tenant aktif).
/// Galat seragam `GalatApi`/`GalatJaringan` dari `klien_api`. 401 = token kedaluwarsa/dicabut → masuk ulang.
class KlienPemilik {
  KlienPemilik({
    required this.alamatDasar,
    required this.versiAplikasi,
    required this.ambilToken,
    required this.ambilTenant,
    http.Client? klien,
    this.batasWaktu = const Duration(seconds: 20),
  }) : _klien = klien ?? http.Client();

  final Uri alamatDasar;
  final String versiAplikasi;
  final FutureOr<String?> Function() ambilToken;
  final FutureOr<String?> Function() ambilTenant;
  final Duration batasWaktu;
  final http.Client _klien;

  Future<HasilMasukPemilik> Masuk({
    required String email,
    required String kataSandi,
    required String namaPerangkat,
  }) async => HasilMasukPemilik.DariJson(
    await _Kirim('POST', 'masuk', {
      'Email': email.trim(),
      'KataSandi': kataSandi,
      'NamaPerangkat': namaPerangkat,
    }, pakaiToken: false),
  );

  Future<HasilMasukPemilik> MasukDuaFaktor({
    required String tokenTantangan,
    required String kode,
    required String namaPerangkat,
  }) async => HasilMasukPemilik.DariJson(
    await _Kirim('POST', 'masuk/dua-faktor', {
      'TokenTantangan': tokenTantangan,
      'Kode': kode.replaceAll(' ', ''),
      'NamaPerangkat': namaPerangkat,
    }, pakaiToken: false),
  );

  Future<void> Keluar() => _Kirim('POST', 'keluar', null);

  Future<HasilMasukPemilik> AmbilProfil() async => HasilMasukPemilik.DariJson(await _Kirim('GET', 'profil', null));

  Future<DasborPemilik> AmbilDasbor({required String tanggal, String? outlet}) async =>
      DasborPemilik.DariJson(await _Kirim('GET', _Jalur('dasbor', {'tanggal': tanggal, 'outlet': ?outlet}), null));

  Future<LaporanPenjualanPemilik> AmbilLaporanPenjualan({
    required String dari,
    required String sampai,
    required String kelompok,
    String? outlet,
  }) async => LaporanPenjualanPemilik.DariJson(
    await _Kirim(
      'GET',
      _Jalur('laporan/penjualan', {'dari': dari, 'sampai': sampai, 'kelompok': kelompok, 'outlet': ?outlet}),
      null,
    ),
  );

  Future<List<ShiftPemilik>> AmbilShift({required String tanggal, String? outlet}) async {
    final json = await _Kirim('GET', _Jalur('shift', {'tanggal': tanggal, 'outlet': ?outlet}), null);
    return [for (final s in UraiJson.AmbilDaftarPeta(json['Shift'])) ShiftPemilik.DariJson(s)];
  }

  Future<List<PerangkatPemilik>> AmbilPerangkat() async {
    final json = await _Kirim('GET', 'perangkat', null);
    return [for (final p in UraiJson.AmbilDaftarPeta(json['Perangkat'])) PerangkatPemilik.DariJson(p)];
  }

  static String _Jalur(String jalur, Map<String, String> kueri) =>
      kueri.isEmpty ? jalur : '$jalur?${Uri(queryParameters: kueri).query}';

  Future<Map<String, Object?>> _Kirim(
    String metode,
    String jalur,
    Map<String, Object?>? isi, {
    bool pakaiToken = true,
  }) async {
    final permintaan = http.Request(metode, alamatDasar.resolve('api/pemilik/v1/$jalur'))
      ..headers.addAll({'Accept': 'application/json', 'X-Versi-Aplikasi': versiAplikasi});
    if (pakaiToken) {
      final token = await ambilToken();
      if (token != null && token.isNotEmpty) {
        permintaan.headers['Authorization'] = 'Bearer $token';
      }
      final tenant = await ambilTenant();
      if (tenant != null && tenant.isNotEmpty) {
        permintaan.headers['X-Tenant'] = tenant;
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
      throw GalatJaringan('Server sedang bermasalah (${respons.statusCode}). Coba lagi sebentar lagi.');
    }

    final json = _Urai(respons.body);
    if (respons.statusCode >= 400) {
      final galat = UraiJson.AmbilPetaAtauNull(json['Galat']);
      final kesalahan = UraiJson.AmbilPetaAtauNull(json['errors']);
      throw GalatApi(
        kode: galat != null
            ? UraiJson.AmbilTeks(galat['Kode'], 'GalatServer')
            : kesalahan != null
            ? 'DataTidakValid'
            : respons.statusCode == 401
            ? 'SesiBerakhir'
            : 'GalatServer',
        pesan: galat != null
            ? UraiJson.AmbilTeks(galat['Pesan'], 'Permintaan ditolak server.')
            : kesalahan != null && kesalahan.values.first is List && (kesalahan.values.first! as List).isNotEmpty
            ? '${(kesalahan.values.first! as List).first}'
            : respons.statusCode == 401
            ? 'Sesi berakhir. Masuk lagi.'
            : 'Permintaan ditolak server (${respons.statusCode}).',
        statusHttp: respons.statusCode,
        bidang: kesalahan?.keys.first,
      );
    }
    return json;
  }

  static Map<String, Object?> _Urai(String isi) {
    if (isi.isEmpty || !isi.trimLeft().startsWith('{')) {
      return const {};
    }
    try {
      final hasil = jsonDecode(isi);
      return hasil is Map<String, Object?> ? hasil : const {};
    } on FormatException {
      return const {};
    }
  }
}
