import 'dart:convert';
import 'dart:io';

import 'package:mesin_kasir/MesinKasir.dart';
import 'package:test/test.dart';

/// Membaca semua test vector bersama di `Spesifikasi/VektorUjiKalkulasi/` (PRD §23.2, Lampiran D).
///
/// Tahap Fase 0: memastikan setiap vektor terbaca dan semua nilai uang valid sebagai `Uang`.
/// Saat engine F-07 dibangun, test ini menjalankan engine dan membandingkan hasilnya dengan `Harapan`.
Directory CariFolderVektor() {
  var folder = Directory.current;
  while (true) {
    final kandidat = Directory('${folder.path}/Spesifikasi/VektorUjiKalkulasi');
    if (kandidat.existsSync()) {
      return kandidat;
    }
    final induk = folder.parent;
    if (induk.path == folder.path) {
      throw StateError('Folder Spesifikasi/VektorUjiKalkulasi tidak ditemukan dari ${Directory.current.path}');
    }
    folder = induk;
  }
}

void main() {
  final berkas = CariFolderVektor().listSync().whereType<File>().where((file) => file.path.endsWith('.json')).toList()
    ..sort((a, b) => a.path.compareTo(b.path));

  test('ada minimal satu test vector', () {
    expect(berkas, isNotEmpty);
  });

  for (final file in berkas) {
    final vektor = jsonDecode(file.readAsStringSync()) as Map<String, Object?>;

    group('vektor ${vektor['Id']}', () {
      test('nama file sama dengan Id', () {
        expect(file.uri.pathSegments.last, '${vektor['Id']}.json');
      });

      test('memiliki bagian wajib', () {
        expect(vektor.keys, containsAll(<String>['Id', 'Keterangan', 'Pengaturan', 'Baris', 'Harapan']));
      });

      test('semua nilai Harapan adalah uang string desimal yang valid', () {
        final harapan = vektor['Harapan']! as Map<String, Object?>;
        for (final MapEntry(:key, :value) in harapan.entries) {
          expect(value, isA<String>(), reason: 'Harapan.$key harus string desimal');
          expect(() => Uang.Dari(value! as String), returnsNormally, reason: 'Harapan.$key');
        }
      });

      test('Jumlah dan HargaSatuan setiap baris adalah string desimal', () {
        final baris = (vektor['Baris']! as List<Object?>).cast<Map<String, Object?>>();
        for (final item in baris) {
          expect(() => Kuantitas.Dari(item['Jumlah']! as String), returnsNormally);
          expect(() => Uang.Dari(item['HargaSatuan']! as String), returnsNormally);
        }
      });
    });
  }
}
