import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:kasir/Domain/Pin/PemverifikasiPinOffline.dart';
import 'package:klien_api/KlienApi.dart';

/// Vektor bersama PHP & Dart (`Spesifikasi/VektorUjiPin/VerifierPin.json`).
Map<String, Object?> BacaVektor() {
  final berkas = File('../../Spesifikasi/VektorUjiPin/VerifierPin.json');
  return jsonDecode(berkas.readAsStringSync()) as Map<String, Object?>;
}

void main() {
  final vektor = BacaVektor();
  final parameter = ParameterPin.DariJson(vektor['Parameter']! as Map<String, Object?>);
  final kunci = vektor['KunciPerangkat']! as String;
  final kasus = (vektor['Kasus']! as List<Object?>).cast<Map<String, Object?>>();
  const pemverifikasi = PemverifikasiPinOffline();

  PinTerbungkus Terbungkus(Map<String, Object?> k) =>
      PinTerbungkus(garam: k['Garam']! as String, nonce: k['Nonce']! as String, sandi: k['Sandi']! as String);

  for (final k in kasus) {
    test('vektor ${k['Nama']}: Argon2id & AES-256-GCM sama dengan PHP; PIN benar diterima', () async {
      final hash = await PemverifikasiPinOffline.HitungHash(
        k['Pin']! as String,
        base64Decode(k['Garam']! as String),
        parameter,
      );
      expect(base64Encode(hash), k['Hash']);
      expect(base64Encode(await PemverifikasiPinOffline.BukaVerifier(Terbungkus(k), kunci)), k['Hash']);
      expect(
        await pemverifikasi.Verifikasi(
          pin: k['Pin']! as String,
          terbungkus: Terbungkus(k),
          kunciPerangkatBase64: kunci,
          parameter: parameter,
        ),
        isTrue,
      );
    });
  }

  test('PIN salah, kunci perangkat lain, atau verifier dirusak ditolak', () async {
    final k = kasus.first;
    expect(
      await pemverifikasi.Verifikasi(
        pin: '999999',
        terbungkus: Terbungkus(k),
        kunciPerangkatBase64: kunci,
        parameter: parameter,
      ),
      isFalse,
    );
    expect(
      await pemverifikasi.Verifikasi(
        pin: k['Pin']! as String,
        terbungkus: Terbungkus(k),
        kunciPerangkatBase64: base64Encode(List<int>.filled(32, 7)),
        parameter: parameter,
      ),
      isFalse,
    );
    final sandi = base64Decode(k['Sandi']! as String)..[0] ^= 1;
    expect(
      await pemverifikasi.Verifikasi(
        pin: k['Pin']! as String,
        terbungkus: PinTerbungkus(
          garam: k['Garam']! as String,
          nonce: k['Nonce']! as String,
          sandi: base64Encode(sandi),
        ),
        kunciPerangkatBase64: kunci,
        parameter: parameter,
      ),
      isFalse,
    );
  });
}
