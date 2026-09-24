import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mesin_kasir/MesinKasir.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import '../Domain/GalatKasir.dart';
import '../Domain/Katalog/KatalogLokal.dart';
import '../Domain/Katalog/LayananKatalog.dart';
import '../Domain/Penjualan/KonteksPenjualan.dart';
import '../Domain/Penjualan/LayananPenjualan.dart';
import '../Domain/Perangkat/PengaturanPerangkat.dart';
import '../Domain/Sesi/StafLokal.dart';
import 'Jual/PanelBayar.dart';
import 'Jual/PanelDiskon.dart';
import 'Jual/PanelItem.dart';
import 'Jual/PanelKeranjang.dart';
import 'Jual/PanelTertahan.dart';
import 'Jual/PengenalPemindai.dart';

enum _JenisPanel { Keranjang, Item, DiskonPesanan, Bayar, Selesai, Tertahan }

/// Beranda ruang kerja: layar Jual (F-07 mode retail, Rincian F-07c, PRD §17.2.3 & §17.2.7).
/// - Katalog: cari nama/SKU/barcode, kategori, ubin produk seragam; keranjang di sisi yang diatur (kiri/kanan) mulai
///   600dp, di HP keranjang dibuka sebagai lembar dengan bilah Bayar menempel di bawah.
/// - Tugas (pilihan item, diskon, bayar, pesanan tertahan) dibuka sebagai panel samping (≥ 1024dp) atau lembar di atas
///   area kerja, sehingga keranjang tidak hilang.
/// - Pemindai barcode tanpa fokus: rangkaian karakter cepat diakhiri Enter di mana pun di layar Jual (bukan saat mengetik
///   di kolom isian). Pintasan desktop (tabel §17.2.3): F1 cari, F8 bayar, F9 uang pas (tunai uang pas langsung
///   disimpan), Esc tutup panel atau hapus item terakhir keranjang. F2 dicadangkan untuk pelanggan (belum ada).
///   Batalkan transaksi hanya lewat tombol di keranjang (dengan konfirmasi).
/// - Katalog diperbarui berkala 60 detik saat online dan keranjang kosong (perubahan tidak mengejutkan di tengah
///   transaksi, §17.2.7).
class LayarJual extends ConsumerStatefulWidget {
  const LayarJual({super.key, required this.kasir, this.aktif = true});

  /// Lebar area kerja minimum untuk katalog + keranjang berdampingan.
  static const double lebarDuaPanel = 600;
  static const Duration selangKatalog = Duration(seconds: 60);

  final StafLokal kasir;

  /// Layar Jual sedang tampil dan tidak tertutup layar kunci/panel bingkai (pemindai & pintasan aktif).
  final bool aktif;

  @override
  ConsumerState<LayarJual> createState() => _LayarJualState();
}

class _LayarJualState extends ConsumerState<LayarJual> {
  final _cari = TextEditingController();
  final _fokusCari = FocusNode(debugLabel: 'Cari produk');
  final _fokusAkar = FocusNode(debugLabel: 'Layar Jual');
  final _pemindai = PengenalPemindai();
  final _kunciBayar = GlobalKey<PanelBayarState>();
  Timer? _pewaktuKatalog;

  String? _uuidKategori;
  _JenisPanel? _panel;
  ProdukJual? _produkPanel;
  String? _uuidBarisPanel;
  PenjualanTersimpan? _selesai;
  ({String teks, bool galat})? _pesan;
  bool _memperbarui = false;

  @override
  void initState() {
    super.initState();
    HardwareKeyboard.instance.addHandler(_SaatTombolPemindai);
    _pewaktuKatalog = Timer.periodic(LayarJual.selangKatalog, (_) => unawaited(_PerbaruiBerkala()));
    if (widget.aktif) {
      _FokusAkar();
    }
  }

  @override
  void didUpdateWidget(LayarJual lama) {
    super.didUpdateWidget(lama);
    if (widget.aktif && !lama.aktif) {
      _FokusAkar();
    }
    if (!widget.aktif) {
      _pemindai.Reset();
    }
  }

  @override
  void dispose() {
    HardwareKeyboard.instance.removeHandler(_SaatTombolPemindai);
    _pewaktuKatalog?.cancel();
    _cari.dispose();
    _fokusCari.dispose();
    _fokusAkar.dispose();
    super.dispose();
  }

