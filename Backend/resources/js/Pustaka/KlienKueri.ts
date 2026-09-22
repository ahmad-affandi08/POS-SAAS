import { QueryClient } from '@tanstack/react-query';

/**
 * Klien TanStack Query untuk back-office & web publik (PRD §17.4.2).
 * Master data dianggap segar 30 detik; data transaksi sebaiknya menimpa `staleTime: 0` per kueri.
 */
export function BuatKlienKueri(): QueryClient {
    return new QueryClient({
        defaultOptions: {
            queries: {
                staleTime: 30_000,
                retry: 2,
                refetchOnWindowFocus: false,
            },
        },
    });
}
