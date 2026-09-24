import 'dart:convert';
import 'dart:io';

import 'package:mesin_kasir/MesinKasir.dart';
import 'package:test/test.dart';

/// Test vector harga bersama (F-03 price engine lapis 3–5, CLAUDE.md #18): setiap kasus di
/// `Spesifikasi/VektorUjiKalkulasi/Harga/*.json` dijalankan lewat `PenentuHarga` dan harus sama persis dengan
/// `Harapan`. Vektor yang sama dijalankan PHP di `Aplikasi/Web/tests/Unit/Kalkulasi/VektorUjiHargaTes.php`.
Directory CariFolderVektorHarga() {
  var folder = Directory.current;
  while (true) {
    final kandidat = Directory('${folder.path}/Spesifikasi/VektorUjiKalkulasi/Harga');
    if (kandidat.existsSync()) {
      return kandidat;
    }
    final induk = folder.parent;
    if (induk.path == folder.path) {
      throw StateError('Folder Spesifikasi/VektorUjiKalkulasi/Harga tidak ditemukan dari ${Directory.current.path}');
    }
    folder = induk;
  }
}

KanalPenjualan? BacaKanal(Object? nilai) => nilai == null ? null : KanalPenjualan.values.byName(nilai as String);

DateTime? BacaWaktu(Object? nilai) => nilai == null ? null : DateTime.parse(nilai as String);

KatalogHarga BacaKatalog(Map<String, Object?> katalog) => KatalogHarga(
  daftarHarga: (katalog['DaftarHarga']! as List<Object?>).cast<Map<String, Object?>>().map((daftar) {
    return DaftarHargaResolusi(
      uuid: daftar['Uuid']! as String,
      aktif: daftar['Aktif']! as bool,
      uuidOutlet: (daftar['UuidOutlet'] as List<Object?>?)?.cast<String>(),
      kanal: BacaKanal(daftar['Kanal']),
      tierPelanggan: daftar['TierPelanggan'] as String?,
      mulaiPada: BacaWaktu(daftar['MulaiPada']),
      selesaiPada: BacaWaktu(daftar['SelesaiPada']),
      prioritas: daftar['Prioritas']! as int,
    );
  }).toList(),
  harga: (katalog['ProdukHarga']! as List<Object?>).cast<Map<String, Object?>>().map((baris) {
    return BarisProdukHarga(
      uuidProduk: baris['UuidProduk']! as String,
      uuidProdukSatuan: baris['UuidProdukSatuan']! as String,
      uuidDaftarHarga: baris['UuidDaftarHarga'] as String?,
      jumlahMinimum: Kuantitas.Dari(baris['JumlahMinimum']! as String),
      harga: Uang.Dari(baris['Harga']! as String),
    );
  }).toList(),
);

void main() {
  final berkas =
      CariFolderVektorHarga().listSync().whereType<File>().where((file) => file.path.endsWith('.json')).toList()
        ..sort((a, b) => a.path.compareTo(b.path));

  test('menemukan test vector harga F-03', () {
    expect(berkas.length, greaterThanOrEqualTo(11));
  });

  for (final file in berkas) {
    final vektor = jsonDecode(file.readAsStringSync()) as Map<String, Object?>;
    final katalog = BacaKatalog(vektor['Katalog']! as Map<String, Object?>);
    final daftarKasus = (vektor['Kasus']! as List<Object?>).cast<Map<String, Object?>>();

    group('vektor ${vektor['Id']}', () {
      test('nama file sama dengan Id dan berbagian wajib', () {
        expect(file.uri.pathSegments.last, '${vektor['Id']}.json');
        expect(vektor.keys, containsAll(<String>['Id', 'Keterangan', 'Katalog', 'Kasus']));
        expect(daftarKasus, isNotEmpty);
      });

      for (final kasus in daftarKasus) {
        test(kasus['Nama']! as String, () {
          final masukan = kasus['Masukan']! as Map<String, Object?>;
          final harapan = kasus['Harapan']! as Map<String, Object?>;
          final hasil = const PenentuHarga().Tentukan(
            katalog,
            PermintaanHarga(
              uuidProduk: masukan['UuidProduk']! as String,
              uuidProdukSatuan: masukan['UuidProdukSatuan']! as String,
              jumlah: Kuantitas.Dari(masukan['Jumlah']! as String),
              uuidOutlet: masukan['UuidOutlet'] as String?,
              kanal: BacaKanal(masukan['Kanal']),
              tierPelanggan: masukan['TierPelanggan'] as String?,
              waktu: BacaWaktu(masukan['Waktu'])!,
            ),
          );

          if (harapan.containsKey('Galat')) {
            expect(harapan['Galat'], 'HargaTidakDitemukan');
            expect(hasil, isNull);
            return;
          }

          expect(hasil, isNotNull);
          expect(
            <String, Object?>{
              'Harga': hasil!.harga.KeString(),
              'Sumber': hasil.sumber.name,
              'UuidDaftarHarga': hasil.uuidDaftarHarga,
              'JumlahMinimum': hasil.jumlahMinimum.KeString(),
            },
            <String, Object?>{
              'Harga': harapan['Harga'],
              'Sumber': harapan['Sumber'],
              'UuidDaftarHarga': harapan['UuidDaftarHarga'],
              'JumlahMinimum': harapan['JumlahMinimum'],
            },
          );
        });
      }
    });
  }
}
