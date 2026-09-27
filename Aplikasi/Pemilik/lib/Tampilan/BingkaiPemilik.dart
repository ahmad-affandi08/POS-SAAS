import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../Aplikasi/Penyedia.dart';
import 'LayarBeranda.dart';
import 'LayarLaporan.dart';
import 'LayarPerangkat.dart';
import 'LayarPersetujuan.dart';
import 'LayarShift.dart';

/// Bingkai Aplikasi Owner: bilah atas (nama usaha, ganti usaha, keluar) dan navigasi bawah Beranda · Laporan ·
/// Persetujuan (lencana jumlah menunggu) · Shift · Perangkat (mode kepadatan Nyaman, §17.6). Antrean persetujuan
/// jarak jauh dimuat ulang tiap [penyediaSelangPantauPersetujuan] selama aplikasi terbuka (belum ada push).
class BingkaiPemilik extends ConsumerStatefulWidget {
  const BingkaiPemilik({super.key});

  @override
  ConsumerState<BingkaiPemilik> createState() => _BingkaiPemilikState();
}

class _BingkaiPemilikState extends ConsumerState<BingkaiPemilik> {
  var _indeks = 0;
  Timer? _pantau;

  static const int _indeksPersetujuan = 2;

  static const _tujuan = [
    (Icons.home_outlined, Icons.home, 'Beranda'),
    (Icons.bar_chart_outlined, Icons.bar_chart, 'Laporan'),
    (Icons.approval_outlined, Icons.approval, 'Persetujuan'),
    (Icons.schedule_outlined, Icons.schedule, 'Shift'),
    (Icons.point_of_sale_outlined, Icons.point_of_sale, 'Perangkat'),
  ];

  @override
  void initState() {
    super.initState();
    final selang = ref.read(penyediaSelangPantauPersetujuan);
    if (selang != null) {
      _pantau = Timer.periodic(selang, (_) => ref.invalidate(penyediaPersetujuan));
    }
  }

  @override
  void dispose() {
    _pantau?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final sesi = ref.watch(penyediaSesi);
    final notifier = ref.read(penyediaSesi.notifier);
    final menunggu = ref.watch(penyediaPersetujuan).value?.length ?? 0;
    return Scaffold(
      appBar: AppBar(
        title: Text(sesi.namaTenant ?? 'PAYOU Owner'),
        actions: [
          PopupMenuButton<String>(
            tooltip: 'Akun',
            icon: const Icon(Icons.account_circle_outlined),
            onSelected: (pilih) => pilih == 'ganti' ? notifier.GantiTenant() : unawaited(notifier.Keluar()),
            itemBuilder: (_) => [
              if (sesi.tenant.length > 1) const PopupMenuItem(value: 'ganti', child: Text('Ganti usaha')),
              const PopupMenuItem(value: 'keluar', child: Text('Keluar')),
            ],
          ),
        ],
      ),
      body: IndexedStack(
        index: _indeks,
        children: const [LayarBeranda(), LayarLaporan(), LayarPersetujuan(), LayarShift(), LayarPerangkat()],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _indeks,
        onDestinationSelected: (i) {
          if (i == _indeksPersetujuan) {
            ref.invalidate(penyediaPersetujuan);
          }
          setState(() => _indeks = i);
        },
        destinations: [
          for (final (i, (ikon, ikonAktif, label)) in _tujuan.indexed)
            NavigationDestination(
              icon: i == _indeksPersetujuan && menunggu > 0
                  ? Badge(label: Text('$menunggu'), child: Icon(ikon))
                  : Icon(ikon),
              selectedIcon: i == _indeksPersetujuan && menunggu > 0
                  ? Badge(label: Text('$menunggu'), child: Icon(ikonAktif))
                  : Icon(ikonAktif),
              label: label,
              tooltip: i == _indeksPersetujuan && menunggu > 0 ? '$label ($menunggu menunggu)' : label,
            ),
        ],
      ),
    );
  }
}
