import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { Link } from '@inertiajs/react';
import { Fragment } from 'react';

export function Breadcrumbs({ items }: { items: Array<{ label: string; href?: string }> }) {
    return (
        <>
            {items && items.length > 0 && (
                <Breadcrumb>
                    <BreadcrumbList className="flex-nowrap items-center overflow-hidden">
                        {items.map((item, index) => {
                            const isLast = index === items.length - 1;
                            return (
                                <Fragment key={index}>
                                    <BreadcrumbItem className={!isLast ? "max-lg:hidden" : "truncate"}>
                                        {isLast ? (
                                            <BreadcrumbPage className="truncate">{item.label}</BreadcrumbPage>
                                        ) : (
                                            <BreadcrumbLink asChild>
                                                <Link href={item.href || '#'} className="truncate">{item.label}</Link>
                                            </BreadcrumbLink>
                                        )}
                                    </BreadcrumbItem>
                                    {!isLast && <BreadcrumbSeparator className="max-lg:hidden" />}
                                </Fragment>
                            );
                        })}
                    </BreadcrumbList>
                </Breadcrumb>
            )}
        </>
    );
}
