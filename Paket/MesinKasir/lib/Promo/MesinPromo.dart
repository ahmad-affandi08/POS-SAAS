import 'package:inti/Inti.dart';

import '../Kalkulasi/DataKalkulasi.dart';
import '../Kalkulasi/HasilKalkulasi.dart';
import '../Kalkulasi/MesinKalkulasi.dart';
import 'DataPromo.dart';

/// Mesin promo F-16c bagian 1 (PRD F-16 Promo Engine, "Rincian F-16c"). Satu algoritma dengan
/// `App\Domain\Penjualan\Kalkulasi\MesinPromo` di Backend; keduanya wajib lolos test vector bersama
/// `Spesifikasi/VektorUjiKalkulasi/Promo/` (CLAUDE.md #18).
///
/// 1. Promo **berlaku** bila kuota belum habis, waktu di `[mulaiPada, selesaiPada)`, hari & jam lokal cocok, outlet,
///    kanal, dan tier cocok, voucher sudah divalidasi (promo wajib voucher), subtotal awal (setelah diskon manual
///    baris) ≥ minimal, dan barang kondisi cukup.
/// 2. Pilih promo: `PrioritasKetat` = urut prioritas (besar dulu, seri menurut kode), promo eksklusif hanya bila
///    belum ada yang terpilih dan menghentikan evaluasi; `Terbaik` = bandingkan semua promo non-eksklusif bersama
///    dengan tiap promo eksklusif sendiri, ambil potongan terbesar (seri: kandidat lebih awal).
/// 3. Terapkan: promo barang dulu (urut prioritas, dibatasi sisa netto baris), lalu promo pesanan (dari subtotal
///    setelah promo barang, dibatasi sisa subtotal). Semua potongan menjadi nominal lalu dihitung `MesinKalkulasi`.
final class MesinPromo {
  const MesinPromo();

  static const MesinKalkulasi _mesin = MesinKalkulasi();

  HasilPromo Terapkan(
    DataKalkulasi dasar,
    List<BarisPromo> barisPromo,
    List<DefinisiPromo> promo,
    KonteksPromo konteks, {
    ModeResolusiPromo mode = ModeResolusiPromo.Terbaik,
  }) {
    if (barisPromo.length != dasar.baris.length) {
      throw ArgumentError('Jumlah baris promo harus sama dengan baris kalkulasi');
    }
    final hasilDasar = _mesin.Hitung(dasar);
    final keadaan = _Keadaan(dasar, barisPromo, hasilDasar);
    final semua = promo.where((p) => CekBerlaku(p, konteks, hasilDasar.subtotal) && keadaan.CekKondisi(p)).toList()
      ..sort(BandingkanUrutan);
    // Bagian 4: poin berlipat tidak ikut resolusi potongan; pengali terbesar menang (seri: urutan prioritas).
    DefinisiPromo? poinBerlipat;
    for (final p in semua.where((p) => p.aksi == JenisAksiPromo.PoinBerlipat)) {
      if (p.pengali > (poinBerlipat?.pengali ?? Decimal.one)) {
        poinBerlipat = p;
      }
    }
    final berlaku = semua.where((p) => p.aksi != JenisAksiPromo.PoinBerlipat).toList();

    List<PromoTerpakai> terpilih;
    if (mode == ModeResolusiPromo.PrioritasKetat) {
      final daftar = <DefinisiPromo>[];
      for (final p in berlaku) {
        if (keadaan.Evaluasi([p]).isEmpty) {
          continue;
        }
        if (p.eksklusif) {
          if (daftar.isEmpty) {
            daftar.add(p);
            break;
          }
          continue;
        }
        daftar.add(p);
      }
      terpilih = keadaan.Evaluasi(daftar);
    } else {
      final kandidat = [
        keadaan.Evaluasi(berlaku.where((p) => !p.eksklusif).toList()),
        for (final p in berlaku.where((p) => p.eksklusif)) keadaan.Evaluasi([p]),
      ];
      terpilih = kandidat.first;
      for (final k in kandidat.skip(1)) {
        if (HitungTotal(k).Bandingkan(HitungTotal(terpilih)) > 0) {
          terpilih = k;
        }
      }
    }

    final data = SusunData(dasar, terpilih);
    return HasilPromo(terpakai: terpilih, data: data, hasil: _mesin.Hitung(data), poinBerlipat: poinBerlipat);
  }

  static int BandingkanUrutan(DefinisiPromo a, DefinisiPromo b) {
    final prioritas = b.prioritas.compareTo(a.prioritas);
    return prioritas != 0 ? prioritas : a.kode.compareTo(b.kode);
  }

