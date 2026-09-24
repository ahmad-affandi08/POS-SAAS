/// Format waktu Indonesia untuk tampilan (PRD §17.6.7): jam `14.32`, tanggal `22 Sep 2026`. Waktu lokal perangkat.
abstract final class FormatWaktu {
  static const List<String> _bulan = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
  ];

  static String FormatJam(DateTime waktu) {
    final lokal = waktu.toLocal();
    return '${_DuaAngka(lokal.hour)}.${_DuaAngka(lokal.minute)}';
  }

  static String FormatTanggal(DateTime waktu) {
    final lokal = waktu.toLocal();
    return '${lokal.day} ${_bulan[lokal.month - 1]} ${lokal.year}';
  }

  static String FormatTanggalJam(DateTime waktu) => '${FormatTanggal(waktu)} ${FormatJam(waktu)}';

  static String _DuaAngka(int n) => n.toString().padLeft(2, '0');
}
