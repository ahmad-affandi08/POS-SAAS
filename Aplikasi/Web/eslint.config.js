// ESLint untuk back-office & web publik (PRD §13.7, §23.2). File penjaga: perubahan wajib disetujui pemilik produk.
import js from '@eslint/js';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    {
        ignores: [
            'public/**',
            'vendor/**',
            'node_modules/**',
            'storage/**',
            'bootstrap/**',
            'resources/js/Komponen/Ui/**',
        ],
    },
    js.configs.recommended,
    ...tseslint.configs.strict,
    {
        files: ['resources/js/**/*.{ts,tsx}'],
        languageOptions: {
            globals: globals.browser,
            parserOptions: { projectService: true, tsconfigRootDir: import.meta.dirname },
        },
        plugins: { 'react-hooks': reactHooks },
        rules: {
            ...reactHooks.configs.recommended.rules,
            // D-05: function PascalCase Bahasa Indonesia; hook React wajib diawali "use" (camelCase).
            '@typescript-eslint/naming-convention': [
                'error',
                { selector: 'function', format: ['PascalCase'], filter: { regex: '^use[A-Z]', match: false } },
                { selector: 'function', format: ['camelCase'], filter: { regex: '^use[A-Z]', match: true } },
                {
                    selector: 'variable',
                    types: ['function'],
                    format: ['PascalCase'],
                    filter: { regex: '^use[A-Z]', match: false },
                },
                { selector: 'typeLike', format: ['PascalCase'] },
            ],
            // Uang tidak pernah diubah ke number (CLAUDE.md #7).
            'no-restricted-globals': ['error', { name: 'parseFloat', message: 'Uang/kuantitas tidak boleh float.' }],
            'no-restricted-properties': [
                'error',
                { object: 'Number', property: 'parseFloat', message: 'Uang/kuantitas tidak boleh float.' },
            ],
            'no-console': 'error',
        },
    },
);
