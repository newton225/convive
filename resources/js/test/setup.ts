import { cleanup } from '@testing-library/react';
import { afterEach } from 'vite-plus/test';

// Chaque test repart d'un document vide : ce qu'un test a rendu ne doit pas se retrouver dans le
// suivant.
afterEach(() => {
    cleanup();
});
