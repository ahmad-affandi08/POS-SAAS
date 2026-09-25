import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:klien_api/KlienApi.dart';
import 'package:mesin_kasir/MesinKasir.dart' show Kuantitas;
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/Dapur/LayananDapur.dart';
import '../../Domain/GalatKasir.dart';
import '../Komponen/FormatAngka.dart';

/// Layar dapur / KDS (F-10b fase 1) untuk perangkat berjenis `Kds`: tiket aktif outlet per stasiun sebagai kartu, urut
/// waktu kirim (terlama dulu). Umur tiket berwarna dan bertulisan (normal < 10 menit, "Lama" 10–20, "Terlambat" > 20;
/// jam server dipakai agar tidak bergantung jam perangkat). Ketuk tombol utama kartu = maju satu status; "Kembalikan"
/// = mundur satu langkah untuk salah ketuk. Tiket ditarik ulang tiap [selangTarik] detik (online di fase 1).
class LayarKds extends ConsumerStatefulWidget {
  const LayarKds({super.key});

  static const Duration selangTarik = Duration(seconds: 5);
  static const double lebarKartu = 300;

  @override
  ConsumerState<LayarKds> createState() => _LayarKdsState();
}

class _LayarKdsState extends ConsumerState<LayarKds> {
  Timer? _pewaktu;
  List<TiketDapurPos> _tiket = const [];
  List<StasiunDapurPos> _stasiun = const [];
  List<String> _terpilih = const [];

  /// Selisih jam server − jam perangkat saat tiket terakhir ditarik.
  Duration _selisihJam = Duration.zero;
  bool _dimuat = false;
  bool _menarik = false;
  String? _galat;
  final Set<String> _sibuk = {};

  @override
  void initState() {
    super.initState();
    unawaited(_Mulai());
    _pewaktu = Timer.periodic(LayarKds.selangTarik, (_) => unawaited(_Tarik()));
  }

  @override
  void dispose() {
    _pewaktu?.cancel();
    super.dispose();
  }

  LayananDapur get _layanan => ref.read(penyediaLayananDapur);

  Future<void> _Mulai() async {
    _terpilih = await _layanan.AmbilStasiunTerpilih();
    try {
      final stasiun = await _layanan.AmbilStasiun();
      if (mounted) {
        setState(() => _stasiun = stasiun);
      }
    } on GalatKasir {
      // Daftar stasiun dicoba lagi saat penyaring dibuka.
    }
    await _Tarik();
  }

