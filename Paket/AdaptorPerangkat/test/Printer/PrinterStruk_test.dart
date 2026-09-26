import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:test/test.dart';

class _PrinterSistem implements TransportDokumen {
  final List<(DokumenStruk, LebarKertas)> dokumen = [];
  final List<List<int>> bita = [];

  @override
  Future<void> CetakDokumen(DokumenStruk dokumen, LebarKertas lebar) async => this.dokumen.add((dokumen, lebar));

  @override
  Future<void> Kirim(List<int> data) async => bita.add(data);
}

/// PRD v1.97: printer sistem menerima dokumen utuh (bukan ESC/POS) dan tidak bisa membuka laci kas.
void main() {
  test('TransportDokumen menerima dokumen & lebar, tanpa byte ESC/POS', () async {
    final sistem = _PrinterSistem();
    const dokumen = DokumenStruk([BarisTeks('Kopi Senja')], bukaLaci: true);
    await PrinterStruk(sistem, LebarKertas.Mm80).Cetak(dokumen);
    expect(sistem.dokumen.single.$1, same(dokumen));
    expect(sistem.dokumen.single.$2, LebarKertas.Mm80);
    expect(sistem.bita, isEmpty);
  });

  test('buka laci lewat printer sistem ditolak dengan pesan', () async {
    final sistem = _PrinterSistem();
    await expectLater(
      PrinterStruk(sistem, LebarKertas.Mm58).BukaLaci(),
      throwsA(isA<GalatPrinter>().having((g) => g.pesan, 'pesan', contains('tidak bisa membuka laci'))),
    );
    expect(sistem.bita, isEmpty);
  });
}
