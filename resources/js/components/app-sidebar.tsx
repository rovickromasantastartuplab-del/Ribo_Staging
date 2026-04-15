import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { cn } from '@/lib/utils';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useLayout } from '@/contexts/LayoutContext';
import { useSidebarSettings } from '@/contexts/SidebarContext';
import { useBrand } from '@/contexts/BrandContext';
import { type NavItem } from '@/types';
import { Link, usePage, router } from '@inertiajs/react';
import {
    Folder, LayoutGrid, ShoppingBag, Users, FileIcon, Settings, FileText, Briefcase, Calendar, CreditCard, Ticket, Gift, CalendarDays, Image, Building2, Phone, TrendingUp, Package, Megaphone, DollarSign, Palette, Mail, CheckCircle2,
    Heart, MessageSquare
} from 'lucide-react';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import AppLogo from './app-logo';
import { useEffect, useState, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { hasPermission } from '@/utils/authorization';
import { toast } from '@/components/custom-toast';


export function AppSidebar() {
    const { t, i18n } = useTranslation();
    const { auth } = usePage().props as any;
    const userRole = auth.user?.type || auth.user?.role;
    const permissions = auth?.permissions || [];
    const companyFeatures = auth?.company_features || [];
    const disabledModules: string[] = auth?.disabled_modules || [];

    // Get current direction
    const isRtl = document.documentElement.dir === 'rtl';

    // Business switch handler removed

    const getSuperAdminNavItems = (): NavItem[] => [
        {
            title: t('Dashboard'),
            href: route('dashboard'),
            icon: LayoutGrid,
        },

        {
            title: t('Companies'),
            href: route('companies.index'),
            icon: Briefcase,
        },
        {
            title: t('Wedding Suppliers'),
            icon: Users,
            children: [
                {
                    title: t('Suppliers'),
                    href: route('wedding-suppliers.index'),
                },
            ]
        },
        {
            title: t('Media Library'),
            href: route('media-library'),
            icon: Image,
        },


        {
            title: t('Billing & Subscriptions'),
            icon: CreditCard,
            children: [
                {
                    title: t('Available Plans'),
                    href: route('plans.index')
                },
                {
                    title: t('Subscription Requests'),
                    href: route('plan-requests.index')
                },
                {
                    title: t('Subscription History'),
                    href: route('plan-orders.index')
                }
            ]
        },
        {
            title: t('Coupons'),
            href: route('coupons.index'),
            icon: Settings,
        },

        {
            title: t('Currencies'),
            href: route('currencies.index'),
            icon: DollarSign,
        },
        {
            title: t('Referral Program'),
            href: route('referral.index'),
            icon: Gift,
        },
        {
            title: t('Landing Page'),
            icon: Palette,
            children: [
                {
                    title: t('Landing Page'),
                    href: route('landing-page')
                },
                {
                    title: t('Custom Pages'),
                    href: route('landing-page.custom-pages.index')
                },
                {
                    title: t('Contact Messages'),
                    href: route('contact-messages.index')
                },
                {
                    title: t('Newsletters'),
                    href: route('newsletters.index')
                },
            ]
        },
        {
            title: t('Email Templates'),
            href: route('email-templates.index'),
            icon: Mail,
        },
        {
            title: t('Settings'),
            href: route('settings'),
            icon: Settings,
        },

    ];

    const getCompanyNavItems = (): NavItem[] => {
        const items: NavItem[] = [];

        // 1. Dashboard
        if (hasPermission(permissions, 'manage-dashboard')) {
            items.push({
                title: t('Dashboard'),
                href: route('dashboard'),
                icon: LayoutGrid,
            });
        }

        // 2. Staff
        const staffChildren = [];
        if (hasPermission(permissions, 'manage-users')) {
            staffChildren.push({
                title: t('Users'),
                href: route('users.index')
            });
        }
        if (hasPermission(permissions, 'manage-roles')) {
            staffChildren.push({
                title: t('Roles'),
                href: route('roles.index')
            });
        }
        if (staffChildren.length > 0) {
            items.push({
                title: t('Staff'),
                icon: Users,
                children: staffChildren
            });
        }

        // 3. Lead Management
        const leadChildren = [];
        if (!disabledModules.includes('leads')) {
            if (hasPermission(permissions, 'manage-lead-statuses')) {
                leadChildren.push({
                    title: t('Lead Statuses'),
                    href: route('lead-statuses.index')
                });
            }
            if (hasPermission(permissions, 'manage-lead-sources')) {
                leadChildren.push({
                    title: t('Lead Sources'),
                    href: route('lead-sources.index')
                });
            }
            if (hasPermission(permissions, 'manage-leads')) {
                leadChildren.push({
                    title: t('Leads'),
                    href: route('leads.index')
                });
            }
            if (leadChildren.length > 0) {
                items.push({
                    title: t('Lead Management'),
                    icon: Users,
                    children: leadChildren
                });
            }
        } // end !disabledModules.includes('leads')

        // 4. Opportunity Management
        const opportunityChildren = [];
        if (!disabledModules.includes('opportunities')) {
            if (hasPermission(permissions, 'manage-opportunity-stages')) {
                opportunityChildren.push({
                    title: t('Opportunity Stages'),
                    href: route('opportunity-stages.index')
                });
            }
            if (hasPermission(permissions, 'manage-opportunity-sources')) {
                opportunityChildren.push({
                    title: t('Opportunity Sources'),
                    href: route('opportunity-sources.index')
                });
            }
            if (hasPermission(permissions, 'manage-opportunities')) {
                opportunityChildren.push({
                    title: t('Opportunities'),
                    href: route('opportunities.index')
                });
            }
            if (opportunityChildren.length > 0) {
                items.push({
                    title: t('Opportunity Management'),
                    icon: Briefcase,
                    children: opportunityChildren
                });
            }
        } // end !disabledModules.includes('opportunities')

        // 5. Account Management
        const accountChildren = [];
        if (!disabledModules.includes('accounts')) {
            if (hasPermission(permissions, 'manage-account-types')) {
                accountChildren.push({
                    title: t('Account Types'),
                    href: route('account-types.index')
                });
            }
            if (hasPermission(permissions, 'manage-account-industries')) {
                accountChildren.push({
                    title: t('Account Industries'),
                    href: route('account-industries.index')
                });
            }
            if (hasPermission(permissions, 'manage-accounts')) {
                accountChildren.push({
                    title: t('Accounts'),
                    href: route('accounts.index')
                });
            }
            if (accountChildren.length > 0) {
                items.push({
                    title: t('Account Management'),
                    icon: Building2,
                    children: accountChildren
                });
            }
        } // end !disabledModules.includes('accounts')

        // 6. Contacts
        if (!disabledModules.includes('contacts') && hasPermission(permissions, 'manage-contacts')) {
            items.push({
                title: t('Contacts'),
                href: route('contacts.index'),
                icon: Users,
            });
        }

        // Conversations Group
        const chatChildren = [];
        if (!disabledModules.includes('conversations') && hasPermission(permissions, 'view-conversations')) {
            chatChildren.push({
                title: t('Chat'),
                href: route('conversations.index'),
                badge: { label: t('New') }
            });
        }
        if (!disabledModules.includes('calendar') && (hasPermission(permissions, 'manage-meetings') || hasPermission(permissions, 'manage-calls') || hasPermission(permissions, 'manage-project-tasks'))) {
            chatChildren.push({
                title: t('Calendar'),
                href: route('calendar.index')
            });
        }
        if (!disabledModules.includes('meetings') && hasPermission(permissions, 'manage-meetings')) {
            chatChildren.push({
                title: t('Meetings'),
                href: route('meetings.index')
            });
        }
        if (!disabledModules.includes('calls') && hasPermission(permissions, 'manage-calls')) {
            chatChildren.push({
                title: t('Calls'),
                href: route('calls.index')
            });
        }

        if (chatChildren.length > 0) {
            items.push({
                title: t('Conversations'),
                icon: MessageSquare,
                children: chatChildren
            });
        }

        // Wedding Suppliers (Feature Flagged)
        if (companyFeatures && companyFeatures.includes('wedding_suppliers_module')) {
            items.push({
                title: t('Wedding Suppliers'),
                icon: Users,
                children: [
                    {
                        title: t('Suppliers'),
                        href: route('wedding-suppliers.index'),
                    }
                ]
            });
        }

        // Product Management
        const productManagementChildren = [];
        if (!disabledModules.includes('products') && hasPermission(permissions, 'manage-products')) {
            productManagementChildren.push({
                title: t('Products'),
                href: route('products.index')
            });
        }
        if (!disabledModules.includes('categories') && hasPermission(permissions, 'manage-categories')) {
            productManagementChildren.push({
                title: t('Categories'),
                href: route('categories.index')
            });
        }
        if (!disabledModules.includes('brands') && hasPermission(permissions, 'manage-brands')) {
            productManagementChildren.push({
                title: t('Brands'),
                href: route('brands.index')
            });
        }
        if (!disabledModules.includes('taxes') && hasPermission(permissions, 'manage-taxes')) {
            productManagementChildren.push({
                title: t('Taxes'),
                href: route('taxes.index')
            });
        }

        if (productManagementChildren.length > 0) {
            items.push({
                title: t('Product Management'),
                icon: Package,
                children: productManagementChildren
            });
        }

        // Order Management
        const orderManagementChildren = [];
        if (!disabledModules.includes('quotes') && hasPermission(permissions, 'manage-quotes')) {
            orderManagementChildren.push({
                title: t('Quotes'),
                href: route('quotes.index')
            });
        }
        if (!disabledModules.includes('sales_orders') && hasPermission(permissions, 'manage-sales-orders')) {
            orderManagementChildren.push({
                title: t('Sales Orders'),
                href: route('sales-orders.index')
            });
        }
        if (!disabledModules.includes('invoices') && hasPermission(permissions, 'manage-invoices')) {
            orderManagementChildren.push({
                title: t('Invoices'),
                href: route('invoices.index')
            });
        }

        if (orderManagementChildren.length > 0) {
            items.push({
                title: t('Order Management'),
                icon: FileText,
                children: orderManagementChildren
            });
        }

        // E-commerce
        const ecommerceChildren = [];
        if (!disabledModules.includes('delivery_orders') && hasPermission(permissions, 'manage-delivery-orders')) {
            ecommerceChildren.push({
                title: t('Delivery Orders'),
                href: route('delivery-orders.index')
            });
        }
        if (!disabledModules.includes('return_orders') && hasPermission(permissions, 'view-return-orders')) {
            ecommerceChildren.push({
                title: t('Return Orders'),
                href: route('return-orders.index')
            });
        }
        if (!disabledModules.includes('receipt_orders') && hasPermission(permissions, 'manage-receipt-orders')) {
            ecommerceChildren.push({
                title: t('Receipt Orders'),
                href: route('receipt-orders.index')
            });
        }
        if (!disabledModules.includes('purchase_orders') && hasPermission(permissions, 'manage-purchase-orders')) {
            ecommerceChildren.push({
                title: t('Purchase Orders'),
                href: route('purchase-orders.index')
            });
        }
        if (!disabledModules.includes('shipping_provider_types') && hasPermission(permissions, 'manage-shipping-provider-types')) {
            ecommerceChildren.push({
                title: t('Shipping Provider Types'),
                href: route('shipping-provider-types.index')
            });
        }

        if (ecommerceChildren.length > 0) {
            items.push({
                title: t('E-commerce'),
                icon: ShoppingBag,
                children: ecommerceChildren
            });
        }

        // 17. Document Management
        const documentChildren = [];
        if (!disabledModules.includes('documents')) {
            if (hasPermission(permissions, 'manage-document-folders')) {
                documentChildren.push({
                    title: t('Folders'),
                    href: route('document-folders.index')
                });
            }
            if (hasPermission(permissions, 'manage-document-types')) {
                documentChildren.push({
                    title: t('Types'),
                    href: route('document-types.index')
                });
            }
            if (hasPermission(permissions, 'manage-documents')) {
                documentChildren.push({
                    title: t('Documents'),
                    href: route('documents.index')
                });
            }
            if (documentChildren.length > 0) {
                items.push({
                    title: t('Document Management'),
                    icon: Folder,
                    children: documentChildren
                });
            }
        } // end !disabledModules.includes('documents')

        // 18. Campaign Management
        const campaignChildren = [];
        if (!disabledModules.includes('campaigns')) {
            if (hasPermission(permissions, 'manage-campaign-types')) {
                campaignChildren.push({
                    title: t('Campaign Types'),
                    href: route('campaign-types.index')
                });
            }
            if (hasPermission(permissions, 'manage-target-lists')) {
                campaignChildren.push({
                    title: t('Target Lists'),
                    href: route('target-lists.index')
                });
            }
            if (hasPermission(permissions, 'manage-campaigns')) {
                campaignChildren.push({
                    title: t('Campaigns'),
                    href: route('campaigns.index')
                });
            }
            if (campaignChildren.length > 0) {
                items.push({
                    title: t('Campaign Management'),
                    icon: Megaphone,
                    children: campaignChildren
                });
            }
        } // end !disabledModules.includes('campaigns')

        // 19. Project Management
        const projectChildren = [];
        if (!disabledModules.includes('projects')) {
            if (hasPermission(permissions, 'manage-task-statuses')) {
                projectChildren.push({
                    title: t('Task Statuses'),
                    href: route('task-statuses.index')
                });
            }
            if (hasPermission(permissions, 'manage-project-tasks')) {
                projectChildren.push({
                    title: t('Project Tasks'),
                    href: route('project-tasks.index')
                });
            }
            if (hasPermission(permissions, 'manage-projects')) {
                projectChildren.push({
                    title: t('Projects'),
                    href: route('projects.index')
                });
            }
            if (projectChildren.length > 0) {
                items.push({
                    title: t('Project Management'),
                    icon: Briefcase,
                    children: projectChildren
                });
            }
        } // end !disabledModules.includes('projects')



        // 21. Cases
        if (!disabledModules.includes('cases') && hasPermission(permissions, 'manage-cases')) {
            items.push({
                title: t('Cases'),
                href: route('cases.index'),
                icon: FileText,
            });
        }



        // 24. Reports
        if (!disabledModules.includes('reports') && hasPermission(permissions, 'manage-reports')) {
            items.push({
                title: t('Reports'),
                icon: TrendingUp,
                children: [
                    {
                        title: t('Lead Reports'),
                        href: route('reports.leads')
                    },
                    {
                        title: t('Sales Reports'),
                        href: route('reports.sales')
                    },
                    {
                        title: t('Product Reports'),
                        href: route('reports.product-reports')
                    },
                    {
                        title: t('Contact Reports'),
                        href: route('reports.customers')
                    },
                    {
                        title: t('Project Reports'),
                        href: route('reports.projects')
                    }
                ]
            });
        }





        // 26. Plans
        const planChildren = [];
        if (hasPermission(permissions, 'manage-plans')) {
            planChildren.push({
                title: t('Available Plans'),
                href: route('plans.index')
            });
        }
        if (hasPermission(permissions, 'manage-plan-requests')) {
            planChildren.push({
                title: t('Subscription Requests'),
                href: route('plan-requests.index')
            });
        }
        if (hasPermission(permissions, 'manage-plan-orders')) {
            planChildren.push({
                title: t('Subscription History'),
                href: route('plan-orders.index')
            });
        }
        if (planChildren.length > 0) {
            items.push({
                title: t('Billing & Subscriptions'),
                icon: CreditCard,
                children: planChildren
            });
        }

        // 27. Referral Program
        if (!disabledModules.includes('referral') && hasPermission(permissions, 'manage-referral')) {
            items.push({
                title: t('Referral Program'),
                href: route('referral.index'),
                icon: Gift,
            });
        }

        // 28. Media Library
        if (!disabledModules.includes('media_library') && hasPermission(permissions, 'manage-media')) {
            items.push({
                title: t('Media Library'),
                href: route('media-library'),
                icon: Image,
            });
        }

        // System Configuration
        const configChildren = [];
        if (!disabledModules.includes('notification_templates') && hasPermission(permissions, 'manage-notification-templates')) {
            configChildren.push({
                title: t('Notification Templates'),
                href: route('notification-templates.index')
            });
        }
        if (hasPermission(permissions, 'manage-settings')) {
            configChildren.push({
                title: t('Settings'),
                href: route('settings')
            });
        }

        if (configChildren.length > 0) {
            items.push({
                title: t('System Configuration'),
                icon: Settings,
                children: configChildren
            });
        }




        return items;
    };

    const mainNavItems = userRole === 'superadmin' ? getSuperAdminNavItems() : getCompanyNavItems();

    const { position, effectivePosition } = useLayout();
    const { variant, collapsible, style } = useSidebarSettings();
    const { logoLight, logoDark, favicon, updateBrandSettings } = useBrand();
    const [sidebarStyle, setSidebarStyle] = useState({});

    useEffect(() => {

        // Apply styles based on sidebar style
        if (style === 'colored') {
            setSidebarStyle({ backgroundColor: 'var(--primary)', color: 'white' });
        } else if (style === 'gradient') {
            setSidebarStyle({
                background: 'linear-gradient(to bottom, var(--primary), color-mix(in srgb, var(--primary), transparent 20%))',
                color: 'white'
            });
        } else {
            setSidebarStyle({});
        }
    }, [style]);

    const filteredNavItems = mainNavItems;

    // Get the first available menu item's href for logo link
    const getFirstAvailableHref = () => {
        if (filteredNavItems.length === 0) return route('dashboard');

        const firstItem = filteredNavItems[0];
        if (firstItem.href) {
            return firstItem.href;
        } else if (firstItem.children && firstItem.children.length > 0) {
            return firstItem.children[0].href || route('dashboard');
        }
        return route('dashboard');
    };

    return (
        <Sidebar
            side={effectivePosition}
            collapsible={collapsible}
            variant={variant}
            className={cn('hidden lg:block', style !== 'plain' ? 'sidebar-custom-style' : '')}
        >
            <SidebarHeader className={style !== 'plain' ? 'sidebar-styled' : ''} style={sidebarStyle}>
                <div className="flex justify-center items-center p-2">
                    <Link href={getFirstAvailableHref()} prefetch className="flex items-center justify-center">
                        {/* Logo for expanded sidebar */}
                        <div className="group-data-[collapsible=icon]:hidden flex items-center">
                            {(() => {
                                const isDark = document.documentElement.classList.contains('dark');
                                const currentLogo = isDark ? logoLight : logoDark;
                                const displayUrl = currentLogo ? (
                                    currentLogo.startsWith('http') ? currentLogo :
                                        currentLogo.startsWith('/storage/') ? `${window.location.origin}${currentLogo}` :
                                            currentLogo.startsWith('/') ? `${window.location.origin}${currentLogo}` : currentLogo
                                ) : '';

                                return displayUrl ? (
                                    <img
                                        key={`${currentLogo}-${Date.now()}`}
                                        src={displayUrl}
                                        alt="Logo"
                                        className="w-auto transition-all duration-200"
                                        onError={() => updateBrandSettings({ [isDark ? 'logoLight' : 'logoDark']: '' })}
                                    />
                                ) : (
                                    <div className="h-12 text-inherit font-semibold flex items-center text-lg tracking-tight">
                                        WorkDo
                                    </div>
                                );
                            })()}
                        </div>

                        {/* Icon for collapsed sidebar */}
                        <div className="h-8 w-8 hidden group-data-[collapsible=icon]:block">
                            {(() => {
                                const displayFavicon = favicon ? (
                                    favicon.startsWith('http') ? favicon :
                                        favicon.startsWith('/storage/') ? `${window.location.origin}${favicon}` :
                                            favicon.startsWith('/') ? `${window.location.origin}${favicon}` : favicon
                                ) : '';

                                return displayFavicon ? (
                                    <img
                                        key={`${favicon}-${Date.now()}`}
                                        src={displayFavicon}
                                        alt="Icon"
                                        className="h-8 w-8 transition-all duration-200"
                                        onError={() => updateBrandSettings({ favicon: '' })}
                                    />
                                ) : (
                                    <div className="h-8 w-8 bg-primary text-white rounded flex items-center justify-center font-bold shadow-sm">
                                        W
                                    </div>
                                );
                            })()}
                        </div>
                    </Link>
                </div>

                {/* Business Switcher removed */}
            </SidebarHeader>

            <SidebarContent>
                <div style={sidebarStyle} className={`h-full ${style !== 'plain' ? 'sidebar-styled' : ''}`}>
                    <NavMain items={filteredNavItems} position={effectivePosition} />
                </div>
            </SidebarContent>

            <SidebarFooter>
                {/* <NavFooter items={footerNavItems} className="mt-auto" position={position} /> */}
                {/* Profile menu moved to header */}
            </SidebarFooter>
        </Sidebar>
    );
}
