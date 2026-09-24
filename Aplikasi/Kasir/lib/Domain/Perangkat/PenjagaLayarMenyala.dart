/// Menjaga layar tetap menyala selama shift terbuka (PRD §17.2.7). Abstraksi kecil agar kode tampilan tidak memanggil
/// paket platform langsung dan test bisa memakai tiruan. Kegagalan platform diabaikan (bukan hal kritis).
abstract class PenjagaLayarMenyala {
  Future<void> Aktifkan();

  Future<void> Nonaktifkan();
}
