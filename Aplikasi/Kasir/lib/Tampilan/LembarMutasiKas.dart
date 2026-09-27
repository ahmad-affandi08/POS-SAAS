import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:klien_api/KlienApi.dart';
import 'package:mesin_kasir/MesinKasir.dart' show Uang;
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import '../Data/BasisData/BasisDataKasir.dart';
import '../Domain/GalatKasir.dart';
import '../Domain/Sesi/LayananPersetujuanJarakJauh.dart';
import '../Domain/Sesi/StafLokal.dart';
import '../Domain/Shift/LayananShift.dart';
import 'Komponen/MasukanUang.dart';
import 'Komponen/PapanPin.dart';

/// F-06 langkah 4: catat kas masuk/keluar/setoran non-penjualan. Kas keluar di atas batas meminta PIN supervisor
/// (BR-06.4) sebelum disimpan. Isi formulir ini ditampilkan bingkai ruang kerja di dalam `PanelTugas` (panel samping
/// atau lembar bawah) yang juga memberi judul; [saatTersimpan] dipanggil setelah mutasi tersimpan.
class LembarMutasiKas extends ConsumerStatefulWidget {
  const LembarMutasiKas({
    super.key,
    required this.shift,
    required this.jenis,
    required this.pencatat,
    required this.saatTersimpan,
  });