  static Uang HitungTotal(List<PromoTerpakai> daftar) => daftar.fold(Uang.Nol(), (a, p) => a.Tambah(p.HitungTotal()));

  /// Syarat promo selain barang kondisi.
  static bool CekBerlaku(DefinisiPromo p, KonteksPromo k, Uang subtotalAwal) {
    if (p.kuotaTersisa != null && p.kuotaTersisa! <= 0) {
      return false;
    }
    if (p.mulaiPada != null && k.waktu.isBefore(p.mulaiPada!)) {
      return false;
    }
    if (p.selesaiPada != null && !k.waktu.isBefore(p.selesaiPada!)) {
      return false;
    }
    if (p.hari.isNotEmpty && !p.hari.contains(k.waktuLokal.weekday)) {
      return false;
    }
    if (p.jamMulai != null || p.jamSelesai != null) {
      final menit = k.waktuLokal.hour * 60 + k.waktuLokal.minute;
      final mulai = p.jamMulai ?? 0;
      final selesai = p.jamSelesai ?? 1440;
      final cocok = mulai < selesai ? menit >= mulai && menit < selesai : menit >= mulai || menit < selesai;
      if (!cocok) {
        return false;
      }
    }
    if (p.uuidOutlet.isNotEmpty && !p.uuidOutlet.contains(k.uuidOutlet)) {
      return false;
    }
    if (p.kanal.isNotEmpty && !p.kanal.contains(k.kanal)) {
      return false;
    }
    if (p.tier.isNotEmpty && !p.tier.contains(k.tier)) {
      return false;
    }
    if (p.wajibVoucher && !k.voucher.contains(p.uuid)) {
      return false;
    }
    if (p.metodeBayar.isNotEmpty &&
        (k.metodeBayar == null || k.metodeBayar!.isEmpty || !k.metodeBayar!.every(p.metodeBayar.contains))) {
      return false;
    }
    if (p.ulangTahun != null && !CekUlangTahun(p, k)) {
      return false;
    }
    if (p.transaksiPertama && (!k.berpelanggan || k.jumlahTransaksiPelanggan != 0)) {
      return false;
    }
    if (p.batasPerPelanggan != null) {
      final pakai = k.pemakaianPelanggan[p.uuid] ?? const PemakaianPromoPelanggan();
      if (!k.berpelanggan || pakai.Ambil(p.periodeBatasPelanggan) >= p.batasPerPelanggan!) {
        return false;
      }
    }
    return subtotalAwal.Bandingkan(p.minimalSubtotal) >= 0;
  }

  /// Tanggal lokal outlet vs tanggal lahir pelanggan (tahun lahir diabaikan; 29 Februari = 28 Februari di tahun bukan
  /// kabisat). `Rentang` memeriksa ulang tahun tahun lalu, tahun ini, dan tahun depan agar ± N hari melewati tahun baru.
  static bool CekUlangTahun(DefinisiPromo p, KonteksPromo k) {
    final cocok = RegExp(r'^\d{4}-(\d{2})-(\d{2})$').firstMatch(k.tanggalLahir ?? '');
    if (cocok == null) {
      return false;
    }
    final bulan = int.parse(cocok.group(1)!);
    final tanggal = int.parse(cocok.group(2)!);
    final hariIni = DateTime.utc(k.waktuLokal.year, k.waktuLokal.month, k.waktuLokal.day);
    if (p.ulangTahun == JenisUlangTahunPromo.Bulan) {
      return hariIni.month == bulan;
    }
    final jarak = p.ulangTahun == JenisUlangTahunPromo.Rentang ? p.hariUlangTahun : 0;
    for (final geser in const [-1, 0, 1]) {
      final tahun = hariIni.year + geser;
      final kabisat = (tahun % 4 == 0 && tahun % 100 != 0) || tahun % 400 == 0;
      final hari = bulan == 2 && tanggal == 29 && !kabisat ? 28 : tanggal;
      if (DateTime.utc(tahun, bulan, hari).difference(hariIni).inDays.abs() <= jarak) {
        return true;
      }
    }
    return false;
  }

  static bool CekAksiPesanan(JenisAksiPromo aksi) =>
      aksi == JenisAksiPromo.DiskonPersenPesanan || aksi == JenisAksiPromo.DiskonTetapPesanan;

