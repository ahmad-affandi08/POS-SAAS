import 'dart:convert';

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:test/test.dart';

class _TransportRekam implements TransportPrinter {
  final List<List<int>> terkirim = [];

  @override
  Future<void> Kirim(List<int> data) async => terkirim.add(List.of(data));
}

void main() {
  const keranjang = IsiLayarPelanggan(
    keadaan: KeadaanLayarPelanggan.Keranjang,
    namaToko: 'Kopi Senja',
    baris: [BarisLayarPelanggan(nama: 'Es Kopi Susu Aren Ukuran Besar', rincian: '2 × Rp 25.000', nilai: 'Rp 50.000')],
    total: 'Rp 50.000',
  );

  group('IsiLayarPelanggan.SusunVfd (v2.01)', () {
    test('keranjang: item terakhir + total, tepat 20 kolom, ASCII', () {
      final (atas, bawah) = keranjang.SusunVfd();
      expect(atas, 'Es Kopi Su Rp 50.000');
      expect(bawah, 'Total      Rp 50.000');
      expect(atas.length, 20);
      expect(bawah.length, 20);
    });

    test('siaga & selesai', () {
      expect(const IsiLayarPelanggan.Siaga('Kopi Senja · Solo').SusunVfd(), (
        'Selamat datang      ',
        'Kopi Senja - Solo   ',
      ));
      const selesai = IsiLayarPelanggan(
        keadaan: KeadaanLayarPelanggan.Selesai,
        namaToko: 'Kopi Senja',
        labelTotal: 'Kembali',
        total: 'Rp 5.000',
        pesan: 'Terima kasih',
      );
      expect(selesai.SusunVfd(), ('Terima kasih        ', 'Kembali     Rp 5.000'));
    });
  });

  test('LayarPelangganVfd mengirim perintah CD5220 dan tidak mengirim ulang isi yang sama', () async {
    final transport = _TransportRekam();
    final layar = LayarPelangganVfd(transport);
    await layar.Tampilkan(keranjang);
    await layar.Tampilkan(keranjang);
    expect(transport.terkirim, hasLength(1));
    expect(transport.terkirim.single, [
      0x1B, 0x40, 0x0C, //
      0x1B, 0x51, 0x41, ...ascii.encode('Es Kopi Su Rp 50.000'), 0x0D,
      0x1B, 0x51, 0x42, ...ascii.encode('Total      Rp 50.000'), 0x0D,
    ]);
    await layar.Tutup();
    expect(transport.terkirim.last, [0x1B, 0x40, 0x0C]);
  });

  test('KeJson untuk adaptor layar kedua', () {
    expect(keranjang.KeJson()['Keadaan'], 'Keranjang');
    expect((keranjang.KeJson()['Baris']! as List).single, {
      'Nama': 'Es Kopi Susu Aren Ukuran Besar',
      'Rincian': '2 × Rp 25.000',
      'Nilai': 'Rp 50.000',
    });
  });
}
