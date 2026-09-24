import 'dart:convert';

import 'package:drift/drift.dart' show Value;
import 'package:klien_api/KlienApi.dart';
import 'package:mesin_kasir/MesinKasir.dart';

import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Data/RepositoriKasir.dart';
import '../../Data/RepositoriPenjualan.dart';
import '../GalatKasir.dart';
import '../Katalog/KatalogLokal.dart';
import '../Sesi/StafLokal.dart';
import 'Keranjang.dart';
import 'KonteksPenjualan.dart';

/// Kode jenis pajak yang punya syarat profil pajak outlet (Rincian F-07c).
abstract final class KodePajak {
  static const String ppn = 'Ppn';
  static const String pbjtMakananMinuman = 'PbjtMakananMinuman';

  static String AmbilLabel(String kode) => switch (kode) {
    ppn => 'PPN',
    pbjtMakananMinuman => 'PBJT',
    _ => kode,
  };
}

/// Hasil hitung keranjang: keluaran `MesinKalkulasi` beserta pajak dokumen yang dipakai (snapshot outbox) dan
/// peringatan pajak (tarif belum tersedia).
class HitunganKeranjang {
  const HitunganKeranjang({
    required this.hasil,
    required this.pajakDokumen,
    required this.tarifDipakai,
    required this.kodePajakBaris,
    required this.peringatan,
    required this.tanggalBisnis,
  });

  final HasilKalkulasi hasil;
  final List<DataPajakKalkulasi> pajakDokumen;
  final Map<String, TarifPajakLokal> tarifDipakai;
  final List<List<String>> kodePajakBaris;
  final List<String> peringatan;

  /// `YYYY-MM-DD`.
  final String tanggalBisnis;
}

/// Satu pembayaran yang dimasukkan kasir. Tunai: [jumlah] = uang diterima.
class PembayaranMasukan {
  const PembayaranMasukan({required this.metode, required this.jumlah, this.referensi});

  final BarisMetodePembayaran metode;
  final Uang jumlah;
  final String? referensi;

  bool CekTunai() => metode.Jenis == JenisMetodeBayar.tunai;

  DataPembayaranKalkulasi KeKalkulasi() =>
      DataPembayaranKalkulasi(metode: CekTunai() ? DataPembayaranKalkulasi.metodeTunai : metode.Jenis, jumlah: jumlah);
}

class PenjualanTersimpan {
  const PenjualanTersimpan({
    required this.uuid,
    required this.nomor,
    required this.totalAkhir,
    required this.totalDibayar,
    required this.kembalian,
    required this.pembayaran,
  });

  final String uuid;
  final String nomor;
  final Uang totalAkhir;
  final Uang totalDibayar;
  final Uang kembalian;
  final List<PembayaranMasukan> pembayaran;
}

/// Keputusan diskon manual terhadap batas BR-07.3.
enum StatusDiskon { Boleh, ButuhPenyetuju, MelebihiBatas }

/// Keranjang, harga, pajak, diskon, dan simpan penjualan di perangkat (F-07 mode retail, Rincian F-07c). Aturan sama
/// dengan server (Rincian F-07b) agar kasir langsung tahu bila ditolak:
/// - produk `IndukVarian`/`BahanBaku`/`Konsinyasi` dan berpelacakan batch/seri tidak bisa dijual;
/// - harga satuan dari `PenentuHarga` (outlet, kanal `BawaPulang`), total dari `MesinKalkulasi` (paket MesinKasir);
/// - pajak per produk dari kelompok pajaknya: PPN hanya bila PKP, PBJT makanan & minuman hanya bila memungut PBJT, tarif
///   dari `TarifPajak` yang berlaku pada tanggal bisnis (tanpa tarif → tidak dihitung + peringatan, CLAUDE.md #12);
/// - BR-07.3 diskon manual butuh `penjualan.diskon.manual`; di atas `BatasDiskonManual` butuh penyetuju ber-izin
///   `penjualan.diskon.setujui` sampai `BatasDiskonPenyetuju`; Pemilik tanpa batas;
/// - BR-07.4 butuh shift terbuka; BR-07.1 nomor `INV/{KodeOutlet}/{YYMMDD}/{KodePerangkat}-{SEQ4}`;
/// - penjualan + detail + pembayaran + outbox `Penjualan.Buat` dalam satu transaksi SQLite (PRD §18.3 no. 3).
class LayananPenjualan {
  LayananPenjualan({
    required this.repositori,
    required this.repositoriPenjualan,
    PembuatUlid? ulid,
    DateTime Function()? jam,
  }) : _ulid = ulid ?? PembuatUlid(),
       _jam = jam ?? DateTime.now;

