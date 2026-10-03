import 'dart:async';
import 'dart:typed_data';

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
/// (BR-06.4) sebelum disimpan. Kategori dipilih lewat chip (satu-satunya kategori terpilih otomatis) dan isian
/// diperiksa sebelum PIN diminta, supaya supervisor tidak memasukkan PIN untuk formulir yang lalu ditolak. Isi formulir ini ditampilkan bingkai ruang kerja di dalam `PanelTugas` (panel samping
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
  String? _galatKategori;
  Uint8List? _bukti;

  Future<void> _AmbilBukti() async {
    final foto = await ref.read(penyediaKameraBukti).Ambil();
    if (foto != null && mounted) {
      setState(() => _bukti = foto);
    }
  }

  @override
  void dispose() {
    _jumlah.dispose();
    _catatan.dispose();
    super.dispose();
  }

  /// Kategori yang dipakai: pilihan kasir, atau satu-satunya kategori jenis ini.
  String? _AmbilKategori() {
    if (widget.jenis == JenisMutasi.setoran) {
      return null;
    }
    final daftar = ref.read(penyediaKategori(widget.jenis)).value ?? const <BarisKategoriKas>[];
    return _uuidKategori ?? (daftar.length == 1 ? daftar.single.Uuid : null);
  }

  Future<void> _Simpan() async {
    final layanan = ref.read(penyediaLayananShift);
    final jumlah = MasukanUang.AmbilNilai(_jumlah);
    final uuidKategori = _AmbilKategori();
    final kurangKategori = widget.jenis != JenisMutasi.setoran && uuidKategori == null;
    if (jumlah == null || jumlah.Bandingkan(Uang.Nol()) <= 0 || kurangKategori) {
      setState(() {
        _galat = jumlah == null
            ? 'Isi jumlah kas.'
            : jumlah.Bandingkan(Uang.Nol()) <= 0
            ? 'Jumlah kas harus lebih dari Rp 0.'
            : null;
        _galatKategori = kurangKategori ? 'Pilih kategori kas terlebih dahulu.' : null;
      });
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
      _galatKategori = null;
    });
    try {
      await layanan.CatatMutasi(
        shift: widget.shift,
        jenis: widget.jenis,
        jumlah: jumlah,
        pencatat: widget.pencatat,
        uuidKategori: uuidKategori,
        catatan: _catatan.text,
        penyetuju: penyetuju,
        bukti: _bukti,
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
    final bisaFoto = widget.jenis != JenisMutasi.setoran && ref.watch(penyediaKameraBukti).CekTersedia();
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
                  : _PilihanKategori(
                      daftar: daftar,
                      terpilih: _uuidKategori ?? (daftar.length == 1 ? daftar.single.Uuid : null),
                      galat: _galatKategori,
                      saatDipilih: (uuid) => setState(() {
                        _uuidKategori = uuid;
                        _galatKategori = null;
                      }),
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
          if (bisaFoto) ...[
            if (_bukti case final foto?)
              Row(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(TokenJarak.radiusKontrol),
                    child: Image.memory(foto, width: 56, height: 56, fit: BoxFit.cover),
                  ),
                  const SizedBox(width: 12),
                  const Expanded(child: Text('Foto bukti terlampir')),
                  TextButton(
                    onPressed: _sibuk ? null : () => setState(() => _bukti = null),
                    child: const Text('Hapus'),
                  ),
                ],
              )
            else
              OutlinedButton.icon(
                onPressed: _sibuk ? null : _AmbilBukti,
                icon: const Icon(Icons.photo_camera_outlined),
                label: const Text('Foto bukti / nota (opsional)'),
              ),
            const SizedBox(height: 8),
          ],
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

/// Kategori kas sebagai chip (audit kemudahan pakai #8): satu ketukan, terlihat semua tanpa membuka dropdown.
class _PilihanKategori extends StatelessWidget {
  const _PilihanKategori({
    required this.daftar,
    required this.terpilih,
    required this.galat,
    required this.saatDipilih,
  });

  final List<BarisKategoriKas> daftar;
  final String? terpilih;
  final String? galat;
  final ValueChanged<String> saatDipilih;

  @override
  Widget build(BuildContext context) {
    final warna = TokenWarna.AmbilDari(context);
    return Semantics(
      container: true,
      label: 'Kategori',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Kategori', style: Theme.of(context).textTheme.labelLarge),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final k in daftar)
                ChoiceChip(
                  label: Text(k.Nama, maxLines: 1, overflow: TextOverflow.ellipsis),
                  selected: k.Uuid == terpilih,
                  onSelected: (_) => saatDipilih(k.Uuid),
                ),
            ],
          ),
          if (galat != null) ...[const SizedBox(height: 4), Text(galat!, style: TextStyle(color: warna.bahaya))],
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
    this.judulDialog = 'Persetujuan supervisor',
    this.pesanKosong,
    this.bolehJarakJauh = true,
  });

  final String izin;
  final String pesan;
  final bool hanyaPemilik;

  /// Judul permintaan jarak jauh (null = judul bawaan per izin).
  final String? judul;

  /// Nilai uang yang dimintakan persetujuannya (ditampilkan di Aplikasi Owner).
  final Uang? nilai;
  final List<({String label, String nilai})> rincian;

  /// Judul dialog (misal "PIN apoteker" untuk penyerahan obat keras, Apotek §9.5).
  final String judulDialog;

  /// Pesan bila tidak ada staf ber-[izin] di perangkat ini (null = pesan bawaan supervisor/pemilik).
  final String? pesanKosong;

  /// False = tombol persetujuan jarak jauh (X4) tidak ditawarkan, misal penyerahan obat keras yang wajib dilakukan
  /// apoteker di tempat.
  final bool bolehJarakJauh;

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
      title: Text(menunggu == null ? widget.judulDialog : 'Menunggu persetujuan'),
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
                      widget.pesanKosong ??
                          (widget.hanyaPemilik
                              ? 'Pemilik belum terdaftar di perangkat ini.'
                              : 'Tidak ada supervisor di outlet ini.'),
                      style: TextStyle(color: warna.bahaya),
                    ),
                  for (final s in supervisor)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 4),
                      child: OutlinedButton(onPressed: () => setState(() => _dipilih = s), child: Text(s.nama)),
                    ),
                  if (_jarakJauhTersedia && widget.bolehJarakJauh) ...[
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