  final BarisShift shift;
  final String jenis;
  final StafLokal pencatat;
  final VoidCallback saatTersimpan;

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
      penyetuju = await showDialog<StafLokal>(
        context: context,
        builder: (_) => DialogPinSupervisor(
          nilai: jumlah,
          rincian: [(label: 'Catatan', nilai: _catatan.text.trim().isEmpty ? '-' : _catatan.text.trim())],
        ),
      );
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
      final sesi = ref.read(penyediaSesi.notifier);
      if (mounted) {
        widget.saatTersimpan();
      }
      await sesi.Sinkronkan();
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
    return Padding(
      padding: const EdgeInsets.all(TokenJarak.jarak24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (kategori != null)
            kategori.when(
              loading: () => const LinearProgressIndicator(),
              error: (galat, _) => Text('$galat'),
              data: (daftar) => daftar.isEmpty
                  ? const Text('Belum ada kategori. Minta admin menambahkannya di back-office menu Shift & kas.')
                  : DropdownButtonFormField<String>(
                      isExpanded: true,
                      initialValue: _uuidKategori,
                      decoration: const InputDecoration(labelText: 'Kategori', border: OutlineInputBorder()),
                      items: [
                        for (final k in daftar)
                          DropdownMenuItem(
                            value: k.Uuid,
                            child: Text(k.Nama, maxLines: 1, overflow: TextOverflow.ellipsis),
                          ),
                      ],
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

/// Pilih penyetuju lalu masukkan PIN-nya. Hasil = staf yang lolos PIN. Bawaan untuk kas keluar (BR-06.4, izin
/// `kas.keluar.setujui`); dipakai juga untuk diskon di atas batas (BR-07.3, izin `penjualan.diskon.setujui`, atau hanya
/// Pemilik lewat [hanyaPemilik]).
///
/// X4: bila fitur persetujuan jarak jauh aktif, kasir bisa memilih "Minta persetujuan jarak jauh": permintaan
/// ([judul], [pesan], [nilai], [rincian]) dikirim ke Aplikasi Owner lalu dialog menunggu keputusan; disetujui = hasil
/// dialog adalah penyetuju itu, ditolak/kedaluwarsa = alasan tampil dan kasir bisa kembali memilih PIN.
class DialogPinSupervisor extends ConsumerStatefulWidget {
  const DialogPinSupervisor({
    super.key,
    this.izin = IzinKasir.kasKeluarSetujui,
    this.pesan = 'Kas keluar ini di atas batas. Pilih supervisor yang menyetujui.',
    this.hanyaPemilik = false,
    this.judul,
    this.nilai,
    this.rincian = const [],
  });

  final String izin;
  final String pesan;
  final bool hanyaPemilik;

  /// Judul permintaan jarak jauh (null = judul bawaan per izin).
  final String? judul;

  /// Nilai uang yang dimintakan persetujuannya (ditampilkan di Aplikasi Owner).
  final Uang? nilai;
  final List<({String label, String nilai})> rincian;

  @override
  ConsumerState<DialogPinSupervisor> createState() => _DialogPinSupervisorState();
}

class _DialogPinSupervisorState extends ConsumerState<DialogPinSupervisor> {
  StafLokal? _dipilih;
  bool _sibuk = false;
  String? _galat;

  /// X4: fitur aktif (dibaca dari data awal lokal).
  bool _jarakJauhTersedia = false;

  /// Permintaan jarak jauh yang sedang ditunggu (null = tidak menunggu).
  PermintaanPersetujuanPos? _menunggu;
  StreamSubscription<PermintaanPersetujuanPos>? _pantau;

  LayananPersetujuanJarakJauh get _jarakJauh => ref.read(penyediaLayananPersetujuanJarakJauh);

  @override
  void initState() {
    super.initState();
    unawaited(
      _jarakJauh.CekTersedia().then((ada) {
        if (mounted) {
          setState(() => _jarakJauhTersedia = ada);
        }
      }),
    );
  }

  @override
  void dispose() {
    unawaited(_pantau?.cancel());
    final menunggu = _menunggu;
    if (menunggu != null && !menunggu.selesai) {
      unawaited(_jarakJauh.Batalkan(menunggu.uuid));
    }
    super.dispose();
  }

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

  Future<void> _MintaJarakJauh() async {
    final pemohon = ref.read(penyediaSesi).kasir;
    if (pemohon == null) {
      return;
    }
    setState(() {
      _sibuk = true;
      _galat = null;
    });
    try {
      final permintaan = await _jarakJauh.Ajukan(
        pemohon: pemohon,
        izin: widget.izin,
        hanyaPemilik: widget.hanyaPemilik,
        judul: widget.judul ?? LayananPersetujuanJarakJauh.AmbilJudulBawaan(widget.hanyaPemilik ? null : widget.izin),
        nilai: widget.nilai,
        rincian: [(label: 'Keterangan', nilai: widget.pesan), ...widget.rincian],
      );
      if (!mounted) {
        return;
      }
      setState(() => _menunggu = permintaan);
      _pantau = _jarakJauh.Pantau(permintaan.uuid).listen(
        _SaatStatus,
        onError: (Object galat) {
          if (mounted) {
            setState(() {
              _galat = galat is GalatKasir ? galat.pesan : 'Status persetujuan tidak bisa dibaca. Coba lagi.';
              _menunggu = null;
            });
          }
        },
      );
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

  void _SaatStatus(PermintaanPersetujuanPos status) {
    if (!mounted) {
      return;
    }
    if (status.status == PermintaanPersetujuanPos.disetujui && status.penyetuju != null) {
      _menunggu = status;
      Navigator.of(context).pop(LayananPersetujuanJarakJauh.KeStaf(status.penyetuju!));
      return;
    }
    setState(() {
      if (status.selesai) {
        _menunggu = null;
        _galat = switch (status.status) {
          PermintaanPersetujuanPos.ditolak =>
            'Ditolak ${status.namaPemutus ?? ''}: ${status.alasanTolak ?? 'tanpa alasan'}',
          _ => 'Permintaan ${status.labelStatus.toLowerCase()}. Minta lagi atau pakai PIN penyetuju.',
        };
      } else {
        _menunggu = status;
      }
    });
  }

  Future<void> _BerhentiMenunggu() async {
    final menunggu = _menunggu;
    await _pantau?.cancel();
    _pantau = null;
    setState(() => _menunggu = null);
    if (menunggu != null) {
      await _jarakJauh.Batalkan(menunggu.uuid);
    }
  }

  @override
  Widget build(BuildContext context) {
    final supervisor = (ref.watch(penyediaStaf).value ?? const <StafLokal>[])
        .where((s) => widget.hanyaPemilik ? s.pemilik : s.PunyaIzin(widget.izin))
        .toList();
    final warna = TokenWarna.AmbilDari(context);
    final menunggu = _menunggu;
    // Tepi dialog diperkecil agar papan PIN (3 × 96dp) muat di layar 360dp tanpa terpotong.
    return AlertDialog(
      insetPadding: const EdgeInsets.symmetric(horizontal: TokenJarak.jarak16, vertical: TokenJarak.jarak24),
      contentPadding: const EdgeInsets.fromLTRB(
        TokenJarak.jarak16,
        TokenJarak.jarak16,
        TokenJarak.jarak16,
        TokenJarak.jarak24,
      ),
      title: Text(menunggu == null ? 'Persetujuan supervisor' : 'Menunggu persetujuan'),
      content: SingleChildScrollView(
        child: menunggu != null
            ? Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const LinearProgressIndicator(),
                  const SizedBox(height: 12),
                  Text(
                    'Permintaan "${menunggu.judul}" sudah dikirim ke Aplikasi Owner. Tunggu keputusan pemilik atau '
                    'supervisor; berlaku 10 menit.',
                  ),
                ],
              )
            : _dipilih == null
            ? Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(widget.pesan),
                  const SizedBox(height: 12),
                  if (supervisor.isEmpty)
                    Text(
                      widget.hanyaPemilik
                          ? 'Pemilik belum terdaftar di perangkat ini.'
                          : 'Tidak ada supervisor di outlet ini.',
                      style: TextStyle(color: warna.bahaya),
                    ),
                  for (final s in supervisor)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 4),
                      child: OutlinedButton(onPressed: () => setState(() => _dipilih = s), child: Text(s.nama)),
                    ),
                  if (_jarakJauhTersedia) ...[
                    const SizedBox(height: 8),
                    FilledButton.tonalIcon(
                      onPressed: _sibuk ? null : () => unawaited(_MintaJarakJauh()),
                      icon: const Icon(Icons.phone_iphone),
                      label: const Text('Minta persetujuan jarak jauh'),
                    ),
                  ],
                  if (_galat != null) ...[
                    const SizedBox(height: 8),
                    Text(_galat!, style: TextStyle(color: warna.bahaya)),
                  ],
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
      actions: [
        if (menunggu != null)
          TextButton(onPressed: () => unawaited(_BerhentiMenunggu()), child: const Text('Berhenti menunggu'))
        else
          TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Batal')),
      ],
    );
  }
}
