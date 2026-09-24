import 'dart:math';

import 'package:inti/Inti.dart';
import 'package:test/test.dart';

void main() {
  test('ULID 26 karakter valid, bagian waktu mengikuti jam, dan terurut menurut waktu', () {
    var sekarang = DateTime.utc(2026, 9, 24, 1, 15);
    final pembuat = PembuatUlid(acak: Random(7), jam: () => sekarang);
    final pertama = pembuat.Buat();
    sekarang = sekarang.add(const Duration(milliseconds: 1));
    final kedua = pembuat.Buat();

    expect(pertama, hasLength(26));
    expect(PembuatUlid.CekValid(pertama), isTrue);
    expect(PembuatUlid.CekValid(kedua), isTrue);
    expect(pertama.substring(0, 10), '01M38FNTH0');
    expect(pertama.compareTo(kedua), lessThan(0));
  });

  test('ribuan ULID pada milidetik yang sama tetap unik', () {
    final pembuat = PembuatUlid(jam: () => DateTime.utc(2026, 9, 24));
    final semua = {for (var indeks = 0; indeks < 5000; indeks++) pembuat.Buat()};
    expect(semua, hasLength(5000));
  });

  test('format yang salah ditolak', () {
    expect(PembuatUlid.CekValid('01K5WE0KC0ABCDEFGHJKMNPQRS'), isTrue);
    expect(PembuatUlid.CekValid('01k5we0kc0abcdefghjkmnpqrs'), isFalse);
    expect(PembuatUlid.CekValid('81K5WE0KC0ABCDEFGHJKMNPQRS'), isFalse);
    expect(PembuatUlid.CekValid('01K5WE0KC0ABCDEFGHJKMNPQRI'), isFalse);
  });
}