  /// Kembalikan fokus ke akar layar Jual (setelah panel/dialog ditutup) agar pintasan & pemindai tetap bekerja.
  void _FokusAkar() => WidgetsBinding.instance.addPostFrameCallback((_) {
    if (mounted && widget.aktif && !_fokusAkar.hasPrimaryFocus && !_CekFokusDiIsian()) {
      _fokusAkar.requestFocus();
    }
  });

  void _TampilPesan(String teks, {bool galat = true}) => setState(() => _pesan = (teks: teks, galat: galat));

  // Pemindai & pintasan ------------------------------------------------------------------------------------------------

  static bool _CekFokusDiIsian() {
    final konteks = FocusManager.instance.primaryFocus?.context;
    return konteks != null &&
        (konteks.widget is EditableText || konteks.findAncestorWidgetOfExactType<EditableText>() != null);
  }

  bool _SaatTombolPemindai(KeyEvent event) {
    if (!mounted || !widget.aktif || event is! KeyDownEvent) {
      return false;
    }
    // Pintasan saat fokus hilang dari layar Jual (misal setelah panel ditutup): tetap ditangani selama tidak ada
    // dialog di atasnya. Bila fokus ada di dalam layar Jual, pintasan ditangani `Focus.onKeyEvent` (panel lebih dulu).
    if (!_fokusAkar.hasFocus && (ModalRoute.of(context)?.isCurrent ?? true)) {
      if (_SaatTombolPintasan(_fokusAkar, event) == KeyEventResult.handled) {
        return true;
      }
    }
    if (_CekFokusDiIsian() || _panel == _JenisPanel.Bayar) {
      _pemindai.Reset();
      return false;
    }
    if (event.logicalKey == LogicalKeyboardKey.enter || event.logicalKey == LogicalKeyboardKey.numpadEnter) {
      final kode = _pemindai.Selesai(event.timeStamp);
      if (kode == null) {
        return false;
      }
      _TanganiKode(kode);
      return true;
    }
    final karakter = event.character;
    if (karakter != null && karakter.length == 1 && karakter.codeUnitAt(0) >= 0x20) {
      _pemindai.Terima(karakter, event.timeStamp);
    }
    return false;
  }

  KeyEventResult _SaatTombolPintasan(FocusNode _, KeyEvent event) {
    if (!widget.aktif || event is! KeyDownEvent) {
      return KeyEventResult.ignored;
    }
    if (event.logicalKey == LogicalKeyboardKey.f1) {
      _fokusCari.requestFocus();
      return KeyEventResult.handled;
    }
    if (event.logicalKey == LogicalKeyboardKey.f8) {
      _BukaBayar();
      return KeyEventResult.handled;
    }
    if (event.logicalKey == LogicalKeyboardKey.f9) {
      _UangPas();
      return KeyEventResult.handled;
    }
    if (event.logicalKey == LogicalKeyboardKey.escape) {
      _Esc();
      return KeyEventResult.handled;
    }
    return KeyEventResult.ignored;
  }

  /// Esc: tutup panel yang terbuka; tanpa panel → hapus item terakhir keranjang. Saat mengetik di kolom cari, Esc
  /// mengosongkan pencarian lebih dulu (tidak menghapus item tanpa sengaja).
  void _Esc() {
    if (_panel == _JenisPanel.Selesai) {
      _TransaksiBaru();
    } else if (_panel != null) {
      _TutupPanel();
    } else if (_fokusCari.hasFocus && _cari.text.isNotEmpty) {
      setState(_cari.clear);
    } else {
      final keranjang = ref.read(penyediaKeranjang);
      if (keranjang.CekKosong) {
        return;
      }
      final terakhir = keranjang.baris.last;
      ref
          .read(penyediaKeranjang.notifier)
          .Ganti(ref.read(penyediaLayananPenjualan).HapusBaris(keranjang, terakhir.uuid));
      _TampilPesan('${terakhir.nama} dihapus dari keranjang.', galat: false);
      _FokusAkar();
    }
  }

