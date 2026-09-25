import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mesin_kasir/MesinKasir.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../../Aplikasi/Penyedia.dart';
import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Domain/GalatKasir.dart';
import '../../Domain/Penjualan/KonteksPenjualan.dart';
import '../../Domain/Penjualan/LayananPenjualan.dart';
import '../../Domain/Sesi/StafLokal.dart';
import 'PanelKeranjang.dart';

/// Gambar QRIS statis metode pembayaran (diunduh sekali per sesi aplikasi).
final penyediaGambarQris = FutureProvider.family<Uint8List, String>(
  (ref, uuidMetode) => ref.watch(penyediaKlienPos).AmbilGambarQris(uuidMetode),
);

/// Label jenis metode pembayaran untuk kasir.
String AmbilLabelJenisMetode(String jenis) => switch (jenis) {
  JenisMetodeBayar.tunai => 'Tunai',
  JenisMetodeBayar.qrisStatis => 'QRIS',
  JenisMetodeBayar.edc => 'Kartu (EDC)',
  JenisMetodeBayar.transfer => 'Transfer',
  JenisMetodeBayar.ewallet => 'E-wallet',
  _ => jenis,
};

/// Pecahan cepat untuk tagihan tunai: uang pas lalu pembulatan ke atas ke Rp 5.000, 10.000, 20.000, 50.000, 100.000
/// (unik, maksimal [batas] tombol selain uang pas).
List<Uang> HitungPecahanCepat(Uang tagihan, {int batas = 4}) {
  final hasil = <Uang>[];
  for (final kelipatan in const [5000, 10000, 20000, 50000, 100000]) {
    final atas = tagihan.BulatkanKeKelipatan(kelipatan, ModePembulatan.KeAtas);
    if (atas.Bandingkan(tagihan) > 0 && !hasil.contains(atas)) {
      hasil.add(atas);
    }
  }
  return hasil.take(batas).toList();
}

/// Panel Bayar (F-08 fase 1, Rincian F-07c): tunai (pecahan cepat & uang pas), QRIS statis (gambar + konfirmasi
/// kasir), EDC (bank & nomor approval), transfer & e-wallet (referensi), split pembayaran (BR-08.1). Pembulatan tunai
/// hanya untuk bagian tunai (BR-08.6). Setelah tersimpan memanggil [saatSelesai].
class PanelBayar extends ConsumerStatefulWidget {
  const PanelBayar({super.key, required this.kasir, required this.saatSelesai});

  final StafLokal kasir;
  final ValueChanged<PenjualanTersimpan> saatSelesai;

  @override
  ConsumerState<PanelBayar> createState() => PanelBayarState();
}

/// Publik agar pintasan F9 (uang pas, PRD §17.2.3) di layar Jual bisa memicu pembayaran tunai uang pas.
class PanelBayarState extends ConsumerState<PanelBayar> {
  final List<PembayaranMasukan> _entri = [];
  BarisMetodePembayaran? _metode;
  final _nominal = TextEditingController();
  final _referensi = TextEditingController();
  final _bank = TextEditingController();
  bool _qrisDikonfirmasi = false;
  bool _sibuk = false;
  String? _galat;

  @override
  void dispose() {
    _nominal.dispose();
    _referensi.dispose();
    _bank.dispose();
    super.dispose();
  }

  Uang _AmbilDibayar() => _entri.fold(Uang.Nol(), (t, p) => t.Tambah(p.jumlah));

  /// Sisa tagihan bila sisa dibayar dengan [metode] (tunai memakai pembulatan tunai).
  Uang _HitungSisa(KonteksPenjualan k, BarisMetodePembayaran? metode) {
    final layanan = ref.read(penyediaLayananPenjualan);
    final keranjang = ref.read(penyediaKeranjangEfektif);
    if (metode?.Jenis == JenisMetodeBayar.tunai) {
      return layanan.HitungTagihanTunai(keranjang, k, _entri);
    }
    final hasil = layanan.Hitung(keranjang, k, pembayaran: [for (final p in _entri) p.KeKalkulasi()]).hasil;
    return hasil.totalAkhir.Kurangi(_AmbilDibayar());
  }

