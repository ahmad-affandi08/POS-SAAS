/**
 * Format tampilan Indonesia (PRD §17.6.7).
 *
 * Uang dari API selalu berupa string desimal ("1250000.00"). Fungsi ini TIDAK mengubahnya
 * ke number/float (CLAUDE.md #7): pemisah ribuan disisipkan langsung pada teks.
 */
export function FormatRupiah(nilai: string): string {
    const cocok = /^(-?)(\d+)(?:\.(\d{1,2}))?$/.exec(nilai.trim());

    if (!cocok) {
        throw new Error(`Nilai uang tidak valid: "${nilai}"`);
    }

    const [, tanda = '', bulat = '0', sen = ''] = cocok;
    const bulatBersih = bulat.replace(/^0+(?=\d)/, '');
    const denganTitik = bulatBersih.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const senDuaDigit = sen.padEnd(2, '0');
    const bagianSen = senDuaDigit === '00' || sen === '' ? '' : `,${senDuaDigit}`;
    const negatif = tanda === '-' && (bulatBersih !== '0' || bagianSen !== '');

    return `${negatif ? '−' : ''}Rp ${denganTitik}${bagianSen}`;
}
