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

interface ComparisonTableProps {
    brandColor?: string;
    plans: Plan[];
}

export default function ComparisonTable({ brandColor = '#3b82f6', plans = [] }: ComparisonTableProps) {
    const { t } = useTranslation();
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
                    <Check className="w-5 h-5 text-emerald-500" />
                </div>
            ) : (
                <div className="flex justify-center">
                    <X className="w-5 h-5 text-gray-300" />
                </div>
            );
        }
        return <span className="text-gray-600 font-medium">{value}</span>;
    };

    const FEATURES = [
        {
            name: 'Maximum Users',
            description: 'Number of team members who can access the account.',
            getValue: (p: Plan) => getLimit(p.max_users),
        },
        {
            name: 'Maximum Projects',
            description: 'Number of active projects you can manage.',
            getValue: (p: Plan) => getLimit(p.max_projects),
        },
        {
            name: 'Maximum Contacts',
            description: 'Number of total contacts in your database.',
            getValue: (p: Plan) => getLimit(p.max_contacts),
        },
        {
            name: 'Maximum Accounts',
            description: 'Number of financial accounts you can connect.',
            getValue: (p: Plan) => getLimit(p.max_accounts),
        },
        {
            name: 'Storage Limit (GB)',
            description: 'Total file storage space available.',
            getValue: (p: Plan) => `${p.storage_limit ?? 0} GB`,
        },
        {
            name: 'Trial Days',
            description: 'Number of days for the trial period.',
            getValue: (p: Plan) => getTrialDays(p),
        },
        {
            name: 'Enable Branding',
            description: 'Remove Ribo branding and use your own.',
            getValue: (p: Plan): boolean => p.enable_branding === 'on',
        },
        {
            name: 'Wedding Suppliers',
            description: 'Access to the wedding supplier directory.',
            getValue: (p: Plan): boolean => hasModule(p, 'wedding_suppliers_module'),
        },
        {
            name: 'E-commerce',
            description: 'Sell products and services online.',
            getValue: (p: Plan): boolean => hasModule(p, 'ecommerce'),
        },
    ];

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
                    className="flex items-center gap-3 text-sm font-bold text-gray-500 hover:text-gray-900 transition-all group"
                >
                    <div className="w-10 h-10 rounded-2xl bg-white border border-gray-100 flex items-center justify-center shadow-sm group-hover:shadow-md group-hover:border-gray-200 transition-all">
                        <ArrowLeft size={18} className="group-hover:-translate-x-0.5 transition-transform" />
                    </div>
                    {t('Go to home')}
                </Link>

                {/* Monthly/Yearly Switcher */}
                <div className="flex items-center gap-4 bg-gray-50/50 p-1.5 rounded-2xl border border-gray-100 shadow-inner">
                    <button
                        onClick={() => setIsYearly(false)}
                        className={`px-6 py-2.5 rounded-xl text-sm font-bold transition-all ${!isYearly ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
                    >
                        {t('Monthly')}
                    </button>
                    <button
                        onClick={() => setIsYearly(true)}
                        className={`px-6 py-2.5 rounded-xl text-sm font-bold transition-all flex items-center gap-2 ${isYearly ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
                    >
                        {t('Yearly')}
                        <span className="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] rounded-full font-bold">
                            -20%
                        </span>
                    </button>
                </div>
            </div>

            {/* Pricing Header Cards */}
            <div className={`grid grid-cols-1 ${gridColsClass} gap-4 mb-10 items-stretch`}>
                {/* Enterprise Plan Card */}
                <div className="bg-white rounded-3xl border border-gray-100 p-6 flex flex-col items-center text-center shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
                    <div className="h-14 flex items-center justify-center w-full mb-2">
                        <h3 className="text-xl font-black text-gray-900">{t('Enterprise')}</h3>
                    </div>
                    <p className="text-xs text-gray-400 mb-6 leading-relaxed flex-grow">
                        {t('Advanced features and custom limits for large-scale operations.')}
                    </p>
                    <div className="flex items-baseline mb-1">
                        <span className="text-2xl font-extrabold text-gray-900">{t('Custom')}</span>
                    </div>
                    <p className="text-[10px] text-gray-400 mb-8 uppercase tracking-wider w-full truncate">{t('Contact for quote')}</p>
                    <a
                        href="/#contact"
                        className="w-full mt-auto h-[42px] px-2 rounded-full bg-white border border-gray-200 text-gray-900 font-bold text-[13px] hover:bg-gray-50 hover:border-gray-300 transition-all flex items-center justify-center gap-1.5 shadow-sm overflow-hidden"
                    >
                        <span className="whitespace-nowrap truncate">{t('Contact Sales')}</span>
                        <ArrowUpRight size={16} className="text-gray-400 group-hover:text-gray-900 transition-colors flex-shrink-0" />
                    </a>
                </div>

                {/* Dynamic Plans */}
                {activePlans.map((plan, idx) => {
                    const displayPrice = isYearly ? (plan.yearly_price ?? plan.price ?? 0) : (plan.monthly_price ?? plan.price ?? 0);
                    const isHighlighted = idx === activePlans.length - 1;
                    return (
                        <div
                            key={plan.id}
                            className={`bg-white rounded-3xl border p-6 flex flex-col items-center text-center shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group ${isHighlighted ? 'border-2' : 'border-gray-200'}`}
                            style={isHighlighted ? { borderColor: brandColor } : {}}
                        >
                            {isHighlighted && (
                                <div className="absolute top-0 left-0 w-full bg-emerald-600 text-white py-1.5 text-[10px] font-black uppercase tracking-widest shadow-sm">
                                    {t('Most popular')}
                                </div>
                            )}
                            {!isHighlighted && (
                                <div className="absolute top-0 left-0 w-full h-1.5 bg-gray-100 group-hover:bg-gray-200 transition-all"></div>
                            )}

                            <div className={`h-14 flex items-center justify-center w-full px-2 ${isHighlighted ? 'mt-3 mb-2' : 'mb-2'}`}>
                                <h3 className="text-xl font-black text-gray-900 line-clamp-2 leading-tight">
                                    {t(plan.name)}
                                </h3>
                            </div>
                            <p className="text-xs text-gray-400 mb-6 flex-grow">{t('Starting from')}</p>
                            
                            <div className="flex items-baseline mb-1">
                                <span className="text-3xl xl:text-4xl font-extrabold text-gray-900 tracking-tight">
                                    ${displayPrice}
                                </span>
                            </div>
                            <p className="text-[10px] text-gray-400 mb-8 uppercase tracking-wider w-full truncate">
                                {isYearly ? t('Per year') : t('Per month')}
                            </p>
                            <a
                                href={`/register?plan=${plan.id}`}
                                className="w-full mt-auto h-[42px] px-2 rounded-full bg-white border border-gray-200 text-gray-900 font-bold text-[13px] hover:bg-gray-50 hover:border-gray-300 transition-all flex items-center justify-center gap-1.5 shadow-sm overflow-hidden"
                            >
                                <span className="whitespace-nowrap truncate">{t('Get')} {plan.name}</span>
                                <ArrowUpRight size={16} className="text-gray-400 group-hover:text-gray-900 transition-colors flex-shrink-0" />
                            </a>
                        </div>
                    );
                })}
            </div>

            {/* Comparison Table */}
            {activePlans.length > 0 && (
                <div className="bg-white rounded-[2.5rem] border border-gray-100 shadow-md">
                    <div className="overflow-x-auto overflow-y-visible">
                        <div className="min-w-[800px] lg:min-w-0">
                            {FEATURES.map((feature, fIdx) => (
                                <div
                                    key={fIdx}
                                    className={`grid grid-cols-1 ${gridColsClass.replace('md:', '')} gap-0 border-b border-gray-200 last:border-0 hover:bg-gray-50/60 transition-colors group/row
                                        ${fIdx === 0 ? 'rounded-t-[2.5rem]' : ''}
                                        ${fIdx === FEATURES.length - 1 ? 'rounded-b-[2.5rem]' : ''}`}
                                >
                                    {/* Feature Name Column */}
                                    <div className="p-6 flex items-center gap-3 min-h-[72px]">
                                        <span className="font-bold text-gray-800 text-sm tracking-tight">{t(feature.name)}</span>
                                        {feature.description && (
                                            <div className="group relative">
                                                <HelpCircle className="w-4 h-4 text-gray-300 cursor-help group-hover:text-gray-500 transition-colors" />
                                                <div className={`absolute ${fIdx === 0 ? 'top-full mt-3' : 'bottom-full mb-3'} left-1/2 -translate-x-1/2 w-56 p-3 bg-gray-900 text-white text-[11px] leading-relaxed rounded-xl shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50`}>
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
                                            className={`p-6 flex flex-col justify-center items-center border-l border-gray-200 ${pIdx === activePlans.length - 1 ? 'bg-emerald-50/50 relative' : ''}`}
                                        >
                                            <div className="lg:hidden font-bold text-[10px] uppercase text-gray-400 mb-1">{t(plan.name)}</div>
                                            <div className={`font-medium ${pIdx === activePlans.length - 1 ? 'font-bold text-gray-900' : 'text-gray-600'}`}>
                                                {renderValue(feature.getValue(plan))}
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
                <div className="inline-flex items-center gap-2 px-4 py-2 bg-gray-50 rounded-full border border-gray-100 text-xs font-medium text-gray-500 italic shadow-sm">
                    <Check className="w-3 h-3 text-emerald-500" />
                    {t('Sign up for any plan to get started immediately')}
                </div>
            </div>
        </div>
    );
}