  static const String jenisOutbox = 'Penjualan.Buat';
  static const String kanalBawaan = 'BawaPulang';
  static const String statusLunas = 'Lunas';

  final RepositoriKasir repositori;
  final RepositoriPenjualan repositoriPenjualan;
  final PembuatUlid _ulid;
  final DateTime Function() _jam;
  final MesinKalkulasi _mesin = const MesinKalkulasi();

  String BuatUuid() => _ulid.Buat();

  // Harga & keranjang --------------------------------------------------------------------------------------------------

  Uang? TentukanHarga(
    KatalogLokal katalog,
    KonteksPenjualan k,
    String uuidProduk,
    String uuidSatuan,
    Kuantitas jumlah,
  ) => const PenentuHarga()
      .Tentukan(
        katalog.AmbilKatalogHarga(uuidProduk),
        PermintaanHarga(
          uuidProduk: uuidProduk,
          uuidProdukSatuan: uuidSatuan,
          jumlah: jumlah,
          uuidOutlet: k.uuidOutlet,
          kanal: KanalPenjualan.BawaPulang,
          tierPelanggan: null,
          waktu: _jam().toUtc(),
        ),
      )
      ?.harga;

  /// Buat baris baru untuk [produk]. Menolak produk yang belum bisa dijual, pilihan yang tidak lengkap, dan produk
  /// tanpa harga.
  ItemKeranjang BuatBaris(
    KatalogLokal katalog,
    KonteksPenjualan k,
    ProdukJual produk, {
    SatuanJual? satuan,
    List<PilihanTerpilih> pilihan = const [],
    Kuantitas? jumlah,
    String? catatan,
  }) {
    final alasan = produk.AmbilAlasanTidakBisaDijual();
    if (alasan != null) {
      throw GalatKasir(alasan.kode, alasan.pesan);
    }
    final satuanJual = satuan ?? produk.AmbilSatuanBawaan();
    if (satuanJual == null) {
      throw GalatKasir('SatuanTidakAda', '"${produk.nama}" belum punya satuan jual. Atur di back-office menu Produk.');
    }
    ValidasiPilihan(produk, pilihan);
    final qty = jumlah ?? Kuantitas.DariBulat(1);
    final harga = TentukanHarga(katalog, k, produk.uuid, satuanJual.uuid, qty);
    if (harga == null) {
      throw GalatKasir(
        'HargaTidakDitemukan',
        'Harga "${produk.nama}" (${satuanJual.nama}) belum diatur. Atur di back-office menu Harga.',
      );
    }
    final rapi = catatan?.trim();
    return ItemKeranjang(
      uuid: BuatUuid(),
      uuidProduk: produk.uuid,
      nama: produk.nama,
      uuidProdukSatuan: satuanJual.uuid,
      namaSatuan: satuanJual.nama,
      bolehDesimal: satuanJual.bolehDesimal,
      jumlah: qty,
      hargaSatuan: harga,
      pilihan: pilihan,
      catatan: rapi == null || rapi.isEmpty ? null : rapi,
      hargaTermasukPajak: produk.hargaTermasukPajak,
      pajak: produk.pajak,
    );
  }

  /// Pilihan wajib/opsional sesuai `MinimalPilih`/`MaksimalPilih` tiap kelompok produk.
  static void ValidasiPilihan(ProdukJual produk, List<PilihanTerpilih> pilihan) {
    final dipilih = pilihan.map((p) => p.uuid).toSet();
    for (final k in produk.kelompokPilihan) {
      final jumlah = k.pilihan.where((p) => dipilih.contains(p.uuid)).length;
      if (jumlah < k.minimal) {
        throw GalatKasir('PilihanBelumLengkap', 'Pilih minimal ${k.minimal} untuk "${k.nama}".');
      }
      if (k.maksimal != null && jumlah > k.maksimal!) {
        throw GalatKasir('PilihanTerlaluBanyak', 'Pilih maksimal ${k.maksimal} untuk "${k.nama}".');
      }
    }
  }

