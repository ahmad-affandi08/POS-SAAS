import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:klien_api/KlienApi.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Domain/Perangkat/PenjagaLayarMenyala.dart';
import '../../Domain/Sesi/StafLokal.dart';
import '../Komponen/FormatWaktu.dart';
import '../LayarJual.dart';
import '../LayarKas.dart';
import '../LayarPengaturan.dart';
import '../LayarRiwayat.dart';
import '../LayarShift.dart';
import '../LayarStatusSinkron.dart';
import '../LembarBukaLaci.dart';
import '../LembarMutasiKas.dart';
import '../Meja/LayarMeja.dart';
import '../Penjualan/LembarAmbilPreOrder.dart';
import '../Penjualan/LembarRetur.dart';
import '../Penjualan/LembarVoid.dart';
import '../Shift/KartuLaporanShift.dart';
import '../Shift/LembarTutupShift.dart';
import '../Struk/BagianCetakDokumen.dart';
import 'BilahAtasRuangKerja.dart';
import 'ItemNavigasi.dart';
import 'LayarKunci.dart';
import 'PanelWajibPembaruan.dart';

/// Bingkai Ruang Kerja Kasir (PRD §17.2.7, D-16): bilah atas, rel navigasi (bilah bawah di HP), area kerja, dan bilah
/// status. Membungkus semua layar setelah shift terbuka; layar fitur hanya mengisi area kerja. Tugas rutin (kas
/// masuk/keluar/setoran) dibuka sebagai panel samping (≥ 1024dp) atau lembar bawah di atas area kerja.
///
/// Selama bingkai ini tampil (shift terbuka): layar dijaga tetap menyala, outbox dikirim tiap 30 detik, dan layar
/// terkunci otomatis setelah perangkat diam selama waktu di pengaturan perangkat. Saat terkunci, area kerja tetap
/// hidup di bawah layar kunci (keranjang tidak hilang).
///
/// Mode Pelayan (v2.00): [shift] null untuk perangkat berjenis `Pelayan` — rel berisi Meja (beranda), Pesanan,
/// Sinkron, Pengaturan; tanpa kas, shift, riwayat, dan pembayaran.
class RuangKerja extends ConsumerStatefulWidget {
  const RuangKerja({super.key, required this.shift, required this.kasir, this.kunci = KeadaanKunci.Bebas});

  /// Lebar minimum untuk rel navigasi kiri; di bawahnya memakai bilah navigasi bawah.
  static const double lebarRel = 600;

  /// Lebar minimum untuk panel samping; di bawahnya panel tugas tampil sebagai lembar bawah.
  static const double lebarPanelSamping = 1024;

  static const Duration selangSinkron = Duration(seconds: 30);

  /// Mode meja: snapshot pesanan terbuka outlet ditarik tiap 7 detik (rentang 5–10 detik, Rincian F-07 mode meja).
  static const Duration selangPesananMeja = Duration(seconds: 7);

  /// Null = mode Pelayan (tanpa shift).
  final BarisShift? shift;
  final StafLokal kasir;
  final KeadaanKunci kunci;

  @override
  ConsumerState<RuangKerja> createState() => _RuangKerjaState();
}

class _RuangKerjaState extends ConsumerState<RuangKerja> {
  late TujuanRuangKerja _tujuan = _beranda;

  bool get _pelayan => widget.shift == null;

  /// Beranda: Jual untuk kasir, Meja untuk pelayan.
  TujuanRuangKerja get _beranda => _pelayan ? TujuanRuangKerja.Meja : TujuanRuangKerja.Jual;
  bool _relDiciutkan = false;

  /// Jenis mutasi kas yang formulirnya sedang terbuka di panel tugas (null = tertutup).
  String? _jenisKas;

  /// Panel shift yang sedang terbuka (F-11: tutup shift atau laporan X); null = tertutup.
  _PanelShift? _panelShift;

