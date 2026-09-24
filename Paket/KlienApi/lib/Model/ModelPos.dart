/// Model respons API POS (PRD §16.3). Key JSON = nama kolom PascalCase (§16.2); uang = string desimal.
library;

Map<String, Object?> _Peta(Object? nilai) => nilai is Map<String, Object?> ? nilai : const <String, Object?>{};

List<Object?> _Daftar(Object? nilai) => nilai is List<Object?> ? nilai : const <Object?>[];

String _Teks(Object? nilai) => nilai is String ? nilai : '';

String? _TeksAtauNull(Object? nilai) => nilai is String ? nilai : null;

bool _Benar(Object? nilai) => nilai == true;

int _Bulat(Object? nilai) => nilai is int ? nilai : 0;

/// `POST /perangkat/aktivasi`.
class HasilAktivasi {
  const HasilAktivasi({
    required this.tokenPerangkat,
    required this.kunciPinOffline,
    required this.uuidPerangkat,
    required this.kodePerangkat,
    required this.namaPerangkat,
    required this.uuidOutlet,
    required this.namaOutlet,
    required this.namaUsaha,
  });

  final String tokenPerangkat;
  final String? kunciPinOffline;
  final String uuidPerangkat;
  final String kodePerangkat;
  final String namaPerangkat;
  final String uuidOutlet;
  final String namaOutlet;
  final String namaUsaha;

  static HasilAktivasi DariJson(Map<String, Object?> json) {
    final perangkat = _Peta(json['Perangkat']);
    final outlet = _Peta(json['Outlet']);
    return HasilAktivasi(
      tokenPerangkat: _Teks(json['TokenPerangkat']),
      kunciPinOffline: _TeksAtauNull(json['KunciPinOffline']),
      uuidPerangkat: _Teks(perangkat['Uuid']),
      kodePerangkat: _Teks(perangkat['Kode']),
      namaPerangkat: _Teks(perangkat['Nama']),
      uuidOutlet: _Teks(outlet['Uuid']),
      namaOutlet: _Teks(outlet['Nama']),
      namaUsaha: _Teks(_Peta(json['Tenant'])['Nama']),
    );
  }
}

/// Verifier PIN offline yang dibungkus kunci perangkat (PRD §25.2 no. 3).
class PinTerbungkus {
  const PinTerbungkus({required this.garam, required this.nonce, required this.sandi});

  final String garam;
  final String nonce;
  final String sandi;

  static PinTerbungkus? DariJson(Object? json) {
    if (json is! Map<String, Object?>) {
      return null;
    }
    return PinTerbungkus(garam: _Teks(json['Garam']), nonce: _Teks(json['Nonce']), sandi: _Teks(json['Sandi']));
  }
}

class StafPos {
  const StafPos({
    required this.uuid,
    required this.nama,
    required this.pemilik,
    required this.izin,
    required this.pinDiatur,
    required this.pin,
  });

  final String uuid;
  final String nama;
  final bool pemilik;
  final List<String> izin;
  final bool pinDiatur;
  final PinTerbungkus? pin;

  bool PunyaIzin(String kunci) => pemilik || izin.contains(kunci);

  static StafPos DariJson(Map<String, Object?> json) => StafPos(
    uuid: _Teks(json['Uuid']),
    nama: _Teks(json['Nama']),
    pemilik: _Benar(json['Pemilik']),
    izin: _Daftar(json['Izin']).whereType<String>().toList(),
    pinDiatur: _Benar(json['PinDiatur']),
    pin: PinTerbungkus.DariJson(json['Pin']),
  );
}

class KategoriKasPos {
  const KategoriKasPos({required this.uuid, required this.nama, required this.jenis});

  final String uuid;
  final String nama;

  /// `Masuk` atau `Keluar`.
  final String jenis;

  static KategoriKasPos DariJson(Map<String, Object?> json) =>
      KategoriKasPos(uuid: _Teks(json['Uuid']), nama: _Teks(json['Nama']), jenis: _Teks(json['Jenis']));
}

class ParameterPin {
  const ParameterPin({
    required this.iterasi,
    required this.memoriKiB,
    required this.paralelisme,
    required this.panjang,
  });

