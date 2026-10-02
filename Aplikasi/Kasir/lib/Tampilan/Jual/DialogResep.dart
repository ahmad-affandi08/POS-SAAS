import 'package:flutter/material.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Domain/Penjualan/AturanApotek.dart';

/// Apotek bagian 2 (§9.5, PMK 73/2016): isian resep dokter sebelum menerima pembayaran obat wajib resep. Hasil =
/// [ResepPenjualan] yang sudah lolos [AturanApotek.Susun] (tanggal tidak setelah [hariIni]; alamat pasien wajib bila
/// [wajibAlamat], yaitu ada psikotropika/narkotika). Data pasien tidak dicetak di struk dan tidak dicatat di log.
class DialogResep extends StatefulWidget {
  const DialogResep({super.key, required this.hariIni, required this.wajibAlamat, required this.namaObat, this.awal});

  /// `YYYY-MM-DD` tanggal kalender outlet.
  final String hariIni;
  final bool wajibAlamat;

  /// Obat wajib resep di keranjang (untuk konteks kasir).
  final String namaObat;

  /// Isian sebelumnya (ubah resep).
  final ResepPenjualan? awal;

  @override
  State<DialogResep> createState() => _DialogResepState();
}

class _DialogResepState extends State<DialogResep> {
  static const List<String> _bulan = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
  ];

  late final _nomor = TextEditingController(text: widget.awal?.nomorResep);
  late final _dokter = TextEditingController(text: widget.awal?.namaDokter);
  late final _sip = TextEditingController(text: widget.awal?.noSipDokter);
  late final _pasien = TextEditingController(text: widget.awal?.namaPasien);
  late final _umur = TextEditingController(text: widget.awal?.umurPasien);
  late final _alamat = TextEditingController(text: widget.awal?.alamatPasien);
  late String _tanggal = widget.awal?.tanggalResep ?? widget.hariIni;
  String? _galat;

  @override
  void dispose() {
    for (final c in [_nomor, _dokter, _sip, _pasien, _umur, _alamat]) {
      c.dispose();
    }
    super.dispose();
  }

  static DateTime _Urai(String tanggal) {
    final b = tanggal.split('-').map(int.parse).toList();
    return DateTime(b[0], b[1], b[2]);
  }

  static String _Format(String tanggal) {
    final t = _Urai(tanggal);
    return '${t.day} ${_bulan[t.month - 1]} ${t.year}';
  }

  Future<void> _PilihTanggal() async {
    final batas = _Urai(widget.hariIni);
    final dipilih = await showDatePicker(
      context: context,
      initialDate: _Urai(_tanggal),
      firstDate: batas.subtract(const Duration(days: 366)),
      lastDate: batas,
      helpText: 'Tanggal resep',
    );
    if (dipilih != null && mounted) {
      String Dua(int n) => n.toString().padLeft(2, '0');
      setState(() {
        _tanggal = '${dipilih.year}-${Dua(dipilih.month)}-${Dua(dipilih.day)}';
        _galat = null;
      });
    }
  }

  void _Simpan() {
    final hasil = AturanApotek.Susun(
      nomorResep: _nomor.text,
      tanggalResep: _tanggal,
      namaDokter: _dokter.text,
      noSipDokter: _sip.text,
      namaPasien: _pasien.text,
      umurPasien: _umur.text,
      alamatPasien: _alamat.text,
      hariIni: widget.hariIni,
      wajibAlamat: widget.wajibAlamat,
    );
    if (hasil.resep == null) {
      setState(() => _galat = hasil.galat);
      return;
    }
    Navigator.of(context).pop(hasil.resep);
  }

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    Widget Isian(String kunci, TextEditingController c, String label, int maks, {int baris = 1}) => Padding(
      padding: const EdgeInsets.only(bottom: TokenJarak.jarak8),
      child: TextField(
        key: ValueKey(kunci),
        controller: c,
        maxLength: maks,
        maxLines: baris,
        minLines: 1,
        textInputAction: baris > 1 ? TextInputAction.newline : TextInputAction.next,
        onChanged: (_) {
          if (_galat != null) {
            setState(() => _galat = null);
          }
        },
        decoration: InputDecoration(labelText: label, border: const OutlineInputBorder(), counterText: ''),
      ),
    );

    return AlertDialog(
      insetPadding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak16, vertical: TokenJarak.jarak24),
      title: const Text('Resep dokter'),
      content: SizedBox(
        width: 480,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('Wajib resep: ${widget.namaObat}.', style: teks.bodyMedium),
              const SizedBox(height: TokenJarak.jarak12),
              Isian('NomorResep', _nomor, 'Nomor resep', AturanApotek.panjangNomorResep),
              SizedBox(
                height: TokenJarak.targetSentuh,
                child: OutlinedButton.icon(
                  key: const ValueKey('TanggalResep'),
                  onPressed: _PilihTanggal,
                  icon: const Icon(Icons.event_outlined),
                  label: Text('Tanggal resep: ${_Format(_tanggal)}'),
                ),
              ),
              const SizedBox(height: TokenJarak.jarak8),
              Isian('NamaDokter', _dokter, 'Nama dokter', AturanApotek.panjangNamaDokter),
              Isian('NoSipDokter', _sip, 'No. SIP dokter (opsional)', AturanApotek.panjangNoSip),
              Isian('NamaPasien', _pasien, 'Nama pasien', AturanApotek.panjangNamaPasien),
              Isian('UmurPasien', _umur, 'Umur pasien (opsional)', AturanApotek.panjangUmur),
              Isian(
                'AlamatPasien',
                _alamat,
                widget.wajibAlamat ? 'Alamat pasien (wajib psikotropika/narkotika)' : 'Alamat pasien (opsional)',
                AturanApotek.panjangAlamat,
                baris: 2,
              ),
              Text(
                'Nama & alamat pasien hanya disimpan di catatan resep, tidak dicetak di struk.',
                style: teks.bodySmall?.copyWith(color: warna.teksSekunder),
              ),
              if (_galat != null) ...[
                const SizedBox(height: TokenJarak.jarak8),
                Semantics(
                  liveRegion: true,
                  child: Text(_galat!, style: TextStyle(color: warna.bahaya)),
                ),
              ],
            ],
          ),
        ),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Batal')),
        FilledButton(onPressed: _Simpan, child: const Text('Simpan resep')),
      ],
    );
  }
}
