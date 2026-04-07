import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import Header from './components/Header';
import Footer from './components/Footer';
import ComparisonTable from './components/ComparisonTable';
import { useBrand } from '@/contexts/BrandContext';
import { THEME_COLORS } from '@/hooks/use-appearance';
import { useFavicon } from '@/hooks/use-favicon';

interface LandingSettings {
    company_name: string;
    contact_email: string;
    contact_phone: string;
    contact_address: string;
    config_sections?: {
        sections: Array<{
            key: string;
            [key: string]: any;
        }>;
        theme?: {
            primary_color?: string;
            secondary_color?: string;
            accent_color?: string;
            logo_light?: string;
            logo_dark?: string;
            favicon?: string;
        };
        seo?: {
            meta_title?: string;
            meta_description?: string;
            meta_keywords?: string;
        };
    };
}

interface CustomPage {
    id: number;
    title: string;
    slug: string;
}

interface PageProps extends Record<string, unknown> {
    customPages: CustomPage[];
    settings: LandingSettings;
}

export default function PricingPage() {
    const { customPages = [], settings } = usePage<PageProps>().props;
    const globalSettings = (usePage().props as any).globalSettings;

    // Get brand colors
    const { themeColor, customColor } = useBrand();
    const configPrimaryColor = settings.config_sections?.theme?.primary_color;
    const primaryColor = configPrimaryColor || (themeColor === 'custom' ? customColor : THEME_COLORS[themeColor as keyof typeof THEME_COLORS]);
    
    useFavicon();

    // SEO Meta tags
    const seo = settings.config_sections?.seo;
    const pageTitle = `${seo?.meta_title || globalSettings?.title_text || 'Ribo'} - Pricing`;

    const getSectionData = (key: string) => {
        return settings.config_sections?.sections?.find(section => section.key === key) || {};
    };

    return (
        <div 
            className="min-h-screen bg-white w-full max-w-full overflow-x-hidden"
            style={{
                scrollBehavior: 'smooth',
                '--brand-color': primaryColor,
                '--primary-color': settings.config_sections?.theme?.primary_color || primaryColor,
                '--secondary-color': settings.config_sections?.theme?.secondary_color || '#8b5cf6',
                '--accent-color': settings.config_sections?.theme?.accent_color || '#10b77f'
            } as React.CSSProperties}
        >
            <Head title={pageTitle}>
                {seo?.meta_description && (
                    <meta name="description" content={seo.meta_description} />
                )}
            </Head>

            <Header
                settings={settings}
                sectionData={getSectionData('header')}
                customPages={customPages}
                brandColor={primaryColor}
            />

            <main className="pt-20">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 text-center">
                    <h1 className="text-4xl font-extrabold text-gray-900 sm:text-5xl md:text-6xl mb-4">
                        Choose the <span style={{ color: primaryColor }}>perfect plan</span> for your business
                    </h1>
                    <p className="mt-3 max-w-md mx-auto text-base text-gray-500 sm:text-lg md:mt-5 md:text-xl md:max-w-3xl">
                        Transparent pricing for businesses of all sizes. Compare our plans and find the one that fits your needs.
                    </p>
                </div>

                <ComparisonTable brandColor={primaryColor} />
            </main>

            <Footer
                settings={settings}
                sectionData={getSectionData('footer')}
                brandColor={primaryColor}
                customPages={customPages}
            />
        </div>
    );
}
