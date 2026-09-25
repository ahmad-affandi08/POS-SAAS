import 'dart:convert';
import 'dart:io';

import 'package:mesin_kasir/MesinKasir.dart';
import 'package:test/test.dart';

/// Test vector promo bersama (F-16c, CLAUDE.md #18): setiap kasus di `Spesifikasi/VektorUjiKalkulasi/Promo/*.json`
/// dijalankan lewat `MesinPromo` lalu `MesinKalkulasi`; promo terpakai (urut, potongan per baris & pesanan) dan setiap
/// angka `Harapan` harus sama persis. Vektor yang sama dijalankan PHP di `tests/Unit/Kalkulasi/VektorUjiPromoTes.php`.
Directory CariFolderVektorPromo() {
  var folder = Directory.current;
  while (true) {
    final kandidat = Directory('${folder.path}/Spesifikasi/VektorUjiKalkulasi/Promo');
    if (kandidat.existsSync()) {
      return kandidat;
    }
    final induk = folder.parent;
    if (induk.path == folder.path) {
      throw StateError('Folder Spesifikasi/VektorUjiKalkulasi/Promo tidak ditemukan dari ${Directory.current.path}');
    }
    folder = induk;
  }
}

DataPotongan? BacaPotongan(Object? json) {
  if (json is! Map<String, Object?>) {
    return null;
  }
  return json['Persen'] != null
      ? DataPotongan.DariPersen(Decimal.parse(json['Persen']! as String))
      : DataPotongan.DariJumlah(Uang.Dari(json['Jumlah']! as String));
}

DataKalkulasi BacaDasar(Map<String, Object?> vektor) {
  final pengaturan = vektor['Pengaturan']! as Map<String, Object?>;
  final pembulatan = pengaturan['PembulatanTunai'] as Map<String, Object?>?;
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
          pengaliDpp: BacaPecahan((pajak['PengaliDpp'] as String?) ?? '1/1'),
          dasarPengenaan: DasarPengenaanPajak.values.byName((pajak['DasarPengenaan'] as String?) ?? 'Subtotal'),
        ),
    ],
    baris: [
      for (final baris in (vektor['Baris']! as List<Object?>).cast<Map<String, Object?>>())
        DataBarisKalkulasi(
          jumlah: Kuantitas.Dari(baris['Jumlah']! as String),
          hargaSatuan: Uang.Dari(baris['HargaSatuan']! as String),
          hargaPilihan: Uang.Dari((baris['HargaPilihan'] as String?) ?? '0'),
          potongan: [?BacaPotongan(baris['DiskonManual'])],
        ),
    ],
    potonganPesanan: [?BacaPotongan(vektor['DiskonManualPesanan'])],
    pembayaran: [
      for (final item in daftarBayar)
        DataPembayaranKalkulasi(
          metode: item['Metode']! as String,
          jumlah: item['Jumlah'] == null ? null : Uang.Dari(item['Jumlah']! as String),
        ),
    ],
  );
}

Rational BacaPecahan(String teks) {
  final bagian = teks.split('/');
  return Rational(BigInt.parse(bagian[0]), BigInt.parse(bagian[1]));
}

DateTime? BacaWaktu(Object? nilai) => nilai == null ? null : DateTime.parse(nilai as String);

Map<String, Object?> UbahHasilKePeta(HasilKalkulasi hasil) => {
  'Subtotal': hasil.subtotal.KeString(),
  'DiskonBaris': hasil.diskonBaris.KeString(),
  'DiskonPesanan': hasil.diskonPesanan.KeString(),
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
  final berkas =
      CariFolderVektorPromo().listSync().whereType<File>().where((file) => file.path.endsWith('.json')).toList()
        ..sort((a, b) => a.path.compareTo(b.path));

  test('menemukan test vector promo F-16c', () {
    expect(berkas.length, greaterThanOrEqualTo(8));
  });

  for (final file in berkas) {
    final vektor = jsonDecode(file.readAsStringSync()) as Map<String, Object?>;

    test('vektor ${vektor['Id']}: MesinPromo + MesinKalkulasi sama dengan Harapan (F-16c)', () {
      expect(file.uri.pathSegments.last, '${vektor['Id']}.json');
      final baris = (vektor['Baris']! as List<Object?>).cast<Map<String, Object?>>();
      final konteks = vektor['Konteks']! as Map<String, Object?>;
      final promo = [
        for (final p in (vektor['Promo']! as List<Object?>).cast<Map<String, Object?>>())
          DefinisiPromo.Urai(
            uuid: p['Uuid']! as String,
            kode: p['Kode']! as String,
            prioritas: p['Prioritas']! as int,
            eksklusif: p['Eksklusif']! as bool,
            mulaiPada: BacaWaktu(p['MulaiPada']),
            selesaiPada: BacaWaktu(p['SelesaiPada']),
            kuotaTersisa: p['KuotaTersisa'] as int?,
            definisi: p['Definisi']! as Map<String, Object?>,
          ),
      ];
      final hasil = const MesinPromo().Terapkan(
        BacaDasar(vektor),
        [for (final b in baris) BarisPromo(uuidProduk: b['Sku']! as String, uuidKategori: b['Kategori'] as String?)],
        promo,
        KonteksPromo(
          waktu: DateTime.parse(konteks['Waktu']! as String),
          waktuLokal: DateTime.parse(konteks['WaktuLokal']! as String),
          uuidOutlet: konteks['UuidOutlet'] as String?,
          kanal: konteks['Kanal'] == null ? null : KanalPenjualan.values.byName(konteks['Kanal']! as String),
          tier: konteks['Tier'] as String?,
          voucher: ((konteks['Voucher'] as List<Object?>?) ?? const []).cast<String>(),
        ),
        mode: ModeResolusiPromo.values.byName(vektor['Mode']! as String),
      );

      final harapan = vektor['Harapan']! as Map<String, Object?>;
      final terpakai = [
        for (final t in hasil.terpakai)
          {
            'Kode': t.kode,
            'DiskonBaris': {
              for (final MapEntry(:key, :value) in t.diskonBaris.entries) baris[key]['Sku']: value.KeString(),
            },
            'DiskonPesanan': t.diskonPesanan.KeString(),
          },
      ];
      expect(terpakai, harapan['PromoTerpakai'], reason: 'PromoTerpakai');
      final aktual = UbahHasilKePeta(hasil.hasil);
      for (final MapEntry(:key, :value) in harapan.entries.where((e) => e.key != 'PromoTerpakai')) {
        expect(aktual[key], value, reason: 'Harapan.$key');
      }
    });
  }
}
