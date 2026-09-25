import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Domain/Struk/PemindaiPrinter.dart';
import '../../Domain/Struk/ProfilPrinter.dart';

/// Atur printer struk perangkat ini di layar Pengaturan (PRD v1.79–v1.80): LAN/Wi-Fi (alamat IP & port), Bluetooth
/// (printer yang sudah di-pair; Android & Windows), atau Bluetooth LE (pindai; iOS, Android, Windows); lebar kertas,
/// cetak otomatis, dan buka laci untuk tunai. Cetak uji memakai isian yang sedang dipilih sebelum disimpan.
class BagianPrinterStruk extends ConsumerStatefulWidget {
  const BagianPrinterStruk({super.key});

  @override
  ConsumerState<BagianPrinterStruk> createState() => _BagianPrinterStrukState();
}

class _BagianPrinterStrukState extends ConsumerState<BagianPrinterStruk> {
  final _alamat = TextEditingController();
  final _port = TextEditingController(text: '${TransportJaringan.portBawaan}');
  var _jenis = JenisTransport.Jaringan;
  var _lebar = LebarKertas.Mm58;
  var _cetakOtomatis = true;
  var _bukaLaci = true;
  var _mengubah = false;
  var _sibuk = false;
  var _mencari = false;
  List<PrinterDitemukan>? _hasilCari;
  PrinterDitemukan? _terpilih;
  String? _galatAlamat;
  String? _galatPort;
  String? _galatCari;
  String? _pesan;
  var _pesanGalat = false;

  @override
  void dispose() {
    _alamat.dispose();
    _port.dispose();
    super.dispose();
  }

  static String LabelJenis(JenisTransport jenis) => switch (jenis) {
    JenisTransport.Jaringan => 'LAN/Wi-Fi',
    JenisTransport.BluetoothKlasik => 'Bluetooth',
    JenisTransport.Ble => 'Bluetooth LE',
    _ => jenis.label,
  };

  void _MulaiUbah(ProfilPrinter? profil) => setState(() {
    _jenis = profil?.jenis ?? JenisTransport.Jaringan;
    final jaringan = _jenis == JenisTransport.Jaringan;
    _alamat.text = jaringan ? profil?.alamat ?? '' : '';
    _port.text = '${profil?.port ?? TransportJaringan.portBawaan}';
    _terpilih = profil == null || jaringan
        ? null
        : PrinterDitemukan(jenis: profil.jenis, alamat: profil.alamat, nama: profil.nama ?? profil.alamat);
    _lebar = profil?.lebar ?? LebarKertas.Mm58;
    _cetakOtomatis = profil?.cetakOtomatis ?? true;
    _bukaLaci = profil?.bukaLaciTunai ?? true;
    _hasilCari = null;
    _galatAlamat = null;
    _galatPort = null;
    _galatCari = null;
    _pesan = null;
    _mengubah = true;
  });

  void _GantiJenis(JenisTransport jenis) => setState(() {
    _jenis = jenis;
    _terpilih = null;
    _hasilCari = null;
    _galatCari = null;
  });

  Future<void> _CariPrinter() async {
    final pemindai = ref.read(penyediaPemindaiPrinter);
    final jenis = _jenis;
    setState(() {
      _mencari = true;
      _galatCari = null;
      _hasilCari = null;
    });
    String? galat;
    List<PrinterDitemukan>? hasil;
    try {
      galat = await pemindai.SiapkanBluetooth(jenis);
      if (galat == null) {
        hasil = await pemindai.CariPrinter(jenis);
      }
    } on GalatPrinter catch (g) {
      galat = g.pesan;
    }
    if (!mounted || jenis != _jenis) {
      return;
    }
    setState(() {
      _mencari = false;
      _galatCari = galat;
      _hasilCari = hasil;
      if (hasil != null && hasil.length == 1) {
        _terpilih = hasil.single;
      }
    });
  }

  ProfilPrinter? _AmbilIsian() {
    if (_jenis == JenisTransport.Jaringan) {
      setState(() {
        _galatAlamat = ProfilPrinter.ValidasiAlamat(_alamat.text);
        _galatPort = ProfilPrinter.ValidasiPort(_port.text);
      });
      if (_galatAlamat != null || _galatPort != null) {
        return null;
      }
    } else if (_terpilih == null) {
      setState(() => _galatCari = 'Pilih printer dulu. Ketuk Cari printer untuk melihat daftarnya.');
      return null;
    }
    final pilihan = _terpilih;
    return ProfilPrinter(
      jenis: _jenis,
      alamat: _jenis == JenisTransport.Jaringan ? _alamat.text.trim() : pilihan!.alamat,
      nama: _jenis == JenisTransport.Jaringan ? null : pilihan!.nama,
      port: _jenis == JenisTransport.Jaringan ? int.parse(_port.text.trim()) : TransportJaringan.portBawaan,
      lebar: _lebar,
      cetakOtomatis: _cetakOtomatis,
      bukaLaciTunai: _bukaLaci,
    );
  }