  void _PilihMetode(KonteksPenjualan k, BarisMetodePembayaran metode) {
    setState(() {
      _metode = metode;
      _galat = null;
      _qrisDikonfirmasi = false;
      _referensi.clear();
      _bank.clear();
      final sisa = _HitungSisa(k, metode);
      _nominal.text = metode.Jenis == JenisMetodeBayar.tunai || sisa.Bandingkan(Uang.Nol()) <= 0
          ? ''
          : sisa.KeDesimal().ceil().toString();
    });
  }

  Uang? _AmbilNominal() {
    final teks = _nominal.text.trim();
    return teks.isEmpty ? null : Uang.Dari(teks);
  }

  String? _SusunReferensi(BarisMetodePembayaran metode) {
    if (metode.Jenis == JenisMetodeBayar.edc) {
      return LayananPenjualan.SusunReferensiEdc(_bank.text, _referensi.text);
    }
    final teks = _referensi.text.trim();
    return teks.isEmpty ? null : teks;
  }

  Future<void> _Terapkan(KonteksPenjualan k) async {
    final metode = _metode;
    if (metode == null) {
      setState(() => _galat = 'Pilih metode pembayaran dulu.');
      return;
    }
    final nominal = _AmbilNominal();
    if (nominal == null || nominal.Bandingkan(Uang.Nol()) <= 0) {
      setState(() => _galat = 'Isi jumlah pembayaran.');
      return;
    }
    if (metode.Jenis == JenisMetodeBayar.qrisStatis && !_qrisDikonfirmasi) {
      setState(() => _galat = 'Pastikan dana QRIS sudah masuk, lalu centang konfirmasi.');
      return;
    }
    if (metode.Jenis == JenisMetodeBayar.edc && _referensi.text.trim().isEmpty) {
      setState(() => _galat = 'Isi nomor approval dari struk EDC.');
      return;
    }
    final sisa = _HitungSisa(k, metode);
    if (metode.Jenis != JenisMetodeBayar.tunai && nominal.Bandingkan(sisa) > 0) {
      setState(() => _galat = 'Pembayaran ${metode.Nama} tidak boleh melebihi sisa ${sisa.FormatRupiah()}.');
      return;
    }
    final entri = PembayaranMasukan(metode: metode, jumlah: nominal, referensi: _SusunReferensi(metode));
    if (nominal.Bandingkan(sisa) < 0) {
      // Split (BR-08.1): simpan bagian ini, lanjutkan dengan metode lain.
      setState(() {
        _entri.add(entri);
        _metode = null;
        _nominal.clear();
        _galat = null;
      });
      return;
    }
    await _Selesaikan(k, [..._entri, entri]);
  }

  /// F9: bayar sisa tagihan dengan tunai uang pas (pembulatan tunai BR-08.6 ikut dihitung) lalu simpan.
  Future<void> BayarUangPas() async {
    if (_sibuk) {
      return;
    }
    final k = await ref.read(penyediaKonteksPenjualan.future);
    final tunai = k.metodePembayaran.where((m) => m.Jenis == JenisMetodeBayar.tunai).firstOrNull;
    if (tunai == null || _entri.any((p) => p.CekTunai())) {
      if (mounted) {
        setState(() => _galat = 'Uang pas tidak bisa dipakai: metode tunai tidak tersedia atau sudah dipakai.');
      }
      return;
    }
    final tagihan = ref
        .read(penyediaLayananPenjualan)
        .HitungTagihanTunai(ref.read(penyediaKeranjangEfektif), k, _entri);
    if (tagihan.Bandingkan(Uang.Nol()) <= 0) {
      await _Selesaikan(k, List.of(_entri));
      return;
    }
    await _Selesaikan(k, [..._entri, PembayaranMasukan(metode: tunai, jumlah: tagihan)]);
  }

