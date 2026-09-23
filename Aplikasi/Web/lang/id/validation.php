<?php

declare(strict_types=1);

/*
 * Pesan validasi Bahasa Indonesia (PRD §17.6 microcopy). Aturan yang belum ada di sini jatuh ke bahasa cadangan;
 * tambahkan pesannya bersama flow yang pertama memakai aturan tersebut.
 */
return [
    'array' => ':Attribute harus berupa daftar.',
    'distinct' => ':Attribute berisi nilai ganda.',
    'email' => ':Attribute harus berupa alamat email yang valid.',
    'exists' => ':Attribute yang dipilih tidak tersedia.',
    'max' => [
        'array' => ':Attribute maksimal :max item.',
        'string' => ':Attribute maksimal :max karakter.',
    ],
    'min' => [
        'array' => 'Pilih minimal :min :attribute.',
        'string' => ':Attribute minimal :min karakter.',
    ],
    'password' => [
        'letters' => ':Attribute harus berisi minimal satu huruf.',
        'mixed' => ':Attribute harus berisi huruf besar dan huruf kecil.',
        'numbers' => ':Attribute harus berisi minimal satu angka.',
        'symbols' => ':Attribute harus berisi minimal satu simbol.',
        'uncompromised' => ':Attribute ini pernah bocor di internet. Pilih kata sandi lain.',
    ],
    'required' => ':Attribute wajib diisi.',
    'same' => ':Attribute harus sama dengan :other.',
    'string' => ':Attribute harus berupa teks.',

    'attributes' => [
        'Alasan' => 'alasan',
        'Email' => 'email',
        'Kode' => 'kode',
        'KodePeran' => 'peran',
        'KodePeran.*' => 'peran',
        'KataSandi' => 'kata sandi',
        'KonfirmasiKataSandi' => 'konfirmasi kata sandi',
        'Nama' => 'nama',
    ],
];
