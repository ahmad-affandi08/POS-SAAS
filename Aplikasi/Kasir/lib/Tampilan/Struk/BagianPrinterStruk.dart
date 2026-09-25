import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/Struk/ProfilPrinter.dart';

/// Atur printer struk perangkat ini di layar Pengaturan (PRD v1.79): printer LAN/Wi-Fi (alamat IP & port), lebar
/// kertas, cetak otomatis, dan buka laci untuk tunai. Cetak uji memakai isian yang sedang diketik sebelum disimpan.
class BagianPrinterStruk extends ConsumerStatefulWidget {
  const BagianPrinterStruk({super.key});

  @override
  ConsumerState<BagianPrinterStruk> createState() => _BagianPrinterStrukState();
}

class _BagianPrinterStrukState extends ConsumerState<BagianPrinterStruk> {
  final _alamat = TextEditingController();
  final _port = TextEditingController(text: '${TransportJaringan.portBawaan}');
  var _lebar = LebarKertas.Mm58;
  var _cetakOtomatis = true;
  var _bukaLaci = true;
  var _mengubah = false;
  var _sibuk = false;
  String? _galatAlamat;
  String? _galatPort;
  String? _pesan;
  var _pesanGalat = false;

  @override
  void dispose() {
    _alamat.dispose();
    _port.dispose();
    super.dispose();
  }

  void _MulaiUbah(ProfilPrinter? profil) => setState(() {
    _alamat.text = profil?.alamat ?? '';
    _port.text = '${profil?.port ?? TransportJaringan.portBawaan}';
    _lebar = profil?.lebar ?? LebarKertas.Mm58;
    _cetakOtomatis = profil?.cetakOtomatis ?? true;
    _bukaLaci = profil?.bukaLaciTunai ?? true;
    _galatAlamat = null;
    _galatPort = null;
    _pesan = null;
    _mengubah = true;
  });

  ProfilPrinter? _AmbilIsian() {
    setState(() {
      _galatAlamat = ProfilPrinter.ValidasiAlamat(_alamat.text);
      _galatPort = ProfilPrinter.ValidasiPort(_port.text);
    });
    if (_galatAlamat != null || _galatPort != null) {
      return null;
    }
    return ProfilPrinter(
      alamat: _alamat.text.trim(),
      port: int.parse(_port.text.trim()),
      lebar: _lebar,
      cetakOtomatis: _cetakOtomatis,
      bukaLaciTunai: _bukaLaci,
    );
  }

  Future<void> _Jalankan(Future<String?> Function() aksi, String berhasil) async {
    setState(() {
      _sibuk = true;
      _pesan = null;
    });
    final galat = await aksi();
    if (!mounted) {
      return;
    }
    setState(() {
      _sibuk = false;
      _pesan = galat ?? berhasil;
      _pesanGalat = galat != null;
    });
  }

  Future<void> _CetakUji() async {
    final profil = _mengubah ? _AmbilIsian() : ref.read(penyediaPrinter).profil;
    if (profil != null) {
      await _Jalankan(
        () => ref.read(penyediaPrinter.notifier).CetakUji(profil),
        'Halaman uji terkirim. Pastikan semua baris lurus dan tidak terpotong.',
      );
    }
  }

  Future<void> _Simpan() async {
    final profil = _AmbilIsian();
    if (profil == null) {
      return;
    }
    await ref.read(penyediaPrinter.notifier).SimpanProfil(profil);
    if (mounted) {
      setState(() {
        _mengubah = false;
        _pesan = 'Printer struk disimpan.';
        _pesanGalat = false;
      });
    }
  }