  /// F9 uang pas: buka Bayar bila perlu, lalu bayar sisa dengan tunai uang pas dan simpan.
  void _UangPas() {
    if (_panel == _JenisPanel.Bayar) {
      unawaited(_kunciBayar.currentState?.BayarUangPas());
      return;
    }
    if (_panel == _JenisPanel.Selesai) {
      return;
    }
    _BukaBayar();
    if (_panel == _JenisPanel.Bayar) {
      WidgetsBinding.instance.addPostFrameCallback((_) => unawaited(_kunciBayar.currentState?.BayarUangPas()));
    }
  }

  // Aksi keranjang -----------------------------------------------------------------------------------------------------

  void _TanganiKode(String kode) {
    final katalog = ref.read(penyediaKatalog).value;
    final hasil = katalog?.CariKode(kode);
    if (hasil == null) {
      _TampilPesan('Kode $kode tidak ditemukan di katalog. Perbarui katalog atau cari manual.');
      return;
    }
    _TambahProduk(hasil.produk, satuan: hasil.satuan);
  }

  void _TambahProduk(ProdukJual produk, {SatuanJual? satuan}) {
    final alasan = produk.AmbilAlasanTidakBisaDijual();
    if (alasan != null) {
      _TampilPesan(alasan.pesan);
      return;
    }
    if (produk.kelompokPilihan.isNotEmpty) {
      setState(() {
        _pesan = null;
        _panel = _JenisPanel.Item;
        _produkPanel = produk;
        _uuidBarisPanel = null;
      });
      return;
    }
    final katalog = ref.read(penyediaKatalog).value;
    final k = ref.read(penyediaKonteksPenjualan).value;
    if (katalog == null || k == null) {
      return;
    }
    final layanan = ref.read(penyediaLayananPenjualan);
    try {
      final baris = layanan.BuatBaris(katalog, k, produk, satuan: satuan);
      ref.read(penyediaKeranjang.notifier).Ganti(layanan.TambahBaris(ref.read(penyediaKeranjang), baris, katalog, k));
      if (_pesan != null) {
        setState(() => _pesan = null);
      }
    } on GalatKasir catch (galat) {
      _TampilPesan(galat.pesan);
    }
  }

  void _GeserJumlah(String uuidBaris, int arah) {
    final katalog = ref.read(penyediaKatalog).value ?? KatalogLokal.kosong;
    final k = ref.read(penyediaKonteksPenjualan).value;
    if (k == null) {
      return;
    }
    final keranjang = ref.read(penyediaKeranjang);
    final baris = keranjang.baris.firstWhere((b) => b.uuid == uuidBaris);
    try {
      ref
          .read(penyediaKeranjang.notifier)
          .Ganti(
            ref
                .read(penyediaLayananPenjualan)
                .UbahJumlah(keranjang, uuidBaris, baris.jumlah.Tambah(Kuantitas.DariBulat(arah)), katalog, k),
          );
    } on GalatKasir catch (galat) {
      _TampilPesan(galat.pesan);
    }
  }

  void _UbahBaris(String uuidBaris) {
    final baris = ref.read(penyediaKeranjang).baris.firstWhere((b) => b.uuid == uuidBaris);
    setState(() {
      _panel = _JenisPanel.Item;
      _uuidBarisPanel = uuidBaris;
      _produkPanel = ref.read(penyediaKatalog).value?.CariProduk(baris.uuidProduk);
    });
  }

  void _BukaTertahan() => setState(() {
    _panel = _JenisPanel.Tertahan;
    _pesan = null;
  });

  void _BukaBayar() {
    if (ref.read(penyediaKeranjang).CekKosong) {
      _TampilPesan('Keranjang masih kosong. Tambahkan produk dulu.');
      return;
    }
    setState(() {
      _pesan = null;
      _panel = _JenisPanel.Bayar;
    });
  }

  Future<void> _Tahan() async {
    final keranjang = ref.read(penyediaKeranjang);
    final k = await ref.read(penyediaKonteksPenjualan.future);
    final layanan = ref.read(penyediaLayananPenjualan);
    try {
      await layanan.TahanPesanan(keranjang, widget.kasir, layanan.Hitung(keranjang, k).hasil.totalAkhir);
      ref.read(penyediaKeranjang.notifier).Kosongkan();
      _TutupPanel();
      _TampilPesan('Pesanan ditahan. Buka lagi lewat tombol Tertahan.', galat: false);
    } on GalatKasir catch (galat) {
      _TampilPesan(galat.pesan);
    }
  }

