import 'package:flutter_test/flutter_test.dart';
import 'package:kasir/Domain/Dapur/PenyusunLayarAntrian.dart';
import 'package:klien_api/KlienApi.dart';

/// K-2 lanjutan (§9.2): layar panggil antrian mengelompokkan tiket per penjualan; siap bila semua stasiun siap; pesanan
/// meja & tiket tanpa label tidak tampil; semua disajikan = hilang.
void main() {
  TiketDapurPos Tiket(String dokumen, String status, {String? label, String? meja, int menit = 0, String uuid = ''}) =>
      TiketDapurPos(
        uuid: uuid.isEmpty ? '$dokumen-$status-$menit' : uuid,
        uuidStasiun: null,
        nomorDokumen: dokumen,
        namaMeja: meja,
        label: label,
        ronde: 1,
        status: status,
        dikirimPada: DateTime.utc(2026, 10, 2, 5, menit),
        baris: const [],
      );

  test('mengurai label #042 Budi menjadi nomor & nama', () {
    expect(PenyusunLayarAntrian.UraiLabel('#042 Budi Santoso'), ('042', 'Budi Santoso'));
    expect(PenyusunLayarAntrian.UraiLabel('#007'), ('007', null));
    expect(PenyusunLayarAntrian.UraiLabel('Ojol GoFood'), ('Ojol GoFood', null));
  });

  test('dikelompokkan per dokumen: siap bila semua stasiun siap, disajikan semua hilang, meja tidak tampil', () {
    final hasil = PenyusunLayarAntrian.Susun([
      // 041: bar siap, dapur masih dimasak → disiapkan.
      Tiket('INV-41', 'Siap', label: '#041 Ani', menit: 1),
      Tiket('INV-41', 'Dimasak', label: '#041 Ani', menit: 1, uuid: 'x'),
      // 042: semua siap → siap.
      Tiket('INV-42', 'Siap', label: '#042 Budi', menit: 2),
      // 043: satu disajikan, satu siap → masih menunggu diambil.
      Tiket('INV-43', 'Disajikan', label: '#043', menit: 3),
      Tiket('INV-43', 'Siap', label: '#043', menit: 4, uuid: 'y'),
      // 044: semua disajikan → hilang.
      Tiket('INV-44', 'Disajikan', label: '#044', menit: 5),
      // Meja & tanpa label → tidak tampil.
      Tiket('OB-1', 'Antre', meja: 'Meja 5', menit: 6),
      Tiket('INV-45', 'Antre', menit: 7),
      Tiket('INV-40', 'Antre', label: '#040', menit: 0),
    ]);
    expect(hasil.disiapkan.map((n) => n.nomor), ['040', '041']);
    expect(hasil.siap.map((n) => (n.nomor, n.nama)), [('043', null), ('042', 'Budi')]);
  });
}
