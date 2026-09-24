import './CadanganApiPeramban';

import { router } from '@inertiajs/react';
import { ChevronDownIcon, LogOutIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { Avatar, AvatarFallback } from '@/Komponen/Ui/avatar';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Komponen/Ui/breadcrumb';
import { Button } from '@/Komponen/Ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Komponen/Ui/dropdown-menu';
import { Separator } from '@/Komponen/Ui/separator';
import { SidebarTrigger } from '@/Komponen/Ui/sidebar';
import { Toaster } from '@/Komponen/Ui/sonner';
import { cn } from '@/Komponen/Ui/utils';

/** Cookie bawaan SidebarProvider shadcn/ui; dibaca agar bilah samping tetap diciutkan setelah pindah halaman. */
export function BacaSidebarTerbuka(): boolean {
    if (typeof document === 'undefined') {
        return true;
    }

    return !document.cookie.split('; ').includes('sidebar_state=false');
}

/** Inisial untuk avatar menu akun ("Rina Wulandari" → "RW"). */
export function AmbilInisial(nama: string | undefined): string {
    const kata = (nama ?? '').trim().split(/\s+/).filter(Boolean);

    return kata
        .slice(0, 2)
        .map((bagian) => bagian.charAt(0).toUpperCase())
        .join('');
}

/** Toast (sonner) dengan label Bahasa Indonesia; dipasang sekali di setiap tata letak. */
export function PemberitahuanMelayang() {
    return (
        <Toaster
            position="top-right"
            closeButton
            containerAriaLabel="Notifikasi"
            toastOptions={{ closeButtonAriaLabel: 'Tutup' }}
        />
    );
}

type PropsKepala = {
    /** Rantai remah roti: induk (nama usaha/platform) lalu halaman ini. */
    induk: string;
    judul: string;
    /** Kepala gelap untuk Platform Pengelola (PRD §13.8). */
    gelap?: boolean;
    /** false bila pembungkusnya sudah sticky (misal bersama penanda lingkungan Pengelola). */
    lengket?: boolean;
    children?: ReactNode;
};

/** Bilah atas di samping bilah menu: tombol buka/tutup menu, remah roti, lalu menu akun. */
export function KepalaTataLetak({ induk, judul, gelap = false, lengket = true, children }: PropsKepala) {
    return (
        <header
            className={cn(
                'flex min-h-14 flex-wrap items-center gap-2 border-b px-4 py-2',
                lengket && 'sticky top-0 z-10',
                gelap ? 'border-teks-utama bg-teks-utama text-permukaan' : 'border-garis bg-permukaan',
            )}
        >
            <SidebarTrigger
                aria-label="Buka atau tutup menu samping"
                title="Buka atau tutup menu samping (Ctrl+B)"
                className={cn('-ml-1 size-9', gelap && 'hover:bg-permukaan/15 hover:text-permukaan')}
            />
            <Separator
                orientation="vertical"
                className={cn('mr-1 data-[orientation=vertical]:h-5', gelap ? 'bg-permukaan/40' : 'bg-garis')}
            />
            <Breadcrumb aria-label="Remah roti" className="min-w-0 flex-1">
                <BreadcrumbList className={cn('text-label', gelap ? 'text-permukaan/80' : 'text-teks-sekunder')}>
                    <BreadcrumbItem className="min-w-0">
                        <span className="truncate font-semibold">{induk}</span>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem className="min-w-0">
                        {/* shadcn memberi role="link" + aria-disabled; halaman saat ini cukup aria-current. */}
                        <BreadcrumbPage
                            role={undefined}
                            aria-disabled={undefined}
                            className={cn('truncate', gelap ? 'text-permukaan' : 'text-teks-utama')}
                        >
                            {judul}
                        </BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
            {children}
        </header>
    );
}

type PropsMenuAkun = {
    nama: string | undefined;
    email?: string | undefined;
    gelap?: boolean;
};

/** Menu akun (DropdownMenu): nama & email pengguna, lalu Keluar (POST /keluar). */
export function MenuAkun({ nama, email, gelap = false }: PropsMenuAkun) {
    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    aria-label={`Menu akun ${nama ?? ''}`.trim()}
                    className={cn(
                        'h-10 gap-2 px-2 text-label font-semibold',
                        gelap && 'text-permukaan hover:bg-permukaan/15 hover:text-permukaan',
                    )}
                >
                    <Avatar size="sm" aria-hidden="true">
                        <AvatarFallback className="bg-brand-lembut text-keterangan font-semibold text-brand">
                            {AmbilInisial(nama)}
                        </AvatarFallback>
                    </Avatar>
                    <span className="hidden max-w-48 truncate sm:inline">{nama}</span>
                    <ChevronDownIcon aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-60">
                <DropdownMenuLabel className="flex flex-col gap-0.5">
                    <span className="truncate text-label font-semibold text-teks-utama">{nama}</span>
                    {email ? <span className="truncate text-keterangan text-teks-sekunder">{email}</span> : null}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem className="text-label" onSelect={() => router.post('/keluar')}>
                    <LogOutIcon aria-hidden="true" />
                    Keluar
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