  /// Tambah baris; produk + satuan + pilihan yang sama (tanpa catatan & diskon) → jumlah bertambah.
  Keranjang TambahBaris(Keranjang keranjang, ItemKeranjang baru, KatalogLokal katalog, KonteksPenjualan k) {
    final indeks = keranjang.baris.indexWhere((b) => b.CekBisaDigabung(baru));
    if (indeks < 0) {
      return keranjang.Salin(baris: [...keranjang.baris, baru]);
    }
    final lama = keranjang.baris[indeks];
    return UbahJumlah(keranjang, lama.uuid, lama.jumlah.Tambah(baru.jumlah), katalog, k);
  }

  /// Ubah jumlah baris (≤ 0 = hapus). Harga ditentukan ulang karena harga bertingkat bergantung jumlah.
  Keranjang UbahJumlah(
    Keranjang keranjang,
    String uuidBaris,
    Kuantitas jumlah,
    KatalogLokal katalog,
    KonteksPenjualan k,
  ) {
    if (jumlah.Bandingkan(Kuantitas.Nol()) <= 0) {
      return HapusBaris(keranjang, uuidBaris);
    }
    return _UbahBaris(keranjang, uuidBaris, (b) {
      if (!b.bolehDesimal && jumlah.KeDesimal() != jumlah.KeDesimal().truncate()) {
        throw GalatKasir('JumlahTidakValid', 'Jumlah ${b.namaSatuan ?? 'satuan ini'} harus bilangan bulat.');
      }
      final harga = b.uuidProdukSatuan == null
          ? null
          : TentukanHarga(katalog, k, b.uuidProduk, b.uuidProdukSatuan!, jumlah);
      return b.Salin(jumlah: jumlah, hargaSatuan: harga ?? b.hargaSatuan);
    });
  }

  Keranjang GantiSatuan(
    Keranjang keranjang,
    String uuidBaris,
    SatuanJual satuan,
    KatalogLokal katalog,
    KonteksPenjualan k,
  ) => _UbahBaris(keranjang, uuidBaris, (b) {
    final jumlah = satuan.bolehDesimal ? b.jumlah : Kuantitas.DariDesimal(b.jumlah.KeDesimal().ceil());
    final harga = TentukanHarga(katalog, k, b.uuidProduk, satuan.uuid, jumlah);
    if (harga == null) {
      throw GalatKasir('HargaTidakDitemukan', 'Harga "${b.nama}" per ${satuan.nama} belum diatur.');
    }
    return b.Salin(
      uuidProdukSatuan: satuan.uuid,
      namaSatuan: satuan.nama,
      bolehDesimal: satuan.bolehDesimal,
      jumlah: jumlah,
      hargaSatuan: harga,
    );
  });

  Keranjang AturPilihan(Keranjang keranjang, String uuidBaris, ProdukJual produk, List<PilihanTerpilih> pilihan) {
    ValidasiPilihan(produk, pilihan);
    return _UbahBaris(keranjang, uuidBaris, (b) => b.Salin(pilihan: pilihan));
  }

  Keranjang AturCatatan(Keranjang keranjang, String uuidBaris, String? catatan) {
    final rapi = catatan?.trim();
    return _UbahBaris(keranjang, uuidBaris, (b) => b.Salin(catatan: () => rapi == null || rapi.isEmpty ? null : rapi));
  }

  Keranjang HapusBaris(Keranjang keranjang, String uuidBaris) {
    final baris = keranjang.baris.where((b) => b.uuid != uuidBaris).toList();
    return baris.isEmpty ? Keranjang.kosong : keranjang.Salin(baris: baris);
  }

  Keranjang _UbahBaris(Keranjang keranjang, String uuidBaris, ItemKeranjang Function(ItemKeranjang) ubah) =>
      keranjang.Salin(baris: [for (final b in keranjang.baris) b.uuid == uuidBaris ? ubah(b) : b]);

  // Hitung & pajak -----------------------------------------------------------------------------------------------------