  /// Masukan kalkulasi baru: potongan promo nominal ditambahkan setelah potongan yang sudah ada.
  static DataKalkulasi SusunData(DataKalkulasi dasar, List<PromoTerpakai> terpakai) => DataKalkulasi(
    hargaTermasukPajak: dasar.hargaTermasukPajak,
    persenBiayaLayanan: dasar.persenBiayaLayanan,
    pembulatanTunai: dasar.pembulatanTunai,
    pajak: dasar.pajak,
    baris: [
      for (var i = 0; i < dasar.baris.length; i++)
        DataBarisKalkulasi(
          jumlah: dasar.baris[i].jumlah,
          hargaSatuan: dasar.baris[i].hargaSatuan,
          hargaPilihan: dasar.baris[i].hargaPilihan,
          hargaTermasukPajak: dasar.baris[i].hargaTermasukPajak,
          kodePajak: dasar.baris[i].kodePajak,
          potongan: [
            ...dasar.baris[i].potongan,
            for (final t in terpakai)
              if (t.diskonBaris[i] != null) DataPotongan.DariJumlah(t.diskonBaris[i]!),
          ],
        ),
    ],
    potonganPesanan: [
      ...dasar.potonganPesanan,
      for (final t in terpakai)
        if (!t.diskonPesanan.BernilaiNol()) DataPotongan.DariJumlah(t.diskonPesanan),
    ],
    pembayaran: dasar.pembayaran,
    tukarPoin: dasar.tukarPoin,
  );
}

/// Keadaan awal keranjang untuk mengevaluasi sekumpulan promo.
final class _Keadaan {
  _Keadaan(this.dasar, this.barisPromo, HasilKalkulasi hasilDasar)
    : sisaAwal = [for (final b in hasilDasar.baris) b.bruto.Kurangi(b.diskon)],
      bruto = [for (final b in hasilDasar.baris) b.bruto];

  final DataKalkulasi dasar;
  final List<BarisPromo> barisPromo;
  final List<Uang> sisaAwal;
  final List<Uang> bruto;

  List<int> AmbilIndeksKondisi(DefinisiPromo p) => [
    for (var i = 0; i < barisPromo.length; i++)
      if (switch (p.kondisi) {
        JenisKondisiPromo.Semua => true,
        JenisKondisiPromo.Produk => p.uuidKondisi.contains(barisPromo[i].uuidProduk),
        JenisKondisiPromo.Kategori =>
          barisPromo[i].uuidKategori != null && p.uuidKondisi.contains(barisPromo[i].uuidKategori),
      })
        i,
  ];

  bool CekKondisi(DefinisiPromo p) {
    final indeks = AmbilIndeksKondisi(p);
    if (indeks.isEmpty) {
      return false;
    }
    final total = indeks.fold(Kuantitas.Nol(), (t, i) => t.Tambah(dasar.baris[i].jumlah));
    return total.Bandingkan(p.jumlahMinimal) >= 0;
  }

  /// Satuan utuh barang kondisi (baris berjumlah pecahan dilewati), urut harga satuan terbesar lalu indeks baris.
  List<({int indeks, Uang harga})> AmbilSatuan(DefinisiPromo p) {
    final satuan = <({int indeks, Uang harga})>[];
    for (final i in AmbilIndeksKondisi(p)) {
      final jumlah = dasar.baris[i].jumlah.KeDesimal();
      if (!jumlah.isInteger) {
        continue;
      }
      final harga = dasar.baris[i].hargaSatuan.Tambah(dasar.baris[i].hargaPilihan);
      for (var n = 0; n < jumlah.toBigInt().toInt(); n++) {
        satuan.add((indeks: i, harga: harga));
      }
    }
    satuan.sort((a, b) {
      final banding = b.harga.Bandingkan(a.harga);
      return banding != 0 ? banding : a.indeks.compareTo(b.indeks);
    });
    return satuan;
  }

