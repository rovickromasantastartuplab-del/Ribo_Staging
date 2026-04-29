import React from 'react';
import { Skeleton } from '@/components/ui/skeleton';
import { SidebarMenuSkeleton } from '@/components/ui/sidebar';
import { useThemePreview } from '@/hooks/use-theme-preview';
import { useLogos } from '@/contexts/LogoContext';
import { THEME_COLORS, type Appearance, type ThemeColor } from '@/hooks/use-appearance';
import { type LayoutPosition } from '@/contexts/LayoutContext';
import { type SidebarVariant, type SidebarCollapsible } from '@/components/sidebar-style-settings';
import { useTranslation } from 'react-i18next';

export interface ThemePreviewProps {
  appearance?: Appearance;
  themeColor?: ThemeColor;
  customColor?: string;
  position?: LayoutPosition;
  variant?: SidebarVariant;
  collapsible?: SidebarCollapsible;
  style?: string;
}

export function ThemePreview({
  appearance: propsAppearance,
  themeColor: propsThemeColor,
  customColor: propsCustomColor,
  position: propsPosition,
  variant: propsVariant,
  collapsible: propsCollapsible,
  style: propsStyle
}: ThemePreviewProps) {
  const { t } = useTranslation();
  const globalSettings = useThemePreview();
  const { logoLight, logoDark } = useLogos();
  const [logoError, setLogoError] = React.useState(false);

  // Use props if provided, otherwise use global settings
  const appearance = propsAppearance ?? globalSettings.appearance;
  const themeColor = propsThemeColor ?? globalSettings.themeColor;
  const customColor = propsCustomColor ?? globalSettings.customColor;
  const position = propsPosition ?? globalSettings.position;
  const variant = propsVariant ?? (globalSettings.variant as SidebarVariant);
  const collapsible = propsCollapsible ?? (globalSettings.collapsible as SidebarCollapsible);
  const style = propsStyle ?? globalSettings.style;

  // Reset logo error when logo sources change
  React.useEffect(() => {
    setLogoError(false);
  }, [logoLight, logoDark, appearance]);

  // Determine sidebar style class
  const getSidebarStyleClass = () => {
    if (style === 'colored') return 'bg-primary text-white';
    if (style === 'gradient') return 'bg-gradient-to-b from-primary to-primary/80 text-white';
    return ''; // Will be handled by inline styles
  };

  // Logo preview based on appearance
  const getLogoSrc = () => {
    if (logoError) return '';

    // If preview is dark, use light logo (white)
    if (appearance === 'dark') {
      return logoLight || '/images/logos/logo-light.png';
    } else {
      // If preview is light, use dark logo (black)
      return logoDark || '/images/logos/logo-dark.png';
    }
  };

  // Get title text
  const getTitleText = () => {
    return 'WorkDo';
  };

  const primaryColor = themeColor === 'custom' ? customColor : THEME_COLORS[themeColor as keyof typeof THEME_COLORS];

  return (
    <div
      className={`border rounded-xl overflow-hidden transition-all duration-500 shadow-lg ${appearance === 'dark' ? 'dark' : ''}`}
      style={{
        '--primary': primaryColor,
        '--theme-color': primaryColor,
        '--background': appearance === 'dark' ? '#0f172a' : '#f8fafc',
        '--foreground': appearance === 'dark' ? '#f8fafc' : '#0f172a',
        '--sidebar': appearance === 'dark' ? '#111827' : '#ffffff',
        '--sidebar-foreground': appearance === 'dark' ? '#f8fafc' : '#0f172a',
        '--border': appearance === 'dark' ? '#1e293b' : '#e2e8f0',
        '--muted': appearance === 'dark' ? '#1e293b' : '#f1f5f9',
      } as React.CSSProperties}
    >
      <div 
        className="h-full flex flex-col transition-colors duration-500"
        style={{ 
          backgroundColor: appearance === 'dark' ? '#0f172a' : '#f8fafc',
          color: appearance === 'dark' ? '#f8fafc' : '#0f172a'
        }}
      >
        <div 
          className="p-2.5 text-[10px] uppercase tracking-wider font-bold flex justify-between items-center border-b"
          style={{ 
            backgroundColor: appearance === 'dark' ? 'rgba(30, 41, 59, 0.8)' : '#ffffff',
            borderColor: appearance === 'dark' ? '#1e293b' : '#f1f5f9'
          }}
        >
          <div className="flex items-center gap-2">
              <div className="w-2.5 h-2.5 rounded-full bg-red-500/80 shadow-sm" />
              <div className="w-2.5 h-2.5 rounded-full bg-yellow-500/80 shadow-sm" />
              <div className="w-2.5 h-2.5 rounded-full bg-green-500/80 shadow-sm" />
              <span 
                className="ml-2"
                style={{ color: appearance === 'dark' ? '#94a3b8' : '#64748b' }}
              >
                {t("Theme Preview")}
              </span>
          </div>
          <div className="flex gap-1.5">
            <span className={`px-2 py-0.5 rounded-md text-[9px] font-black ${appearance === 'dark' ? 'bg-primary/20 text-primary' : 'bg-primary/10 text-primary'}`}>
              {appearance.toUpperCase()}
            </span>
            <span className={`px-2 py-0.5 rounded-md text-[9px] font-black ${appearance === 'dark' ? 'bg-primary/20 text-primary' : 'bg-primary/10 text-primary'}`}>
              {themeColor.toUpperCase()}
            </span>
          </div>
        </div>

        <div className={`flex ${position === 'right' ? 'flex-row-reverse' : 'flex-row'} h-64 overflow-hidden`}>
          {/* Sidebar */}
          <div
            className={`
              w-[32%] flex flex-col transition-all duration-500
              ${variant === 'floating' ? 'rounded-xl m-2 border shadow-md' : ''}
              ${collapsible === 'icon' ? 'max-w-[4rem]' : ''}
              ${style === 'colored' ? 'bg-primary text-white' : 
                style === 'gradient' ? 'bg-gradient-to-b from-primary to-primary/80 text-white' : ''}
            `}
            style={{
              backgroundColor: style === 'plain' ? (appearance === 'dark' ? '#111827' : '#ffffff') : undefined,
              color: style === 'plain' ? (appearance === 'dark' ? '#f8fafc' : '#0f172a') : undefined,
              borderRight: style === 'plain' ? `1px solid ${appearance === 'dark' ? '#1e293b' : '#f1f5f9'}` : undefined
            }}
          >
            {/* Sidebar Header with Logo */}
            <div 
              className="p-4 border-b flex items-center justify-center overflow-hidden"
              style={{ borderColor: appearance === 'dark' ? 'rgba(30, 41, 59, 0.5)' : '#f1f5f9' }}
            >
              {!logoError && getLogoSrc() ? (
                <img
                  key={`preview-${appearance}-${getLogoSrc()}`}
                  src={getLogoSrc()}
                  alt={getTitleText()}
                  className="h-6 max-w-full object-contain brightness-110"
                  onError={() => setLogoError(true)}
                />
              ) : (
                <div className="h-6 text-inherit font-black flex items-center text-sm tracking-tighter">
                  {getTitleText()}
                </div>
              )}
            </div>

            {/* Sidebar Content */}
            <div className="flex-1 p-3.5 space-y-3 overflow-hidden">
              <SidebarMenuSkeleton showIcon={true} active={true} />
              <SidebarMenuSkeleton showIcon={true} />
              <SidebarMenuSkeleton showIcon={true} />

              {/* Nested menu */}
              {collapsible !== 'icon' && (
                <div 
                  className="ml-4 pl-3 border-l mt-3 space-y-2.5"
                  style={{ borderColor: appearance === 'dark' ? '#1e293b' : '#f1f5f9' }}
                >
                  <SidebarMenuSkeleton showIcon={true} />
                </div>
              )}
            </div>
          </div>

          {/* Main Content */}
          <div 
            className={`flex-1 p-6 transition-all duration-500 ${variant === 'inset' ? 'rounded-2xl m-2 border shadow-sm' : ''}`}
            style={{
              backgroundColor: variant === 'inset' ? (appearance === 'dark' ? 'rgba(30, 41, 59, 0.5)' : '#ffffff') : (appearance === 'dark' ? '#0f172a' : '#f8fafc'),
              borderColor: appearance === 'dark' ? '#1e293b' : '#f1f5f9'
            }}
          >
            <div className="space-y-6">
              <div className="flex items-center justify-between">
                  <Skeleton className="h-8 w-1/2 rounded-lg" />
                  <div className="flex gap-2">
                    <Skeleton className="h-8 w-8 rounded-full" />
                    <Skeleton className="h-8 w-24 rounded-lg" />
                  </div>
              </div>

              <div className="space-y-3">
                <Skeleton className={`h-3 w-full ${appearance === 'dark' ? 'opacity-20' : 'opacity-40'}`} />
                <Skeleton className={`h-3 w-11/12 ${appearance === 'dark' ? 'opacity-20' : 'opacity-40'}`} />
                <Skeleton className={`h-3 w-4/5 ${appearance === 'dark' ? 'opacity-20' : 'opacity-40'}`} />
              </div>

              <div className="flex gap-3 mt-8">
                <div className="h-10 w-28 rounded-xl bg-primary flex items-center justify-center shadow-sm">
                  <div className="h-3 w-12 bg-primary-foreground/30 rounded" />
                </div>
                <div 
                  className="h-10 w-28 rounded-xl border flex items-center justify-center shadow-sm"
                  style={{ 
                    borderColor: appearance === 'dark' ? '#1e293b' : '#e2e8f0',
                    backgroundColor: appearance === 'dark' ? '#1e293b' : '#ffffff'
                  }}
                >
                  <div className={`h-3 w-12 rounded ${appearance === 'dark' ? 'bg-slate-700' : 'bg-slate-200'}`} />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-5 mt-8">
                <div 
                  className="h-28 rounded-2xl border p-4 shadow-sm"
                  style={{ 
                    borderColor: appearance === 'dark' ? '#1e293b' : '#f1f5f9',
                    backgroundColor: appearance === 'dark' ? 'rgba(17, 24, 39, 0.5)' : '#ffffff'
                  }}
                >
                   <Skeleton className="h-4 w-1/2 mb-3" />
                   <Skeleton className="h-8 w-3/4" />
                </div>
                <div 
                  className="h-28 rounded-2xl border p-4 shadow-sm"
                  style={{ 
                    borderColor: appearance === 'dark' ? '#1e293b' : '#f1f5f9',
                    backgroundColor: appearance === 'dark' ? 'rgba(17, 24, 39, 0.5)' : '#ffffff'
                  }}
                >
                   <Skeleton className="h-4 w-1/2 mb-3" />
                   <Skeleton className="h-8 w-3/4" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}