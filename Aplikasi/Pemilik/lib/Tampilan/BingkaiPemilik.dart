import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../Aplikasi/Penyedia.dart';
import 'LayarBeranda.dart';
import 'LayarLaporan.dart';
import 'LayarPerangkat.dart';
import 'LayarShift.dart';

/// Bingkai Aplikasi Owner: bilah atas (nama usaha, ganti usaha, keluar) dan navigasi bawah Beranda · Laporan · Shift ·
/// Perangkat (mode kepadatan Nyaman, §17.6).
class BingkaiPemilik extends ConsumerStatefulWidget {
  const BingkaiPemilik({super.key});

  @override
  ConsumerState<BingkaiPemilik> createState() => _BingkaiPemilikState();
}

class _BingkaiPemilikState extends ConsumerState<BingkaiPemilik> {
  var _indeks = 0;

  static const _tujuan = [
    (Icons.home_outlined, Icons.home, 'Beranda'),
    (Icons.bar_chart_outlined, Icons.bar_chart, 'Laporan'),
    (Icons.schedule_outlined, Icons.schedule, 'Shift'),
    (Icons.point_of_sale_outlined, Icons.point_of_sale, 'Perangkat'),
  ];

  @override
  Widget build(BuildContext context) {
    final sesi = ref.watch(penyediaSesi);
    final notifier = ref.read(penyediaSesi.notifier);
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
        children: const [LayarBeranda(), LayarLaporan(), LayarShift(), LayarPerangkat()],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _indeks,
        onDestinationSelected: (i) => setState(() => _indeks = i),
        destinations: [
          for (final (ikon, ikonAktif, label) in _tujuan)
            NavigationDestination(icon: Icon(ikon), selectedIcon: Icon(ikonAktif), label: label),
        ],
      ),
    );
  }
}
