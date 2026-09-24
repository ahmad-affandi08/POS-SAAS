import 'package:flutter/widgets.dart';

/// Nama sistem (D-15). Dipakai sebagai label semantik logo agar pembaca layar tetap menyebut nama merek.
const String namaMerek = 'PAYOU';

/// Logo PAYOU (sumber `Spesifikasi/Merek`, dibuat ulang lewat `Spesifikasi/Merek/BuatTurunanAset.py`).
///
/// [LogoMerek.lengkap] = logo horizontal dengan slogan, untuk layar sambutan/aktivasi.
/// [LogoMerek.ikon] = tanda huruf P saja, untuk ruang sempit.
class LogoMerek extends StatelessWidget {
  const LogoMerek.lengkap({super.key, this.tinggi = 56}) : _berkas = 'assets/merek/LogoHorizontal.png';

  const LogoMerek.ikon({super.key, this.tinggi = 40}) : _berkas = 'assets/merek/IkonMerek.png';

  final double tinggi;
  final String _berkas;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: namaMerek,
      image: true,
      child: ExcludeSemantics(
        child: Image.asset(_berkas, package: 'sistem_desain', height: tinggi, fit: BoxFit.contain),
      ),
    );
  }
}
