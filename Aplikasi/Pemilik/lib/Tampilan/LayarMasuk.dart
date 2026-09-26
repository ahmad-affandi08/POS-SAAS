import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Aplikasi/Penyedia.dart';

/// Masuk OWN-01: email + kata sandi akun back-office; bila 2FA aktif, lanjut ke kode dari aplikasi autentikator.
class LayarMasuk extends ConsumerStatefulWidget {
  const LayarMasuk({super.key});

  @override
  ConsumerState<LayarMasuk> createState() => _LayarMasukState();
}

class _LayarMasukState extends ConsumerState<LayarMasuk> {
  final _email = TextEditingController();
  final _sandi = TextEditingController();
  final _kode = TextEditingController();

  @override
  void dispose() {
    _email.dispose();
    _sandi.dispose();
    _kode.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final sesi = ref.watch(penyediaSesi);
    final notifier = ref.read(penyediaSesi.notifier);
    final teks = Theme.of(context).textTheme;
    final warna = TokenWarna.AmbilDari(context);
    final duaFaktor = sesi.tahap == TahapSesi.DuaFaktor;
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(TokenJarak.jarak24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: AutofillGroup(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Center(child: LogoMerek.lengkap()),
                    const SizedBox(height: TokenJarak.jarak24),
                    Text(duaFaktor ? 'Verifikasi dua langkah' : 'Masuk ke PAYOU Owner', style: teks.headlineSmall),
                    const SizedBox(height: TokenJarak.jarak8),
                    Text(
                      duaFaktor
                          ? 'Masukkan 6 angka dari aplikasi autentikator Anda, atau kode pemulihan.'
                          : 'Pakai email dan kata sandi akun back-office.',
                      style: teks.bodyMedium?.copyWith(color: warna.teksSekunder),
                    ),
                    const SizedBox(height: TokenJarak.jarak16),
                    if (duaFaktor)
                      TextField(
                        controller: _kode,
                        keyboardType: TextInputType.number,
                        autofillHints: const [AutofillHints.oneTimeCode],
                        decoration: const InputDecoration(labelText: 'Kode verifikasi'),
                        onSubmitted: (_) => unawaited(notifier.KonfirmasiDuaFaktor(_kode.text)),
                      )
                    else ...[
                      TextField(
                        controller: _email,
                        keyboardType: TextInputType.emailAddress,
                        autofillHints: const [AutofillHints.email],
                        decoration: const InputDecoration(labelText: 'Email'),
                      ),
                      const SizedBox(height: TokenJarak.jarak12),
                      TextField(
                        controller: _sandi,
                        obscureText: true,
                        autofillHints: const [AutofillHints.password],
                        decoration: const InputDecoration(labelText: 'Kata sandi'),
                        onSubmitted: (_) => unawaited(notifier.Masuk(_email.text, _sandi.text)),
                      ),
                    ],
                    if (sesi.pesan != null) ...[
                      const SizedBox(height: TokenJarak.jarak12),
                      Text(sesi.pesan!, style: teks.bodyMedium?.copyWith(color: warna.bahaya)),
                    ],
                    const SizedBox(height: TokenJarak.jarak16),
                    SizedBox(
                      height: TokenJarak.targetSentuh,
                      child: FilledButton(
                        onPressed: sesi.sibuk
                            ? null
                            : () => unawaited(
                                duaFaktor
                                    ? notifier.KonfirmasiDuaFaktor(_kode.text)
                                    : notifier.Masuk(_email.text, _sandi.text),
                              ),
                        child: Text(sesi.sibuk ? 'Memproses…' : (duaFaktor ? 'Verifikasi' : 'Masuk')),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
