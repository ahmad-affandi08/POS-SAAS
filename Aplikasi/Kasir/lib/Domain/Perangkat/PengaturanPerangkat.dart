import '../../Data/RepositoriKasir.dart';

/// Ukuran tampilan per perangkat (PRD §17.2.7): skala teks Normal 1,0 / Besar 1,15.
enum UkuranTampilan {
  Normal('Normal', 1),
  Besar('Besar', 1.15);

  const UkuranTampilan(this.label, this.skalaTeks);

  final String label;
  final num skalaTeks;
}

/// Posisi keranjang di layar Jual (kasir kidal, penempatan layar di meja). Dipakai F-07.
enum PosisiKeranjang {
  Kiri('Kiri'),
  Kanan('Kanan');

  const PosisiKeranjang(this.label);

  final String label;
}

/// K-8: tampilan katalog layar Jual. `Otomatis` mengikuti mode kasir outlet: Retail & Grosir (pemindai, ribuan
/// SKU) memakai daftar ringkas, mode lain memakai ubin bergambar.
enum TampilanKatalog {
  Otomatis('Otomatis'),
  Ubin('Ubin'),
  Daftar('Daftar');

  const TampilanKatalog(this.label);

  final String label;

  /// Tampilan yang dipakai: pilihan perangkat, atau menurut [modeKasir] bila `Otomatis`.
  TampilanKatalog Tentukan(String? modeKasir) => switch (this) {
    TampilanKatalog.Otomatis => modeKasir == 'Retail' || modeKasir == 'Grosir' ? Daftar : Ubin,
    _ => this,
  };
}

/// Pengaturan lokal perangkat kasir (D-16, §17.2.7), disimpan di tabel `Pengaturan` (kunci-nilai) sehingga tidak
/// butuh perubahan skema. Bawaan: Normal, keranjang kanan, kunci otomatis 5 menit.
class PengaturanPerangkat {
  const PengaturanPerangkat({
    this.ukuran = UkuranTampilan.Normal,
    this.posisiKeranjang = PosisiKeranjang.Kanan,
    this.menitKunciOtomatis = menitKunciBawaan,
    this.tampilanKatalog = TampilanKatalog.Otomatis,
  });

  static const int menitKunciBawaan = 5;

  /// Pilihan waktu kunci otomatis (menit diam).
  static const List<int> pilihanMenitKunci = [1, 2, 5, 10, 15, 30];

  final UkuranTampilan ukuran;
  final PosisiKeranjang posisiKeranjang;
  final int menitKunciOtomatis;
  final TampilanKatalog tampilanKatalog;

  Duration AmbilBatasDiam() => Duration(minutes: menitKunciOtomatis);

  PengaturanPerangkat copyWith({
    UkuranTampilan? ukuran,
    PosisiKeranjang? posisiKeranjang,
    int? menitKunciOtomatis,
    TampilanKatalog? tampilanKatalog,
  }) => PengaturanPerangkat(
    ukuran: ukuran ?? this.ukuran,
    posisiKeranjang: posisiKeranjang ?? this.posisiKeranjang,
    menitKunciOtomatis: menitKunciOtomatis ?? this.menitKunciOtomatis,
    tampilanKatalog: tampilanKatalog ?? this.tampilanKatalog,
  );

  /// Baca dari tabel `Pengaturan`; nilai kosong/tidak dikenal kembali ke bawaan.
  static Future<PengaturanPerangkat> Muat(RepositoriKasir repositori) async {
    final ukuran = await repositori.AmbilPengaturan(KunciPengaturan.ukuranTampilan);
    final posisi = await repositori.AmbilPengaturan(KunciPengaturan.posisiKeranjang);
    final menit = int.tryParse(await repositori.AmbilPengaturan(KunciPengaturan.menitKunciOtomatis) ?? '');
    final tampilan = await repositori.AmbilPengaturan(KunciPengaturan.tampilanKatalog);
    return PengaturanPerangkat(
      ukuran: UkuranTampilan.values.where((u) => u.name == ukuran).firstOrNull ?? UkuranTampilan.Normal,
      posisiKeranjang: PosisiKeranjang.values.where((p) => p.name == posisi).firstOrNull ?? PosisiKeranjang.Kanan,
      menitKunciOtomatis: menit != null && menit > 0 ? menit : menitKunciBawaan,
      tampilanKatalog: TampilanKatalog.values.where((t) => t.name == tampilan).firstOrNull ?? TampilanKatalog.Otomatis,
    );
  }

  Future<void> Simpan(RepositoriKasir repositori) async {
    await repositori.SimpanPengaturan(KunciPengaturan.ukuranTampilan, ukuran.name);
    await repositori.SimpanPengaturan(KunciPengaturan.posisiKeranjang, posisiKeranjang.name);
    await repositori.SimpanPengaturan(KunciPengaturan.menitKunciOtomatis, '$menitKunciOtomatis');
    await repositori.SimpanPengaturan(KunciPengaturan.tampilanKatalog, tampilanKatalog.name);
  }
}
