Terima kasih telah berbelanja di {{ $NamaUsaha }}{{ $NamaOutlet !== null && $NamaOutlet !== $NamaUsaha ? ' ('.$NamaOutlet.')' : '' }}.

Nomor struk: {{ $Nomor }}
Waktu: {{ $Waktu }}
@if ($Dibatalkan)
Transaksi ini sudah dibatalkan.
@endif

@foreach ($Baris as $b)
{{ $b['Nama'] }} x {{ $b['Jumlah'] }}: {{ $b['Total'] }}
@endforeach

Total: {{ $Total }}

Lihat struk lengkap:
{{ $Tautan }}

Email ini dikirim atas permintaan Anda di kasir {{ $NamaUsaha }}. Jika Anda tidak merasa berbelanja, abaikan email ini.
