import { describe, expect, it } from 'vitest';
import { cn } from '@/Komponen/Ui/utils';

// `cn` (Komponen/Ui/utils.ts) harus mengenal token tipografi & radius dari Gaya/Aplikasi.css.
describe('cn untuk komponen shadcn/ui', () => {
    it('mempertahankan ukuran teks token dan warna teks token bersamaan', () => {
        expect(cn('text-isi', 'text-teks-utama')).toBe('text-isi text-teks-utama');
        expect(cn('text-sm text-foreground', 'text-label')).toBe('text-foreground text-label');
    });

    it('kelas terakhir menang untuk grup yang sama', () => {
        expect(cn('text-teks-utama', 'text-bahaya')).toBe('text-bahaya');
        expect(cn('rounded-md', 'rounded-panel')).toBe('rounded-panel');
        expect(cn('bg-primary', false, undefined, 'bg-permukaan')).toBe('bg-permukaan');
    });
});
