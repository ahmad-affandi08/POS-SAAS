Halo,

@if ($DibukaLagi)
Pelapor membuka lagi tiket {{ $Nomor }} ({{ $Judul }}) yang Anda tangani.
@else
Pelapor membalas tiket {{ $Nomor }} ({{ $Judul }}) yang Anda tangani.
@endif

Baca balasannya di Platform Pengelola:
{{ $Tautan }}