  HitunganKeranjang Hitung(
    Keranjang keranjang,
    KonteksPenjualan k, {
    List<DataPembayaranKalkulasi> pembayaran = const [],
  }) {
    final tanggal = k.HitungTanggalBisnis(_jam());
    final pajakDokumen = <String, DataPajakKalkulasi>{};
    final tarifDipakai = <String, TarifPajakLokal>{};
    final peringatan = <String>{};
    final kodeBaris = <List<String>>[];

    for (final b in keranjang.baris) {
      final kode = <String>[];
      for (final p in b.pajak) {
        if (p.kode == KodePajak.ppn && !k.profilPajak.pkp) {
          continue;
        }
        if (p.kode == KodePajak.pbjtMakananMinuman && !k.profilPajak.pungutPbjt) {
          continue;
        }
        final tarif = k.CariTarif(p.kode, tanggal);
        if (tarif == null) {
          peringatan.add(
            'Tarif ${KodePajak.AmbilLabel(p.kode)} belum tersedia di perangkat, jadi pajak ini tidak dihitung. '
            'Sambungkan ke internet agar tarif terbaru terunduh.',
          );
          continue;
        }
        if (!kode.contains(p.kode)) {
          kode.add(p.kode);
        }
        tarifDipakai.putIfAbsent(p.kode, () => tarif);
        pajakDokumen.putIfAbsent(
          p.kode,
          () => DataPajakKalkulasi(
            kode: p.kode,
            tarif: Decimal.parse(tarif.tarif),
            pengaliDpp: Rational(BigInt.from(tarif.pembilang), BigInt.from(tarif.penyebut)),
            dasarPengenaan: p.dasarPengenaan == DasarPengenaanPajak.SubtotalPlusLayanan.name
                ? DasarPengenaanPajak.SubtotalPlusLayanan
                : DasarPengenaanPajak.Subtotal,
          ),
        );
      }
      kodeBaris.add(kode);
    }

    final hasil = _mesin.Hitung(
      DataKalkulasi(
        hargaTermasukPajak: k.profilPajak.hargaTermasukPajak,
        persenBiayaLayanan: k.AmbilPersenBiayaLayanan(),
        pembulatanTunai: k.pembulatanTunai,
        pajak: pajakDokumen.values.toList(),
        baris: [
          for (var i = 0; i < keranjang.baris.length; i++)
            DataBarisKalkulasi(
              jumlah: keranjang.baris[i].jumlah,
              hargaSatuan: keranjang.baris[i].hargaSatuan,
              hargaPilihan: keranjang.baris[i].AmbilHargaPilihan(),
              hargaTermasukPajak: keranjang.baris[i].hargaTermasukPajak,
              kodePajak: kodeBaris[i],
              potongan: [if (keranjang.baris[i].diskon != null) keranjang.baris[i].diskon!.KePotongan()],
            ),
        ],
        potonganPesanan: [if (keranjang.diskonPesanan != null) keranjang.diskonPesanan!.KePotongan()],
        pembayaran: pembayaran,
      ),
    );

    return HitunganKeranjang(
      hasil: hasil,
      pajakDokumen: pajakDokumen.values.toList(),
      tarifDipakai: tarifDipakai,
      kodePajakBaris: kodeBaris,
      peringatan: peringatan.toList(),
      tanggalBisnis: tanggal,
    );
  }

  /// Tagihan untuk bagian tunai bila sisa dibayar tunai: total (dengan pembulatan tunai, BR-08.6) − yang sudah dibayar.
  Uang HitungTagihanTunai(Keranjang keranjang, KonteksPenjualan k, List<PembayaranMasukan> pembayaran) {
    final nonTunai = pembayaran.where((p) => !p.CekTunai()).toList();
    final hitungan = Hitung(
      keranjang,
      k,
      pembayaran: [
        for (final p in nonTunai) p.KeKalkulasi(),
        const DataPembayaranKalkulasi(metode: 'Tunai'),
      ],
    );
    final dibayar = nonTunai.fold(Uang.Nol(), (total, p) => total.Tambah(p.jumlah));
    return hitungan.hasil.totalAkhir.Kurangi(dibayar);
  }

  // Diskon (BR-07.3) ---------------------------------------------------------------------------------------------------

  /// Persen efektif diskon terhadap [dasar] (bruto baris atau subtotal untuk pesanan).
  static Rational HitungPersenEfektif(Uang dasar, DiskonManual diskon) {
    if (diskon.persen != null) {
      return diskon.persen!.toRational();
    }
    if (dasar.Bandingkan(Uang.Nol()) <= 0) {
      return Rational.fromInt(100);
    }
    return diskon.jumlah!.KeDesimal().toRational() * Rational.fromInt(100) / dasar.KeDesimal().toRational();
  }

