Halo {!! $Nama !!},

Terima kasih sudah mendaftar. Buka tautan berikut untuk memastikan alamat email ini benar milik Anda, lalu akun usaha Anda langsung bisa dipakai:

{!! $Tautan !!}

Tautan ini berlaku {!! $JamBerlaku !!} jam. Kalau sudah lewat, masuk ke akun Anda lalu minta tautan baru lewat tombol kirim ulang.

Anda menerima email ini karena alamat ini dipakai saat mendaftar. Jika Anda tidak merasa mendaftar, abaikan saja -- tanpa verifikasi akun tidak akan aktif.

--
{!! config('app.name') !!} -- kasir, stok, dan pembukuan dalam satu aplikasi.
Butuh bantuan? Balas email ini atau hubungi {!! config('mail.from.address') !!}.
{{--
    Badan ini `text/plain`, jadi `{{ }}` justru merusaknya: escaping HTML mengubah `&signature=` menjadi
    `&amp;signature=`, PHP lalu membaca parameternya sebagai `amp;signature` dan tautan verifikasi ditolak
    403 "Invalid signature". Nama juga ikut rusak ("Toko A & B" jadi "Toko A &amp; B").

    Karena itu **seluruh** echo di templat teks memakai `{!! !!}`, bukan hanya tautan: `'`, `"`, `<`, dan `>`
    pada nama usaha atau isi pesan sama-sama rusak kalau di-escape. Aman, karena badan ini tidak pernah
    dirender sebagai HTML. Dijaga `tests/Arsitektur/SurelTes.php`; templat HTML tetap wajib `{{ }}`.
--}}
