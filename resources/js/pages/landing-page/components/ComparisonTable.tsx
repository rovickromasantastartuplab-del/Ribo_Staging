import React from 'react';
import { Check, X, HelpCircle, ArrowLeft, ArrowUpRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link } from '@inertiajs/react';

interface Plan {
    id: number;
    name: string;
    price: number;
    monthly_price?: number;
    yearly_price: number;
    max_users: number;
    max_projects: number;
    max_contacts: number;
    max_accounts: number;
    enable_branding?: string;
    storage_limit?: string | number;
    trial_day?: number;
    trial_days?: number;
    is_trial?: string | null;
    module?: string[];
    recommended?: boolean;
}

import { usePage } from '@inertiajs/react';

interface ComparisonFeature {
    name: string;
    description: string;
    type: 'property' | 'module' | 'method';
    key: string;
}

interface ComparisonTableProps {
    brandColor?: string;
    plans: Plan[];
    comparisonFeatures?: ComparisonFeature[];
}
import { defaultLandingPageSections } from '../templates/default-sections';

export default function ComparisonTable({ brandColor = '#3b82f6', plans = [], comparisonFeatures = [] }: ComparisonTableProps) {
    const { t } = useTranslation();
    const { globalSettings } = usePage().props as any;
    const currencySymbol = globalSettings?.superAdminCurrencySymbol || '$';
    const [isYearly, setIsYearly] = React.useState(false);

    const activePlans = (plans || []).filter(p => p && p.name !== 'Enterprise');

    const getTrialDays = (plan: Plan): string => {
        const days = plan.trial_days ?? plan.trial_day ?? 0;
        const isTrial = plan.is_trial === 'on' || plan.is_trial === '1';
        return isTrial ? String(days) : '0';
    };

    const getLimit = (val: number | undefined): string => {
        if (val === undefined || val === null) return 'N/A';
        return val === -1 ? 'Unlimited' : String(val);
    };

    const hasModule = (plan: Plan, key: string): boolean => {
        return Array.isArray(plan.module) && plan.module.includes(key);
    };

    const renderValue = (value: string | boolean) => {
        if (typeof value === 'boolean') {
            return value ? (
                <div className="flex justify-center">
                    <Check className="w-5 h-5" style={{ color: brandColor }} />
                </div>
            ) : (
                <div className="flex justify-center">
                    <X className="w-5 h-5 text-muted-foreground/30" />
                </div>
            );
        }
        return <span className="text-muted-foreground font-medium">{value}</span>;
    };

    // Use features from CMS or fallback to defaults
    const defaultFeatures = defaultLandingPageSections.sections.find(s => s.key === 'plans')?.comparison_features || [];
    const displayFeatures = (comparisonFeatures !== undefined && comparisonFeatures !== null) ? comparisonFeatures : defaultFeatures;

    const getFeatureValue = (plan: Plan, feature: ComparisonFeature) => {
        const { type, key } = feature;
        
        switch (type) {
            case 'property':
                if (key === 'storage_limit') return `${plan.storage_limit ?? 0} GB`;
                if (key === 'trial_day' || key === 'trial_days') return getTrialDays(plan);
                return getLimit((plan as any)[key]);
            case 'module':
                return hasModule(plan, key);
            case 'method':
                if (key === 'enable_branding') return plan.enable_branding === 'on';
                return false;
            default:
                return 'N/A';
        }
    };


    const totalCols = activePlans.length + 1;
    
    const getGridColsClass = (cols: number) => {
        switch (cols) {
            case 2: return 'md:grid-cols-2';
            case 3: return 'md:grid-cols-3';
            case 4: return 'md:grid-cols-4';
            case 5: return 'md:grid-cols-5';
            case 6: return 'md:grid-cols-6';
            case 7: return 'md:grid-cols-7';
            case 8: return 'md:grid-cols-8';
            default: return 'md:grid-cols-5'; // Fallback
        }
    };
    
    const gridColsClass = getGridColsClass(totalCols);

    return (
        <div className="w-full max-w-7xl mx-auto px-4 py-20 pb-40">
            {/* Top Navigation & Switcher Bar */}
            <div className="flex flex-col md:flex-row justify-between items-center mb-12 gap-8 lg:px-0">
                <Link
                    href={route('home')}
                    className="flex items-center gap-3 text-sm font-bold text-muted-foreground hover:text-foreground transition-all group"
                >
                    <div className="w-10 h-10 rounded-2xl bg-card border border-border flex items-center justify-center shadow-sm group-hover:shadow-md group-hover:border-border transition-all">
                        <ArrowLeft size={18} className="group-hover:-translate-x-0.5 transition-transform" />
                    </div>
                    {t('Go to home')}
                </Link>

                {/* Monthly/Yearly Switcher */}
                <div className="flex items-center gap-4 bg-muted/50 p-1.5 rounded-2xl border border-border shadow-inner">
                    <button
                        onClick={() => setIsYearly(false)}
                        className={`px-6 py-2.5 rounded-xl text-sm font-bold transition-all ${!isYearly ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                    >
                        {t('Monthly')}
                    </button>
                    <button
                        onClick={() => setIsYearly(true)}
                        className={`px-6 py-2.5 rounded-xl text-sm font-bold transition-all flex items-center gap-2 ${isYearly ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                    >
                        {t('Yearly')}
                        <span className="px-2 py-0.5 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 text-[10px] rounded-full font-bold">
                            -20%
                        </span>
                    </button>
                </div>
            </div>

            {/* Pricing Header Cards */}
            <div className={`grid grid-cols-1 ${gridColsClass} gap-4 mb-10 items-stretch`}>
                {/* Enterprise Plan Card */}
                <div className="bg-card rounded-3xl border border-border p-6 flex flex-col items-center text-center shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
                    <div className="h-14 flex items-center justify-center w-full mb-2">
                        <h3 className="text-xl font-black text-foreground">{t('Enterprise')}</h3>
                    </div>
                    <p className="text-xs text-muted-foreground mb-6 leading-relaxed flex-grow">
                        {t('Advanced features and custom limits for large-scale operations.')}
                    </p>
                    <div className="flex items-baseline mb-1">
                        <span className="text-2xl font-extrabold text-foreground">{t('Custom')}</span>
                    </div>
                    <p className="text-[10px] text-muted-foreground mb-8 uppercase tracking-wider w-full truncate">{t('Contact for quote')}</p>
                    <a
                        href="/#contact"
                        className="w-full mt-auto h-[42px] px-2 rounded-full bg-background border border-border text-foreground font-bold text-[13px] hover:bg-muted hover:border-border transition-all flex items-center justify-center gap-1.5 shadow-sm overflow-hidden"
                    >
                        <span className="whitespace-nowrap truncate">{t('Contact Sales')}</span>
                        <ArrowUpRight size={16} className="text-muted-foreground group-hover:text-foreground transition-colors flex-shrink-0" />
                    </a>
                </div>

                {/* Dynamic Plans */}
                {activePlans.map((plan, idx) => {
                    const displayPrice = isYearly ? (plan.yearly_price ?? plan.price ?? 0) : (plan.monthly_price ?? plan.price ?? 0);
                    const isHighlighted = idx === activePlans.length - 1;
                    return (
                        <div
                            key={plan.id}
                            className={`bg-card rounded-3xl border p-6 flex flex-col items-center text-center shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group ${isHighlighted ? 'border-2' : 'border-border'}`}
                            style={isHighlighted ? { borderColor: brandColor } : {}}
                        >
                            {isHighlighted && (
                                <div 
                                    className="absolute top-0 left-0 w-full text-white py-1.5 text-[10px] font-black uppercase tracking-widest shadow-sm"
                                    style={{ backgroundColor: brandColor }}
                                >
                                    {t('Most popular')}
                                </div>
                            )}
                            {!isHighlighted && (
                                <div className="absolute top-0 left-0 w-full h-1.5 bg-muted group-hover:bg-muted/80 transition-all"></div>
                            )}

                            <div className={`h-14 flex items-center justify-center w-full px-2 ${isHighlighted ? 'mt-3 mb-2' : 'mb-2'}`}>
                                <h3 className="text-xl font-black text-foreground line-clamp-2 leading-tight">
                                    {t(plan.name)}
                                </h3>
                            </div>
                            <p className="text-xs text-muted-foreground mb-6 flex-grow">{t('Starting from')}</p>
                            
                            <div className="flex items-baseline mb-1">
                                <span className="text-3xl xl:text-4xl font-extrabold text-foreground tracking-tight">
                                    {currencySymbol}{displayPrice}
                                </span>
                            </div>
                            <p className="text-[10px] text-muted-foreground mb-8 uppercase tracking-wider w-full truncate">
                                {isYearly ? t('Per year') : t('Per month')}
                            </p>
                            <a
                                href={`/register?plan=${plan.id}`}
                                className="w-full mt-auto h-[42px] px-2 rounded-full bg-background border border-border text-foreground font-bold text-[13px] hover:bg-muted hover:border-border transition-all flex items-center justify-center gap-1.5 shadow-sm overflow-hidden"
                            >
                                <span className="whitespace-nowrap truncate">{t('Get')} {plan.name}</span>
                                <ArrowUpRight size={16} className="text-muted-foreground group-hover:text-foreground transition-colors flex-shrink-0" />
                            </a>
                        </div>
                    );
                })}
            </div>

            {/* Comparison Table */}
            {activePlans.length > 0 && (
                <div className="bg-card rounded-[2.5rem] border border-border shadow-md">
                    <div className="overflow-x-auto overflow-y-visible">
                        <div className="min-w-[800px] lg:min-w-0">
                            {displayFeatures.map((feature, fIdx) => (
                                <div
                                    key={fIdx}
                                    className={`grid grid-cols-1 ${gridColsClass.replace('md:', '')} gap-0 border-b border-border last:border-0 hover:bg-muted/30 transition-colors group/row
                                        ${fIdx === 0 ? 'rounded-t-[2.5rem]' : ''}
                                        ${fIdx === displayFeatures.length - 1 ? 'rounded-b-[2.5rem]' : ''}`}
                                >
                                    {/* Feature Name Column */}
                                    <div className="p-6 flex items-center gap-3 min-h-[72px]">
                                        <span className="font-bold text-foreground text-sm tracking-tight">{t(feature.name)}</span>
                                        {feature.description && (
                                            <div className="group relative">
                                                <HelpCircle className="w-4 h-4 text-muted-foreground/50 cursor-help group-hover:text-foreground transition-colors" />
                                                <div className={`absolute ${fIdx === 0 ? 'top-full mt-3' : 'bottom-full mb-3'} left-1/2 -translate-x-1/2 w-56 p-3 bg-foreground text-background text-[11px] leading-relaxed rounded-xl shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50`}>
                                                    {t(feature.description)}
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    {/* Removed Enterprise Custom Column as requested */}

                                    {/* Dynamic Plan Columns */}
                                    {activePlans.map((plan, pIdx) => (
                                        <div
                                            key={plan.id}
                                            className={`p-6 flex flex-col justify-center items-center border-l border-border ${pIdx === activePlans.length - 1 ? 'relative' : ''}`}
                                            style={pIdx === activePlans.length - 1 ? { backgroundColor: `${brandColor}12` } : {}}
                                        >
                                            <div className="lg:hidden font-bold text-[10px] uppercase text-muted-foreground mb-1">{t(plan.name)}</div>
                                            <div className={`font-medium ${pIdx === activePlans.length - 1 ? 'font-bold text-foreground' : 'text-muted-foreground'}`}>
                                                {renderValue(getFeatureValue(plan, feature))}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            <div className="mt-16 text-center">
                <div className="inline-flex items-center gap-2 px-4 py-2 bg-muted rounded-full border border-border text-xs font-medium text-muted-foreground italic shadow-sm">
                    <Check className="w-3 h-3" style={{ color: brandColor }} />
                    {t('Sign up for any plan to get started immediately')}
                </div>
            </div>
        </div>
    );
}