  Future<void> _Tarik() async {
    if (_menarik || !mounted) {
      return;
    }
    _menarik = true;
    try {
      final daftar = await _layanan.AmbilTiket(_terpilih);
      if (!mounted) {
        return;
      }
      final jam = ref.read(penyediaJam)().toUtc();
      setState(() {
        _tiket = daftar.tiket;
        _selisihJam = daftar.waktuServer == null ? Duration.zero : daftar.waktuServer!.toUtc().difference(jam);
        _galat = null;
        _dimuat = true;
      });
      ref.read(penyediaKoneksi.notifier).Tandai(StatusKoneksi.Online);
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() {
          _galat = galat.pesan;
          _dimuat = true;
        });
        if (galat.kode == 'Offline') {
          ref.read(penyediaKoneksi.notifier).Tandai(StatusKoneksi.Offline);
        } else if (galat.kode == 'PerangkatDicabut' || galat.kode == 'TokenPerangkatTidakValid') {
          // Perangkat dicabut dari back-office: alur sesi mengembalikan ke layar aktivasi.
          unawaited(ref.read(penyediaSesi.notifier).SegarkanData());
        }
      }
    } finally {
      _menarik = false;
    }
  }

  Future<void> _Ubah(TiketDapurPos tiket, String status) async {
    setState(() => _sibuk.add(tiket.uuid));
    try {
      await _layanan.UbahStatus(tiket.uuid, status);
      await _Tarik();
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _galat = galat.pesan);
      }
    } finally {
      if (mounted) {
        setState(() => _sibuk.remove(tiket.uuid));
      }
    }
  }

  Future<void> _PilihStasiun() async {
    if (_stasiun.isEmpty) {
      try {
        final stasiun = await _layanan.AmbilStasiun();
        if (mounted) {
          setState(() => _stasiun = stasiun);
        }
      } on GalatKasir catch (galat) {
        if (mounted) {
          setState(() => _galat = galat.pesan);
        }
        return;
      }
    }
    if (!mounted) {
      return;
    }
    final hasil = await showDialog<List<String>>(
      context: context,
      builder: (_) => _DialogStasiun(stasiun: _stasiun, terpilih: _terpilih),
    );
    if (hasil == null) {
      return;
    }
    await _layanan.SimpanStasiunTerpilih(hasil);
    setState(() => _terpilih = hasil);
    await _Tarik();
  }

  String _AmbilLabelStasiun() {
    if (_terpilih.isEmpty) {
      return 'Semua stasiun';
    }
    final nama = _stasiun.where((s) => _terpilih.contains(s.uuid)).map((s) => s.nama).toList();
    return nama.isEmpty ? '${_terpilih.length} stasiun' : nama.join(', ');
  }

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    final identitas = ref.watch(penyediaIdentitas).value;
    final sekarang = ref.read(penyediaJam)().toUtc().add(_selisihJam);
    final aktif = _tiket.where((t) => t.status != 'Disajikan').toList();
    final disajikan = _tiket.where((t) => t.status == 'Disajikan').toList();

    return Scaffold(
      backgroundColor: warna.latar,
      body: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Material(
              color: warna.permukaan,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak16, vertical: TokenJarak.jarak8),
                decoration: BoxDecoration(
                  border: Border(
                    bottom: BorderSide(color: warna.garis, width: TokenJarak.tebalGaris),
                  ),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Semantics(header: true, child: Text('Dapur', style: teks.titleLarge)),
                          Text(
                            [
                              if (identitas?.outlet.isNotEmpty ?? false) identitas!.outlet,
                              '${aktif.length} tiket aktif',
                            ].join(' · '),
                            style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
                          ),
                        ],
                      ),
                    ),
                    Flexible(
                      child: SizedBox(
                        height: TokenJarak.targetSentuh,
                        child: OutlinedButton.icon(
                          onPressed: () => unawaited(_PilihStasiun()),
                          icon: const Icon(Icons.filter_list),
                          label: Text(_AmbilLabelStasiun(), maxLines: 1, overflow: TextOverflow.ellipsis),
                        ),
                      ),
                    ),
                    IconButton(
                      tooltip: 'Muat ulang tiket',
                      onPressed: _menarik ? null : () => unawaited(_Tarik()),
                      icon: const Icon(Icons.refresh),
                    ),
                  ],
                ),
              ),
            ),
            if (_galat != null)
              Semantics(
                liveRegion: true,
                child: Container(
                  color: warna.peringatan.withValues(alpha: 0.12),
                  padding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak16, vertical: TokenJarak.jarak8),
                  child: Row(
                    children: [
                      Icon(Icons.wifi_off, size: TokenJarak.ikonSedang, color: warna.peringatan),
                      const SizedBox(width: TokenJarak.jarak8),
                      Expanded(
                        child: Text(
                          _tiket.isEmpty ? _galat! : '$_galat Tiket terakhir tetap ditampilkan.',
                          style: teks.bodyMedium,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            Expanded(
              child: !_dimuat
                  ? const Center(child: CircularProgressIndicator())
                  : _tiket.isEmpty
                  ? Center(
                      child: Padding(
                        padding: const EdgeInsets.all(TokenJarak.jarak24),
                        child: Text(
                          'Belum ada pesanan untuk dimasak. Tiket baru muncul otomatis.',
                          textAlign: TextAlign.center,
                          style: teks.titleMedium?.copyWith(color: warna.teksSekunder),
                        ),
                      ),
                    )
                  : LayoutBuilder(
                      builder: (context, batas) {
                        final kolom = (batas.maxWidth / LayarKds.lebarKartu).floor().clamp(1, 6);
                        final lebar =
                            (batas.maxWidth - TokenJarak.jarak16 * 2 - TokenJarak.jarak8 * (kolom - 1)) / kolom;
                        return SingleChildScrollView(
                          padding: const EdgeInsets.all(TokenJarak.jarak16),
                          child: Wrap(
                            spacing: TokenJarak.jarak8,
                            runSpacing: TokenJarak.jarak8,
                            children: [
                              for (final t in [...aktif, ...disajikan])
                                SizedBox(
                                  width: lebar,
                                  child: _KartuTiket(
                                    key: ValueKey(t.uuid),
                                    tiket: t,
                                    umur: sekarang.difference(t.dikirimPada.toUtc()),
                                    sibuk: _sibuk.contains(t.uuid),
                                    saatUbah: (status) => unawaited(_Ubah(t, status)),
                                  ),
                                ),
                            ],
                          ),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class _KartuTiket extends StatelessWidget {
  const _KartuTiket({super.key, required this.tiket, required this.umur, required this.sibuk, required this.saatUbah});

  final TiketDapurPos tiket;
  final Duration umur;
  final bool sibuk;
  final ValueChanged<String> saatUbah;

  static String FormatUmur(Duration umur) {
    final menit = umur.isNegative ? 0 : umur.inMinutes;
    final detik = umur.isNegative ? 0 : umur.inSeconds % 60;
    return '$menit:${detik.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    final teks = Theme.of(context).textTheme;
    final disajikan = tiket.status == 'Disajikan';
    final tingkat = LayananDapur.HitungUmur(umur);
    final (warnaUmur, labelUmur) = switch (tingkat) {
      UmurTiket.Normal => (warna.teksSekunder, null),
      UmurTiket.Lama => (warna.peringatan, 'Lama'),
      UmurTiket.Terlambat => (warna.bahaya, 'Terlambat'),
    };
    final maju = LayananDapur.AmbilStatusBerikutnya(tiket.status);
    final mundur = LayananDapur.AmbilStatusSebelumnya(tiket.status);
    final judul = tiket.namaMeja ?? tiket.label ?? tiket.nomorDokumen;

    return Opacity(
      opacity: disajikan ? 0.6 : 1,
      child: Material(
        color: warna.permukaan,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(TokenJarak.radiusPanel),
          side: BorderSide(
            color: disajikan || tingkat == UmurTiket.Normal ? warna.garis : warnaUmur,
            width: tingkat == UmurTiket.Normal || disajikan ? TokenJarak.tebalGaris : 2,
          ),
        ),
        child: Padding(
          padding: const EdgeInsets.all(TokenJarak.jarak12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(judul, maxLines: 1, overflow: TextOverflow.ellipsis, style: teks.titleLarge),
                        Text(
                          '${tiket.ronde > 1 ? 'Tambahan ke-${tiket.ronde - 1} · ' : ''}${tiket.status}',
                          style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
                        ),
                      ],
                    ),
                  ),
                  Semantics(
                    label: 'Umur tiket ${umur.inMinutes} menit${labelUmur == null ? '' : ', $labelUmur'}',
                    excludeSemantics: true,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(
                          FormatUmur(umur),
                          style: teks.titleMedium?.copyWith(
                            color: warnaUmur,
                            fontFeatures: const [FontFeature.tabularFigures()],
                          ),
                        ),
                        if (labelUmur != null && !disajikan)
                          Text(labelUmur, style: teks.labelSmall?.copyWith(color: warnaUmur)),
                      ],
                    ),
                  ),
                ],
              ),
              const Divider(height: TokenJarak.jarak16),
              for (final b in tiket.baris)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 2),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${FormatAngka.FormatJumlah(Kuantitas.Dari(b.jumlah))} × ${b.namaProduk}'
                        '${b.dibatalkan ? ' (dibatalkan)' : ''}',
                        style: teks.titleMedium?.copyWith(
                          decoration: b.dibatalkan ? TextDecoration.lineThrough : null,
                          color: b.dibatalkan ? warna.bahaya : null,
                        ),
                      ),
                      if (b.pilihan.isNotEmpty)
                        Text(b.pilihan.join(', '), style: teks.bodyMedium?.copyWith(color: warna.teksSekunder)),
                      if (b.catatan != null && b.catatan!.isNotEmpty)
                        Text('Catatan: ${b.catatan}', style: teks.bodyMedium?.copyWith(fontStyle: FontStyle.italic)),
                    ],
                  ),
                ),
              const SizedBox(height: TokenJarak.jarak12),
              Row(
                children: [
                  if (mundur != null)
                    TextButton(
                      onPressed: sibuk ? null : () => saatUbah(mundur),
                      child: Text(disajikan ? 'Kembalikan' : 'Mundur'),
                    ),
                  const Spacer(),
                  if (maju != null)
                    SizedBox(
                      height: TokenJarak.targetSentuh,
                      child: FilledButton(
                        onPressed: sibuk ? null : () => saatUbah(maju),
                        child: Text(LayananDapur.AmbilLabelMaju(tiket.status) ?? maju),
                      ),
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DialogStasiun extends StatefulWidget {
  const _DialogStasiun({required this.stasiun, required this.terpilih});

  final List<StasiunDapurPos> stasiun;
  final List<String> terpilih;

  @override
  State<_DialogStasiun> createState() => _DialogStasiunState();
}

class _DialogStasiunState extends State<_DialogStasiun> {
  late final Set<String> _pilih = {...widget.terpilih};

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Stasiun di layar ini'),
    content: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          CheckboxListTile(
            value: _pilih.isEmpty,
            title: const Text('Semua stasiun'),
            onChanged: (_) => setState(_pilih.clear),
          ),
          for (final s in widget.stasiun)
            CheckboxListTile(
              value: _pilih.contains(s.uuid),
              title: Text(s.nama),
              onChanged: (v) => setState(() => v == true ? _pilih.add(s.uuid) : _pilih.remove(s.uuid)),
            ),
          if (widget.stasiun.isEmpty) const Text('Belum ada stasiun dapur. Atur di back-office menu Stasiun dapur.'),
        ],
      ),
    ),
    actions: [
      TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Batal')),
      FilledButton(onPressed: () => Navigator.of(context).pop(_pilih.toList()), child: const Text('Simpan')),
    ],
  );
}