  Future<void> _CetakUji() async {
    final profil = _mengubah ? _AmbilIsian() : ref.read(penyediaPrinter).profil;
    if (profil == null) {
      return;
    }
    setState(() {
      _sibuk = true;
      _pesan = null;
    });
    final galat = await ref.read(penyediaPrinter.notifier).CetakUji(profil);
    if (!mounted) {
      return;
    }
    setState(() {
      _sibuk = false;
      _pesan = galat ?? 'Halaman uji terkirim. Pastikan semua baris lurus dan tidak terpotong.';
      _pesanGalat = galat != null;
    });
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

  List<Widget> _BangunPilihBluetooth(TextTheme teks, TokenWarna warna) {
    final hasil = _hasilCari;
    final kosong = switch (_jenis) {
      JenisTransport.BluetoothKlasik =>
        'Belum ada printer Bluetooth yang di-pair. Pasangkan printer di pengaturan Bluetooth perangkat '
            '(PIN biasanya 0000 atau 1234), lalu ketuk Cari printer lagi.',
      _ => 'Tidak ada printer Bluetooth LE di sekitar. Nyalakan printer dan dekatkan ke perangkat, lalu cari lagi.',
    };
    return [
      Text(
        _jenis == JenisTransport.BluetoothKlasik
            ? 'Printer harus sudah di-pair di pengaturan Bluetooth perangkat.'
            : 'Printer dicari di sekitar selama beberapa detik.',
        style: teks.bodySmall,
      ),
      const SizedBox(height: TokenJarak.jarak8),
      SizedBox(
        height: TokenJarak.targetSentuh,
        child: OutlinedButton.icon(
          onPressed: _mencari || _sibuk ? null : _CariPrinter,
          icon: const Icon(Icons.bluetooth_searching),
          label: Text(_mencari ? 'Mencari printer…' : 'Cari printer'),
        ),
      ),
      if (_galatCari != null)
        Padding(
          padding: const EdgeInsets.only(top: TokenJarak.jarak8),
          child: Text(_galatCari!, style: teks.bodyMedium?.copyWith(color: warna.bahaya)),
        ),
      if (hasil != null && hasil.isEmpty)
        Padding(
          padding: const EdgeInsets.only(top: TokenJarak.jarak8),
          child: Text(kosong, style: teks.bodyMedium),
        ),
      if (hasil != null && hasil.isNotEmpty) ...[
        const SizedBox(height: TokenJarak.jarak8),
        for (final p in hasil)
          ListTile(
            contentPadding: EdgeInsets.zero,
            minTileHeight: TokenJarak.targetSentuh,
            leading: Icon(
              _terpilih?.alamat == p.alamat ? Icons.radio_button_checked : Icons.radio_button_unchecked,
              color: _terpilih?.alamat == p.alamat ? warna.brand : warna.teksSekunder,
            ),
            title: Text(p.nama),
            subtitle: p.nama == p.alamat ? null : Text(p.alamat, style: teks.bodySmall),
            onTap: () => setState(() {
              _terpilih = p;
              _galatCari = null;
            }),
          ),
      ] else if (_terpilih != null)
        Padding(
          padding: const EdgeInsets.only(top: TokenJarak.jarak8),
          child: Text('Printer terpilih: ${_terpilih!.nama}', style: teks.bodyMedium),
        ),
    ];
  }

  @override
  Widget build(BuildContext context) {
    final printer = ref.watch(penyediaPrinter);
    final jenisDidukung = ref.watch(penyediaPemindaiPrinter).AmbilJenisDidukung();
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
        if (jenisDidukung.length > 1) ...[
          Text('Sambungan printer', style: teks.labelLarge),
          const SizedBox(height: TokenJarak.jarak4),
          SegmentedButton<JenisTransport>(
            showSelectedIcon: false,
            segments: [for (final j in jenisDidukung) ButtonSegment(value: j, label: Text(LabelJenis(j)))],
            selected: {_jenis},
            onSelectionChanged: (pilihan) => _GantiJenis(pilihan.single),
          ),
          const SizedBox(height: TokenJarak.jarak12),
        ],
        if (_jenis == JenisTransport.Jaringan)
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
          )
        else
          ..._BangunPilihBluetooth(teks, warna),
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
        Text('Printer ${profil.label}', style: teks.bodyLarge),
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
