import { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { getBrandSettings, type BrandSettings } from '@/pages/settings/components/brand-settings';
import { getCookie, isDemoMode } from '@/utils/cookie-utils';

interface BrandContextType extends BrandSettings {
    updateBrandSettings: (settings: Partial<BrandSettings>) => void;
}

const BrandContext = createContext<BrandContextType | undefined>(undefined);

const getDisplayUrl = (path: string): string => {

    if (!path) return path;

    // If it's already a full URL, return as is
    if (path.startsWith('http')) return path;

    // Get the base path from current URL (e.g., /product/salesy-saas-react)
    const basePath = window.appSettings.baseUrl;

    // If it's just a filename or other relative path, assume it's in storage
    const imagePath=`${basePath}/${path}`;
    return imagePath.replace(/([^:]\/)\/+/g, '$1');
};

export function BrandProvider({ children, globalSettings, user }: { children: ReactNode; globalSettings?: any; user?: any }) {
    // Determine which settings to use based on user role and route
    const getEffectiveSettings = () => {
        const isPublicRoute = window.location.pathname.includes('/public/') ||
            window.location.pathname === '/' ||
            window.location.pathname.includes('/auth/');

        // For public routes (landing page, auth pages), always use superadmin settings
        if (isPublicRoute) {
            return { ...globalSettings,
                favicon:getDisplayUrl(globalSettings.favicon),
                logoDark:getDisplayUrl(globalSettings.logoDark),
                logoLight:getDisplayUrl(globalSettings.logoLight) };
        }

        // For authenticated routes, use user's own settings if company role
        if (user?.role === 'company' && user?.globalSettings) {
            return { ...user.globalSettings,
                favicon:getDisplayUrl(user.globalSettings?.favicon),
                logoDark:getDisplayUrl(user.globalSettings?.logoDark),
                logoLight:getDisplayUrl(user.globalSettings?.logoLight) };
        }

        // Default to global settings (superadmin)
        return { ...globalSettings,
            favicon:getDisplayUrl(globalSettings.favicon),
            logoDark:getDisplayUrl(globalSettings.logoDark),
            logoLight:getDisplayUrl(globalSettings.logoLight) };
    };

    const [brandSettings, setBrandSettings] = useState<BrandSettings>(() =>
        getBrandSettings(getEffectiveSettings())
    );

    // Listen for changes in settings
    useEffect(() => {
        let effectiveSettings = getEffectiveSettings();

        // In demo mode, also check cookies for brand settings
        if (isDemoMode()) {
            try {
                const cookieBrandSettings = getCookie('brandSettings');
                if (cookieBrandSettings) {
                    const parsedCookieSettings = JSON.parse(cookieBrandSettings);
                    // Merge cookie settings with effective settings
                    effectiveSettings = { ...effectiveSettings, ...parsedCookieSettings };
                }
            } catch (error) {
                console.error('Error loading brand settings from cookies', error);
            }
        }

        const updatedSettings = getBrandSettings(effectiveSettings);
        setBrandSettings(updatedSettings);

        // Apply layout direction (RTL/LTR) to DOM
        // if (updatedSettings?.layoutDirection) {
        //   const dir = updatedSettings.layoutDirection === 'right' ? 'ltr' : 'rtl';
        //   document.documentElement.dir = dir;
        //   document.documentElement.setAttribute('dir', dir);
        // }
        // Apply theme settings immediately for landing page (both demo and non-demo modes)
        if (updatedSettings) {
            // Apply theme color globally
            const color = updatedSettings.themeColor === 'custom' ? updatedSettings.customColor : {
                blue: '#3b82f6',
                green: '#10b77f',
                purple: '#8b5cf6',
                orange: '#f97316',
                red: '#ef4444'
            }[updatedSettings.themeColor] || '#3b82f6';

            document.documentElement.style.setProperty('--theme-color', color);
            document.documentElement.style.setProperty('--primary', color);

            // Apply theme mode
            const isDark = updatedSettings.themeMode === 'dark' ||
                (updatedSettings.themeMode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', isDark);
            document.body.classList.toggle('dark', isDark);

            // Apply layout direction (RTL/LTR)
            document.documentElement.dir = updatedSettings.layoutDirection;
            document.documentElement.setAttribute('dir', updatedSettings.layoutDirection);
        }
    }, [globalSettings, user]);

    const updateBrandSettings = (newSettings: Partial<BrandSettings>) => {
        setBrandSettings(prev => ({ ...prev, ...newSettings }));
    };

    return (
        <BrandContext.Provider value={{ ...brandSettings, updateBrandSettings }}>
            {children}
        </BrandContext.Provider>
    );
}

export function useBrand() {
    const context = useContext(BrandContext);
    if (context === undefined) {
        throw new Error('useBrand must be used within a BrandProvider');
    }
    return context;
}
