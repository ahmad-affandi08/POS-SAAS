import 'dart:convert';
import 'dart:io';

import 'package:mesin_kasir/MesinKasir.dart';
import 'package:test/test.dart';

/// Membaca semua test vector bersama di `Spesifikasi/VektorUjiKalkulasi/` (PRD §23.2, Lampiran D).
///
/// Memastikan setiap vektor terbaca, semua nilai uang valid sebagai `Uang`, lalu menjalankan `MesinKalkulasi` (F-07a)
/// dan membandingkan setiap kunci `Harapan` (termasuk rincian `Pajak` per kode dan `Baris[]`) sampai sen (CLAUDE.md #18).
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

/// Menyusun masukan mesin dari format test vector (PRD Rincian F-07a, Lampiran D).
///
/// Baris tanpa kunci `Pajak` = semua pajak dokumen; `Pajak: []` = tanpa pajak. Promo item berlaku ke baris pertama
/// dengan `Sku` sama; promo pesanan dan `DiskonManualPesanan` menjadi potongan pesanan. `Pembayaran` boleh objek
/// tunggal atau daftar.
DataKalkulasi SusunDataKalkulasi(Map<String, Object?> vektor) {
  final pengaturan = vektor['Pengaturan']! as Map<String, Object?>;
  final pembulatan = pengaturan['PembulatanTunai'] as Map<String, Object?>?;

  DataPotongan SusunPotongan(Map<String, Object?> spesifikasi) => spesifikasi.containsKey('Persen')
      ? DataPotongan.DariPersen(Decimal.parse(spesifikasi['Persen']! as String))
      : DataPotongan.DariJumlah(Uang.Dari(spesifikasi['Jumlah']! as String));

  final dataBaris = (vektor['Baris']! as List<Object?>).cast<Map<String, Object?>>();
  final sku = [for (final baris in dataBaris) baris['Sku']! as String];
  final potonganBaris = [
    for (final baris in dataBaris)
      <DataPotongan>[if (baris['DiskonManual'] case final Map<String, Object?> manual) SusunPotongan(manual)],
  ];
  final potonganPesanan = <DataPotongan>[];
  for (final promo in ((vektor['Promo'] as List<Object?>?) ?? const []).cast<Map<String, Object?>>()) {
    switch (promo['Jenis']) {
      case 'DiskonTetapItem' || 'DiskonPersenItem':
        final indeks = sku.indexOf(promo['Sku']! as String);
        expect(indeks, isNonNegative, reason: 'promo item untuk Sku ${promo['Sku']} tanpa baris');
        potonganBaris[indeks].add(SusunPotongan(promo));
      case 'DiskonTetapPesanan' || 'DiskonPersenPesanan':
        potonganPesanan.add(SusunPotongan(promo));
      default:
        fail('Jenis promo tidak dikenal: ${promo['Jenis']}');
    }
  }
  if (vektor['DiskonManualPesanan'] case final Map<String, Object?> manual) {
    potonganPesanan.add(SusunPotongan(manual));
  }

  final bayar = vektor['Pembayaran'];
  final daftarBayar = switch (bayar) {
    null => const <Map<String, Object?>>[],
    final Map<String, Object?> tunggal => [tunggal],
    final List<Object?> daftar => daftar.cast<Map<String, Object?>>(),
    _ => throw StateError('Format Pembayaran tidak dikenal'),
  };

  return DataKalkulasi(
    hargaTermasukPajak: pengaturan['HargaTermasukPajak']! as bool,
    persenBiayaLayanan: Decimal.parse((pengaturan['PersenBiayaLayanan'] as String?) ?? '0'),
    pembulatanTunai: pembulatan == null
        ? null
        : DataPembulatanTunai(
            kelipatan: pembulatan['Kelipatan']! as int,
            arah: ArahPembulatan.values.byName(pembulatan['Arah']! as String),
          ),
    pajak: [
      for (final pajak in ((vektor['Pajak'] as List<Object?>?) ?? const []).cast<Map<String, Object?>>())
        DataPajakKalkulasi(
          kode: pajak['Kode']! as String,
          tarif: Decimal.parse(pajak['Tarif']! as String),
          pengaliDpp: UraiPecahan((pajak['PengaliDpp'] as String?) ?? '1/1'),
          dasarPengenaan: DasarPengenaanPajak.values.byName((pajak['DasarPengenaan'] as String?) ?? 'Subtotal'),
        ),
    ],
    baris: [
      for (var i = 0; i < dataBaris.length; i++)
        DataBarisKalkulasi(
          jumlah: Kuantitas.Dari(dataBaris[i]['Jumlah']! as String),
          hargaSatuan: Uang.Dari(dataBaris[i]['HargaSatuan']! as String),
          hargaPilihan: Uang.Dari((dataBaris[i]['HargaPilihan'] as String?) ?? '0'),
          hargaTermasukPajak: dataBaris[i]['HargaTermasukPajak'] as bool?,
          kodePajak: (dataBaris[i]['Pajak'] as List<Object?>?)?.cast<String>(),
          potongan: potonganBaris[i],
        ),
    ],
    potonganPesanan: potonganPesanan,
    tukarPoin: vektor['TukarPoin'] == null ? null : Uang.Dari(vektor['TukarPoin']! as String),
    pembayaran: [
      for (final item in daftarBayar)
        DataPembayaranKalkulasi(
          metode: item['Metode']! as String,
          jumlah: item['Jumlah'] == null ? null : Uang.Dari(item['Jumlah']! as String),
        ),
    ],
  );
}

