import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import '../Data/RepositoriKasir.dart';
import '../Domain/Sinkron/LayananSinkron.dart';
import 'Komponen/FormatWaktu.dart';
import 'RuangKerja/IsiAreaKerja.dart';

/// Status sinkron (PRD §18): jumlah data belum terkirim dan daftar "Perlu Tindakan" (ditolak server) beserta
/// alasannya. K-17 (§18.3 butir 7 & 10): waktu sinkron terakhir, peringatan data tertunda > 2 jam, peringatan jam
/// perangkat berbeda > 10 menit dari server, dan penjualan yang diterima dengan tanda tinjauan back-office. Item bisa dikirim ulang setelah penyebabnya diperbaiki di back-office. Tampil di area ruang kerja
/// (dibuka dari rel navigasi atau dengan mengetuk bilah status).
class LayarStatusSinkron extends ConsumerStatefulWidget {
  const LayarStatusSinkron({super.key});

  /// §18.3 butir 10: data tertunda lebih lama dari ini diberi peringatan.
  static const Duration batasTertunda = Duration(hours: 2);

  /// §18.3 butir 7: selisih jam perangkat − server lebih dari ini diberi peringatan.
  static const int batasSelisihJamDetik = 600;

  /// Label jenis item outbox untuk kasir (audit kemudahan pakai #30: semua jenis berlabel bahasa sehari-hari; jenis
  /// yang belum dikenal tampil sebagai "Data lain" supaya kode teknis tidak muncul di layar).
  static String AmbilLabelJenis(String jenis) => switch (jenis) {
    'Shift.Buka' => 'Buka shift',
    'Shift.Tutup' => 'Tutup shift',
    'Shift.BukaUlang' => 'Buka ulang shift',
    'Penjualan.Buat' => 'Penjualan',
    'Penjualan.Void' => 'Pembatalan transaksi',
    'ReturPenjualan.Buat' => 'Retur penjualan',
    'ReturPenjualan.TanpaStruk' => 'Retur tanpa struk',
    'MutasiKas.Catat' => 'Kas masuk/keluar',
    'Laci.Buka' => 'Buka laci',
    'Absensi.Masuk' => 'Absen masuk',
    'Absensi.Keluar' => 'Absen pulang',
    'Pelanggan.Buat' => 'Pelanggan baru',
    'Deposit.Isi' => 'Isi saldo pelanggan',
    'Sesi.Pakai' => 'Pakai paket sesi',
    'PesananPenjualan.Buat' => 'Pesanan dengan uang muka',
    'PesananTerbuka.Buka' => 'Buka pesanan meja',
    'PesananTerbuka.Tambah' => 'Tambah item pesanan meja',
    'PesananTerbuka.Ubah' => 'Ubah pesanan meja',
    'PesananTerbuka.KirimDapur' => 'Kirim ke dapur',
    'PesananTerbuka.BatalkanBaris' => 'Batalkan item pesanan meja',
    'PesananTerbuka.PindahBaris' => 'Pindah/pisah pesanan meja',
    'PesananTerbuka.Batal' => 'Batalkan pesanan meja',
    'Meja.Bersih' => 'Meja selesai dibersihkan',
    'BahanTerbuang.Catat' => 'Bahan terbuang',
    'PesananGrosir.Buat' => 'Pesanan salesman',
    'Kunjungan.Catat' => 'Kunjungan salesman',
    _ => 'Data lain',
  };

  @override
  ConsumerState<LayarStatusSinkron> createState() => _LayarStatusSinkronState();
}

class _LayarStatusSinkronState extends ConsumerState<LayarStatusSinkron> {
  bool _sibuk = false;
  String? _pesan;

