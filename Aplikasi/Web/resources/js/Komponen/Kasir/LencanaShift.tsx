import { Badge } from '@/Komponen/Ui/badge';
import type { StatusShift } from '@/Tipe/Kasir';

const varian: Record<StatusShift, 'default' | 'secondary' | 'outline'> = {
    Terbuka: 'default',
    DibukaUlang: 'default',
    Menutup: 'secondary',
    Tertutup: 'outline',
};

/** Lencana status shift, ditambah penanda "Perlu ditinjau" untuk shift yang melanggar BR-06.1 saat offline. */
export default function LencanaShift({
    status,
    label,
    perluTinjauan,
    bersama,
}: {
    status: StatusShift;
    label: string;
    perluTinjauan: boolean;
    bersama: boolean;
}) {
    return (
        <span className="flex flex-wrap gap-1">
            <Badge variant={varian[status]}>{label}</Badge>
            {bersama ? <Badge variant="outline">Shift bersama</Badge> : null}
            {perluTinjauan ? <Badge variant="destructive">Perlu ditinjau</Badge> : null}
        </span>
    );
}