  /// Panel void/retur yang sedang terbuka (F-09); null = tertutup.
  _PanelPenjualan? _panelPenjualan;

  bool get _adaPanel => _jenisKas != null || _panelShift != null || _panelPenjualan != null;

  Timer? _pewaktuSinkron;
  Timer? _pewaktuPesanan;
  Timer? _pewaktuDiam;
  bool _menarikPesanan = false;
  late final PenjagaLayarMenyala _penjagaLayar;

  bool get _terkunci => widget.kunci != KeadaanKunci.Bebas;

  KonfigurasiAplikasi? get _konfigurasi => ref.watch(penyediaKonfigurasiAplikasi);

  @override
  void initState() {
    super.initState();
    _penjagaLayar = ref.read(penyediaPenjagaLayar);
    unawaited(_penjagaLayar.Aktifkan());
    _pewaktuSinkron = Timer.periodic(RuangKerja.selangSinkron, (_) => unawaited(_Sinkronkan()));
    unawaited(Future<void>.microtask(_Sinkronkan));
    _pewaktuPesanan = Timer.periodic(RuangKerja.selangPesananMeja, (_) => unawaited(_TarikPesanan()));
    HardwareKeyboard.instance.addHandler(_SaatTombol);
    _MulaiHitungDiam();
  }

