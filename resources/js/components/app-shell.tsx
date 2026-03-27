import { SidebarProvider } from '@/components/ui/sidebar';
import { useLayout } from '@/contexts/LayoutContext';
import { FloatingChatGpt } from '@/components/FloatingChatGpt';
import CookieConsentBanner from '@/components/CookieConsentBanner';
import { CustomToast } from '@/components/custom-toast';
import { ChatWidgetToggle } from '@/components/ChatWidgetToggle';
import { cn } from '@/lib/utils';
import { useState } from 'react';

interface AppShellProps {
    children: React.ReactNode;
    variant?: 'header' | 'sidebar';
}

export function AppShell({ children, variant = 'header' }: AppShellProps) {
    const [isOpen, setIsOpen] = useState(() => (typeof window !== 'undefined' ? localStorage.getItem('sidebar') !== 'false' : true));

    const handleSidebarChange = (open: boolean) => {
        setIsOpen(open);

        if (typeof window !== 'undefined') {
            localStorage.setItem('sidebar', String(open));
        }
    };

    if (variant === 'header') {
        return (
            <div className="flex min-h-screen w-full max-w-full flex-col overflow-x-hidden">
                {children}
                <FloatingChatGpt />
                <CookieConsentBanner />
                <ChatWidgetToggle />
                <CustomToast />
            </div>
        );
    }

    const { position } = useLayout();

    return (
        <SidebarProvider defaultOpen={isOpen} open={isOpen} onOpenChange={handleSidebarChange}>
            <div className={cn('flex w-full max-w-full min-w-0 overflow-hidden', position === 'right' ? 'flex-row-reverse' : 'flex-row')}>
                {children}
                <FloatingChatGpt />
                <CookieConsentBanner />
                <ChatWidgetToggle />
                <CustomToast />
            </div>
        </SidebarProvider>
    );
}
