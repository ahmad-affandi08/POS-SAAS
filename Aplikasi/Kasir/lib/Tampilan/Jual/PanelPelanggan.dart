import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/GalatKasir.dart';
import '../../Domain/Pelanggan/LayananPelanggan.dart';
import '../../Domain/Penjualan/Keranjang.dart';
import '../../Domain/Sesi/StafLokal.dart';

/// Panel pelanggan layar Jual (F-16a, pintasan F2): cari nama/nomor HP (online; offline = pelanggan yang pernah dipakai
/// perangkat ini), pelanggan terakhir, tambah pelanggan baru (nama + nomor HP, berlaku offline), atau "Tanpa pelanggan".
/// Pilihan disimpan di keranjang dan dikirim bersama penjualan.
class PanelPelanggan extends ConsumerStatefulWidget {
  const PanelPelanggan({super.key, required this.kasir, required this.saatSelesai});

  static const Duration jedaCari = Duration(milliseconds: 350);

  final StafLokal kasir;
  final VoidCallback saatSelesai;

  @override
  ConsumerState<PanelPelanggan> createState() => _PanelPelangganState();
}

class _PanelPelangganState extends ConsumerState<PanelPelanggan> {
  final _cari = TextEditingController();
  final _nama = TextEditingController();
  final _noHp = TextEditingController();
  Timer? _jeda;
  List<PelangganTerpilih> _hasil = const [];
  List<PelangganTerpilih> _terakhir = const [];
  bool _online = true;
  bool _mencari = false;
  bool _formBaru = false;
  bool _menyimpan = false;
  String? _galat;
  int _urutCari = 0;

  LayananPelanggan get _layanan => ref.read(penyediaLayananPelanggan);

  @override
  void initState() {
    super.initState();
    unawaited(_MuatTerakhir());
  }

  @override
  void dispose() {
    _jeda?.cancel();
    _cari.dispose();
    _nama.dispose();
    _noHp.dispose();
    super.dispose();
  }

  Future<void> _MuatTerakhir() async {
    final terakhir = await _layanan.AmbilTerakhir();
    if (mounted) {
      setState(() => _terakhir = terakhir);
    }
  }

  void _SaatKetik(String _) {
    _jeda?.cancel();
    _jeda = Timer(PanelPelanggan.jedaCari, () => unawaited(_Cari()));
    setState(() {});
  }