  Future<void> _KonfirmasiBatal() async {
    if (ref.read(penyediaKeranjang).CekKosong) {
      return;
    }
    final warna = TokenWarna.AmbilDari(context);
    final ya = await showDialog<bool>(
      context: context,
      builder: (konteks) => AlertDialog(
        title: const Text('Batalkan transaksi ini?'),
        content: const Text('Semua item di keranjang dihapus. Transaksi yang belum dibayar tidak disimpan.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(konteks).pop(false), child: const Text('Kembali')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: warna.bahaya),
            onPressed: () => Navigator.of(konteks).pop(true),
            child: const Text('Batalkan transaksi'),
          ),
        ],
      ),
    );
    if (ya == true) {
      ref.read(penyediaKeranjang.notifier).Kosongkan();
      _TutupPanel();
    }
    _FokusAkar();
  }

  void _TutupPanel() {
    if (!mounted) {
      return;
    }
    setState(() {
      _panel = null;
      _produkPanel = null;
      _uuidBarisPanel = null;
    });
    _FokusAkar();
  }

  void _TransaksiBaru() {
    setState(() {
      _selesai = null;
      _pesan = null;
    });
    _TutupPanel();
  }

  Future<void> _PerbaruiKatalog({bool manual = false}) async {
    if (_memperbarui) {
      return;
    }
    setState(() => _memperbarui = true);
    final hasil = await ref.read(penyediaSesi.notifier).PerbaruiKatalog();
    if (!mounted) {
      return;
    }
    setState(() => _memperbarui = false);
    if (manual) {
      switch (hasil) {
        case HasilPerbaruiKatalog.Lengkap || HasilPerbaruiKatalog.Delta:
          _TampilPesan('Katalog sudah diperbarui.', galat: false);
        case HasilPerbaruiKatalog.Offline:
          _TampilPesan('Belum tersambung ke server. Katalog di perangkat tetap dipakai.');
        case HasilPerbaruiKatalog.Gagal:
          _TampilPesan('Katalog gagal diperbarui. Coba lagi beberapa saat lagi.');
      }
    }
  }

  Future<void> _PerbaruiBerkala() async {
    if (ref.read(penyediaKoneksi) == StatusKoneksi.Online && ref.read(penyediaKeranjang).CekKosong && _panel == null) {
      await _PerbaruiKatalog();
    }
  }

  // Tampilan -----------------------------------------------------------------------------------------------------------

  Widget _BangunKatalog(BuildContext context, KatalogLokal? katalog, KonteksPenjualan? k, bool sempit) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final tepi = sempit ? TokenJarak.jarak16 : TokenJarak.jarak24;
    final tertahan = ref.watch(penyediaPesananTertahan).value?.length ?? 0;
    final daftar = katalog?.AmbilTampil(uuidKategori: _uuidKategori, kata: _cari.text) ?? const <ProdukJual>[];
    final layanan = ref.read(penyediaLayananPenjualan);
    final pesan = _pesan;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: EdgeInsets.fromLTRB(tepi, TokenJarak.jarak16, tepi, TokenJarak.jarak8),
          child: LayoutBuilder(
            builder: (context, batasKepala) {
              final ringkas = batasKepala.maxWidth < 440;
              return Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _cari,
                      focusNode: _fokusCari,
                      textInputAction: TextInputAction.search,
                      onChanged: (_) => setState(() {}),
                      onSubmitted: (kata) {
                        final hasil = katalog?.CariKode(kata);
                        if (hasil != null) {
                          _TambahProduk(hasil.produk, satuan: hasil.satuan);
                          setState(_cari.clear);
                        } else if (daftar.length == 1) {
                          _TambahProduk(daftar.single);
                          setState(_cari.clear);
                        }
                        _FokusAkar();
                      },
                      decoration: InputDecoration(
                        hintText: ringkas ? 'Cari produk atau barcode' : 'Cari nama, SKU, atau barcode (F1)',
                        prefixIcon: const Icon(Icons.search),
                        suffixIcon: _cari.text.isEmpty
                            ? null
                            : IconButton(
                                tooltip: 'Hapus pencarian',
                                onPressed: () => setState(_cari.clear),
                                icon: const Icon(Icons.clear),
                              ),
                        border: const OutlineInputBorder(),
                        isDense: true,
                      ),
                    ),
                  ),
                  const SizedBox(width: TokenJarak.jarak8),
                  if (ringkas)
                    IconButton(
                      tooltip: 'Pesanan tertahan ($tertahan)',
                      onPressed: _BukaTertahan,
                      icon: Badge.count(
                        count: tertahan,
                        isLabelVisible: tertahan > 0,
                        child: const Icon(Icons.pause_circle_outline),
                      ),
                    )
                  else
                    SizedBox(
                      height: TokenJarak.targetSentuh,
                      child: OutlinedButton.icon(
                        onPressed: _BukaTertahan,
                        icon: const Icon(Icons.pause_circle_outline),
                        label: Text('Tertahan ($tertahan)'),
                      ),
                    ),
                  IconButton(
                    tooltip: 'Perbarui katalog',
                    onPressed: _memperbarui ? null : () => _PerbaruiKatalog(manual: true),
                    icon: const Icon(Icons.sync),
                  ),
                ],
              );
            },
          ),
        ),
        if (katalog != null && katalog.kategori.isNotEmpty)
          SizedBox(
            height: TokenJarak.targetSentuh,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: EdgeInsets.symmetric(horizontal: tepi),
              children: [
                for (final (uuid, nama) in [(null, 'Semua'), for (final k in katalog.kategori) (k.Uuid, k.Nama)])
                  Padding(
                    padding: const EdgeInsets.only(right: TokenJarak.jarak8),
                    child: ChoiceChip(
                      label: Text(nama),
                      selected: _uuidKategori == uuid,
                      onSelected: (_) => setState(() => _uuidKategori = uuid),
                    ),
                  ),
              ],
            ),
          ),
        if (pesan != null)
          Padding(
            padding: EdgeInsets.fromLTRB(tepi, TokenJarak.jarak8, tepi, 0),
            child: Semantics(
              liveRegion: true,
              child: Row(
                children: [
                  Icon(
                    pesan.galat ? Icons.error_outline : Icons.info_outline,
                    size: TokenJarak.ikonSedang,
                    color: pesan.galat ? warna.bahaya : warna.info,
                  ),
                  const SizedBox(width: TokenJarak.jarak8),
                  Expanded(child: Text(pesan.teks, style: teks.bodyMedium)),
                  IconButton(
                    tooltip: 'Tutup pesan',
                    onPressed: () => setState(() => _pesan = null),
                    icon: const Icon(Icons.close),
                  ),
                ],
              ),
            ),
          ),
        if (_memperbarui) const LinearProgressIndicator(),
        Expanded(
          child: katalog == null || k == null
              ? const Center(child: CircularProgressIndicator())
              : katalog.CekKosong
              ? _BangunKosong(
                  context,
                  'Katalog belum ada di perangkat ini.',
                  'Sambungkan ke internet lalu ketuk Perbarui katalog.',
                  aksi: _memperbarui ? null : () => _PerbaruiKatalog(manual: true),
                )
              : daftar.isEmpty
              ? _BangunKosong(
                  context,
                  _cari.text.isEmpty ? 'Belum ada produk di kategori ini.' : 'Tidak ada produk yang cocok.',
                  _cari.text.isEmpty ? 'Pilih kategori lain.' : 'Periksa ejaan atau cari dengan SKU/barcode.',
                )
              : GridView.builder(
                  padding: EdgeInsets.fromLTRB(tepi, TokenJarak.jarak8, tepi, tepi),
                  gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                    maxCrossAxisExtent: UbinProduk.lebarMaksimum,
                    mainAxisExtent: UbinProduk.tinggi,
                    crossAxisSpacing: TokenJarak.jarak8,
                    mainAxisSpacing: TokenJarak.jarak8,
                  ),
                  itemCount: daftar.length,
                  itemBuilder: (context, i) {
                    final p = daftar[i];
                    final satuan = p.AmbilSatuanBawaan();
                    final alasan = p.AmbilAlasanTidakBisaDijual();
                    return UbinProduk(
                      key: ValueKey(p.uuid),
                      nama: p.nama,
                      harga: satuan == null
                          ? null
                          : layanan.TentukanHarga(katalog, k, p.uuid, satuan.uuid, Kuantitas.DariBulat(1)),
                      nonaktif: alasan != null,
                      keterangan: alasan != null
                          ? 'Tidak bisa dijual'
                          : p.kelompokPilihan.isNotEmpty
                          ? 'Ada pilihan'
                          : null,
                      saatDiketuk: () => _TambahProduk(p),
                    );
                  },
                ),
        ),
      ],
    );
  }

  Widget _BangunKosong(BuildContext context, String judul, String isi, {VoidCallback? aksi}) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(TokenJarak.jarak24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.inventory_2_outlined, size: TokenJarak.ikonBesar, color: warna.teksSekunder),
            const SizedBox(height: TokenJarak.jarak8),
            Text(judul, style: teks.titleMedium, textAlign: TextAlign.center),
            const SizedBox(height: TokenJarak.jarak4),
            Text(
              isi,
              style: teks.bodyMedium?.copyWith(color: warna.teksSekunder),
              textAlign: TextAlign.center,
            ),
            if (aksi != null) ...[
              const SizedBox(height: TokenJarak.jarak16),
              OutlinedButton(onPressed: aksi, child: const Text('Perbarui katalog')),
            ],
          ],
        ),
      ),
    );
  }

  Widget _BangunKeranjang(HitunganKeranjang? hitungan, {bool tampilKepala = true}) => PanelKeranjang(
    keranjang: ref.watch(penyediaKeranjang),
    hitungan: hitungan,
    tampilKepala: tampilKepala,
    saatUbahBaris: _UbahBaris,
    saatTambah: (uuid) => _GeserJumlah(uuid, 1),
    saatKurang: (uuid) => _GeserJumlah(uuid, -1),
    saatDiskonPesanan: () => setState(() => _panel = _JenisPanel.DiskonPesanan),
    saatTahan: () => unawaited(_Tahan()),
    saatKosongkan: () => unawaited(_KonfirmasiBatal()),
    saatBayar: _BukaBayar,
  );

  /// Bilah bawah HP: ringkasan keranjang (ketuk → lembar keranjang) dan tombol Bayar yang menempel.
  Widget _BangunBilahHp(BuildContext context, HitunganKeranjang? hitungan) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final keranjang = ref.watch(penyediaKeranjang);
    return Material(
      color: warna.permukaan,
      child: Container(
        padding: const EdgeInsets.fromLTRB(
          TokenJarak.jarak16,
          TokenJarak.jarak8,
          TokenJarak.jarak16,
          TokenJarak.jarak8,
        ),
        decoration: BoxDecoration(
          border: Border(
            top: BorderSide(color: warna.garis, width: TokenJarak.tebalGaris),
          ),
        ),
        child: Row(
          children: [
            Expanded(
              child: InkWell(
                onTap: () => setState(() => _panel = _JenisPanel.Keranjang),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(minHeight: 56),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        keranjang.CekKosong ? 'Keranjang kosong' : 'Keranjang · ${keranjang.baris.length} baris',
                        style: teks.bodySmall,
                      ),
                      TeksUang(hitungan?.hasil.totalAkhir ?? Uang.Nol(), rataKanan: false, gaya: teks.titleMedium),
                    ],
                  ),
                ),
              ),
            ),
            SizedBox(
              height: 56,
              child: FilledButton(onPressed: keranjang.CekKosong ? null : _BukaBayar, child: const Text('Bayar')),
            ),
          ],
        ),
      ),
    );
  }

  ({String judul, Widget isi})? _AmbilIsiPanel(HitunganKeranjang? hitungan, double tinggiArea) {
    final keranjang = ref.read(penyediaKeranjang);
    return switch (_panel) {
      null => null,
      _JenisPanel.Keranjang => (
        judul: 'Keranjang',
        // Lembar maks. 92% area; dikurangi kepala panel (±57dp) agar tombol Bayar tetap terlihat.
        isi: SizedBox(
          height: (tinggiArea * 0.92 - 64).clamp(240, double.infinity),
          child: _BangunKeranjang(hitungan, tampilKepala: false),
        ),
      ),
      _JenisPanel.Item => (
        judul: _uuidBarisPanel == null
            ? (_produkPanel?.nama ?? 'Item')
            : keranjang.baris.where((b) => b.uuid == _uuidBarisPanel).firstOrNull?.nama ?? 'Item',
        isi: PanelItem(
          key: ValueKey('item-${_uuidBarisPanel ?? _produkPanel?.uuid}'),
          produk: _produkPanel,
          baris: keranjang.baris.where((b) => b.uuid == _uuidBarisPanel).firstOrNull,
          kasir: widget.kasir,
          saatSelesai: _TutupPanel,
        ),
      ),
      _JenisPanel.DiskonPesanan => (
        judul: 'Diskon pesanan',
        isi: PanelDiskonPesanan(kasir: widget.kasir, saatSelesai: _TutupPanel),
      ),
      _JenisPanel.Bayar => (
        judul: 'Bayar',
        isi: PanelBayar(
          key: _kunciBayar,
          kasir: widget.kasir,
          saatSelesai: (hasil) => setState(() {
            _selesai = hasil;
            _panel = _JenisPanel.Selesai;
          }),
        ),
      ),
      _JenisPanel.Selesai => (
        judul: 'Transaksi selesai',
        isi: _selesai == null
            ? const SizedBox.shrink()
            : TampilanSelesai(hasil: _selesai!, saatTransaksiBaru: _TransaksiBaru),
      ),
      _JenisPanel.Tertahan => (judul: 'Pesanan tertahan', isi: PanelTertahan(saatDibuka: _TutupPanel)),
    };
  }

  @override
  Widget build(BuildContext context) {
    final katalog = ref.watch(penyediaKatalog).value;
    final k = ref.watch(penyediaKonteksPenjualan).value;
    final keranjang = ref.watch(penyediaKeranjang);
    final posisi = ref.watch(penyediaPengaturanPerangkat.select((p) => p.posisiKeranjang));
    final warna = TokenWarna.AmbilDari(context);
    final lebarLayar = MediaQuery.sizeOf(context).width;
    HitunganKeranjang? hitungan;
    if (k != null && !keranjang.CekKosong) {
      try {
        hitungan = ref.read(penyediaLayananPenjualan).Hitung(keranjang, k);
      } on ArgumentError {
        hitungan = null;
      }
    }

    return Focus(
      focusNode: _fokusAkar,
      onKeyEvent: _SaatTombolPintasan,
      child: LayoutBuilder(
        builder: (context, batas) {
          final duaPanel = batas.maxWidth >= LayarJual.lebarDuaPanel;
          final lebarKeranjang = batas.maxWidth >= 960 ? 400.0 : 320.0;
          final Widget isi;
          if (duaPanel) {
            final keranjangSamping = SizedBox(width: lebarKeranjang, child: _BangunKeranjang(hitungan));
            final pemisah = VerticalDivider(
              width: TokenJarak.tebalGaris,
              thickness: TokenJarak.tebalGaris,
              color: warna.garis,
            );
            isi = Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: posisi == PosisiKeranjang.Kiri
                  ? [keranjangSamping, pemisah, Expanded(child: _BangunKatalog(context, katalog, k, false))]
                  : [Expanded(child: _BangunKatalog(context, katalog, k, false)), pemisah, keranjangSamping],
            );
          } else {
            isi = Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Expanded(child: _BangunKatalog(context, katalog, k, true)),
                _BangunBilahHp(context, hitungan),
              ],
            );
          }
          final panel = _AmbilIsiPanel(hitungan, batas.maxHeight);
          if (panel == null) {
            return isi;
          }
          final samping = lebarLayar >= 1024;
          final tutup = _panel == _JenisPanel.Selesai ? _TransaksiBaru : _TutupPanel;
          return Stack(
            children: [
              Positioned.fill(child: isi),
              Positioned.fill(
                child: Semantics(
                  label: 'Tutup ${panel.judul}',
                  button: true,
                  child: GestureDetector(
                    onTap: tutup,
                    child: ColoredBox(color: warna.teksUtama.withValues(alpha: 0.24)),
                  ),
                ),
              ),
              if (samping)
                Positioned(
                  top: 0,
                  right: 0,
                  bottom: 0,
                  child: PanelTugas(judul: panel.judul, saatTutup: tutup, anak: panel.isi),
                )
              else
                Positioned(
                  left: 0,
                  right: 0,
                  bottom: 0,
                  child: ConstrainedBox(
                    constraints: BoxConstraints(maxHeight: batas.maxHeight * 0.92),
                    child: PanelTugas(
                      judul: panel.judul,
                      saatTutup: tutup,
                      tataLetak: TataLetakPanel.Lembar,
                      anak: panel.isi,
                    ),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}
