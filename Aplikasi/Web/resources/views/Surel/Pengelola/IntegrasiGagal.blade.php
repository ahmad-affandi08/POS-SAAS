Halo,

Tes koneksi berkala di lingkungan {{ $Lingkungan }} gagal untuk integrasi berikut:

@foreach ($Gagal as $baris)
- {{ $baris['Label'] }}: {{ $baris['Pesan'] }}
@endforeach

Periksa di Platform Pengelola > Integrasi, perbaiki kredensial atau layanan penyedia, lalu uji ulang.
Email ini dikirim sekali saat status berubah menjadi gagal.
