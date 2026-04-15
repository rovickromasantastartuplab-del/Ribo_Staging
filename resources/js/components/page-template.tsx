import { Head, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { ReactNode } from 'react';
import { FloatingChatGpt } from '@/components/FloatingChatGpt';
import { cn } from '@/lib/utils';

export interface PageAction {
  label: string;
  icon?: ReactNode;
  variant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
  onClick?: () => void;
  className?: string;
}

export interface PageTemplateProps {
  title: string;
  description?: string;
  url?: string;
  actions?: PageAction[];
  children: ReactNode;
  noPadding?: boolean;
  /** When true, removes page-level p-4/md:p-6/lg:p-8 so full-bleed children (e.g. Conversations Hub) align with the main column edges. Title row keeps horizontal padding. */
  noOuterPadding?: boolean;
  /** When true, the entire page header row (title + actions) is hidden. */
  hideHeader?: boolean;
  breadcrumbs?: BreadcrumbItem[];
  className?: string;
}

export function PageTemplate({
  title,
  description,
  url,
  actions,
  children,
  noPadding = false,
  noOuterPadding = false,
  hideHeader = false,
  breadcrumbs,
  className
}: PageTemplateProps) {
  // Default breadcrumbs if none provided
  const pageBreadcrumbs: BreadcrumbItem[] = breadcrumbs || [
    {
      title,
      href: url,
    },
  ];

  return (
    <AppLayout breadcrumbs={pageBreadcrumbs}>
      <Head title={`${title} - ${(usePage().props as any).globalSettings?.titleText || 'Sales SaaS'}`} />

      <div
        className={cn(
          'flex w-full max-w-full flex-1 flex-col overflow-x-hidden',
          noOuterPadding ? 'gap-0 p-0' : 'gap-4 p-4 md:p-6 lg:p-8',
          className
        )}
      >
        {/* <div className="flex h-full flex-1 flex-col gap-4 p-4"> */}
        {/* Header with action buttons */}
        {!hideHeader && (
        <div
          className={cn(
            'flex items-center justify-between flex-wrap gap-y-2 print:hidden',
            noOuterPadding && 'px-4 md:px-6 lg:px-8 pt-4 pb-2'
          )}
        >
          <h1 className="text-xl font-semibold">{title}</h1>
          {actions && actions.length > 0 && (
            <div className="flex items-center gap-2 flex-wrap">
              {actions.map((action, index) => (
                <Button
                  key={index}
                  variant={action.variant || 'outline'}
                  size="sm"
                  onClick={action.onClick}
                  className={`cursor-pointer ${action.className || ''}`}
                >
                  {action.icon && <span className="mr-1">{action.icon}</span>}
                  {action.label}
                </Button>
              ))}
            </div>
          )}
        </div>
        )}

        {/* Content */}
        <div className={noPadding ? "" : "rounded-xl border p-4 md:p-6"}>
          {children}
        </div>
      </div>
      <FloatingChatGpt />
    </AppLayout>
  );
}
