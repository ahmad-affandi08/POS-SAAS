<?php

declare(strict_types=1);

/*
 * F-10 pengiriman: disk privat foto bukti serah terima dari portal kurir, batas ukuran unggahan, dan sisi terpanjang
 * foto setelah diolah ulang (EXIF termasuk lokasi GPS dibuang).
 */
return [
    'DiskBukti' => 'local',
    'UkuranMaksimalBuktiKb' => 8192,
    'SisiMaksimalBuktiPx' => 1600,
];
