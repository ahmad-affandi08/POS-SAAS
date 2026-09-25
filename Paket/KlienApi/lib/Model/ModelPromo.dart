import 'UraiJson.dart';

/// Satu promo aktif untuk POS (F-16c, `GET /api/pos/v1/promo`). [definisi] dibaca `DefinisiPromo.Urai` (MesinKasir).
class PromoPos {
  const PromoPos({
    required this.uuid,
    required this.kode,
    required this.nama,
    required this.prioritas,
    required this.eksklusif,
    required this.mulaiPada,
    required this.selesaiPada,
    required this.kuotaTersisa,
    required this.definisi,
  });

  final String uuid;
  final String kode;
  final String nama;
  final int prioritas;
  final bool eksklusif;
  final DateTime? mulaiPada;
  final DateTime? selesaiPada;
  final int? kuotaTersisa;
  final Map<String, Object?> definisi;

  static PromoPos DariJson(Map<String, Object?> json) => PromoPos(
    uuid: UraiJson.AmbilTeks(json['Uuid']),
    kode: UraiJson.AmbilTeks(json['Kode']),
    nama: UraiJson.AmbilTeks(json['Nama']),
    prioritas: UraiJson.AmbilBulat(json['Prioritas']),
    eksklusif: UraiJson.AmbilBenar(json['Eksklusif']),
    mulaiPada: DateTime.tryParse(UraiJson.AmbilTeks(json['MulaiPada'])),
    selesaiPada: DateTime.tryParse(UraiJson.AmbilTeks(json['SelesaiPada'])),
    kuotaTersisa: UraiJson.AmbilBulatAtauNull(json['KuotaTersisa']),
    definisi: UraiJson.AmbilPeta(json['Definisi']),
  );

  Map<String, Object?> KeJson() => {
    'Uuid': uuid,
    'Kode': kode,
    'Nama': nama,
    'Prioritas': prioritas,
    'Eksklusif': eksklusif,
    'MulaiPada': mulaiPada?.toUtc().toIso8601String(),
    'SelesaiPada': selesaiPada?.toUtc().toIso8601String(),
    'KuotaTersisa': kuotaTersisa,
    'Definisi': definisi,
  };
}

/// Promo aktif + mode resolusi konflik (`Terbaik`/`PrioritasKetat`) tenant.
class DataPromoPos {
  const DataPromoPos({required this.modeResolusi, required this.promo});

  static const DataPromoPos kosong = DataPromoPos(modeResolusi: 'Terbaik', promo: []);

  final String modeResolusi;
  final List<PromoPos> promo;

  static DataPromoPos DariJson(Map<String, Object?> json) => DataPromoPos(
    modeResolusi: UraiJson.AmbilTeks(json['ModeResolusi'], 'Terbaik'),
    promo: UraiJson.AmbilDaftarPeta(json['Promo']).map(PromoPos.DariJson).toList(),
  );

  Map<String, Object?> KeJson() => {
    'ModeResolusi': modeResolusi,
    'Promo': [for (final p in promo) p.KeJson()],
  };
}

/// Hasil pesan voucher (F-16c bagian 2, `POST /api/pos/v1/voucher/pesan`): kode kanonik, promo voucher (definisinya ikut
/// agar bisa langsung dievaluasi walau katalog promo belum diperbarui), dan batas pesanan. [sisaPakai] null = tanpa batas.
class VoucherPos {
  const VoucherPos({
    required this.kode,
    required this.uuidPromo,
    required this.dipesanSampai,
    required this.promo,
    this.sisaPakai,
  });

  final String kode;
  final String uuidPromo;
  final DateTime? dipesanSampai;
  final int? sisaPakai;
  final PromoPos promo;

  static VoucherPos DariJson(Map<String, Object?> json) {
    final voucher = UraiJson.AmbilPeta(json['Voucher']);
    return VoucherPos(
      kode: UraiJson.AmbilTeks(voucher['Kode']),
      uuidPromo: UraiJson.AmbilTeks(voucher['UuidPromo']),
      dipesanSampai: DateTime.tryParse(UraiJson.AmbilTeks(voucher['DipesanSampai'])),
      sisaPakai: UraiJson.AmbilBulatAtauNull(voucher['SisaPakai']),
      promo: PromoPos.DariJson(UraiJson.AmbilPeta(json['Promo'])),
    );
  }
}