  /// Validasi bentuk diskon sebelum diterapkan: persen 0–100, nominal tidak melebihi [dasar].
  static void ValidasiBentukDiskon(Uang dasar, DiskonManual diskon) {
    final persen = diskon.persen;
    if (persen != null && (persen <= Decimal.zero || persen > Decimal.fromInt(100))) {
      throw const GalatKasir('DiskonTidakValid', 'Persen diskon harus lebih dari 0 dan paling besar 100.');
    }
    final jumlah = diskon.jumlah;
    if (jumlah != null && (jumlah.Bandingkan(Uang.Nol()) <= 0 || jumlah.Bandingkan(dasar) > 0)) {
      throw GalatKasir('DiskonTidakValid', 'Diskon harus lebih dari Rp 0 dan paling besar ${dasar.FormatRupiah()}.');
    }
  }

  /// Penyetuju efektif: penyetuju yang lolos PIN, atau kasir sendiri bila ia punya izin menyetujui.
  static PenyetujuDiskon? AmbilPenyetujuEfektif(StafLokal kasir, PenyetujuDiskon? penyetuju) =>
      penyetuju ??
      (kasir.PunyaIzin(IzinKasir.penjualanDiskonSetujui)
          ? PenyetujuDiskon(uuid: kasir.uuid, nama: kasir.nama, pemilik: kasir.pemilik)
          : null);

  static StatusDiskon PeriksaDiskon({
    required Uang dasar,
    required DiskonManual diskon,
    required StafLokal kasir,
    required KonteksPenjualan k,
    PenyetujuDiskon? penyetuju,
  }) {
    if (!kasir.PunyaIzin(IzinKasir.penjualanDiskonManual)) {
      throw GalatKasir('TanpaIzin', '${kasir.nama} tidak punya izin memberi diskon manual.');
    }
    final persen = HitungPersenEfektif(dasar, diskon);
    if (kasir.pemilik || persen <= k.batasDiskonManual.toRational()) {
      return StatusDiskon.Boleh;
    }
    final penyetujuEfektif = AmbilPenyetujuEfektif(kasir, penyetuju);
    if (penyetujuEfektif?.pemilik ?? false) {
      return StatusDiskon.Boleh;
    }
    if (persen > k.batasDiskonPenyetuju.toRational()) {
      return StatusDiskon.MelebihiBatas;
    }
    return penyetujuEfektif == null ? StatusDiskon.ButuhPenyetuju : StatusDiskon.Boleh;
  }

  /// Periksa semua diskon keranjang terhadap batas. Kembalikan penyetuju yang dicatat (`UuidPenyetujuDiskon`), atau
  /// null bila semua diskon di bawah batas manual.
  static PenyetujuDiskon? ValidasiDiskon(
    Keranjang keranjang,
    HitunganKeranjang hitungan,
    StafLokal kasir,
    KonteksPenjualan k,
  ) {
    var butuhPenyetuju = false;
    void Periksa(String nama, Uang dasar, DiskonManual diskon) {
      final status = PeriksaDiskon(dasar: dasar, diskon: diskon, kasir: kasir, k: k, penyetuju: keranjang.penyetuju);
      if (status == StatusDiskon.MelebihiBatas) {
        throw GalatKasir(
          'DiskonMelebihiBatas',
          'Diskon $nama melebihi batas ${k.batasDiskonPenyetuju}%. Hanya Pemilik yang bisa menyetujuinya.',
        );
      }
      if (status == StatusDiskon.ButuhPenyetuju) {
        throw GalatKasir(
          'PersetujuanDiperlukan',
          'Diskon $nama di atas ${k.batasDiskonManual}% wajib disetujui supervisor dengan PIN.',
        );
      }
      if (!kasir.pemilik && HitungPersenEfektif(dasar, diskon) > k.batasDiskonManual.toRational()) {
        butuhPenyetuju = true;
      }
    }

    for (var i = 0; i < keranjang.baris.length; i++) {
      final diskon = keranjang.baris[i].diskon;
      if (diskon != null) {
        Periksa('"${keranjang.baris[i].nama}"', hitungan.hasil.baris[i].bruto, diskon);
      }
    }
    if (keranjang.diskonPesanan != null) {
      Periksa('pesanan', hitungan.hasil.subtotal, keranjang.diskonPesanan!);
    }
    return butuhPenyetuju ? AmbilPenyetujuEfektif(kasir, keranjang.penyetuju) : null;
  }

  // Bayar & simpan -----------------------------------------------------------------------------------------------------

