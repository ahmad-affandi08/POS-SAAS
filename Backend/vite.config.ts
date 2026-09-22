import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import laravel from "laravel-vite-plugin";
import { defineConfig } from "vitest/config";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/js/Aplikasi.tsx"],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: { "@": "/resources/js" },
    },
    server: {
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
    },
    test: {
        environment: "jsdom",
        include: ["resources/js/**/*Tes.{ts,tsx}"],
    },
});
