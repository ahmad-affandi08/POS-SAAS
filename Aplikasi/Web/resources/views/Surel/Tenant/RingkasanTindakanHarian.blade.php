Halo {!! $Nama !!},

Berikut yang perlu diperhatikan di {!! $NamaUsaha !!} per {!! $Tanggal !!}:
@foreach ($Butir as $b)

[{!! $b['Tingkat'] !!}] {!! $b['Judul'] !!} ({!! number_format($b['Jumlah'], 0, ',', '.') !!})
{!! $b['Keterangan'] !!}
{!! $b['Tautan'] !!}
@endforeach

Semua butir ada di Kotak Tindakan:
{!! $TautanKotak !!}

Anda menerima email ini karena berlangganan ringkasan pagi Kotak Tindakan. Untuk berhenti, matikan pilihan "Kirim ringkasan ke email saya setiap pagi" di halaman Kotak Tindakan.