  Future<PenjualanTersimpan> Bayar({
    required Keranjang keranjang,
    required List<PembayaranMasukan> pembayaran,
    required StafLokal kasir,
    required KonteksPenjualan k,
  }) async {
    final shift = await repositori.AmbilShiftAktif();
    if (shift == null) {
      throw const GalatKasir('ShiftTidakDitemukan', 'Belum ada shift terbuka. Buka shift dulu sebelum berjualan.');
    }
    if (!kasir.PunyaIzin(IzinKasir.penjualanBuat)) {
      throw GalatKasir('TanpaIzin', '${kasir.nama} tidak punya izin berjualan.');
    }
    if (keranjang.CekKosong) {
      throw const GalatKasir('KeranjangKosong', 'Keranjang masih kosong. Tambahkan produk dulu.');
    }
    final kodeOutlet = k.kodeOutlet ?? '';
    final kodePerangkat = k.kodePerangkat ?? '';
    if (kodeOutlet.isEmpty || kodePerangkat.isEmpty) {
      throw const GalatKasir(
        'DataAwalBelumLengkap',
        'Kode outlet atau perangkat belum ada di perangkat ini. Sambungkan ke internet agar data terbaru terunduh.',
      );
    }

    ValidasiPembayaran(pembayaran);
    final hitungan = Hitung(keranjang, k, pembayaran: [for (final p in pembayaran) p.KeKalkulasi()]);
    final hasil = hitungan.hasil;
    final nonTunai = pembayaran.where((p) => !p.CekTunai()).fold(Uang.Nol(), (t, p) => t.Tambah(p.jumlah));
    if (nonTunai.Bandingkan(hasil.totalAkhir) > 0) {
      throw const GalatKasir('PembayaranMelebihiTotal', 'Pembayaran non-tunai melebihi total belanja.');
    }
    final dibayar = pembayaran.fold(Uang.Nol(), (t, p) => t.Tambah(p.jumlah));
    if (dibayar.Bandingkan(hasil.totalAkhir) < 0) {
      throw GalatKasir('PembayaranKurang', 'Pembayaran kurang ${hasil.totalAkhir.Kurangi(dibayar).FormatRupiah()}.');
    }
    final penyetuju = ValidasiDiskon(keranjang, hitungan, kasir, k);

    final sekarang = _jam().toUtc();
    final t = hitungan.tanggalBisnis;
    final yymmdd = '${t.substring(2, 4)}${t.substring(5, 7)}${t.substring(8, 10)}';
    final uuid = BuatUuid();
    final uuidPembayaran = [for (final _ in pembayaran) BuatUuid()];
    final kembalian = hasil.kembalian ?? Uang.Nol();

    final dokumen = await repositoriPenjualan.SimpanPenjualan(
      kodePerangkat: kodePerangkat,
      tanggal: yymmdd,
      sekarang: sekarang,
      susun: (urut) {
        final nomor = 'INV/$kodeOutlet/$yymmdd/$kodePerangkat-${urut.toString().padLeft(4, '0')}';
        return SusunDokumen(
          uuid: uuid,
          nomor: nomor,
          shift: shift,
          kasir: kasir,
          keranjang: keranjang,
          hitungan: hitungan,
          pembayaran: pembayaran,
          uuidPembayaran: uuidPembayaran,
          penyetuju: penyetuju,
          k: k,
          sekarang: sekarang,
        );
      },
    );

    return PenjualanTersimpan(
      uuid: uuid,
      nomor: dokumen.penjualan.Nomor.value,
      totalAkhir: hasil.totalAkhir,
      totalDibayar: dibayar,
      kembalian: kembalian,
      pembayaran: pembayaran,
    );
  }

  /// Aturan pembayaran fase 1 (Rincian F-07b langkah 9) yang bisa diperiksa sebelum dihitung.
  static void ValidasiPembayaran(List<PembayaranMasukan> pembayaran) {
    if (pembayaran.isEmpty) {
      throw const GalatKasir('PembayaranKurang', 'Pilih metode pembayaran dulu.');
    }
    if (pembayaran.where((p) => p.CekTunai()).length > 1) {
      throw const GalatKasir('TunaiGanda', 'Pembayaran tunai hanya boleh satu kali per transaksi.');
    }
    for (final p in pembayaran) {
      if (!JenisMetodeBayar.fase1.contains(p.metode.Jenis)) {
        throw GalatKasir('MetodeBayarBelumDidukung', 'Metode ${p.metode.Nama} belum didukung aplikasi kasir.');
      }
      if (p.jumlah.Bandingkan(Uang.Nol()) <= 0) {
        throw GalatKasir('JumlahTidakValid', 'Jumlah pembayaran ${p.metode.Nama} harus lebih dari Rp 0.');
      }
    }
  }