  Future<void> _Selesaikan(KonteksPenjualan k, List<PembayaranMasukan> pembayaran) async {
    setState(() {
      _sibuk = true;
      _galat = null;
    });
    try {
      final hasil = await ref
          .read(penyediaLayananPenjualan)
          .Bayar(keranjang: ref.read(penyediaKeranjangEfektif), pembayaran: pembayaran, kasir: widget.kasir, k: k);
      ref.read(penyediaKeranjang.notifier).Kosongkan();
      final sesi = ref.read(penyediaSesi.notifier);
      widget.saatSelesai(hasil);
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

  Widget _BangunTunai(BuildContext context, KonteksPenjualan k) {
    final tagihan = _HitungSisa(k, _metode);
    final nominal = _AmbilNominal();
    final kembalian = nominal?.Kurangi(tagihan);
    final teks = Theme.of(context).textTheme;
    Widget Tombol(String label, Uang nilai) => SizedBox(
      height: TokenJarak.targetSentuh,
      child: OutlinedButton(
        onPressed: () => setState(() {
          _nominal.text = nilai.KeDesimal().ceil().toString();
          _galat = null;
        }),
        child: Text(label),
      ),
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(child: Text('Tagihan tunai', style: teks.bodyMedium)),
            TeksUang(tagihan, gaya: teks.titleMedium),
          ],
        ),
        const SizedBox(height: TokenJarak.jarak8),
        Wrap(
          spacing: TokenJarak.jarak8,
          runSpacing: TokenJarak.jarak8,
          children: [
            Tombol('Uang pas', tagihan),
            for (final p in HitungPecahanCepat(tagihan)) Tombol(p.FormatRupiah(), p),
          ],
        ),
        const SizedBox(height: TokenJarak.jarak12),
        TextField(
          controller: _nominal,
          keyboardType: TextInputType.number,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(13)],
          textAlign: TextAlign.right,
          style: teks.titleMedium?.copyWith(fontFeatures: const [FontFeature.tabularFigures()]),
          onChanged: (_) => setState(() => _galat = null),
          onSubmitted: (_) => _Terapkan(k),
          decoration: const InputDecoration(
            labelText: 'Uang diterima',
            prefixText: 'Rp ',
            border: OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: TokenJarak.jarak8),
        PapanAngka(
          saatTekan: (tombol) => setState(() {
            _nominal.text = PapanAngka.Terapkan(_nominal.text, tombol);
            _galat = null;
          }),
        ),
        if (kembalian != null && !kembalian.BernilaiNegatif()) ...[
          const SizedBox(height: TokenJarak.jarak8),
          Row(
            children: [
              Expanded(child: Text('Kembalian', style: teks.titleMedium)),
              TeksUang(kembalian, gaya: teks.titleMedium),
            ],
          ),
        ],
      ],
    );
  }

  Widget _BangunNonTunai(BuildContext context, KonteksPenjualan k, BarisMetodePembayaran metode) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (metode.Jenis == JenisMetodeBayar.qrisStatis) ...[
          if (metode.AdaGambarQris)
            ref
                .watch(penyediaGambarQris(metode.Uuid))
                .when(
                  loading: () => const SizedBox(height: 240, child: Center(child: CircularProgressIndicator())),
                  error: (_, _) => Text(
                    'Gambar QRIS tidak bisa dimuat. Minta pelanggan memindai QRIS yang tercetak di meja kasir.',
                    style: teks.bodySmall?.copyWith(color: warna.peringatan),
                  ),
                  data: (bait) => Center(
                    child: Semantics(
                      label: 'Kode QRIS ${metode.Nama}',
                      image: true,
                      child: Image.memory(
                        bait,
                        height: 240,
                        fit: BoxFit.contain,
                        errorBuilder: (_, _, _) => Text('Gambar QRIS rusak. Pakai QRIS cetak.', style: teks.bodySmall),
                      ),
                    ),
                  ),
                )
          else
            Text('Minta pelanggan memindai QRIS yang tercetak di meja kasir.', style: teks.bodySmall),
          const SizedBox(height: TokenJarak.jarak8),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            controlAffinity: ListTileControlAffinity.leading,
            value: _qrisDikonfirmasi,
            onChanged: (v) => setState(() {
              _qrisDikonfirmasi = v ?? false;
              _galat = null;
            }),
            title: const Text('Dana sudah masuk (cek notifikasi atau aplikasi bank)'),
          ),
        ],
        if (metode.Jenis == JenisMetodeBayar.transfer && metode.NomorRekening != null) ...[
          Text(
            'Rekening ${metode.NomorRekening}${metode.NamaPemilikRekening == null ? '' : ' a.n. ${metode.NamaPemilikRekening}'}',
            style: teks.bodyMedium,
          ),
          const SizedBox(height: TokenJarak.jarak8),
        ],
        if (metode.Jenis == JenisMetodeBayar.edc) ...[
          TextField(
            controller: _bank,
            maxLength: LayananPenjualan.panjangMaksBankEdc,
            decoration: const InputDecoration(
              labelText: 'Bank penerbit kartu (opsional)',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: TokenJarak.jarak4),
        ],
        TextField(
          controller: _referensi,
          maxLength: metode.Jenis == JenisMetodeBayar.edc ? LayananPenjualan.panjangMaksApprovalEdc : 60,
          onChanged: (_) => setState(() => _galat = null),
          decoration: InputDecoration(
            labelText: metode.Jenis == JenisMetodeBayar.edc ? 'Nomor approval' : 'Referensi (opsional)',
            border: const OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: TokenJarak.jarak4),
        TextField(
          controller: _nominal,
          keyboardType: TextInputType.number,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(13)],
          textAlign: TextAlign.right,
          style: const TextStyle(fontFeatures: [FontFeature.tabularFigures()]),
          onChanged: (_) => setState(() => _galat = null),
          decoration: const InputDecoration(labelText: 'Jumlah', prefixText: 'Rp ', border: OutlineInputBorder()),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final konteks = ref.watch(penyediaKonteksPenjualan);
    final keranjang = ref.watch(penyediaKeranjangEfektif);
    final k = konteks.value;
    if (k == null) {
      return const Padding(padding: EdgeInsets.all(TokenJarak.jarak24), child: LinearProgressIndicator());
    }
    final layanan = ref.read(penyediaLayananPenjualan);
    final metode = _metode;
    final pembayaranHitung = [
      for (final p in _entri) p.KeKalkulasi(),
      if (metode?.Jenis == JenisMetodeBayar.tunai) const DataPembayaranKalkulasi(metode: 'Tunai'),
    ];
    final hitungan = layanan.Hitung(keranjang, k, pembayaran: pembayaranHitung);
    final sisa = _HitungSisa(k, metode);
    final tunaiDipakai = _entri.any((p) => p.CekTunai());
    final nominal = _AmbilNominal();
    final melunasi = nominal != null && nominal.Bandingkan(sisa) >= 0;

    return Padding(
      padding: const EdgeInsets.all(TokenJarak.jarak24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          ...RingkasanTotal.BangunBaris(context, hitungan, keranjang, tampilPembulatan: true),
          for (final p in _entri)
            Row(
              children: [
                Expanded(child: Text('${p.metode.Nama}${p.referensi == null ? '' : ' · ${p.referensi}'}')),
                TeksUang(p.jumlah),
                IconButton(
                  tooltip: 'Hapus pembayaran ${p.metode.Nama}',
                  onPressed: () => setState(() => _entri.remove(p)),
                  icon: const Icon(Icons.close),
                ),
              ],
            ),
          if (_entri.isNotEmpty)
            Row(
              children: [
                Expanded(child: Text('Sisa', style: teks.titleMedium)),
                TeksUang(sisa, gaya: teks.titleMedium),
              ],
            ),
          const SizedBox(height: TokenJarak.jarak16),
          if (k.metodePembayaran.isEmpty)
            Text(
              'Metode pembayaran belum tersedia di perangkat ini. Sambungkan ke internet agar data terbaru terunduh.',
              style: TextStyle(color: warna.bahaya),
            )
          else
            Wrap(
              spacing: TokenJarak.jarak8,
              runSpacing: TokenJarak.jarak8,
              children: [
                for (final m in k.metodePembayaran)
                  if (!(tunaiDipakai && m.Jenis == JenisMetodeBayar.tunai))
                    ChoiceChip(
                      label: Text(m.Nama),
                      tooltip: AmbilLabelJenisMetode(m.Jenis),
                      selected: metode?.Uuid == m.Uuid,
                      onSelected: _sibuk ? null : (_) => _PilihMetode(k, m),
                    ),
              ],
            ),
          if (metode != null) ...[
            const SizedBox(height: TokenJarak.jarak16),
            if (metode.Jenis == JenisMetodeBayar.tunai)
              _BangunTunai(context, k)
            else
              _BangunNonTunai(context, k, metode),
          ],
          if (_galat != null)
            Padding(
              padding: const EdgeInsets.only(top: TokenJarak.jarak8),
              child: Text(_galat!, style: TextStyle(color: warna.bahaya)),
            ),
          const SizedBox(height: TokenJarak.jarak16),
          SizedBox(
            height: 56,
            child: FilledButton(
              onPressed: _sibuk || metode == null ? null : () => _Terapkan(k),
              child: Text(
                _sibuk
                    ? 'Menyimpan…'
                    : melunasi || metode == null
                    ? 'Selesaikan pembayaran'
                    : 'Tambah pembayaran ${metode.Nama}',
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Layar selesai setelah pembayaran tersimpan: kembalian besar, nomor, dan transaksi baru.
class TampilanSelesai extends StatelessWidget {
  const TampilanSelesai({super.key, required this.hasil, required this.saatTransaksiBaru});

  final PenjualanTersimpan hasil;
  final VoidCallback saatTransaksiBaru;

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    return Padding(
      padding: const EdgeInsets.all(TokenJarak.jarak24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(Icons.check_circle_outline, color: warna.sukses),
              const SizedBox(width: TokenJarak.jarak8),
              Expanded(child: Text('Pembayaran berhasil', style: teks.titleMedium)),
            ],
          ),
          const SizedBox(height: TokenJarak.jarak4),
          TeksKode(hasil.nomor, gaya: teks.bodyMedium?.copyWith(color: warna.teksSekunder)),
          const SizedBox(height: TokenJarak.jarak24),
          Text('Kembalian', style: teks.titleMedium),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: TeksUang(hasil.kembalian, rataKanan: false, gaya: teks.displaySmall),
          ),
          const SizedBox(height: TokenJarak.jarak16),
          Row(
            children: [
              Expanded(child: Text('Total', style: teks.bodyMedium)),
              TeksUang(hasil.totalAkhir),
            ],
          ),
          for (final p in hasil.pembayaran)
            Row(
              children: [
                Expanded(child: Text(p.metode.Nama, style: teks.bodyMedium)),
                TeksUang(p.jumlah),
              ],
            ),
          const SizedBox(height: TokenJarak.jarak8),
          Text(
            'Transaksi tersimpan di perangkat dan dikirim otomatis. Cetak struk menyusul setelah printer diatur.',
            style: teks.bodySmall,
          ),
          const SizedBox(height: TokenJarak.jarak24),
          SizedBox(
            height: 56,
            child: FilledButton(autofocus: true, onPressed: saatTransaksiBaru, child: const Text('Transaksi baru')),
          ),
        ],
      ),
    );
  }
}
