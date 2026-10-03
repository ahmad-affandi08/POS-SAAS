import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { describe, expect, it } from 'vitest';

/*
 * F-18 bagian 4 (D-37): model wajah dilayani dari `public/model-wajah` (server sendiri, tanpa CDN pihak ketiga).
 * Berkasnya harus identik dengan model di paket `@vladmandic/human` yang terpasang; setelah memperbarui paket,
 * salin ulang: `for m in blazeface facemesh faceres antispoof liveness; do cp node_modules/@vladmandic/human/models/$m.* public/model-wajah/; done`.
 */
const MODEL = ['blazeface', 'facemesh', 'faceres', 'antispoof', 'liveness'];

function Hash(path: string): string {
    return createHash('sha256')
        .update(readFileSync(resolve(process.cwd(), path)))
        .digest('hex');
}

describe('Model wajah absensi web', () => {
    it.each(MODEL.flatMap((nama) => [`${nama}.json`, `${nama}.bin`]))(
        '%s sama dengan versi paket terpasang',
        (berkas) => {
            expect(Hash(`public/model-wajah/${berkas}`)).toBe(Hash(`node_modules/@vladmandic/human/models/${berkas}`));
        },
    );
});