/// Mengurai pecahan `"p/q"` (misal `"11/12"`) menjadi `Rational` eksak.
Rational UraiPecahan(String teks) {
  final bagian = teks.split('/');
  expect(bagian, hasLength(2), reason: 'PengaliDpp harus berbentuk p/q');
  return Rational(BigInt.parse(bagian[0]), BigInt.parse(bagian[1]));
}

/// Mengubah hasil mesin ke bentuk `Harapan` test vector (key PascalCase, uang string 2 desimal).
Map<String, Object?> UbahHasilKePeta(HasilKalkulasi hasil) => {
  'Subtotal': hasil.subtotal.KeString(),
  'DiskonBaris': hasil.diskonBaris.KeString(),
  'DiskonPesanan': hasil.diskonPesanan.KeString(),
  'DiskonPoin': hasil.diskonPoin.KeString(),
  'TotalDiskon': hasil.totalDiskon.KeString(),
  'BiayaLayanan': hasil.biayaLayanan.KeString(),
  'TotalPajak': hasil.totalPajak.KeString(),
  'TotalPajakEksklusif': hasil.totalPajakEksklusif.KeString(),
  'Pembulatan': hasil.pembulatan.KeString(),
  'TotalAkhir': hasil.totalAkhir.KeString(),
  if (hasil.kembalian case final Uang kembalian) 'Kembalian': kembalian.KeString(),
  'Pajak': {
    for (final MapEntry(:key, :value) in hasil.pajak.entries)
      key: {'Dpp': value.dpp.KeString(), 'Jumlah': value.jumlah.KeString()},
  },
  'Baris': [
    for (final baris in hasil.baris)
      {
        'Bruto': baris.bruto.KeString(),
        'Diskon': baris.diskon.KeString(),
        'DiskonPesanan': baris.diskonPesanan.KeString(),
        'BiayaLayanan': baris.biayaLayanan.KeString(),
        'Pajak': baris.pajak.KeString(),
        'PajakEksklusif': baris.pajakEksklusif.KeString(),
        'TotalBaris': baris.totalBaris.KeString(),
      },
  ],
};

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
        // Rincian `Pajak` (per kode) dan `Baris[]` ditelusuri sampai ke setiap nilai daun.
        void PeriksaNilai(String jalur, Object? nilai) {
          if (nilai is Map<String, Object?>) {
            for (final MapEntry(:key, :value) in nilai.entries) {
              PeriksaNilai('$jalur.$key', value);
            }
          } else if (nilai is List<Object?>) {
            for (var i = 0; i < nilai.length; i++) {
              PeriksaNilai('$jalur[$i]', nilai[i]);
            }
          } else {
            expect(nilai, isA<String>(), reason: '$jalur harus string desimal');
            expect(() => Uang.Dari(nilai! as String), returnsNormally, reason: jalur);
          }
        }

        final harapan = vektor['Harapan']! as Map<String, Object?>;
        expect(harapan, isNotEmpty);
        PeriksaNilai('Harapan', harapan);
      });

      test('Jumlah dan HargaSatuan setiap baris adalah string desimal', () {
        final baris = (vektor['Baris']! as List<Object?>).cast<Map<String, Object?>>();
        for (final item in baris) {
          expect(() => Kuantitas.Dari(item['Jumlah']! as String), returnsNormally);
          expect(() => Uang.Dari(item['HargaSatuan']! as String), returnsNormally);
        }
      });

      test('MesinKalkulasi menghasilkan setiap nilai Harapan sampai sen (F-07a)', () {
        final hasil = const MesinKalkulasi().Hitung(SusunDataKalkulasi(vektor));
        final aktual = UbahHasilKePeta(hasil);
        final harapan = vektor['Harapan']! as Map<String, Object?>;
        for (final MapEntry(:key, :value) in harapan.entries) {
          expect(aktual.containsKey(key), isTrue, reason: 'mesin tidak menghasilkan Harapan.$key');
          expect(aktual[key], value, reason: 'Harapan.$key');
        }
      });
    });
  }
}
