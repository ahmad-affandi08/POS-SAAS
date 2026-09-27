import 'dart:convert';

import 'package:klien_api/KlienApi.dart';

import '../Perangkat/LayananUjiPerangkat.dart';
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
/// dijadwalkan ulang dengan mundur eksponensial.
///
/// Audit P0 F-01: setiap item membawa perangkat pembuatnya (`UuidPerangkatAsal`). Perangkat dicabut: selama masa
/// pemulihan server masih menerima outbox (jawaban `PerangkatDicabut`), jadi outbox dikosongkan dulu baru token & data
/// sensitif dihapus. Bila server sudah menolak (403), token dihapus; outbox tetap tersimpan dan dikirim atas nama
/// perangkat asal setelah perangkat ini diaktifkan ulang.
class LayananSinkron {
  LayananSinkron({
    required this.klien,
    required this.repositori,
    required this.perangkat,
    this.ujiPerangkat,
    DateTime Function()? jam,
  }) : _jam = jam ?? DateTime.now;

  static const int ukuranBatch = 50;

  final KlienPos klien;
  final RepositoriKasir repositori;
  final LayananPerangkat perangkat;

  /// v1.96: laporan Wizard Uji Perangkat yang tertunda ikut dikirim setelah outbox kosong.
  final LayananUjiPerangkat? ujiPerangkat;
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
    var dicabut = false;

    try {
      while (true) {
        final batch = await repositori.AmbilOutboxSiapKirim(ukuranBatch, _jam());
        if (batch.isEmpty) {
          if (dicabut) {
            await perangkat.CabutLokal();
            return RingkasanSinkron(terkirim: terkirim, ditolak: ditolak, perangkatDicabut: true, tersambung: true);
          }
          await ujiPerangkat?.KirimTertunda();
          return RingkasanSinkron(terkirim: terkirim, ditolak: ditolak, tersambung: dijawabServer ? true : null);
        }

        final List<HasilItemSinkron> hasil;
        try {
          final jawaban = await klien.KirimSinkron([
            for (final b in batch)
              ItemOutbox(
                jenis: b.Jenis,
                uuid: b.Uuid,
                data: (jsonDecode(b.Data) as Map<String, Object?>),
                uuidPerangkatAsal: b.UuidPerangkat,
              ),
          ]);
          hasil = jawaban.hasil;
          if (jawaban.perangkatDicabut && !dicabut) {
            // Masa pemulihan: kirim semua sisa sekarang, termasuk yang sedang menunggu jadwal ulang.
            dicabut = true;
            await repositori.SegerakanTertunda(_jam());
          }
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
          return RingkasanSinkron(terkirim: terkirim, ditolak: ditolak, tersambung: true, perangkatDicabut: dicabut);
        }
      }
    } finally {
      _berjalan = false;
    }
  }

  /// Audit P0 F-01: server menyatakan perangkat dicabut (misal saat unduh data awal). Kirim sisa outbox selama masa
  /// pemulihan, lalu hapus token & data sensitif. Offline = token dipertahankan agar sisa outbox bisa dikirim nanti
  /// (mengembalikan `false`); outbox tidak pernah dihapus.
  Future<bool> SelesaikanPencabutan() async {
    if (_berjalan) {
      // Sinkron lain sedang berjalan dan akan menghapus token sendiri setelah outbox kosong.
      return false;
    }
    await repositori.SegerakanTertunda(_jam());
    final hasil = await KirimTertunda();
    if (hasil.offline) {
      return false;
    }
    if (!hasil.perangkatDicabut) {
      await perangkat.CabutLokal();
    }
    return true;
  }
}