  Future<void> _Hapus() async {
    await ref.read(penyediaPrinter.notifier).HapusProfil();
    if (mounted) {
      setState(() {
        _mengubah = false;
        _pesan = 'Printer struk dihapus dari perangkat ini.';
        _pesanGalat = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final printer = ref.watch(penyediaPrinter);
    final profil = printer.profil;
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);

    Widget Tombol(String label, IconData ikon, VoidCallback? aksi, {bool utama = false}) => SizedBox(
      height: TokenJarak.targetSentuh,
      child: utama
          ? FilledButton.icon(onPressed: _sibuk ? null : aksi, icon: Icon(ikon), label: Text(label))
          : OutlinedButton.icon(onPressed: _sibuk ? null : aksi, icon: Icon(ikon), label: Text(label)),
    );

    final isi = <Widget>[];
    if (_mengubah) {
      isi.addAll([
        Wrap(
          spacing: TokenJarak.jarak12,
          runSpacing: TokenJarak.jarak12,
          children: [
            SizedBox(
              width: 240,
              child: TextField(
                controller: _alamat,
                keyboardType: TextInputType.url,
                decoration: InputDecoration(
                  labelText: 'Alamat IP printer',
                  hintText: '192.168.1.50',
                  errorText: _galatAlamat,
                ),
              ),
            ),
            SizedBox(
              width: 120,
              child: TextField(
                controller: _port,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                decoration: InputDecoration(labelText: 'Port', errorText: _galatPort),
              ),
            ),
          ],
        ),
        const SizedBox(height: TokenJarak.jarak12),
        Text('Lebar kertas', style: teks.labelLarge),
        const SizedBox(height: TokenJarak.jarak4),
        SegmentedButton<LebarKertas>(
          showSelectedIcon: false,
          segments: [for (final l in LebarKertas.values) ButtonSegment(value: l, label: Text(l.label))],
          selected: {_lebar},
          onSelectionChanged: (pilihan) => setState(() => _lebar = pilihan.single),
        ),
        const SizedBox(height: TokenJarak.jarak8),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('Cetak struk otomatis setelah bayar'),
          value: _cetakOtomatis,
          onChanged: (nilai) => setState(() => _cetakOtomatis = nilai),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('Buka laci kas untuk pembayaran tunai'),
          subtitle: const Text('Laci tersambung ke printer lewat kabel RJ11.'),
          value: _bukaLaci,
          onChanged: (nilai) => setState(() => _bukaLaci = nilai),
        ),
        const SizedBox(height: TokenJarak.jarak8),
        Wrap(
          spacing: TokenJarak.jarak8,
          runSpacing: TokenJarak.jarak8,
          children: [
            Tombol('Simpan printer', Icons.check, _Simpan, utama: true),
            Tombol('Cetak uji', Icons.print_outlined, _CetakUji),
            Tombol('Batal', Icons.close, () => setState(() => _mengubah = false)),
          ],
        ),
      ]);
    } else if (profil == null) {
      isi.add(Tombol('Atur printer', Icons.print_outlined, () => _MulaiUbah(null), utama: true));
    } else {
      isi.addAll([
        Text('Printer LAN/Wi-Fi ${profil.label}', style: teks.bodyLarge),
        Text(
          '${profil.cetakOtomatis ? 'Cetak otomatis setelah bayar' : 'Cetak manual'}'
          '${profil.bukaLaciTunai ? ' · buka laci untuk tunai' : ''}',
          style: teks.bodySmall,
        ),
        const SizedBox(height: TokenJarak.jarak12),
        Wrap(
          spacing: TokenJarak.jarak8,
          runSpacing: TokenJarak.jarak8,
          children: [
            Tombol('Cetak uji', Icons.print_outlined, _CetakUji),
            Tombol('Ubah', Icons.edit_outlined, () => _MulaiUbah(profil)),
            Tombol('Hapus printer', Icons.delete_outline, _Hapus),
          ],
        ),
      ]);
    }
    if (_pesan != null) {
      isi.add(
        Padding(
          padding: const EdgeInsets.only(top: TokenJarak.jarak8),
          child: Text(
            _pesan!,
            style: teks.bodyMedium?.copyWith(color: _pesanGalat ? warna.bahaya : warna.teksSekunder),
          ),
        ),
      );
    }
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: isi);
  }
}