  @override
  void didUpdateWidget(RuangKerja lama) {
    super.didUpdateWidget(lama);
    if (widget.kunci == lama.kunci) {
      return;
    }
    if (_terkunci) {
      _pewaktuDiam?.cancel();
      // Tutup dialog/menu yang masih terbuka (mis. PIN supervisor) agar tidak ada yang tertinggal di atas layar kunci.
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          Navigator.of(context).popUntil((rute) => rute.isFirst);
        }
      });
    } else {
      _MulaiHitungDiam();
    }
  }

  @override
  void dispose() {
    _pewaktuSinkron?.cancel();
    _pewaktuPesanan?.cancel();
    _pewaktuDiam?.cancel();
    HardwareKeyboard.instance.removeHandler(_SaatTombol);
    unawaited(_penjagaLayar.Nonaktifkan());
    super.dispose();
  }

  Future<void> _Sinkronkan() async {
    if (mounted) {
      await ref.read(penyediaSesi.notifier).Sinkronkan();
    }
  }

  /// Tarik pesanan terbuka outlet (mode meja aktif, tidak terkunci, tidak sedang menarik). Galat diabaikan: data lokal
  /// tetap dipakai dan dicoba lagi pada putaran berikutnya.
  Future<void> _TarikPesanan() async {
    if (!mounted || _menarikPesanan || _terkunci || ref.read(penyediaModeMeja).value != true) {
      return;
    }
    _menarikPesanan = true;
    try {
      await ref.read(penyediaLayananPesananMeja).Tarik();
    } on Object {
      // Offline/galat server: coba lagi nanti.
    }
    try {
      // F-17: pesanan QR meja yang menunggu konfirmasi.
      await ref.read(penyediaPesanSendiri.notifier).Tarik();
    } on Object {
      // Offline/galat server: daftar terakhir tetap tampil.
    } finally {
      _menarikPesanan = false;
    }
  }

  /// Hitung ulang waktu diam dari nol (setiap sentuhan, gulir, gerak tetikus, atau tombol).
  void _MulaiHitungDiam() {
    _pewaktuDiam?.cancel();
    if (_terkunci) {
      return;
    }
    final batas = ref.read(penyediaPengaturanPerangkat).AmbilBatasDiam();
    _pewaktuDiam = Timer(batas, () {
      if (mounted) {
        ref.read(penyediaSesi.notifier).Kunci();
      }
    });
  }

  void _CatatAktivitas(PointerEvent _) => _MulaiHitungDiam();

  bool _SaatTombol(KeyEvent _) {
    _MulaiHitungDiam();
    return false;
  }

  void _Buka(TujuanRuangKerja tujuan) => setState(() => _tujuan = tujuan);

  void _BukaPanelKas(String jenis) => setState(() {
    _panelShift = null;
    _panelPenjualan = null;
    _jenisKas = jenis;
  });

  void _BukaPanelShift(_PanelShift panel) => setState(() {
    _jenisKas = null;
    _panelPenjualan = null;
    _panelShift = panel;
  });

  void _BukaPanelPenjualan(_PanelPenjualan panel) => setState(() {
    _jenisKas = null;
    _panelShift = null;
    _panelPenjualan = panel;
  });

  void _TutupPanel() => setState(() {
    _jenisKas = null;
    _panelShift = null;
    _panelPenjualan = null;
  });

  /// Tombol kembali (Android) selalu kembali ke area kerja: tutup panel, lalu ke beranda Jual. Tidak keluar aplikasi.
  void _SaatKembali(bool sudahKembali, Object? _) {
    if (sudahKembali || _terkunci) {
      return;
    }
    if (_adaPanel) {
      _TutupPanel();
    } else if (_tujuan != _beranda) {
      _Buka(_beranda);
    }
  }

  Widget _BangunLayar(TujuanRuangKerja tujuan) => switch (tujuan) {
    // P-10: di bawah versi minimal, layar jual & meja dikunci sampai aplikasi diperbarui (outbox tetap terkirim).
    TujuanRuangKerja.Jual ||
    TujuanRuangKerja.Meja when _konfigurasi?.wajibPembaruan == true => PanelWajibPembaruan(konfigurasi: _konfigurasi!),
    TujuanRuangKerja.Jual => LayarJual(
      kasir: widget.kasir,
      aktif: _tujuan == TujuanRuangKerja.Jual && !_terkunci && !_adaPanel,
      saatKeMeja: _pelayan || ref.watch(penyediaModeMeja).value == true ? () => _Buka(TujuanRuangKerja.Meja) : null,
      modePelayan: _pelayan,
    ),
    TujuanRuangKerja.Meja => LayarMeja(kasir: widget.kasir, saatBukaPesanan: () => _Buka(TujuanRuangKerja.Jual)),
    TujuanRuangKerja.Riwayat => LayarRiwayat(
      saatVoid: (uuid) => _BukaPanelPenjualan(_PanelPenjualan(uuidPenjualanVoid: uuid)),
      saatRetur: () => _BukaPanelPenjualan(const _PanelPenjualan()),
      saatAmbilPreOrder: () => _BukaPanelPenjualan(const _PanelPenjualan(ambilPreOrder: true)),
    ),
    TujuanRuangKerja.Kas => LayarKas(shift: widget.shift!, saatCatat: _BukaPanelKas),
    TujuanRuangKerja.Shift => LayarShift(
      shift: widget.shift!,
      kasir: widget.kasir,
      saatTutupShift: () => _BukaPanelShift(_PanelShift.Tutup),
      saatLaporanX: () => _BukaPanelShift(_PanelShift.LaporanX),
    ),
    TujuanRuangKerja.StatusSinkron => const LayarStatusSinkron(),
    TujuanRuangKerja.Pengaturan => const LayarPengaturan(),
  };

  List<ItemBilahStatus> _AmbilItemStatus() {
    final koneksi = ref.watch(penyediaKoneksi);
    final tertunda = ref.watch(penyediaJumlahTertunda).value ?? 0;
    final perluTindakan = ref.watch(penyediaPerluTindakan).value?.length ?? 0;
    return [
      switch (koneksi) {
        StatusKoneksi.Online => const ItemBilahStatus(ikon: Icons.wifi, teks: 'Online', nada: NadaStatus.Sukses),
        StatusKoneksi.Offline => const ItemBilahStatus(
          ikon: Icons.wifi_off,
          teks: 'Offline',
          nada: NadaStatus.Peringatan,
        ),
        StatusKoneksi.BelumDiketahui => const ItemBilahStatus(ikon: Icons.wifi_find, teks: 'Memeriksa koneksi'),
      },
      if (perluTindakan > 0)
        ItemBilahStatus(ikon: Icons.error_outline, teks: '$perluTindakan perlu tindakan', nada: NadaStatus.Bahaya)
      else if (tertunda > 0)
        ItemBilahStatus(
          ikon: Icons.cloud_upload_outlined,
          teks: '$tertunda belum terkirim',
          nada: NadaStatus.Peringatan,
        )
      else
        const ItemBilahStatus(ikon: Icons.cloud_done_outlined, teks: 'Tersinkron', nada: NadaStatus.Sukses),
      if (_konfigurasi case final k? when k.wajibPembaruan)
        const ItemBilahStatus(ikon: Icons.system_update, teks: 'Wajib perbarui aplikasi', nada: NadaStatus.Bahaya)
      else if (_konfigurasi case final k? when k.adaPembaruan)
        ItemBilahStatus(ikon: Icons.system_update_outlined, teks: 'Versi ${k.versiTerbaru ?? 'baru'} tersedia'),
      switch (ref.watch(penyediaPrinter)) {
        StatusPrinter(keadaan: KeadaanPrinter.BelumDiatur) => const ItemBilahStatus(
          ikon: Icons.print_disabled_outlined,
          teks: 'Printer belum diatur',
        ),
        StatusPrinter(keadaan: KeadaanPrinter.Gagal) => const ItemBilahStatus(
          ikon: Icons.print_disabled_outlined,
          teks: 'Printer bermasalah',
          nada: NadaStatus.Bahaya,
        ),
        StatusPrinter(keadaan: KeadaanPrinter.Mencetak) => const ItemBilahStatus(
          ikon: Icons.print_outlined,
          teks: 'Mencetak…',
        ),
        StatusPrinter() => const ItemBilahStatus(
          ikon: Icons.print_outlined,
          teks: 'Printer siap',
          nada: NadaStatus.Sukses,
        ),
      },
      if (widget.shift case final shift?)
        ItemBilahStatus(ikon: Icons.schedule, teks: 'Shift ${FormatWaktu.FormatJam(shift.DibukaPada)}')
      else
        const ItemBilahStatus(ikon: Icons.room_service_outlined, teks: 'Mode pelayan'),
    ];
  }

  Widget _BangunRel(List<ItemNavigasi> item, int indeks, double lebar) {
    final lebarPenuh = lebar >= RuangKerja.lebarPanelSamping;
    final diperluas = !_relDiciutkan && lebarPenuh;
    return NavigationRail(
      extended: diperluas,
      minExtendedWidth: 208,
      labelType: _relDiciutkan || diperluas ? NavigationRailLabelType.none : NavigationRailLabelType.all,
      selectedIndex: indeks,
      onDestinationSelected: (i) => _Buka(item[i].tujuan),
      leading: IconButton(
        tooltip: _relDiciutkan ? 'Lebarkan menu' : 'Ciutkan menu',
        onPressed: () => setState(() => _relDiciutkan = !_relDiciutkan),
        icon: Icon(_relDiciutkan ? Icons.menu : Icons.menu_open),
      ),
      destinations: [
        for (final i in item)
          NavigationRailDestination(icon: Icon(i.ikon), selectedIcon: Icon(i.ikonAktif), label: Text(i.label)),
      ],
    );
  }

  Widget _BangunAreaKerja(List<ItemNavigasi> item, int indeks, double lebar) {
    final warna = TokenWarna.AmbilDari(context);
    final jenisKas = _jenisKas;
    final panelShift = _panelShift;
    final panelPenjualan = _panelPenjualan;
    final area = IndexedStack(index: indeks, children: [for (final i in item) _BangunLayar(i.tujuan)]);
    if (jenisKas == null && panelShift == null && panelPenjualan == null) {
      return area;
    }

    final (judul, formulir) = switch ((jenisKas, panelShift, panelPenjualan)) {
      (_, _, _PanelPenjualan(uuidPenjualanVoid: final String uuid)) => (
        LembarVoid.judul,
        LembarVoid(key: ValueKey('Void-$uuid'), uuidPenjualan: uuid, kasir: widget.kasir, saatSelesai: _TutupPanel)
            as Widget,
      ),
      (_, _, _PanelPenjualan(ambilPreOrder: true)) => (
        LembarAmbilPreOrder.judul,
        LembarAmbilPreOrder(
          key: const ValueKey('AmbilPreOrder'),
          saatDimuat: () {
            _TutupPanel();
            _Buka(TujuanRuangKerja.Jual);
          },
        ) as Widget,
      ),
      (_, _, _PanelPenjualan()) => (
        LembarRetur.judul,
        LembarRetur(key: const ValueKey('Retur'), kasir: widget.kasir, saatSelesai: _TutupPanel),
      ),
      (LembarBukaLaci.kunciPanel, _, _) => (
        LembarBukaLaci.judul,
        LembarBukaLaci(
          key: const ValueKey(LembarBukaLaci.kunciPanel),
          shift: widget.shift!,
          pembuka: widget.kasir,
          saatSelesai: _TutupPanel,
        ) as Widget,
      ),
      (final String jenis, _, _) => (
        LembarMutasiKas.AmbilJudul(jenis),
        LembarMutasiKas(
          key: ValueKey(jenis),
          shift: widget.shift!,
          jenis: jenis,
          pencatat: widget.kasir,
          saatTersimpan: _TutupPanel,
        ) as Widget,
      ),
      (_, _PanelShift.Tutup, _) => (
        LembarTutupShift.judul,
        LembarTutupShift(key: const ValueKey('TutupShift'), shift: widget.shift!, penutup: widget.kasir),
      ),
      _ => ('Laporan X', _IsiLaporanX(uuidShift: widget.shift!.Uuid)),
    };

    if (lebar >= RuangKerja.lebarPanelSamping) {
      return Stack(
        children: [
          Positioned.fill(child: area),
          Positioned(
            top: 0,
            right: 0,
            bottom: 0,
            child: PanelTugas(judul: judul, saatTutup: _TutupPanel, anak: formulir),
          ),
        ],
      );
    }

    return LayoutBuilder(
      builder: (context, batas) => Stack(
        children: [
          Positioned.fill(child: area),
          Positioned.fill(
            child: Semantics(
              label: 'Tutup $judul',
              button: true,
              child: GestureDetector(
                onTap: _TutupPanel,
                child: ColoredBox(color: warna.teksUtama.withValues(alpha: 0.24)),
              ),
            ),
          ),
          Positioned(
            left: 0,
            right: 0,
            bottom: 0,
            child: ConstrainedBox(
              constraints: BoxConstraints(maxHeight: batas.maxHeight * 0.9),
              child: PanelTugas(judul: judul, saatTutup: _TutupPanel, tataLetak: TataLetakPanel.Lembar, anak: formulir),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    ref.listen(penyediaPengaturanPerangkat.select((p) => p.menitKunciOtomatis), (_, _) => _MulaiHitungDiam());

    final warna = TokenWarna.AmbilDari(context);
    final lebar = MediaQuery.sizeOf(context).width;
    final pakaiRel = lebar >= RuangKerja.lebarRel;
    final item = _pelayan
        ? ItemNavigasi.pelayan
        : ItemNavigasi.Saring(
            widget.kasir,
            modulAktif: {if (ref.watch(penyediaModeMeja).value == true) ItemNavigasi.modulMeja},
          );
    final indeks = item.indexWhere((i) => i.tujuan == _tujuan).clamp(0, item.length - 1);
    final notifierSesi = ref.read(penyediaSesi.notifier);

    final bingkai = Scaffold(
      body: SafeArea(
        bottom: pakaiRel,
        child: Column(
          children: [
            BilahAtasRuangKerja(
              kasir: widget.kasir,
              saatGantiKasir: () => notifierSesi.Kunci(gantiKasir: true),
              saatKunci: notifierSesi.Kunci,
            ),
            Expanded(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (pakaiRel) ...[
                    _BangunRel(item, indeks, lebar),
                    VerticalDivider(width: TokenJarak.tebalGaris, thickness: TokenJarak.tebalGaris, color: warna.garis),
                  ],
                  // Batas lukis sendiri: perubahan keranjang tidak melukis ulang bilah atas, rel, dan bilah status.
                  Expanded(child: RepaintBoundary(child: _BangunAreaKerja(item, indeks, lebar))),
                ],
              ),
            ),
            BilahStatus(
              item: _AmbilItemStatus(),
              petunjuk: 'Buka status sinkron',
              saatDiketuk: () => _Buka(TujuanRuangKerja.StatusSinkron),
            ),
          ],
        ),
      ),
      bottomNavigationBar: pakaiRel
          ? null
          : NavigationBar(
              selectedIndex: indeks,
              onDestinationSelected: (i) => _Buka(item[i].tujuan),
              labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
              destinations: [
                for (final i in item)
                  NavigationDestination(icon: Icon(i.ikon), selectedIcon: Icon(i.ikonAktif), label: i.label),
              ],
            ),
    );

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: _SaatKembali,
      child: Listener(
        behavior: HitTestBehavior.translucent,
        onPointerDown: _CatatAktivitas,
        onPointerHover: _CatatAktivitas,
        onPointerSignal: _CatatAktivitas,
        child: Stack(
          children: [
            Positioned.fill(
              child: Offstage(
                offstage: _terkunci,
                child: TickerMode(
                  enabled: !_terkunci,
                  child: ExcludeFocus(excluding: _terkunci, child: bingkai),
                ),
              ),
            ),
            if (_terkunci)
              Positioned.fill(
                child: LayarKunci(
                  key: ValueKey(widget.kunci),
                  kasir: widget.kasir,
                  gantiKasir: widget.kunci == KeadaanKunci.GantiKasir,
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// Panel shift di ruang kerja (F-11).
enum _PanelShift { Tutup, LaporanX }

/// Panel void (dengan Uuid penjualan) atau retur dari struk (tanpa Uuid) di ruang kerja (F-09).
class _PanelPenjualan {
  const _PanelPenjualan({this.uuidPenjualanVoid, this.ambilPreOrder = false});

  final String? uuidPenjualanVoid;

  /// F-12 bagian 2: cari & ambil pre-order.
  final bool ambilPreOrder;
}

/// Laporan X: ringkasan shift berjalan dari data perangkat, bisa dibuka kapan saja dari layar Shift.
class _IsiLaporanX extends ConsumerWidget {
  const _IsiLaporanX({required this.uuidShift});

  final String uuidShift;

  @override
  Widget build(BuildContext context, WidgetRef ref) => Padding(
    padding: const EdgeInsets.all(TokenJarak.jarak24),
    child: ref
        .watch(penyediaLaporanShift(uuidShift))
        .when(
          loading: () => const LinearProgressIndicator(),
          error: (galat, _) => Text('Laporan tidak bisa dibaca: $galat'),
          data: (laporan) => Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              KartuLaporanShift(laporan: laporan),
              const SizedBox(height: TokenJarak.jarak12),
              // Cetak struk bagian 3b: laporan X hanya dicetak bila diminta (tanpa kas seharusnya saat tutup buta).
              BagianCetakDokumen(
                kunci: 'LaporanX:$uuidShift',
                namaDokumen: 'laporan X',
                otomatis: false,
                cetak: (layanan, _, _) => layanan.CetakLaporanShift(laporan),
              ),
            ],
          ),
        ),
  );
}