  Future<void> _Cari() async {
    final kata = _cari.text;
    final urut = ++_urutCari;
    if (kata.trim().length < LayananPelanggan.panjangKataMinimal) {
      setState(() => _hasil = const []);
      return;
    }
    setState(() {
      _mencari = true;
      _galat = null;
    });
    try {
      final hasil = await _layanan.Cari(kata);
      if (mounted && urut == _urutCari) {
        setState(() {
          _hasil = hasil.pelanggan;
          _online = hasil.online;
        });
      }
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _galat = galat.pesan);
      }
    } finally {
      if (mounted && urut == _urutCari) {
        setState(() => _mencari = false);
      }
    }
  }

  /// Pasang pelanggan lalu hitung ulang harga item baru menurut tier-nya (F-16b; baris pesanan meja yang sudah
  /// tersimpan memakai harga saat dipesan).
  void _Pasang(PelangganTerpilih? pelanggan) {
    final pengatur = ref.read(penyediaKeranjang.notifier);
    var keranjang = ref.read(penyediaKeranjang).Salin(pelanggan: () => pelanggan);
    final katalog = ref.read(penyediaKatalog).value;
    final k = ref.read(penyediaKonteksPenjualan).value;
    if (katalog != null && k != null) {
      keranjang = ref.read(penyediaLayananPenjualan).HitungUlangHarga(keranjang, katalog, k);
    }
    pengatur.Ganti(keranjang);
    widget.saatSelesai();
  }

  Future<void> _Pilih(PelangganTerpilih pelanggan) async {
    await _layanan.CatatDipakai(pelanggan);
    _Pasang(pelanggan);
  }

  Future<void> _SimpanBaru() async {
    setState(() {
      _menyimpan = true;
      _galat = null;
    });
    try {
      final baru = await _layanan.Buat(nama: _nama.text, noHp: _noHp.text, kasir: widget.kasir);
      unawaited(ref.read(penyediaSesi.notifier).Sinkronkan());
      _Pasang(baru);
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _galat = galat.pesan);
      }
    } finally {
      if (mounted) {
        setState(() => _menyimpan = false);
      }
    }
  }

  Widget _BangunBaris(PelangganTerpilih p, PelangganTerpilih? terpilih) => ListTile(
    key: ValueKey('pelanggan-${p.uuid}'),
    minTileHeight: TokenJarak.targetSentuh,
    leading: Icon(p.uuid == terpilih?.uuid ? Icons.check_circle : Icons.person_outline),
    title: Text(p.nama, maxLines: 2, overflow: TextOverflow.ellipsis),
    subtitle: Text(
      [p.noHpSamar, if (p.namaTier != null) p.namaTier!, if (p.saldoPoin != null) '${p.saldoPoin} poin'].join(' · '),
    ),
    onTap: () => unawaited(_Pilih(p)),
  );

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final terpilih = ref.watch(penyediaKeranjang.select((k) => k.pelanggan));
    final kataCukup = _cari.text.trim().length >= LayananPelanggan.panjangKataMinimal;

    if (_formBaru) {
      return SingleChildScrollView(
        child: Padding(
          padding: const EdgeInsets.all(TokenJarak.jarak16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('Pelanggan baru', style: teks.titleMedium),
              const SizedBox(height: TokenJarak.jarak4),
              Text(
                'Bisa dibuat saat offline; data dikirim ke server bersama transaksi.',
                style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
              ),
              const SizedBox(height: TokenJarak.jarak16),
              TextField(
                controller: _nama,
                autofocus: true,
                textCapitalization: TextCapitalization.words,
                maxLength: LayananPelanggan.panjangNamaMaksimal,
                decoration: const InputDecoration(labelText: 'Nama pelanggan', border: OutlineInputBorder()),
              ),
              const SizedBox(height: TokenJarak.jarak8),
              TextField(
                controller: _noHp,
                keyboardType: TextInputType.phone,
                maxLength: 30,
                decoration: const InputDecoration(
                  labelText: 'No. HP/WA',
                  hintText: '0812-3456-7890',
                  border: OutlineInputBorder(),
                ),
                onSubmitted: (_) => unawaited(_SimpanBaru()),
              ),
              if (_galat != null) ...[
                const SizedBox(height: TokenJarak.jarak8),
                Text(_galat!, style: teks.bodyMedium?.copyWith(color: warna.bahaya)),
              ],
              const SizedBox(height: TokenJarak.jarak16),
              Row(
                children: [
                  Expanded(
                    child: SizedBox(
                      height: TokenJarak.targetSentuh,
                      child: OutlinedButton(
                        onPressed: _menyimpan ? null : () => setState(() => _formBaru = false),
                        child: const Text('Kembali'),
                      ),
                    ),
                  ),
                  const SizedBox(width: TokenJarak.jarak8),
                  Expanded(
                    flex: 2,
                    child: SizedBox(
                      height: TokenJarak.targetSentuh,
                      child: FilledButton(
                        onPressed: _menyimpan ? null : () => unawaited(_SimpanBaru()),
                        child: const Text('Simpan & pakai'),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      );
    }

    final daftar = kataCukup ? _hasil : _terakhir;
    return SingleChildScrollView(
      child: Padding(
        padding: const EdgeInsets.all(TokenJarak.jarak16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextField(
              controller: _cari,
              autofocus: true,
              onChanged: _SaatKetik,
              decoration: const InputDecoration(
                hintText: 'Cari nama atau nomor HP (min. 3 huruf)',
                prefixIcon: Icon(Icons.search),
                border: OutlineInputBorder(),
                isDense: true,
              ),
            ),
            if (_mencari) const LinearProgressIndicator(),
            if (!_online && kataCukup)
              Padding(
                padding: const EdgeInsets.only(top: TokenJarak.jarak8),
                child: Row(
                  children: [
                    Icon(Icons.wifi_off, size: TokenJarak.ikonKecil, color: warna.peringatan),
                    const SizedBox(width: TokenJarak.jarak8),
                    Expanded(
                      child: Text(
                        'Offline: hanya pelanggan yang pernah dipakai di perangkat ini.',
                        style: teks.bodySmall,
                      ),
                    ),
                  ],
                ),
              ),
            if (_galat != null)
              Padding(
                padding: const EdgeInsets.only(top: TokenJarak.jarak8),
                child: Text(_galat!, style: teks.bodyMedium?.copyWith(color: warna.bahaya)),
              ),
            const SizedBox(height: TokenJarak.jarak8),
            Text(
              kataCukup ? 'Hasil pencarian' : 'Terakhir dipakai',
              style: teks.labelLarge?.copyWith(color: warna.teksSekunder),
            ),
            if (daftar.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: TokenJarak.jarak16),
                child: Text(
                  kataCukup ? 'Pelanggan tidak ditemukan. Tambahkan sebagai pelanggan baru.' : 'Belum ada.',
                  style: teks.bodyMedium?.copyWith(color: warna.teksSekunder),
                ),
              )
            else
              for (final p in daftar) _BangunBaris(p, terpilih),
            const SizedBox(height: TokenJarak.jarak8),
            Row(
              children: [
                Expanded(
                  child: SizedBox(
                    height: TokenJarak.targetSentuh,
                    child: OutlinedButton(
                      onPressed: () => _Pasang(null),
                      child: const Text('Tanpa pelanggan', maxLines: 1, overflow: TextOverflow.ellipsis),
                    ),
                  ),
                ),
                const SizedBox(width: TokenJarak.jarak8),
                Expanded(
                  child: SizedBox(
                    height: TokenJarak.targetSentuh,
                    child: FilledButton.icon(
                      onPressed: () => setState(() {
                        _formBaru = true;
                        _galat = null;
                        final angka = _cari.text.replaceAll(RegExp(r'[^0-9+]'), '');
                        if (angka.length >= 6) {
                          _noHp.text = _cari.text.trim();
                        } else {
                          _nama.text = _cari.text.trim();
                        }
                      }),
                      icon: const Icon(Icons.person_add_alt),
                      label: const Text('Pelanggan baru', maxLines: 1, overflow: TextOverflow.ellipsis),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
