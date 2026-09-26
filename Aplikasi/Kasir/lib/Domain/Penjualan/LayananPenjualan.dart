import 'dart:convert';

import 'package:drift/drift.dart' show Value;
import 'package:klien_api/KlienApi.dart';
import 'package:mesin_kasir/MesinKasir.dart';

import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Data/RepositoriKasir.dart';
import '../../Data/RepositoriPelanggan.dart';
import '../../Data/RepositoriPenjualan.dart';
import '../GalatKasir.dart';
import '../Katalog/KatalogLokal.dart';
import '../Sesi/StafLokal.dart';
import 'Keranjang.dart';
import 'KonteksPenjualan.dart';

/// Label jenis pajak untuk kasir menurut kategorinya (`Ppn`/`Pbjt`, PRD v1.46); kategori lain memakai kodenya.
abstract final class KodePajak {
  static String AmbilLabel(PajakProduk pajak) => switch (pajak.AmbilKategori()) {
    PajakKelompokPos.kategoriPpn => 'PPN',
    PajakKelompokPos.kategoriPbjt => 'PBJT',
    _ => pajak.kode,
  };
}

/// Hasil hitung keranjang: keluaran `MesinKalkulasi` beserta pajak dokumen yang dipakai (snapshot outbox) dan
/// peringatan pajak (tarif belum tersedia). F-16c: promo otomatis yang diterapkan dan hasil tanpa promo (dasar batas
/// diskon manual, sama dengan server).
class HitunganKeranjang {
  const HitunganKeranjang({
    required this.hasil,
    this.hasilDasar,
    this.promoTerpakai = const [],
    this.namaPromo = const {},
    required this.pajakDokumen,
    required this.tarifDipakai,
    required this.kodePajakBaris,
    required this.peringatan,
    required this.tanggalBisnis,
    this.labelPajak = const {},
  });

  HasilKalkulasi get hasilTanpaPromo => hasilDasar ?? hasil;

  String AmbilNamaPromo(PromoTerpakai p) => namaPromo[p.uuid] ?? p.kode;

  /// Σ potongan promo pesanan (bagian `hasil.diskonPesanan`).
  Uang HitungDiskonPromoPesanan() => promoTerpakai.fold(Uang.Nol(), (t, p) => t.Tambah(p.diskonPesanan));

  final HasilKalkulasi hasil;

  /// Hasil tanpa promo; null = sama dengan [hasil].
  final HasilKalkulasi? hasilDasar;
  final List<PromoTerpakai> promoTerpakai;

  /// Nama promo per Uuid untuk tampilan.
  final Map<String, String> namaPromo;
  final List<DataPajakKalkulasi> pajakDokumen;
  final Map<String, TarifPajakLokal> tarifDipakai;
  final List<List<String>> kodePajakBaris;
  final List<String> peringatan;

  /// `YYYY-MM-DD`.
  final String tanggalBisnis;

  /// Label tampilan per kode pajak dokumen (`PPN`/`PBJT` menurut kategori jenis pajak).
  final Map<String, String> labelPajak;
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
    this.namaPelanggan,
  });

  final String uuid;
  final String nomor;
  final Uang totalAkhir;
  final Uang totalDibayar;
  final Uang kembalian;
  final List<PembayaranMasukan> pembayaran;

  /// Untuk struk yang dicetak langsung setelah bayar (nama pelanggan tidak disimpan di tabel penjualan lokal).
  final String? namaPelanggan;
}

/// Keputusan diskon manual terhadap batas BR-07.3.
enum StatusDiskon { Boleh, ButuhPenyetuju, MelebihiBatas }

