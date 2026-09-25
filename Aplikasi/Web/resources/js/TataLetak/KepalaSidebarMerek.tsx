import { IkonMerek, LogoMerek } from '@/Komponen/Merek/LogoMerek';
import { SidebarHeader } from '@/Komponen/Ui/sidebar';

type PropsKepalaSidebarMerek = { nama: string };

/**
 * Blok merek sidebar: logo lengkap saat terbuka dan ikon P saat diciutkan.
 * Gradasinya sengaja terbatas pada kepala sidebar sesuai D-15.
 */
export default function KepalaSidebarMerek({ nama }: PropsKepalaSidebarMerek) {
    return (
        <SidebarHeader className="min-h-16 shrink-0 justify-center gap-0 overflow-hidden border-0 border-b border-sidebar-border bg-linear-to-br from-brand-gelap to-brand p-0">
            <div className="flex min-h-16 items-center justify-center px-4 group-data-[collapsible=icon]:px-2">
                <LogoMerek
                    nama={nama}
                    varian="putih"
                    className="h-auto w-full max-w-40 group-data-[collapsible=icon]:hidden"
                />
                <IkonMerek nama={nama} varian="putih" className="hidden size-8 group-data-[collapsible=icon]:block" />
            </div>
        </SidebarHeader>
    );
}
