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