  /// Potongan mentah promo barang per indeks baris (belum dibatasi sisa baris).
  Map<int, Uang> HitungDiskonBarang(DefinisiPromo p) {
    final hasil = <int, Uang>{};
    void Tambah(int i, Uang nilai) => hasil[i] = (hasil[i] ?? Uang.Nol()).Tambah(nilai);

    switch (p.aksi) {
      case JenisAksiPromo.DiskonPersenItem:
        for (final i in AmbilIndeksKondisi(p)) {
          Tambah(i, bruto[i].Kali(p.persen!.shift(-2)));
        }
      case JenisAksiPromo.DiskonTetapItem:
        for (final i in AmbilIndeksKondisi(p)) {
          Tambah(i, p.jumlah!.Kali(dasar.baris[i].jumlah.KeDesimal()));
        }
      case JenisAksiPromo.HargaSpesial:
        for (final i in AmbilIndeksKondisi(p)) {
          final selisih = bruto[i].Kurangi(p.harga!.Kali(dasar.baris[i].jumlah.KeDesimal()));
          if (!selisih.BernilaiNegatif()) {
            Tambah(i, selisih);
          }
        }
      case JenisAksiPromo.BeliXGratisY:
        final satuan = AmbilSatuan(p);
        final ukuran = p.beli! + p.gratis!;
        var set = satuan.length ~/ ukuran;
        if (p.batasPerTransaksi != null && set > p.batasPerTransaksi!) {
          set = p.batasPerTransaksi!;
        }
        for (var s = 0; s < set; s++) {
          for (var n = s * ukuran + p.beli!; n < (s + 1) * ukuran; n++) {
            Tambah(satuan[n].indeks, satuan[n].harga.Kali(p.persenGratis.shift(-2)));
          }
        }
      case JenisAksiPromo.BundelHargaTetap:
        final satuan = AmbilSatuan(p);
        final ukuran = p.jumlahMinimal.KeDesimal().toBigInt().toInt();
        var set = ukuran <= 0 ? 0 : satuan.length ~/ ukuran;
        if (p.batasPerTransaksi != null && set > p.batasPerTransaksi!) {
          set = p.batasPerTransaksi!;
        }
        for (var s = 0; s < set; s++) {
          final isi = satuan.sublist(s * ukuran, (s + 1) * ukuran);
          final total = isi.fold(Uang.Nol(), (t, u) => t.Tambah(u.harga));
          final diskon = total.Kurangi(p.harga!);
          if (diskon.Bandingkan(Uang.Nol()) <= 0) {
            continue;
          }
          final alokasi = MesinKalkulasi.AlokasikanSebanding(diskon, [for (final u in isi) u.harga]);
          for (var n = 0; n < isi.length; n++) {
            Tambah(isi[n].indeks, alokasi[n]);
          }
        }
      case JenisAksiPromo.DiskonPersenPesanan:
      case JenisAksiPromo.DiskonTetapPesanan:
      case JenisAksiPromo.PoinBerlipat:
        break;
    }
    return hasil;
  }

  /// Terapkan [daftar] (urut prioritas): promo barang dulu lalu promo pesanan; promo tanpa potongan dibuang.
  List<PromoTerpakai> Evaluasi(List<DefinisiPromo> daftar) {
    final sisa = [...sisaAwal];
    final potonganBarang = <String, Map<int, Uang>>{};
    for (final p in daftar.where((p) => !MesinPromo.CekAksiPesanan(p.aksi))) {
      final terpakai = <int, Uang>{};
      final mentah = HitungDiskonBarang(p);
      for (final i in mentah.keys.toList()..sort()) {
        final nilai = MesinKalkulasi.AmbilTerkecil(mentah[i]!, sisa[i]);
        if (nilai.Bandingkan(Uang.Nol()) > 0) {
          terpakai[i] = nilai;
          sisa[i] = sisa[i].Kurangi(nilai);
        }
      }
      potonganBarang[p.uuid] = terpakai;
    }

    var sisaPesanan = MesinKalkulasi.JumlahkanUang(sisa);
    final dasarPesanan = sisaPesanan;
    final potonganPesanan = <String, Uang>{};
    for (final p in daftar.where((p) => MesinPromo.CekAksiPesanan(p.aksi))) {
      final mentah = p.aksi == JenisAksiPromo.DiskonPersenPesanan ? dasarPesanan.Kali(p.persen!.shift(-2)) : p.jumlah!;
      final nilai = MesinKalkulasi.AmbilTerkecil(mentah, sisaPesanan);
      potonganPesanan[p.uuid] = nilai;
      sisaPesanan = sisaPesanan.Kurangi(nilai);
    }

    return [
      for (final p in daftar)
        if (MesinPromo.CekAksiPesanan(p.aksi)
            ? potonganPesanan[p.uuid]!.Bandingkan(Uang.Nol()) > 0
            : potonganBarang[p.uuid]!.isNotEmpty)
          PromoTerpakai(
            uuid: p.uuid,
            kode: p.kode,
            diskonBaris: potonganBarang[p.uuid] ?? const {},
            diskonPesanan: potonganPesanan[p.uuid] ?? Uang.Nol(),
          ),
    ];
  }
}
