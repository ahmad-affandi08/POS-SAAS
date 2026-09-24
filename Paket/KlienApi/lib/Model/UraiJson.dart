/// Pembaca nilai JSON yang toleran untuk model API POS: kunci absen atau bertipe lain → nilai bawaan, sehingga aplikasi
/// tetap jalan dengan server versi lebih lama/baru (kompatibel mundur, CLAUDE.md #16).
abstract final class UraiJson {
  static Map<String, Object?> AmbilPeta(Object? nilai) =>
      nilai is Map<String, Object?> ? nilai : const <String, Object?>{};

  static Map<String, Object?>? AmbilPetaAtauNull(Object? nilai) => nilai is Map<String, Object?> ? nilai : null;

  static List<Map<String, Object?>> AmbilDaftarPeta(Object? nilai) =>
      nilai is List<Object?> ? nilai.whereType<Map<String, Object?>>().toList() : const [];

  static List<String> AmbilDaftarTeks(Object? nilai) =>
      nilai is List<Object?> ? nilai.whereType<String>().toList() : const [];

  static String AmbilTeks(Object? nilai, [String bawaan = '']) => nilai is String ? nilai : bawaan;

  static String? AmbilTeksAtauNull(Object? nilai) => nilai is String ? nilai : null;

  /// Angka desimal (uang, jumlah, persen) sebagai teks apa adanya. Server mengirim string; angka JSON diterima
  /// sebagai teks agar tidak pernah melewati tipe pecahan biner di model.
  static String AmbilDesimal(Object? nilai, [String bawaan = '0']) => switch (nilai) {
    final String teks when teks.trim().isNotEmpty => teks.trim(),
    final int bulat => '$bulat',
    final num angka => angka.toString(),
    _ => bawaan,
  };

  static String? AmbilDesimalAtauNull(Object? nilai) => nilai == null ? null : AmbilDesimal(nilai);

  static bool AmbilBenar(Object? nilai, [bool bawaan = false]) => nilai is bool ? nilai : bawaan;

  static bool? AmbilBenarAtauNull(Object? nilai) => nilai is bool ? nilai : null;

  static int AmbilBulat(Object? nilai, [int bawaan = 0]) => switch (nilai) {
    final int bulat => bulat,
    final String teks => int.tryParse(teks) ?? bawaan,
    _ => bawaan,
  };

  static int? AmbilBulatAtauNull(Object? nilai) => nilai == null ? null : AmbilBulat(nilai);
}
