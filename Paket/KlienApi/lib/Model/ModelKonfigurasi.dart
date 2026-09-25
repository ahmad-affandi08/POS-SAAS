import 'UraiJson.dart';

/// Isi `GET /api/pos/v1/konfigurasi-aplikasi` yang dipakai aplikasi (§14.6, P-10): versi terbaru & minimal untuk
/// perangkat ini, tautan unduh, catatan rilis ("Yang baru"), dan flag fitur tenant.
class KonfigurasiAplikasi {
  const KonfigurasiAplikasi({
    required this.versiSaatIni,
    required this.versiTerbaru,
    required this.versiMinimal,
    required this.tautanUnduh,
    required this.catatanRilis,
    required this.adaPembaruan,
    required this.wajibPembaruan,
    required this.flagFitur,
  });

  final String? versiSaatIni;
  final String? versiTerbaru;
  final String? versiMinimal;
  final String? tautanUnduh;
  final String? catatanRilis;
  final bool adaPembaruan;

  /// Di bawah versi minimal: outbox tertunda tetap dikirim, layar jual dikunci sampai aplikasi diperbarui.
  final bool wajibPembaruan;

  /// Kunci → hidup/mati. Kunci tanpa aturan tidak dikirim (dianggap hidup).
  final Map<String, bool> flagFitur;

  /// Flag dengan kunci [kunci]; tanpa aturan = [bawaan].
  bool CekFlag(String kunci, {bool bawaan = true}) => flagFitur[kunci] ?? bawaan;

  static KonfigurasiAplikasi DariJson(Map<String, Object?> json) {
    final aplikasi = UraiJson.AmbilPeta(json['Aplikasi']);
    final flag = <String, bool>{};
    UraiJson.AmbilPeta(json['FlagFitur']).forEach((kunci, nilai) {
      if (nilai is bool) {
        flag[kunci] = nilai;
      }
    });
    return KonfigurasiAplikasi(
      versiSaatIni: UraiJson.AmbilTeksAtauNull(aplikasi['VersiSaatIni']),
      versiTerbaru: UraiJson.AmbilTeksAtauNull(aplikasi['VersiTerbaru']),
      versiMinimal: UraiJson.AmbilTeksAtauNull(aplikasi['VersiMinimal']),
      tautanUnduh: UraiJson.AmbilTeksAtauNull(aplikasi['TautanUnduh']),
      catatanRilis: UraiJson.AmbilTeksAtauNull(aplikasi['CatatanRilis']),
      adaPembaruan: UraiJson.AmbilBenar(aplikasi['AdaPembaruan']),
      wajibPembaruan: UraiJson.AmbilBenar(aplikasi['WajibPembaruan']),
      flagFitur: Map.unmodifiable(flag),
    );
  }
}
