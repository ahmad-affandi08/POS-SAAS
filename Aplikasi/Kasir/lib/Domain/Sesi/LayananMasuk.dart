import 'package:klien_api/KlienApi.dart';

import '../../Data/PenyimpanRahasia.dart';
import '../../Data/RepositoriKasir.dart';
import '../GalatKasir.dart';
import '../Pin/PemverifikasiPinOffline.dart';
import 'StafLokal.dart';

/// Masuk kasir dengan PIN 6 digit (F-02 langkah 4, F-06 langkah 1). Utama: verifikasi lokal dengan verifier offline
/// (bisa tanpa internet, BR-06.3). Bila staf belum punya verifier (PIN diatur sebelum F-06), coba verifikasi online.
/// 5 kali salah → PIN dikunci 5 menit di perangkat ini, juga saat offline (§20.2). PIN tidak pernah disimpan.
class LayananMasuk {
  LayananMasuk({
    required this.repositori,
    required this.rahasia,
    required this.klien,
    this.pemverifikasi = const PemverifikasiPinOffline(),
    DateTime Function()? jam,
  }) : _jam = jam ?? DateTime.now;

  final RepositoriKasir repositori;
  final PenyimpanRahasia rahasia;
  final KlienPos klien;
  final PemverifikasiPinOffline pemverifikasi;
  final DateTime Function() _jam;

  Future<StafLokal> Masuk(StafLokal staf, String pin) async {
    if (!RegExp(r'^\d{6}$').hasMatch(pin)) {
      throw const GalatKasir('PinTidakValid', 'PIN harus 6 angka.');
    }

    await _PastikanTidakTerkunci(staf);
    final benar = await _Periksa(staf, pin);

    if (!benar) {
      await _CatatGagal(staf);
    }

    await repositori.HapusPercobaanPin(staf.uuid);
    return staf;
  }

  Future<bool> _Periksa(StafLokal staf, String pin) async {
    final kunci = await rahasia.Baca(PenyimpanRahasia.kunciPin);
    final parameter = await repositori.AmbilParameterPin();

    if (staf.pin != null && kunci != null && parameter != null) {
      return pemverifikasi.Verifikasi(
        pin: pin,
        terbungkus: staf.pin!,
        kunciPerangkatBase64: kunci,
        parameter: parameter,
      );
    }

    try {
      await klien.MasukPin(uuidPengguna: staf.uuid, pin: pin);
      return true;
    } on GalatApi catch (galat) {
      if (galat.kode == 'PinSalah') {
        return false;
      }
      throw GalatKasir(galat.kode, galat.pesan);
    } on GalatJaringan {
      throw GalatKasir(
        'PinOfflineBelumTersedia',
        'PIN ${staf.nama} belum bisa dipakai tanpa internet. Sambungkan perangkat, atau atur ulang PIN di back-office lalu perbarui data kasir.',
      );
    }
  }

  Future<void> _PastikanTidakTerkunci(StafLokal staf) async {
    final percobaan = await repositori.AmbilPercobaanPin(staf.uuid);
    final sampai = percobaan?.TerkunciSampai;
    if (sampai != null && sampai.isAfter(_jam().toUtc())) {
      final menit = (sampai.difference(_jam().toUtc()).inSeconds / 60).ceil();
      throw GalatKasir(
        'PinTerkunci',
        'Terlalu banyak PIN salah. Coba lagi dalam $menit menit atau minta manajer mengatur ulang PIN.',
      );
    }
  }

  Future<Never> _CatatGagal(StafLokal staf) async {
    final batas = int.tryParse(await repositori.AmbilPengaturan(KunciPengaturan.batasSalahPin) ?? '') ?? 5;
    final menit = int.tryParse(await repositori.AmbilPengaturan(KunciPengaturan.menitKunciPin) ?? '') ?? 5;
    final percobaan = await repositori.AmbilPercobaanPin(staf.uuid);
    final gagal = (percobaan?.TerkunciSampai != null ? 0 : percobaan?.JumlahGagal ?? 0) + 1;

    if (gagal >= batas) {
      await repositori.SimpanPercobaanPin(staf.uuid, 0, _jam().toUtc().add(Duration(minutes: menit)));
      throw GalatKasir('PinTerkunci', 'Terlalu banyak PIN salah. PIN dikunci $menit menit.');
    }

    await repositori.SimpanPercobaanPin(staf.uuid, gagal, null);
    throw GalatKasir('PinSalah', 'PIN salah. Sisa ${batas - gagal} percobaan sebelum PIN dikunci sementara.');
  }
}
