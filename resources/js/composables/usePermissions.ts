import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

export function usePermissions() {
    const page = usePage<SharedData>();

    const can = (permission: string): boolean => {
        const granted = page.props.auth.permissions ?? [];

        return granted.includes('*') || granted.includes(permission);
    };

    const hasRole = (role: string): boolean => (page.props.auth.roles ?? []).includes(role);

    return { can, hasRole };
}
