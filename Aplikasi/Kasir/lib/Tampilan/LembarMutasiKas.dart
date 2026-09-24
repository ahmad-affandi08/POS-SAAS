import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import '../Data/BasisData/BasisDataKasir.dart';
import '../Domain/GalatKasir.dart';
import '../Domain/Sesi/StafLokal.dart';
import '../Domain/Shift/LayananShift.dart';
import 'Komponen/MasukanUang.dart';
import 'Komponen/PapanPin.dart';

/// F-06 langkah 4: catat kas masuk/keluar/setoran non-penjualan. Kas keluar di atas batas meminta PIN supervisor
/// (BR-06.4) sebelum disimpan.
class LembarMutasiKas extends ConsumerStatefulWidget {
  const LembarMutasiKas({super.key, required this.shift, required this.jenis, required this.pencatat});

  final BarisShift shift;
  final String jenis;
  final StafLokal pencatat;

  static String AmbilJudul(String jenis) => switch (jenis) {
    JenisMutasi.masuk => 'Kas masuk',
    JenisMutasi.keluar => 'Kas keluar',
    _ => 'Setoran',
  };

  @override
  ConsumerState<LembarMutasiKas> createState() => _LembarMutasiKasState();
}

class _LembarMutasiKasState extends ConsumerState<LembarMutasiKas> {
  final _jumlah = TextEditingController();
  final _catatan = TextEditingController();
  String? _uuidKategori;
  bool _sibuk = false;
  String? _galat;

  @override
  void dispose() {
    _jumlah.dispose();
    _catatan.dispose();
    super.dispose();
  }

  Future<void> _Simpan() async {
    final layanan = ref.read(penyediaLayananShift);
    final jumlah = MasukanUang.AmbilNilai(_jumlah);
    if (jumlah == null) {
      setState(() => _galat = 'Isi jumlah kas.');
      return;
    }

    StafLokal? penyetuju;
    if (await layanan.CekButuhPersetujuan(widget.jenis, jumlah)) {
      if (!mounted) {
        return;
      }
      penyetuju = await showDialog<StafLokal>(context: context, builder: (_) => const DialogPinSupervisor());
      if (penyetuju == null) {
        return;
      }
    }

    setState(() {
      _sibuk = true;
      _galat = null;
    });
    try {
      await layanan.CatatMutasi(
        shift: widget.shift,
        jenis: widget.jenis,
        jumlah: jumlah,
        pencatat: widget.pencatat,
        uuidKategori: _uuidKategori,
        catatan: _catatan.text,
        penyetuju: penyetuju,
      );
      if (mounted) {
        Navigator.of(context).pop(true);
      }
      await ref.read(penyediaSesi.notifier).Sinkronkan();
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _galat = galat.pesan);
      }
    } finally {
      if (mounted) {
        setState(() => _sibuk = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final kategori = widget.jenis == JenisMutasi.setoran ? null : ref.watch(penyediaKategori(widget.jenis));
    final teks = Theme.of(context).textTheme;
    return Padding(
      padding: EdgeInsets.fromLTRB(24, 24, 24, 24 + MediaQuery.viewInsetsOf(context).bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(LembarMutasiKas.AmbilJudul(widget.jenis), style: teks.headlineSmall),
          const SizedBox(height: 16),
          if (kategori != null)
            kategori.when(
              loading: () => const LinearProgressIndicator(),
              error: (galat, _) => Text('$galat'),
              data: (daftar) => daftar.isEmpty
                  ? const Text('Belum ada kategori. Minta admin menambahkannya di back-office menu Shift & kas.')
                  : DropdownButtonFormField<String>(
                      initialValue: _uuidKategori,
                      decoration: const InputDecoration(labelText: 'Kategori', border: OutlineInputBorder()),
                      items: [for (final k in daftar) DropdownMenuItem(value: k.Uuid, child: Text(k.Nama))],
                      onChanged: (nilai) => setState(() => _uuidKategori = nilai),
                    ),
            ),
          const SizedBox(height: 12),
          MasukanUang(pengendali: _jumlah, label: 'Jumlah', galat: _galat, autofocus: true),
          const SizedBox(height: 12),
          TextField(
            controller: _catatan,
            maxLength: 255,
            decoration: const InputDecoration(labelText: 'Catatan (opsional)', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 8),
          SizedBox(
            height: 56,
            child: FilledButton(
              onPressed: _sibuk ? null : _Simpan,
              child: Text(_sibuk ? 'Menyimpan…' : 'Simpan ${LembarMutasiKas.AmbilJudul(widget.jenis).toLowerCase()}'),
            ),
          ),
        ],
      ),
    );
  }
}

/// BR-06.4: pilih supervisor (berizin `kas.keluar.setujui`) lalu masukkan PIN-nya. Hasil = supervisor yang lolos PIN.
class DialogPinSupervisor extends ConsumerStatefulWidget {
  const DialogPinSupervisor({super.key});

  @override
  ConsumerState<DialogPinSupervisor> createState() => _DialogPinSupervisorState();
}

class _DialogPinSupervisorState extends ConsumerState<DialogPinSupervisor> {
  StafLokal? _dipilih;
  bool _sibuk = false;
  String? _galat;

  Future<void> _Periksa(String pin) async {
    final staf = _dipilih;
    if (staf == null) {
      return;
    }
    setState(() {
      _sibuk = true;
      _galat = null;
    });
    try {
      final hasil = await ref.read(penyediaLayananMasuk).Masuk(staf, pin);
      if (mounted) {
        Navigator.of(context).pop(hasil);
      }
    } on GalatKasir catch (galat) {
      if (mounted) {
        setState(() => _galat = galat.pesan);
      }
    } finally {
      if (mounted) {
        setState(() => _sibuk = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final supervisor = (ref.watch(penyediaStaf).value ?? const <StafLokal>[])
        .where((s) => s.PunyaIzin(IzinKasir.kasKeluarSetujui))
        .toList();
    final warna = TokenWarna.AmbilDari(context);
    return AlertDialog(
      title: const Text('Persetujuan supervisor'),
      content: SingleChildScrollView(
        child: _dipilih == null
            ? Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text('Kas keluar ini di atas batas. Pilih supervisor yang menyetujui.'),
                  const SizedBox(height: 12),
                  if (supervisor.isEmpty)
                    Text('Tidak ada supervisor di outlet ini.', style: TextStyle(color: warna.bahaya)),
                  for (final s in supervisor)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 4),
                      child: OutlinedButton(onPressed: () => setState(() => _dipilih = s), child: Text(s.nama)),
                    ),
                ],
              )
            : Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text('PIN ${_dipilih!.nama}'),
                  const SizedBox(height: 8),
                  PapanPin(saatSelesai: _Periksa, sibuk: _sibuk, pesanGalat: _galat),
                ],
              ),
      ),
      actions: [TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Batal'))],
    );
  }
}
