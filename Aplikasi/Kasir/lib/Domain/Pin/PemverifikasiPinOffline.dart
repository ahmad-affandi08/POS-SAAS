import 'dart:convert';

import 'package:cryptography/cryptography.dart';
import 'package:klien_api/KlienApi.dart';

/// Verifikasi PIN kasir tanpa internet (PRD §25.2 no. 3, BR-06.3): buka verifier Argon2id yang dibungkus
/// AES-256-GCM dengan kunci perangkat (disimpan di secure storage), hitung Argon2id(PIN, garam) dengan parameter dari
/// server, lalu bandingkan waktu-konstan. Nilai wajib sama dengan PHP `VerifierPinOffline`
/// (`Spesifikasi/VektorUjiPin/`).
class PemverifikasiPinOffline {
  const PemverifikasiPinOffline();

  Future<bool> Verifikasi({
    required String pin,
    required PinTerbungkus terbungkus,
    required String kunciPerangkatBase64,
    required ParameterPin parameter,
  }) async {
    final List<int> harapan;
    try {
      harapan = await BukaVerifier(terbungkus, kunciPerangkatBase64);
    } on SecretBoxAuthenticationError {
      return false;
    } on FormatException {
      return false;
    }

    final hash = await HitungHash(pin, base64Decode(terbungkus.garam), parameter);
    return CekSama(hash, harapan);
  }

  static Future<List<int>> BukaVerifier(PinTerbungkus terbungkus, String kunciPerangkatBase64) {
    final sandi = base64Decode(terbungkus.sandi);
    if (sandi.length <= 16) {
      throw const FormatException('Verifier PIN rusak.');
    }
    final kotak = SecretBox(
      sandi.sublist(0, sandi.length - 16),
      nonce: base64Decode(terbungkus.nonce),
      mac: Mac(sandi.sublist(sandi.length - 16)),
    );
    return AesGcm.with256bits().decrypt(kotak, secretKey: SecretKey(base64Decode(kunciPerangkatBase64)));
  }

  static Future<List<int>> HitungHash(String pin, List<int> garam, ParameterPin parameter) async {
    final argon = Argon2id(
      parallelism: parameter.paralelisme,
      memory: parameter.memoriKiB,
      iterations: parameter.iterasi,
      hashLength: parameter.panjang,
    );
    final kunci = await argon.deriveKey(secretKey: SecretKey(utf8.encode(pin)), nonce: garam);
    return kunci.extractBytes();
  }

  /// Perbandingan waktu-konstan agar lama proses tidak membocorkan berapa byte yang cocok.
  static bool CekSama(List<int> a, List<int> b) {
    if (a.length != b.length) {
      return false;
    }
    var beda = 0;
    for (var indeks = 0; indeks < a.length; indeks++) {
      beda |= a[indeks] ^ b[indeks];
    }
    return beda == 0;
  }
}