/// Keranjang, harga, pajak, diskon, dan simpan penjualan di perangkat (F-07 mode retail, Rincian F-07c). Aturan sama
/// dengan server (Rincian F-07b) agar kasir langsung tahu bila ditolak:
/// - produk `IndukVarian`/`BahanBaku`/`Konsinyasi` dan berpelacakan batch/seri tidak bisa dijual;
/// - harga satuan dari `PenentuHarga` (outlet, kanal `BawaPulang`), total dari `MesinKalkulasi` (paket MesinKasir);
/// - pajak per produk dari kelompok pajaknya: PPN hanya bila PKP, PBJT makanan & minuman hanya bila memungut PBJT, tarif
///   dari `TarifPajak` yang berlaku pada tanggal bisnis (tanpa tarif → tidak dihitung + peringatan, CLAUDE.md #12);
/// - BR-07.3 diskon manual sampai `BatasDiskonManual` oleh kasir ber-izin `penjualan.diskon.manual`; kasir tanpa izin
///   itu atau di atas batas butuh penyetuju ber-izin `penjualan.diskon.setujui` sampai `BatasDiskonPenyetuju`;
///   Pemilik tanpa batas. Persen = diskon hasil mesin ÷ bruto baris (pesanan: ÷ subtotal);
/// - BR-07.4 butuh shift terbuka; BR-07.1 nomor `INV/{KodeOutlet}/{YYMMDD}/{KodePerangkat}-{SEQ4}`;
/// - penjualan + detail + pembayaran + outbox `Penjualan.Buat` dalam satu transaksi SQLite (PRD §18.3 no. 3).
class LayananPenjualan {
  LayananPenjualan({
    required this.repositori,
    required this.repositoriPenjualan,
    this.repositoriPelanggan,
    PembuatUlid? ulid,
    DateTime Function()? jam,
  }) : _ulid = ulid ?? PembuatUlid(),
       _jam = jam ?? DateTime.now;

  static const String jenisOutbox = 'Penjualan.Buat';
  static const String kanalBawaan = 'BawaPulang';

  /// Kanal pembayaran pesanan meja (F-07 mode meja).
  static const String kanalMakanDiTempat = 'MakanDiTempat';
  static const String statusLunas = 'Lunas';

  final RepositoriKasir repositori;
  final RepositoriPenjualan repositoriPenjualan;

  /// F-12: cache posisi kredit pelanggan (sisa piutang bertambah setelah penjualan tempo); null = tidak diperbarui.
  final RepositoriPelanggan? repositoriPelanggan;
  final PembuatUlid _ulid;
  final DateTime Function() _jam;
  final MesinKalkulasi _mesin = const MesinKalkulasi();

  String BuatUuid() => _ulid.Buat();

  // Harga & keranjang --------------------------------------------------------------------------------------------------

  /// F-16b: harga ulang semua baris keranjang menurut kanal & tier pelanggan saat ini (dipanggil setelah pelanggan
  /// dipilih/dilepas). Tanpa harga yang cocok = harga lama.
  Keranjang HitungUlangHarga(Keranjang keranjang, KatalogLokal katalog, KonteksPenjualan k) => keranjang.Salin(
    baris: [
      for (final b in keranjang.baris)
        b.uuidProdukSatuan == null
            ? b
            : b.Salin(
                hargaSatuan:
                    TentukanHarga(
                      katalog,
                      k,
                      b.uuidProduk,
                      b.uuidProdukSatuan!,
                      b.jumlah,
                      kanal: AmbilKanal(keranjang),
                      tierPelanggan: keranjang.pelanggan?.kodeTier,
                    ) ??
                    b.hargaSatuan,
              ),
    ],
  );

  /// Kanal harga keranjang: pesanan di meja = `MakanDiTempat` (daftar harga dine-in), selain itu `BawaPulang`.
  static KanalPenjualan AmbilKanal(Keranjang keranjang) =>
      keranjang.pesananMeja?.uuidMeja != null ? KanalPenjualan.MakanDiTempat : KanalPenjualan.BawaPulang;

