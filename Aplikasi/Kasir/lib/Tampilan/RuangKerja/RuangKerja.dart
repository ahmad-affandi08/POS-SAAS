import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
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
import '../LembarMutasiKas.dart';
import 'BilahAtasRuangKerja.dart';
import 'ItemNavigasi.dart';
import 'LayarKunci.dart';

/// Bingkai Ruang Kerja Kasir (PRD §17.2.7, D-16): bilah atas, rel navigasi (bilah bawah di HP), area kerja, dan bilah
/// status. Membungkus semua layar setelah shift terbuka; layar fitur hanya mengisi area kerja. Tugas rutin (kas
/// masuk/keluar/setoran) dibuka sebagai panel samping (≥ 1024dp) atau lembar bawah di atas area kerja.
///
/// Selama bingkai ini tampil (shift terbuka): layar dijaga tetap menyala, outbox dikirim tiap 30 detik, dan layar
/// terkunci otomatis setelah perangkat diam selama waktu di pengaturan perangkat. Saat terkunci, area kerja tetap
/// hidup di bawah layar kunci (keranjang tidak hilang).
class RuangKerja extends ConsumerStatefulWidget {
  const RuangKerja({super.key, required this.shift, required this.kasir, this.kunci = KeadaanKunci.Bebas});

  /// Lebar minimum untuk rel navigasi kiri; di bawahnya memakai bilah navigasi bawah.
  static const double lebarRel = 600;

  /// Lebar minimum untuk panel samping; di bawahnya panel tugas tampil sebagai lembar bawah.
  static const double lebarPanelSamping = 1024;

  static const Duration selangSinkron = Duration(seconds: 30);

  final BarisShift shift;
  final StafLokal kasir;
  final KeadaanKunci kunci;

  @override
  ConsumerState<RuangKerja> createState() => _RuangKerjaState();
}

class _RuangKerjaState extends ConsumerState<RuangKerja> {
  TujuanRuangKerja _tujuan = TujuanRuangKerja.Jual;
  bool _relDiciutkan = false;

  /// Jenis mutasi kas yang formulirnya sedang terbuka di panel tugas (null = tertutup).
  String? _jenisKas;

  Timer? _pewaktuSinkron;
  Timer? _pewaktuDiam;
  late final PenjagaLayarMenyala _penjagaLayar;

  bool get _terkunci => widget.kunci != KeadaanKunci.Bebas;

  @override
  void initState() {
    super.initState();
    _penjagaLayar = ref.read(penyediaPenjagaLayar);
    unawaited(_penjagaLayar.Aktifkan());
    _pewaktuSinkron = Timer.periodic(RuangKerja.selangSinkron, (_) => unawaited(_Sinkronkan()));
    unawaited(Future<void>.microtask(_Sinkronkan));
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

  void _BukaPanelKas(String jenis) => setState(() => _jenisKas = jenis);

  void _TutupPanel() => setState(() => _jenisKas = null);

  /// Tombol kembali (Android) selalu kembali ke area kerja: tutup panel, lalu ke beranda Jual. Tidak keluar aplikasi.
  void _SaatKembali(bool sudahKembali, Object? _) {
    if (sudahKembali || _terkunci) {
      return;
    }
    if (_jenisKas != null) {
      _TutupPanel();
    } else if (_tujuan != TujuanRuangKerja.Jual) {
      _Buka(TujuanRuangKerja.Jual);
    }
  }

  Widget _BangunLayar(TujuanRuangKerja tujuan) => switch (tujuan) {
    TujuanRuangKerja.Jual => LayarJual(
      kasir: widget.kasir,
      aktif: _tujuan == TujuanRuangKerja.Jual && !_terkunci && _jenisKas == null,
    ),
    TujuanRuangKerja.Riwayat => const LayarRiwayat(),
    TujuanRuangKerja.Kas => LayarKas(shift: widget.shift, saatCatat: _BukaPanelKas),
    TujuanRuangKerja.Shift => LayarShift(shift: widget.shift, kasir: widget.kasir),
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
      const ItemBilahStatus(ikon: Icons.print_disabled_outlined, teks: 'Printer belum diatur'),
      ItemBilahStatus(ikon: Icons.schedule, teks: 'Shift ${FormatWaktu.FormatJam(widget.shift.DibukaPada)}'),
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
    final area = IndexedStack(index: indeks, children: [for (final i in item) _BangunLayar(i.tujuan)]);
    if (jenisKas == null) {
      return area;
    }

    final judul = LembarMutasiKas.AmbilJudul(jenisKas);
    final formulir = LembarMutasiKas(
      key: ValueKey(jenisKas),
      shift: widget.shift,
      jenis: jenisKas,
      pencatat: widget.kasir,
      saatTersimpan: _TutupPanel,
    );

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
    final item = ItemNavigasi.Saring(widget.kasir);
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
