import '../../Data/PesananMeja.dart';

/// Konteks pesanan meja yang sedang dibuka di layar Jual. Keranjang dalam mode ini hanya memuat baris **baru** (draf);
/// baris yang sudah tersimpan di pesanan dibaca dari basis data (lihat `penyediaKeranjangEfektif`).
class KonteksPesananMeja {
  const KonteksPesananMeja({
    required this.uuid,
    required this.nomor,
    required this.uuidMeja,
    required this.namaMeja,
    required this.label,
    this.baris = const [],
  });

  final String uuid;
  final String nomor;
  final String? uuidMeja;
  final String? namaMeja;
  final String? label;

  /// Baris yang sudah tersimpan di pesanan (terisi pada keranjang efektif).
  final List<BarisPesananMeja> baris;

  String AmbilJudul() => namaMeja ?? label ?? nomor;

  bool CekTersimpan(String uuidBaris) => baris.any((b) => b.uuid == uuidBaris);

  BarisPesananMeja? CariBaris(String uuidBaris) => baris.where((b) => b.uuid == uuidBaris).firstOrNull;

  static KonteksPesananMeja DariPesanan(PesananMeja p) => KonteksPesananMeja(
    uuid: p.uuid,
    nomor: p.nomor,
    uuidMeja: p.uuidMeja,
    namaMeja: p.namaMeja,
    label: p.label,
    baris: p.baris,
  );
}
