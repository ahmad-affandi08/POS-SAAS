import 'package:flutter/material.dart';

import '../../Domain/Sesi/StafLokal.dart';

/// Tujuan area kerja di rel navigasi (PRD §17.2.7). Urutan enum = urutan tampil.
enum TujuanRuangKerja { Jual, Riwayat, Kas, Shift, StatusSinkron, Pengaturan }

/// Satu item rel navigasi. Item hanya tampil bila [modul] aktif (null = inti, selalu aktif) dan kasir punya [izin]
/// (null = semua kasir). Maksimal 8 item (§17.2.7).
@immutable
class ItemNavigasi {
  const ItemNavigasi({
    required this.tujuan,
    required this.label,
    required this.ikon,
    required this.ikonAktif,
    this.izin,
    this.modul,
  });

  final TujuanRuangKerja tujuan;
  final String label;
  final IconData ikon;
  final IconData ikonAktif;
  final String? izin;
  final String? modul;

  static const int batasItem = 8;

  /// Daftar lengkap item. Modul berikutnya ditambah di sini sesuai urutan §17.2.7 (setelah tujuannya ada di
  /// [TujuanRuangKerja]): Order tersimpan (open bill F-07 fase 2; pesanan tertahan fase 1 dibuka dari layar Jual), Meja
  /// (modul `Meja`), Pelanggan (modul `Pelanggan`).
  static const List<ItemNavigasi> semua = [
    ItemNavigasi(
      tujuan: TujuanRuangKerja.Jual,
      label: 'Jual',
      ikon: Icons.point_of_sale_outlined,
      ikonAktif: Icons.point_of_sale,
    ),
    ItemNavigasi(
      tujuan: TujuanRuangKerja.Riwayat,
      label: 'Riwayat',
      ikon: Icons.receipt_long_outlined,
      ikonAktif: Icons.receipt_long,
    ),
    ItemNavigasi(tujuan: TujuanRuangKerja.Kas, label: 'Kas', ikon: Icons.payments_outlined, ikonAktif: Icons.payments),
    ItemNavigasi(
      tujuan: TujuanRuangKerja.Shift,
      label: 'Shift',
      ikon: Icons.schedule_outlined,
      ikonAktif: Icons.schedule,
    ),
    ItemNavigasi(
      tujuan: TujuanRuangKerja.StatusSinkron,
      label: 'Sinkron',
      ikon: Icons.cloud_sync_outlined,
      ikonAktif: Icons.cloud_sync,
    ),
    ItemNavigasi(
      tujuan: TujuanRuangKerja.Pengaturan,
      label: 'Pengaturan',
      ikon: Icons.settings_outlined,
      ikonAktif: Icons.settings,
    ),
  ];

  /// Item yang tampil untuk [kasir] dengan [modulAktif] (kode modul langganan tenant).
  static List<ItemNavigasi> Saring(
    StafLokal kasir, {
    Set<String> modulAktif = const {},
    List<ItemNavigasi> daftar = semua,
  }) => daftar
      .where((i) => (i.modul == null || modulAktif.contains(i.modul)) && (i.izin == null || kasir.PunyaIzin(i.izin!)))
      .take(batasItem)
      .toList();
}
