import { LucideIcon } from 'lucide-react';

export interface SharedData {
    auth: {
        user: {
            id: number;
            name: string;
            display_name?: string;
            email: string;
            avatar?: string;
            avatar_url?: string;
            type?: string;
            [key: string]: any;
        } | null;
    };
}

export type User = NonNullable<SharedData['auth']['user']>;

export interface NavItem {
    title: string;
    href?: string;
    icon?: React.ReactNode;
    permission?: string;
    children?: NavItem[];
    target?: string;
    external?: boolean;
    defaultOpen?: boolean;
    badge?: {
        label: string;
        variant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost';
    };
}

export interface BreadcrumbItem {
    title: string;
    href?: string;
}

export interface PageAction {
    label: string;
    icon: React.ReactNode;
    variant: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
    onClick: () => void;
}