  final int iterasi;
  final int memoriKiB;
  final int paralelisme;
  final int panjang;

  static ParameterPin DariJson(Map<String, Object?> json) => ParameterPin(
    iterasi: _Bulat(json['Iterasi']),
    memoriKiB: _Bulat(json['MemoriKiB']),
    paralelisme: _Bulat(json['Paralelisme']),
    panjang: _Bulat(json['Panjang']),
  );
}

/// `GET /data-awal` (F-06).
class DataAwal {
  const DataAwal({
    required this.batasKasKeluar,
    required this.shiftBersama,
    required this.kategoriKas,
    required this.staf,
    required this.pinOfflineTersedia,
    required this.parameterPin,
    required this.batasSalahPin,
    required this.menitKunciPin,
    required this.waktuServer,
  });

  final String batasKasKeluar;
  final bool shiftBersama;
  final List<KategoriKasPos> kategoriKas;
  final List<StafPos> staf;
  final bool pinOfflineTersedia;
  final ParameterPin parameterPin;
  final int batasSalahPin;
  final int menitKunciPin;
  final String waktuServer;

  static DataAwal DariJson(Map<String, Object?> json) {
    final pengaturan = _Peta(json['Pengaturan']);
    final pin = _Peta(json['PinOffline']);
    return DataAwal(
      batasKasKeluar: _Teks(pengaturan['BatasKasKeluar']),
      shiftBersama: _Benar(pengaturan['ShiftBersama']),
      kategoriKas: _Daftar(json['KategoriKas']).map((e) => KategoriKasPos.DariJson(_Peta(e))).toList(),
      staf: _Daftar(json['Staf']).map((e) => StafPos.DariJson(_Peta(e))).toList(),
      pinOfflineTersedia: _Benar(pin['Tersedia']),
      parameterPin: ParameterPin.DariJson(_Peta(pin['Parameter'])),
      batasSalahPin: _Bulat(pin['BatasSalah']),
      menitKunciPin: _Bulat(pin['MenitKunci']),
      waktuServer: _Teks(json['WaktuServer']),
    );
  }
}

/// `POST /kasir/masuk-pin`.
class HasilMasukPin {
  const HasilMasukPin({required this.uuid, required this.nama, required this.pemilik, required this.izin});

  final String uuid;
  final String nama;
  final bool pemilik;
  final List<String> izin;

  static HasilMasukPin DariJson(Map<String, Object?> json) {
    final pengguna = _Peta(json['Pengguna']);
    return HasilMasukPin(
      uuid: _Teks(pengguna['Uuid']),
      nama: _Teks(pengguna['Nama']),
      pemilik: _Benar(json['Pemilik']),
      izin: _Daftar(json['Izin']).whereType<String>().toList(),
    );
  }
}

/// Satu item outbox yang dikirim ke `POST /sinkron/kirim`.
class ItemOutbox {
  const ItemOutbox({required this.jenis, required this.uuid, required this.data});

  final String jenis;
  final String uuid;
  final Map<String, Object?> data;

  Map<String, Object?> toJson() => {'Jenis': jenis, 'Uuid': uuid, 'Data': data};
}

enum StatusItemSinkron { Diterima, Duplikat, Ditolak }

class HasilItemSinkron {
  const HasilItemSinkron({
    required this.uuid,
    required this.jenis,
    required this.status,
    this.kodeGalat,
    this.pesanGalat,
  });

  final String uuid;
  final String jenis;
  final StatusItemSinkron status;
  final String? kodeGalat;
  final String? pesanGalat;

  static HasilItemSinkron DariJson(Map<String, Object?> json) {
    final galat = _Peta(json['Galat']);
    return HasilItemSinkron(
      uuid: _Teks(json['Uuid']),
      jenis: _Teks(json['Jenis']),
      status: StatusItemSinkron.values.firstWhere(
        (s) => s.name == json['Status'],
        orElse: () => StatusItemSinkron.Ditolak,
      ),
      kodeGalat: _TeksAtauNull(galat['Kode']),
      pesanGalat: _TeksAtauNull(galat['Pesan']),
    );
  }
}
