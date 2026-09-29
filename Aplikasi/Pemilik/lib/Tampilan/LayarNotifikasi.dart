import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';
import 'FormatTampilan.dart';
import 'KeadaanData.dart';

class LayarNotifikasi extends ConsumerWidget {
  const LayarNotifikasi({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) => RefreshIndicator(
    onRefresh: () => ref.refresh(penyediaNotifikasi.future),
    child: KeadaanData(
      nilai: ref.watch(penyediaNotifikasi),
      saatCobaLagi: () => ref.invalidate(penyediaNotifikasi),
      isi: (data) => ListView(
        padding: const EdgeInsets.all(TokenJarak.jarak16),
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          if (data.belumDibaca > 0)
            Align(
              alignment: Alignment.centerRight,
              child: TextButton.icon(
                onPressed: () => unawaited(_TandaiSemua(ref)),
                icon: const Icon(Icons.done_all),
                label: const Text('Tandai semua dibaca'),
              ),
            ),
          if (data.notifikasi.isEmpty) const Text('Belum ada notifikasi.'),
          for (final n in data.notifikasi)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: Icon(
                n.belumDibaca ? Icons.notifications_active : Icons.notifications_none,
                color: n.belumDibaca ? Theme.of(context).colorScheme.primary : null,
              ),
              title: Text(n.judul, style: n.belumDibaca ? const TextStyle(fontWeight: FontWeight.w700) : null),
              subtitle: Text([n.isi, if (n.dibuatPada != null) FormatTampilan.TanggalJam(n.dibuatPada!)].join('\n')),
              onTap: n.belumDibaca ? () => unawaited(_Tandai(ref, n.uuid)) : null,
            ),
        ],
      ),
    ),
  );

  Future<void> _Tandai(WidgetRef ref, String uuid) async {
    await ref.read(penyediaKlien).TandaiNotifikasiDibaca(uuid: [uuid]);
    ref.invalidate(penyediaNotifikasi);
  }

  Future<void> _TandaiSemua(WidgetRef ref) async {
    await ref.read(penyediaKlien).TandaiNotifikasiDibaca(semua: true);
    ref.invalidate(penyediaNotifikasi);
  }
}