  Uang? TentukanHarga(
    KatalogLokal katalog,
    KonteksPenjualan k,
    String uuidProduk,
    String uuidSatuan,
    Kuantitas jumlah, {
    KanalPenjualan kanal = KanalPenjualan.BawaPulang,
    String? tierPelanggan,
  }) => const PenentuHarga()
      .Tentukan(
        katalog.AmbilKatalogHarga(uuidProduk),
        PermintaanHarga(
          uuidProduk: uuidProduk,
          uuidProdukSatuan: uuidSatuan,
          jumlah: jumlah,
          uuidOutlet: k.uuidOutlet,
          kanal: kanal,
          tierPelanggan: tierPelanggan,
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
    KanalPenjualan kanal = KanalPenjualan.BawaPulang,
    String? tierPelanggan,
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
    final harga = TentukanHarga(
      katalog,
      k,
      produk.uuid,
      satuanJual.uuid,
      qty,
      kanal: kanal,
      tierPelanggan: tierPelanggan,
    );
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
          : TentukanHarga(
              katalog,
              k,
              b.uuidProduk,
              b.uuidProdukSatuan!,
              jumlah,
              kanal: AmbilKanal(keranjang),
              tierPelanggan: keranjang.pelanggan?.kodeTier,
            );
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
    final harga = TentukanHarga(
      katalog,
      k,
      b.uuidProduk,
      satuan.uuid,
      jumlah,
      kanal: AmbilKanal(keranjang),
      tierPelanggan: keranjang.pelanggan?.kodeTier,
    );
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

  /// [metodeBayar]: Uuid metode semua pembayaran transaksi (F-16c bagian 3, promo metode bayar); null = belum memilih
  /// pembayaran (promo metode bayar tidak berlaku, seperti di server).
  HitunganKeranjang Hitung(
    Keranjang keranjang,
    KonteksPenjualan k, {
    List<DataPembayaranKalkulasi> pembayaran = const [],
    List<String>? metodeBayar,
  }) {
    final tanggal = k.HitungTanggalBisnis(_jam());
    final pajakDokumen = <String, DataPajakKalkulasi>{};
    final tarifDipakai = <String, TarifPajakLokal>{};
    final peringatan = <String>{};
    final labelPajak = <String, String>{};
    final kodeBaris = <List<String>>[];

    for (final b in keranjang.baris) {
      final kode = <String>[];
      for (final p in b.pajak) {
        // Syarat profil pajak outlet menurut kategori jenis pajak (PRD v1.46), bukan string kode tetap.
        final kategori = p.AmbilKategori();
        if (kategori == PajakKelompokPos.kategoriPpn && !k.profilPajak.pkp) {
          continue;
        }
        if (kategori == PajakKelompokPos.kategoriPbjt && !k.profilPajak.pungutPbjt) {
          continue;
        }
        final tarif = k.CariTarif(p.kode, tanggal);
        if (tarif == null) {
          peringatan.add(
            'Tarif ${KodePajak.AmbilLabel(p)} belum tersedia di perangkat, jadi pajak ini tidak dihitung. '
            'Sambungkan ke internet agar tarif terbaru terunduh.',
          );
          continue;
        }
        if (!kode.contains(p.kode)) {
          kode.add(p.kode);
        }
        tarifDipakai.putIfAbsent(p.kode, () => tarif);
        labelPajak.putIfAbsent(p.kode, () => KodePajak.AmbilLabel(p));
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

    final dasar = DataKalkulasi(
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
      tukarPoin: keranjang.tukarPoin?.nilai,
      pembayaran: pembayaran,
    );
    final hasilTanpaPromo = _mesin.Hitung(dasar);
    var hasil = hasilTanpaPromo;
    var promoTerpakai = const <PromoTerpakai>[];
    // F-16c bagian 2: promo voucher ikut dievaluasi walau daftar promo tersimpan belum memuatnya.
    final voucher = keranjang.voucher;
    final promoVoucher = voucher == null || k.promo.any((p) => p.uuid == voucher.uuidPromo)
        ? null
        : KonteksPenjualan.UraiPromo(PromoPos.DariJson(voucher.promo));
    final daftarPromo = [...k.promo, ?promoVoucher];
    if (daftarPromo.isNotEmpty && keranjang.baris.isNotEmpty) {
      final waktu = _jam().toUtc();
      final hasilPromo = const MesinPromo().Terapkan(
        dasar,
        [
          for (final b in keranjang.baris)
            BarisPromo(uuidProduk: b.uuidProduk, uuidKategori: k.kategoriProduk[b.uuidProduk]),
        ],
        daftarPromo,
        KonteksPromo(
          waktu: waktu,
          waktuLokal: ZonaWaktuOutlet.KeWaktuOutlet(waktu, k.zonaWaktu),
          uuidOutlet: k.uuidOutlet,
          kanal: AmbilKanal(keranjang),
          tier: keranjang.pelanggan?.kodeTier,
          voucher: [?voucher?.uuidPromo],
          // F-16c bagian 3: data pelanggan terakhir yang diketahui perangkat; server menilai ulang saat sinkron.
          metodeBayar: metodeBayar,
          berpelanggan: keranjang.pelanggan != null,
          tanggalLahir: keranjang.pelanggan?.hariLahir == null ? null : '2000-${keranjang.pelanggan!.hariLahir}',
          jumlahTransaksiPelanggan: keranjang.pelanggan?.jumlahTransaksi,
          pemakaianPelanggan: keranjang.pelanggan?.AmbilPemakaianPada(tanggal) ?? const {},
        ),
        mode: k.modeResolusiPromo,
      );
      hasil = hasilPromo.hasil;
      promoTerpakai = hasilPromo.terpakai;
    }

    return HitunganKeranjang(
      hasil: hasil,
      hasilDasar: hasilTanpaPromo,
      promoTerpakai: promoTerpakai,
      namaPromo: voucher == null ? k.namaPromo : {...k.namaPromo, voucher.uuidPromo: voucher.namaPromo},
      pajakDokumen: pajakDokumen.values.toList(),
      tarifDipakai: tarifDipakai,
      kodePajakBaris: kodeBaris,
      peringatan: peringatan.toList(),
      tanggalBisnis: tanggal,
      labelPajak: labelPajak,
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
      metodeBayar: AmbilMetodeBayar(k, [for (final p in nonTunai) p.metode], tambahanTunai: true),
    );
    final dibayar = nonTunai.fold(Uang.Nol(), (total, p) => total.Tambah(p.jumlah));
    return hitungan.hasil.totalAkhir.Kurangi(dibayar);
  }

  /// Uuid metode pembayaran untuk promo metode bayar (F-16c bagian 3): metode [dipakai] + metode tunai outlet bila
  /// [tambahanTunai] (sisa akan dibayar tunai). Kosong = null (belum memilih pembayaran).
  static List<String>? AmbilMetodeBayar(
    KonteksPenjualan k,
    Iterable<BarisMetodePembayaran> dipakai, {
    bool tambahanTunai = false,
  }) {
    final hasil = <String>{
      for (final m in dipakai) m.Uuid,
      if (tambahanTunai)
        for (final m in k.metodePembayaran.where((m) => m.Jenis == JenisMetodeBayar.tunai)) m.Uuid,
    };
    return hasil.isEmpty ? null : hasil.toList();
  }

  // Diskon (BR-07.3) ---------------------------------------------------------------------------------------------------

  /// Persen efektif diskon (PRD v1.46 tindak lanjut (c), sama dengan server): nilai diskon hasil `MesinKalkulasi`
  /// (sudah dibulatkan ke sen) ÷ [dasar] × 100. Dasar = bruto baris untuk diskon baris, subtotal untuk diskon
  /// pesanan. Bukan persen masukan: diskon 30% dari bruto Rp 4.995,45 dibulatkan menjadi Rp 1.498,64 = 30,0001%.
  static Rational HitungPersenEfektif(Uang dasar, Uang diskon) {
    if (diskon.Bandingkan(Uang.Nol()) <= 0) {
      return Rational.zero;
    }
    if (dasar.Bandingkan(Uang.Nol()) <= 0) {
      return Rational.fromInt(100);
    }
    return diskon.KeDesimal().toRational() * Rational.fromInt(100) / dasar.KeDesimal().toRational();
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

  /// Nilai diskon baris [uuidBaris] bila [diskon] diterapkan: bruto (dasar) dan diskon hasil mesin.
  ({Uang dasar, Uang diskon}) HitungDiskonBaris(
    Keranjang keranjang,
    String uuidBaris,
    DiskonManual diskon,
    KonteksPenjualan k,
  ) {
    final indeks = keranjang.baris.indexWhere((b) => b.uuid == uuidBaris);
    final dengan = keranjang.Salin(
      baris: [for (final b in keranjang.baris) b.uuid == uuidBaris ? b.Salin(diskon: () => diskon) : b],
    );
    final baris = Hitung(dengan, k).hasilTanpaPromo.baris[indeks];
    return (dasar: baris.bruto, diskon: baris.diskon);
  }

  /// Nilai diskon pesanan bila [diskon] diterapkan: subtotal (dasar) dan diskon pesanan hasil mesin.
  ({Uang dasar, Uang diskon}) HitungDiskonPesanan(Keranjang keranjang, DiskonManual diskon, KonteksPenjualan k) {
    final hasil = Hitung(keranjang.Salin(diskonPesanan: () => diskon), k).hasilTanpaPromo;
    return (dasar: hasil.subtotal, diskon: hasil.diskonPesanan.Kurangi(hasil.diskonPoin));
  }

  /// Penyetuju efektif: penyetuju yang lolos PIN, atau kasir sendiri bila ia punya izin menyetujui.
  static PenyetujuDiskon? AmbilPenyetujuEfektif(StafLokal kasir, PenyetujuDiskon? penyetuju) =>
      penyetuju ??
      (kasir.PunyaIzin(IzinKasir.penjualanDiskonSetujui)
          ? PenyetujuDiskon(uuid: kasir.uuid, nama: kasir.nama, pemilik: kasir.pemilik)
          : null);

  /// Apakah diskon ini wajib dicatat penyetujunya: kasir tanpa `penjualan.diskon.manual` (PRD v1.46 (f): diarahkan
  /// ke PIN penyetuju, bukan ditolak), atau persen efektif di atas `BatasDiskonManual`. Pemilik tidak pernah.
  static bool CekButuhPenyetuju({required Rational persen, required StafLokal kasir, required KonteksPenjualan k}) =>
      !kasir.pemilik &&
      (!kasir.PunyaIzin(IzinKasir.penjualanDiskonManual) || persen > k.batasDiskonManual.toRational());

  /// BR-07.3 terhadap persen efektif [diskon] (hasil mesin) dari [dasar].
  static StatusDiskon PeriksaDiskon({
    required Uang dasar,
    required Uang diskon,
    required StafLokal kasir,
    required KonteksPenjualan k,
    PenyetujuDiskon? penyetuju,
  }) {
    final persen = HitungPersenEfektif(dasar, diskon);
    if (!CekButuhPenyetuju(persen: persen, kasir: kasir, k: k)) {
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

  /// Periksa semua diskon keranjang terhadap batas memakai hasil mesin di [hitungan]. Kembalikan penyetuju yang
  /// dicatat (`UuidPenyetujuDiskon`), atau null bila tidak ada diskon yang butuh penyetuju.
  static PenyetujuDiskon? ValidasiDiskon(
    Keranjang keranjang,
    HitunganKeranjang hitungan,
    StafLokal kasir,
    KonteksPenjualan k,
  ) {
    var butuhPenyetuju = false;
    void Periksa(String nama, Uang dasar, Uang diskon) {
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
          kasir.PunyaIzin(IzinKasir.penjualanDiskonManual)
              ? 'Diskon $nama di atas ${k.batasDiskonManual}% wajib disetujui supervisor dengan PIN.'
              : 'Diskon $nama wajib disetujui supervisor dengan PIN.',
        );
      }
      if (CekButuhPenyetuju(persen: HitungPersenEfektif(dasar, diskon), kasir: kasir, k: k)) {
        butuhPenyetuju = true;
      }
    }

    final hasil = hitungan.hasilTanpaPromo;
    for (var i = 0; i < keranjang.baris.length; i++) {
      if (keranjang.baris[i].diskon != null) {
        Periksa('"${keranjang.baris[i].nama}"', hasil.baris[i].bruto, hasil.baris[i].diskon);
      }
    }
    if (keranjang.diskonPesanan != null) {
      // Diskon poin (F-16b) bukan diskon manual: tidak ikut batas diskon kasir.
      Periksa('pesanan', hasil.subtotal, hasil.diskonPesanan.Kurangi(hasil.diskonPoin));
    }
    return butuhPenyetuju ? AmbilPenyetujuEfektif(kasir, keranjang.penyetuju) : null;
  }

  /// F-16b: poin hanya ditukar untuk pelanggan terpilih, dan nilainya tidak boleh terpotong batas sisa subtotal (server
  /// menolak dokumen yang nilainya berbeda dengan hitungan mesin).
  static void ValidasiTukarPoin(Keranjang keranjang, HasilKalkulasi hasil) {
    final tukar = keranjang.tukarPoin;
    if (tukar == null) {
      return;
    }
    if (keranjang.pelanggan == null) {
      throw const GalatKasir('TukarPoinTanpaPelanggan', 'Pilih pelanggan dulu sebelum menukar poin.');
    }
    if (!hasil.diskonPoin.SamaDengan(tukar.nilai)) {
      throw const GalatKasir(
        'TukarPoinMelebihiTotal',
        'Potongan poin melebihi total belanja. Kurangi poin yang ditukar di panel Pelanggan (F2).',
      );
    }
  }

  /// F-12 BR-12.1: alasan penjualan tempo [jumlah] butuh PIN penyetuju ber-izin `penjualan.tempo.setujui` menurut
  /// posisi kredit terakhir yang diketahui perangkat (kosong = boleh tanpa penyetuju). Sama dengan server
  /// `KreditPelanggan::Periksa`; bila cache basi, server tetap menerima penjualan dan menandainya untuk ditinjau.
  static List<String> PeriksaTempo({
    required PelangganTerpilih pelanggan,
    required Uang jumlah,
    required int batasHariLewat,
  }) {
    final alasan = <String>[];
    final limit = pelanggan.limitKredit == null ? null : Uang.Dari(pelanggan.limitKredit!);
    final setelah = Uang.Dari(pelanggan.sisaPiutang ?? '0').Tambah(jumlah);
    if (limit == null || limit.Bandingkan(Uang.Nol()) <= 0) {
      alasan.add('pelanggan belum punya limit kredit');
    } else if (setelah.Bandingkan(limit) > 0) {
      alasan.add('piutang ${setelah.FormatRupiah()} melebihi limit ${limit.FormatRupiah()}');
    }
    final hari = pelanggan.hariLewatJatuhTempo ?? 0;
    if (hari > batasHariLewat) {
      alasan.add('ada piutang lewat jatuh tempo $hari hari');
    }
    return alasan;
  }

  /// F-12: aturan pembayaran tempo: paling banyak satu per transaksi, wajib pelanggan, dan bila BR-12.1 tidak lolos
  /// wajib penyetuju (kasir sendiri bila ber-izin, atau [uuidPenyetujuTempo] hasil PIN).
  static void ValidasiTempo(
    Keranjang keranjang,
    List<PembayaranMasukan> pembayaran,
    StafLokal kasir,
    KonteksPenjualan k,
    String? uuidPenyetujuTempo,
  ) {
    final tempo = pembayaran.where((p) => p.metode.Jenis == JenisMetodeBayar.tempo).toList();
    if (tempo.isEmpty) {
      return;
    }
    if (tempo.length > 1) {
      throw const GalatKasir('TempoGanda', 'Pembayaran tempo hanya boleh satu kali per transaksi.');
    }
    final pelanggan = keranjang.pelanggan;
    if (pelanggan == null) {
      throw const GalatKasir('TempoTanpaPelanggan', 'Pilih pelanggan dulu untuk pembayaran tempo.');
    }
    final alasan = PeriksaTempo(
      pelanggan: pelanggan,
      jumlah: tempo.single.jumlah,
      batasHariLewat: k.batasHariLewatJatuhTempo,
    );
    if (alasan.isNotEmpty && uuidPenyetujuTempo == null && !kasir.PunyaIzin(IzinKasir.penjualanTempoSetujui)) {
      throw GalatKasir('PersetujuanTempoDiperlukan', 'Tempo perlu persetujuan: ${alasan.join('; ')}.');
    }
  }

  /// F-12 bagian 2: uang muka hanya dipakai saat mengambil pre-order, sekali, dan tidak melebihi sisa DP.
  static void ValidasiUangMuka(Keranjang keranjang, List<PembayaranMasukan> pembayaran) {
    final dp = pembayaran.where((p) => p.metode.Jenis == JenisMetodeBayar.uangMuka).toList();
    if (dp.isEmpty) {
      return;
    }
    final praPesan = keranjang.praPesan;
    if (praPesan == null || dp.length > 1) {
      throw const GalatKasir('UangMukaTanpaPesanan', 'Uang muka hanya dipakai sekali saat mengambil pre-order.');
    }
    if (dp.single.jumlah.Bandingkan(praPesan.sisaUangMuka) > 0) {
      throw GalatKasir(
        'UangMukaMelebihiSisa',
        'Uang muka ${praPesan.nomor} tinggal ${praPesan.sisaUangMuka.FormatRupiah()}.',
      );
    }
  }

  // Bayar & simpan -----------------------------------------------------------------------------------------------------

  Future<PenjualanTersimpan> Bayar({
    required Keranjang keranjang,
    required List<PembayaranMasukan> pembayaran,
    required StafLokal kasir,
    required KonteksPenjualan k,
    String? uuidPenyetujuTempo,
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
    final hitungan = Hitung(
      keranjang,
      k,
      pembayaran: [for (final p in pembayaran) p.KeKalkulasi()],
      metodeBayar: AmbilMetodeBayar(k, [for (final p in pembayaran) p.metode]),
    );
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
    ValidasiTukarPoin(keranjang, hasil);
    ValidasiTempo(keranjang, pembayaran, kasir, k, uuidPenyetujuTempo);
    ValidasiUangMuka(keranjang, pembayaran);

    final sekarang = _jam().toUtc();
    final t = hitungan.tanggalBisnis;
    final yymmdd = '${t.substring(2, 4)}${t.substring(5, 7)}${t.substring(8, 10)}';
    // F-16c bagian 2: voucher dipesan server untuk Uuid penjualan ini.
    final uuid = keranjang.voucher?.uuidPenjualan ?? BuatUuid();
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
          uuidPenyetujuTempo: uuidPenyetujuTempo,
        );
      },
    );
    final tempo = pembayaran.where((p) => p.metode.Jenis == JenisMetodeBayar.tempo).firstOrNull;
    final uuidPelanggan = keranjang.pelanggan?.uuid;
    if (tempo != null && uuidPelanggan != null) {
      await repositoriPelanggan?.TambahSisaPiutang(uuidPelanggan, tempo.jumlah.KeString());
    }
    // F-16c bagian 3: jumlah transaksi & pemakaian promo pelanggan di cache ikut bertambah (promo offline berikutnya).
    if (uuidPelanggan != null) {
      await repositoriPelanggan?.CatatTransaksi(uuidPelanggan, hitungan.tanggalBisnis, [
        for (final p in hitungan.promoTerpakai) p.uuid,
      ]);
    }

    return PenjualanTersimpan(
      uuid: uuid,
      nomor: dokumen.penjualan.Nomor.value,
      totalAkhir: hasil.totalAkhir,
      totalDibayar: dibayar,
      kembalian: kembalian,
      pembayaran: pembayaran,
      namaPelanggan: keranjang.pelanggan?.nama,
    );
  }

  /// Server membatasi `Pembayaran.*.Referensi` paling panjang 100 karakter. EDC menggabungkan bank & nomor approval
  /// (`bank · approval`), jadi masing-masing dibatasi agar gabungannya ≤ 100 (40 + 3 + 56 = 99).
  static const int panjangMaksReferensi = 100;
  static const int panjangMaksBankEdc = 40;
  static const int panjangMaksApprovalEdc = 56;

  /// Referensi EDC `bank · approval` (bank opsional); tiap bagian dipangkas ke batasnya.
  static String SusunReferensiEdc(String bank, String approval) {
    String Pangkas(String teks, int maks) {
      final rapi = teks.trim();
      return rapi.runes.length <= maks ? rapi : String.fromCharCodes(rapi.runes.take(maks)).trim();
    }

    return [
      Pangkas(bank, panjangMaksBankEdc),
      Pangkas(approval, panjangMaksApprovalEdc),
    ].where((t) => t.isNotEmpty).join(' · ');
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
      if (!JenisMetodeBayar.fase1.contains(p.metode.Jenis) && p.metode.Jenis != JenisMetodeBayar.uangMuka) {
        throw GalatKasir('MetodeBayarBelumDidukung', 'Metode ${p.metode.Nama} belum didukung aplikasi kasir.');
      }
      if (p.jumlah.Bandingkan(Uang.Nol()) <= 0) {
        throw GalatKasir('JumlahTidakValid', 'Jumlah pembayaran ${p.metode.Nama} harus lebih dari Rp 0.');
      }
      if ((p.referensi?.runes.length ?? 0) > panjangMaksReferensi) {
        throw GalatKasir(
          'ReferensiTerlaluPanjang',
          'Referensi ${p.metode.Nama} paling panjang $panjangMaksReferensi karakter.',
        );
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
    String? uuidPenyetujuTempo,
  }) {
    final hasil = hitungan.hasil;
    final kembalian = hasil.kembalian ?? Uang.Nol();
    final dibayar = pembayaran.fold(Uang.Nol(), (t, p) => t.Tambah(p.jumlah));
    final pembulatan = k.pembulatanTunai;
    final catatan = keranjang.catatan?.trim();
    final pesananMeja = keranjang.pesananMeja;
    final kanal = pesananMeja?.uuidMeja != null ? kanalMakanDiTempat : kanalBawaan;

    final data = <String, Object?>{
      'UuidShift': shift.Uuid,
      'UuidPengguna': kasir.uuid,
      'Nomor': nomor,
      'Kanal': kanal,
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
            if (keranjang.baris[i].staf.isNotEmpty) 'Staf': keranjang.baris[i].staf,
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
      'UuidPesananTerbuka': ?pesananMeja?.uuid,
      'UuidPelanggan': ?keranjang.pelanggan?.uuid,
      'TukarPoin': ?keranjang.tukarPoin?.KeJson(),
      'UuidPenyetujuTempo': ?uuidPenyetujuTempo,
      'Voucher': ?keranjang.voucher?.kode,
      'UuidPesananPenjualan': ?keranjang.praPesan?.uuid,
      if (hitungan.promoTerpakai.isNotEmpty)
        'Promo': [
          for (final p in hitungan.promoTerpakai)
            {
              'UuidPromo': p.uuid,
              'Kode': p.kode,
              'DiskonBaris': [
                for (final MapEntry(:key, :value) in p.diskonBaris.entries)
                  {'UuidBaris': keranjang.baris[key].uuid, 'Jumlah': value.KeString()},
              ],
              'DiskonPesanan': p.diskonPesanan.KeString(),
            },
        ],
    };

    return DokumenPenjualan(
      penjualan: PenjualanCompanion.insert(
        Uuid: uuid,
        Nomor: nomor,
        UuidShift: shift.Uuid,
        UuidPengguna: kasir.uuid,
        NamaKasir: kasir.nama,
        Kanal: kanal,
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
      uuidPesananTerbuka: pesananMeja?.uuid,
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
