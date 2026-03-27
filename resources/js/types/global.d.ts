import type { route as routeFn } from 'ziggy-js';
import { PageProps as InertiaPageProps } from '@inertiajs/core';

declare global {
    const route: typeof routeFn;
}

declare module '@inertiajs/core' {
    interface PageProps extends InertiaPageProps {
        flash: {
            success?: string;
            error?: string;
            warning?: string;
            info?: string;
        };
        [key: string]: any;
    }
}