  /// Susun penjualan lokal + payload outbox `Penjualan.Buat` persis kontrak Rincian F-07b (kunci PascalCase, uang
  /// string desimal, jumlah string desimal).
  static DokumenPenjualan SusunDokumen({
    required String uuid,
    required String nomor,
    required BarisShift shift,
    required StafLokal kasir,
    required Keranjang keranjang,
    required HitunganKeranjang hitungan,
    required List<PembayaranMasukan> pembayaran,
    required List<String> uuidPembayaran,
    required PenyetujuDiskon? penyetuju,
    required KonteksPenjualan k,
    required DateTime sekarang,
  }) {
    final hasil = hitungan.hasil;
    final kembalian = hasil.kembalian ?? Uang.Nol();
    final dibayar = pembayaran.fold(Uang.Nol(), (t, p) => t.Tambah(p.jumlah));
    final pembulatan = k.pembulatanTunai;
    final catatan = keranjang.catatan?.trim();

    final data = <String, Object?>{
      'UuidShift': shift.Uuid,
      'UuidPengguna': kasir.uuid,
      'Nomor': nomor,
      'Kanal': kanalBawaan,
      'DibuatPada': sekarang.toIso8601String(),
      'HargaTermasukPajak': k.profilPajak.hargaTermasukPajak,
      'PersenBiayaLayanan': k.AmbilPersenBiayaLayanan().toString(),
      'PembulatanTunai': pembulatan == null ? null : {'Kelipatan': pembulatan.kelipatan, 'Arah': pembulatan.arah.name},
      'Pajak': [
        for (final p in hitungan.pajakDokumen)
          {
            'Kode': p.kode,
            'Tarif': hitungan.tarifDipakai[p.kode]!.tarif,
            'PengaliDppPembilang': hitungan.tarifDipakai[p.kode]!.pembilang,
            'PengaliDppPenyebut': hitungan.tarifDipakai[p.kode]!.penyebut,
            'DasarPengenaan': p.dasarPengenaan.name,
          },
      ],
      'Baris': [
        for (var i = 0; i < keranjang.baris.length; i++)
          {
            'Uuid': keranjang.baris[i].uuid,
            'UuidProduk': keranjang.baris[i].uuidProduk,
            'UuidProdukSatuan': keranjang.baris[i].uuidProdukSatuan,
            'Jumlah': keranjang.baris[i].jumlah.KeString(),
            'HargaSatuan': keranjang.baris[i].hargaSatuan.KeString(),
            'HargaPilihan': keranjang.baris[i].AmbilHargaPilihan().KeString(),
            'Pilihan': [for (final p in keranjang.baris[i].pilihan) p.KeJson()],
            'HargaTermasukPajak': keranjang.baris[i].hargaTermasukPajak,
            'KodePajak': hitungan.kodePajakBaris[i],
            'DiskonManual': keranjang.baris[i].diskon?.KeJson(),
            'Catatan': keranjang.baris[i].catatan,
          },
      ],
      'DiskonManualPesanan': keranjang.diskonPesanan?.KeJson(),
      'UuidPenyetujuDiskon': penyetuju?.uuid,
      'Pembayaran': [
        for (var i = 0; i < pembayaran.length; i++)
          {
            'Uuid': uuidPembayaran[i],
            'UuidMetodePembayaran': pembayaran[i].metode.Uuid,
            'Jumlah': pembayaran[i].jumlah.KeString(),
            'Referensi': pembayaran[i].referensi,
          },
      ],
      'Ringkasan': {
        'Subtotal': hasil.subtotal.KeString(),
        'TotalPajak': hasil.totalPajak.KeString(),
        'Pembulatan': hasil.pembulatan.KeString(),
        'TotalAkhir': hasil.totalAkhir.KeString(),
        'Kembalian': kembalian.KeString(),
      },
      'Catatan': catatan == null || catatan.isEmpty ? null : catatan,
    };

    return DokumenPenjualan(
      penjualan: PenjualanCompanion.insert(
        Uuid: uuid,
        Nomor: nomor,
        UuidShift: shift.Uuid,
        UuidPengguna: kasir.uuid,
        NamaKasir: kasir.nama,
        Kanal: kanalBawaan,
        DibuatPada: sekarang,
        TanggalBisnis: hitungan.tanggalBisnis,
        Status: statusLunas,
        Subtotal: hasil.subtotal.KeString(),
        TotalDiskon: hasil.totalDiskon.KeString(),
        BiayaLayanan: hasil.biayaLayanan.KeString(),
        TotalPajak: hasil.totalPajak.KeString(),
        Pembulatan: hasil.pembulatan.KeString(),
        TotalAkhir: hasil.totalAkhir.KeString(),
        TotalDibayar: dibayar.KeString(),
        Kembalian: kembalian.KeString(),
        UuidPenyetujuDiskon: Value(penyetuju?.uuid),
        Catatan: Value(data['Catatan'] as String?),
      ),
      detail: [
        for (var i = 0; i < keranjang.baris.length; i++)
          PenjualanDetailCompanion.insert(
            Uuid: keranjang.baris[i].uuid,
            UuidPenjualan: uuid,
            Urutan: i + 1,
            UuidProduk: keranjang.baris[i].uuidProduk,
            UuidProdukSatuan: Value(keranjang.baris[i].uuidProdukSatuan),
            NamaProduk: keranjang.baris[i].nama,
            NamaSatuan: Value(keranjang.baris[i].namaSatuan),
            Jumlah: keranjang.baris[i].jumlah.KeString(),
            HargaSatuan: keranjang.baris[i].hargaSatuan.KeString(),
            HargaPilihan: keranjang.baris[i].AmbilHargaPilihan().KeString(),
            Pilihan: jsonEncode([for (final p in keranjang.baris[i].pilihan) p.KeJson()]),
            Bruto: hasil.baris[i].bruto.KeString(),
            Diskon: hasil.baris[i].diskon.KeString(),
            DiskonPesanan: hasil.baris[i].diskonPesanan.KeString(),
            BiayaLayanan: hasil.baris[i].biayaLayanan.KeString(),
            JumlahPajak: hasil.baris[i].pajak.KeString(),
            PajakEksklusif: hasil.baris[i].pajakEksklusif.KeString(),
            TotalBaris: hasil.baris[i].totalBaris.KeString(),
            Catatan: Value(keranjang.baris[i].catatan),
          ),
      ],
      pembayaran: [
        for (var i = 0; i < pembayaran.length; i++)
          PenjualanPembayaranCompanion.insert(
            Uuid: uuidPembayaran[i],
            UuidPenjualan: uuid,
            UuidMetodePembayaran: pembayaran[i].metode.Uuid,
            Jenis: pembayaran[i].metode.Jenis,
            NamaMetode: pembayaran[i].metode.Nama,
            Jumlah: pembayaran[i].jumlah.KeString(),
            Referensi: Value(pembayaran[i].referensi),
          ),
      ],
      outbox: ItemOutbox(jenis: jenisOutbox, uuid: uuid, data: data),
    );
  }

