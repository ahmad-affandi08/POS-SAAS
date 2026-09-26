import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import laravel from "laravel-vite-plugin";
import { defineConfig } from "vitest/config";

export default defineConfig({
    plugins: [
        // Plugin Laravel hanya untuk dev server & build; di Vitest ia menolak berjalan saat `CI=true`.
        ...(process.env.VITEST
            ? []
            : [
                  laravel({
                      input: ["resources/js/Aplikasi.tsx", "resources/js/Pengelola.tsx"],
                      refresh: true,
                  }),
              ]),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: { "@": "/resources/js" },
    },
    // Sama dengan bawaan plugin Laravel: aset selalu berkas (bukan data URI), juga di Vitest tanpa plugin itu.
    build: {
        assetsInlineLimit: 0,
    },
    server: {
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
    },
    test: {
        environment: "jsdom",
        include: ["resources/js/**/*Tes.{ts,tsx}"],
        setupFiles: ["resources/js/Pengujian/SiapkanLingkunganUji.ts"],
    },
});
