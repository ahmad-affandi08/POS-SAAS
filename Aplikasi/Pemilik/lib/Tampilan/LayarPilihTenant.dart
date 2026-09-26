import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';

/// Pilih usaha (tenant) bila akun terdaftar di lebih dari satu usaha (OWN-01).
class LayarPilihTenant extends ConsumerWidget {
  const LayarPilihTenant({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final sesi = ref.watch(penyediaSesi);
    final notifier = ref.read(penyediaSesi.notifier);
    final teks = Theme.of(context).textTheme;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Pilih usaha'),
        actions: [TextButton(onPressed: () => unawaited(notifier.Keluar()), child: const Text('Keluar'))],
      ),
      body: ListView(
        padding: const EdgeInsets.all(TokenJarak.jarak16),
        children: [
          if (sesi.namaPengguna.isNotEmpty) Text('Halo, ${sesi.namaPengguna}', style: teks.titleMedium),
          if (sesi.pesan != null) Text(sesi.pesan!, style: teks.bodyMedium),
          if (sesi.tenant.isEmpty && sesi.pesan == null) const Center(child: CircularProgressIndicator()),
          for (final t in sesi.tenant)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.storefront_outlined),
              title: Text(t.nama),
              subtitle: Text(t.pemilik ? 'Pemilik' : 'Anggota'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => unawaited(notifier.PilihTenant(t)),
            ),
        ],
      ),
    );
  }
}