  // Pesanan tertahan ---------------------------------------------------------------------------------------------------

  /// Simpan keranjang sebagai pesanan tertahan lokal (tidak dikirim ke server, Rincian F-07b).
  Future<void> TahanPesanan(Keranjang keranjang, StafLokal kasir, Uang total) async {
    if (keranjang.CekKosong) {
      throw const GalatKasir('KeranjangKosong', 'Keranjang masih kosong.');
    }
    final sekarang = _jam().toUtc();
    final lokal = sekarang.toLocal();
    final jam = '${lokal.hour.toString().padLeft(2, '0')}.${lokal.minute.toString().padLeft(2, '0')}';
    await repositoriPenjualan.SimpanPesananTertahan(
      PesananTertahanCompanion.insert(
        Uuid: BuatUuid(),
        Label:
            '${keranjang.baris.first.nama}${keranjang.baris.length > 1 ? ' +${keranjang.baris.length - 1}' : ''} · $jam',
        Data: jsonEncode(keranjang.KeJson()),
        Total: total.KeString(),
        JumlahItem: keranjang.baris.length,
        UuidPengguna: kasir.uuid,
        DibuatPada: sekarang,
      ),
    );
  }

  /// Buka pesanan tertahan: kembalikan keranjangnya dan hapus dari daftar tertahan.
  Future<Keranjang> BukaPesanan(String uuid) async {
    final baris = await repositoriPenjualan.CariPesananTertahan(uuid);
    if (baris == null) {
      throw const GalatKasir('PesananTidakDitemukan', 'Pesanan tertahan ini sudah dibuka atau dibatalkan.');
    }
    final keranjang = Keranjang.DariJson(jsonDecode(baris.Data) as Map<String, Object?>);
    await repositoriPenjualan.HapusPesananTertahan(uuid);
    return keranjang;
  }
}