  Future<void> _Kirim() async {
    setState(() {
      _sibuk = true;
      _pesan = null;
    });
    // K-17: "Kirim sekarang" tidak menunggu jadwal coba ulang (mundur eksponensial) item yang tertunda.
    final hasil = await ref.read(penyediaSesi.notifier).SinkronkanSegera() ?? const RingkasanSinkron();
    if (mounted) {
      setState(() {
        _sibuk = false;
        _pesan = hasil.offline
            ? 'Belum tersambung ke server. Data aman di perangkat dan akan dikirim otomatis.'
            : '${hasil.terkirim} data terkirim${hasil.ditolak > 0 ? ', ${hasil.ditolak} perlu tindakan' : ''}.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final tertunda = ref.watch(penyediaJumlahTertunda).value ?? 0;
    final perlu = ref.watch(penyediaPerluTindakan).value ?? const [];

    final koneksi = ref.watch(penyediaKoneksi);
    final pengaturan = ref.watch(penyediaPengaturanSinkron).value ?? const <String, String>{};
    final terakhir = DateTime.tryParse(pengaturan[KunciPengaturan.sinkronTerakhir] ?? '');
    final selisih = int.tryParse(pengaturan[KunciPengaturan.selisihJamDetik] ?? '');
    final tertua = ref.watch(penyediaOutboxTertua).value;
    final sekarang = ref.watch(penyediaJam)();
    final ditinjau = ref.watch(penyediaPenjualanDitinjau).value ?? const <String>[];
    final peringatan = <String>[
      if (tertua != null && sekarang.difference(tertua) > LayarStatusSinkron.batasTertunda)
        'Ada data belum terkirim sejak ${FormatWaktu.FormatTanggalJam(tertua)} (lebih dari 2 jam). Pastikan perangkat '
            'tersambung internet, lalu ketuk Kirim sekarang.',
      if (selisih != null && selisih.abs() > LayarStatusSinkron.batasSelisihJamDetik)
        'Jam perangkat ${selisih > 0 ? 'lebih cepat' : 'lebih lambat'} ${(selisih.abs() / 60).round()} menit dari '
            'server. Perbaiki tanggal, jam, dan zona waktu perangkat agar waktu transaksi benar.',
    ];

    return IsiAreaKerja(
      judul: 'Status sinkron',
      aksi: [FilledButton(onPressed: _sibuk ? null : _Kirim, child: Text(_sibuk ? 'Mengirim…' : 'Kirim sekarang'))],
      anak: [
        DeretKartuAngka(
          kartu: [
            KartuAngka(
              label: 'Belum terkirim ke server',
              ikon: Icons.cloud_upload_outlined,
              nada: tertunda == 0 ? NadaStatus.Sukses : NadaStatus.Peringatan,
              nilai: Text(tertunda == 0 ? 'Semua data sudah terkirim.' : '$tertunda data belum terkirim.'),
            ),
            KartuAngka(
              label: 'Koneksi',
              ikon: koneksi == StatusKoneksi.Online ? Icons.wifi : Icons.wifi_off,
              nada: koneksi == StatusKoneksi.Offline ? NadaStatus.Peringatan : NadaStatus.Netral,
              nilai: Text(switch (koneksi) {
                StatusKoneksi.Online => 'Online',
                StatusKoneksi.Offline => 'Offline',
                StatusKoneksi.BelumDiketahui => 'Belum diperiksa',
              }),
              keterangan: switch (koneksi) {
                StatusKoneksi.Online => 'Tersambung ke server',
                StatusKoneksi.Offline => 'Dikirim otomatis saat online',
                StatusKoneksi.BelumDiketahui => null,
              },
            ),
            KartuAngka(
              label: 'Sinkron terakhir',
              ikon: Icons.history,
              nilai: Text(
                terakhir == null ? 'Belum pernah' : FormatWaktu.FormatTanggalJam(terakhir),
                key: const ValueKey('SinkronTerakhir'),
              ),
            ),
            KartuAngka(
              label: 'Perlu tindakan',
              ikon: Icons.error_outline,
              nada: perlu.isEmpty ? NadaStatus.Netral : NadaStatus.Bahaya,
              nilai: Text('${perlu.length}'),
              keterangan: 'Ditolak server',
            ),
            KartuAngka(
              label: 'Diperiksa back-office',
              ikon: Icons.flag_outlined,
              nada: ditinjau.isEmpty ? NadaStatus.Netral : NadaStatus.Peringatan,
              nilai: Text('${ditinjau.length}'),
              keterangan: 'Sudah diterima server',
            ),
          ],
        ),
        for (final p in peringatan)
          Padding(
            padding: const EdgeInsets.only(top: TokenJarak.jarak8),
            child: KotakPanel(
              anak: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.warning_amber_outlined, size: TokenJarak.ikonSedang, color: warna.peringatan),
                  const SizedBox(width: TokenJarak.jarak12),
                  Expanded(child: Text(p)),
                ],
              ),
            ),
          ),
        if (_pesan != null)
          Padding(
            padding: const EdgeInsets.only(top: TokenJarak.jarak8),
            child: Text(_pesan!),
          ),
        const SizedBox(height: TokenJarak.jarak16),
        Text('Perlu tindakan', style: teks.titleSmall),
        const SizedBox(height: TokenJarak.jarak4),
        if (perlu.isEmpty)
          Text('Tidak ada data yang ditolak server.', style: teks.bodyMedium?.copyWith(color: warna.teksSekunder))
        else
          for (final b in perlu)
            Padding(
              padding: const EdgeInsets.only(bottom: TokenJarak.jarak8),
              child: KotakPanel(
                anak: Row(
                  children: [
                    Icon(Icons.error_outline, size: TokenJarak.ikonSedang, color: warna.bahaya),
                    const SizedBox(width: TokenJarak.jarak12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(LayarStatusSinkron.AmbilLabelJenis(b.Jenis)),
                          Text(b.PesanGalat ?? 'Ditolak server.', style: TextStyle(color: warna.bahaya)),
                        ],
                      ),
                    ),
                    TextButton(
                      onPressed: () async {
                        await ref.read(penyediaRepositori).CobaLagi(b.Uuid, ref.read(penyediaJam)());
                        await _Kirim();
                      },
                      child: const Text('Kirim ulang'),
                    ),
                  ],
                ),
              ),
            ),
        const SizedBox(height: TokenJarak.jarak16),
        Text('Diperiksa back-office', style: teks.titleSmall),
        const SizedBox(height: TokenJarak.jarak4),
        if (ditinjau.isEmpty)
          Text(
            'Tidak ada transaksi yang ditandai untuk diperiksa.',
            style: teks.bodyMedium?.copyWith(color: warna.teksSekunder),
          )
        else ...[
          Text(
            'Transaksi ini sudah diterima server, tetapi ditandai untuk diperiksa back-office (misal stok kurang atau '
            'pesanan sudah dibayar di perangkat lain). Tidak perlu diulang di kasir.',
            style: teks.bodyMedium?.copyWith(color: warna.teksSekunder),
          ),
          const SizedBox(height: TokenJarak.jarak8),
          for (final nomor in ditinjau)
            Padding(
              padding: const EdgeInsets.only(bottom: TokenJarak.jarak4),
              child: Row(
                children: [
                  Icon(Icons.flag_outlined, size: TokenJarak.ikonKecil, color: warna.peringatan),
                  const SizedBox(width: TokenJarak.jarak8),
                  Expanded(child: Text(nomor)),
                ],
              ),
            ),
        ],
      ],
    );
  }
}
