<?php

declare(strict_types=1);

/*
 * Setup organisasi tenant (F-02). Kunci PascalCase (D-05).
 */
return [
    // F-02 langkah 3: masa berlaku tautan undangan anggota.
    'JamBerlakuUndangan' => 72,

    // F-02b: kode aktivasi perangkat & PIN kasir (§20.2: kunci 5 menit setelah 5 kali gagal).
    'MenitBerlakuKodeAktivasi' => 15,
    'PercobaanPinMaksimal' => 5,
    'MenitKunciPin' => 5,
];
