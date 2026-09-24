import 'dart:convert';

import 'package:klien_api/KlienApi.dart';

import '../../Data/RepositoriKasir.dart';
import '../Sesi/LayananPerangkat.dart';

/// Hasil satu putaran sinkron.
class RingkasanSinkron {
  const RingkasanSinkron({
    this.terkirim = 0,
    this.ditolak = 0,
    this.offline = false,
    this.perangkatDicabut = false,
    this.tersambung,
  });

  final int terkirim;

  /// Server terjangkau pada putaran ini: `true` bila server menjawab, `false` bila gagal jaringan, `null` bila tidak
  /// ada yang dikirim (koneksi tidak diperiksa).
  final bool? tersambung;
  final int ditolak;
  final bool offline;
  final bool perangkatDicabut;
}

/// Kirim outbox FIFO per perangkat (PRD §18 no. 4): batch maks. 50, berurutan (shift sebelum mutasinya).
/// `Diterima`/`Duplikat` → dihapus dari outbox; `Ditolak` → "Perlu Tindakan" beserta alasannya. Gagal jaringan/5xx →
/// dijadwalkan ulang dengan mundur eksponensial. Perangkat dicabut → data sensitif lokal dihapus, transaksi tetap.
class LayananSinkron {
  LayananSinkron({required this.klien, required this.repositori, required this.perangkat, DateTime Function()? jam})
    : _jam = jam ?? DateTime.now;

  static const int ukuranBatch = 50;

  final KlienPos klien;
  final RepositoriKasir repositori;
  final LayananPerangkat perangkat;
  final DateTime Function() _jam;

  bool _berjalan = false;

  Future<RingkasanSinkron> KirimTertunda() async {
    if (_berjalan) {
      return const RingkasanSinkron();
    }
    _berjalan = true;
    var terkirim = 0;
    var ditolak = 0;
    var dijawabServer = false;

    try {
      while (true) {
        final batch = await repositori.AmbilOutboxSiapKirim(ukuranBatch, _jam());
        if (batch.isEmpty) {
          return RingkasanSinkron(terkirim: terkirim, ditolak: ditolak, tersambung: dijawabServer ? true : null);
        }

        final List<HasilItemSinkron> hasil;
        try {
          hasil = await klien.KirimSinkron([
            for (final b in batch)
              ItemOutbox(jenis: b.Jenis, uuid: b.Uuid, data: (jsonDecode(b.Data) as Map<String, Object?>)),
          ]);
        } on GalatJaringan catch (galat) {
          await repositori.JadwalkanUlang(batch, _jam(), galat.pesan);
          return RingkasanSinkron(terkirim: terkirim, ditolak: ditolak, offline: true, tersambung: false);
        } on GalatApi catch (galat) {
          dijawabServer = true;
          if (galat.CekPerangkatDitolak()) {
            await perangkat.CabutLokal();
            return RingkasanSinkron(terkirim: terkirim, ditolak: ditolak, perangkatDicabut: true, tersambung: true);
          }
          // Batch ditolak utuh (bentuk permintaan salah): tandai semua agar antrean berikutnya tidak tertahan.
          for (final b in batch) {
            await repositori.TandaiPerluTindakan(b.Uuid, galat.kode, galat.pesan);
          }
          ditolak += batch.length;
          continue;
        }

        dijawabServer = true;
        final selesai = <String>[];
        for (final h in hasil) {
          if (h.status == StatusItemSinkron.Ditolak) {
            await repositori.TandaiPerluTindakan(h.uuid, h.kodeGalat, h.pesanGalat);
            ditolak++;
          } else {
            selesai.add(h.uuid);
          }
        }
        await repositori.HapusOutbox(selesai);
        terkirim += selesai.length;

        // Item yang tidak dijawab server (seharusnya tidak terjadi) dijadwalkan ulang agar tidak hilang.
        final dijawab = hasil.map((h) => h.uuid).toSet();
        final terlewat = batch.where((b) => !dijawab.contains(b.Uuid)).toList();
        if (terlewat.isNotEmpty) {
          await repositori.JadwalkanUlang(terlewat, _jam(), 'Server tidak menjawab item ini.');
          return RingkasanSinkron(terkirim: terkirim, ditolak: ditolak, tersambung: true);
        }
      }
    } finally {
      _berjalan = false;
    }
  }
}
