import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { IkonMerek, LogoMerek } from './LogoMerek';

describe('LogoMerek', () => {
    it('menyediakan varian warna dan putih untuk logo lengkap serta ikon', () => {
        render(
            <>
                <LogoMerek nama="PAYOU warna" />
                <LogoMerek nama="PAYOU putih" varian="putih" />
                <IkonMerek nama="Ikon PAYOU warna" />
                <IkonMerek nama="Ikon PAYOU putih" varian="putih" />
            </>,
        );

        expect(screen.getByRole('img', { name: 'PAYOU warna' }).getAttribute('src')).toContain('LogoHorizontal.png');
        expect(screen.getByRole('img', { name: 'PAYOU putih' }).getAttribute('src')).toContain(
            'LogoHorizontalPutih.png',
        );
        expect(screen.getByRole('img', { name: 'Ikon PAYOU warna' }).getAttribute('src')).toContain('IkonMerek.png');
        expect(screen.getByRole('img', { name: 'Ikon PAYOU putih' }).getAttribute('src')).toContain(
            'IkonMerekPutih.png',
        );
    });
});
