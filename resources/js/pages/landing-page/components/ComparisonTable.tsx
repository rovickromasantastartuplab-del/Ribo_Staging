import React from 'react';
import { Check, X, HelpCircle, ArrowLeft, ChevronDown, ArrowUpRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link } from '@inertiajs/react';

interface FeatureRow {
    name: string;
    description?: string;
    free: string | boolean;
    starter: string | boolean;
    pro: string | boolean;
}

const COMPARISON_DATA: FeatureRow[] = [
    {
        name: 'Maximum Users',
        description: 'Number of team members who can access the account.',
        free: '2',
        starter: '10',
        pro: 'Unlimited',
    },
    {
        name: 'Maximum Projects',
        description: 'Number of active projects you can manage.',
        free: '1',
        starter: '10',
        pro: 'Unlimited',
    },
    {
        name: 'Maximum Contacts',
        description: 'Number of total contacts in your database.',
        free: '10',
        starter: '500',
        pro: 'Unlimited',
    },
    {
        name: 'Maximum Accounts',
        description: 'Number of financial accounts you can connect.',
        free: '5',
        starter: '20',
        pro: 'Unlimited',
    },
    {
        name: 'Storage Limit (GB)',
        description: 'Total file storage space available.',
        free: '1 GB',
        starter: '10 GB',
        pro: '100 GB',
    },
    {
        name: 'Trial Days',
        description: 'Number of days for the trial period.',
        free: '0',
        starter: '14',
        pro: '30',
    },
    {
        name: 'Enable Branding',
        description: 'Remove Ribo branding and use your own.',
        free: true,
        starter: true,
        pro: true,
    },
    {
        name: 'Enable Trial',
        description: 'Allow your customers to try your services.',
        free: false,
        starter: true,
        pro: true,
    },
    {
        name: 'Wedding Suppliers',
        description: 'Access to the wedding supplier directory.',
        free: false,
        starter: true,
        pro: true,
    },
    {
        name: 'AI Integration',
        description: 'Advanced AI-powered features and insights.',
        free: false,
        starter: true,
        pro: true,
    },
    {
        name: 'E-commerce',
        description: 'Sell products and services online.',
        free: false,
        starter: false,
        pro: true,
    },
];

export default function ComparisonTable({ brandColor = '#3b82f6' }) {
    const { t } = useTranslation();
    const [isYearly, setIsYearly] = React.useState(false);

    const pricing = {
        starter: {
            monthly: 8.08,
            yearly: 6.45 // Approx 20% discount
        },
        pro: {
            monthly: 16.17,
            yearly: 12.94 // Approx 20% discount
        }
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
        return <span className="text-gray-600 font-medium">{t(value)}</span>;
    };

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
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10 items-stretch">
                {/* Enterprise Plan Card (Replaces Feature Card) */}
                <div className="bg-white rounded-3xl border border-gray-100 p-8 flex flex-col items-center text-center shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
                    <div className="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-500">
                        <HelpCircle size={24} />
                    </div>
                    <h3 className="text-2xl font-black text-gray-900 mb-2">{t('Enterprise')}</h3>
                    <p className="text-xs text-gray-400 mb-6 leading-relaxed">
                        {t('Advanced features and custom limits for large-scale operations.')}
                    </p>
                    <div className="flex items-baseline mb-1">
                        <span className="text-2xl font-extrabold text-gray-900">{t('Custom Price')}</span>
                    </div>
                    <p className="text-[10px] text-gray-400 mb-8 uppercase tracking-wider">{t('Contact for quote')}</p>
                    <Link
                        href="#"
                        className="w-full mt-auto py-3 rounded-full bg-white border border-gray-200 text-gray-900 font-bold text-sm hover:bg-gray-50 hover:border-gray-300 transition-all flex items-center justify-center gap-2 shadow-sm"
                    >
                        {t('Contact Sales')}
                        <ArrowUpRight size={16} className="text-gray-400 group-hover:text-gray-900 transition-colors" />
                    </Link>
                </div>

                {/* Free Plan Card */}
                <div className="bg-white rounded-3xl border border-gray-200 p-8 flex flex-col items-center text-center shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
                   <div className="absolute top-0 left-0 w-full h-1.5 bg-gray-200 group-hover:bg-gray-300 transition-all"></div>
                    <h3 className="text-2xl font-black text-gray-900 mb-4">{t('Free')}</h3>
                    <p className="text-xs text-gray-400 mb-1">{t('Starting from')}</p>
                    <div className="flex items-baseline mb-1">
                        <span className="text-4xl font-extrabold text-gray-900">$0</span>
                    </div>
                    <p className="text-[10px] text-gray-400 mb-8 uppercase tracking-wider">
                        {isYearly ? t('Per year') : t('Per month')}
                    </p>
                    <Link
                        href={route('register', { plan: 'free' })}
                        className="w-full mt-auto py-3 rounded-full bg-white border border-gray-200 text-gray-900 font-bold text-sm hover:bg-gray-50 hover:border-gray-300 transition-all flex items-center justify-center gap-2 shadow-sm"
                    >
                        {t('Go Free')}
                        <ArrowUpRight size={16} className="text-gray-400 group-hover:text-gray-900 transition-colors" />
                    </Link>
                </div>

                {/* Starter Plan Card */}
                <div className="bg-white rounded-3xl border border-gray-200 p-8 flex flex-col items-center text-center shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
                    <div className="absolute top-0 left-0 w-full h-1.5 bg-blue-100 group-hover:bg-blue-200 transition-all"></div>
                    <h3 className="text-2xl font-black text-gray-900 mb-4">{t('Starter')}</h3>
                    <p className="text-xs text-gray-400 mb-1">{t('Starting from')}</p>
                    <div className="flex items-baseline mb-1">
                        <span className="text-4xl font-extrabold text-gray-900">
                            ${isYearly ? pricing.starter.yearly : pricing.starter.monthly}
                        </span>
                    </div>
                    <p className="text-[10px] text-gray-400 mb-8 uppercase tracking-wider">
                        {isYearly ? t('Per year') : t('Per month')}
                    </p>
                    <Link
                        href={route('register', { plan: 'starter' })}
                        className="w-full mt-auto py-3 rounded-full bg-white border border-gray-200 text-gray-900 font-bold text-sm hover:bg-gray-50 hover:border-gray-300 transition-all flex items-center justify-center gap-2 shadow-sm"
                    >
                        {t('Go Starter')}
                        <ArrowUpRight size={16} className="text-gray-400 group-hover:text-gray-900 transition-colors" />
                    </Link>
                </div>

                {/* Pro Plan Card */}
                <div className="bg-white rounded-3xl border-2 p-8 flex flex-col items-center text-center shadow-md relative overflow-hidden group" style={{ borderColor: brandColor }}>
                    <div className="absolute top-0 left-0 w-full bg-emerald-600 text-white py-1.5 text-[10px] font-black uppercase tracking-widest shadow-sm">
                        {t('Most popular')}
                    </div>
                    <h3 className="text-2xl font-black text-gray-900 mb-4 mt-2">{t('Pro')}</h3>
                    <p className="text-xs text-gray-400 mb-1">{t('Starting from')}</p>
                    <div className="flex items-baseline mb-1">
                        <span className="text-4xl font-extrabold text-gray-900">
                            ${isYearly ? pricing.pro.yearly : pricing.pro.monthly}
                        </span>
                    </div>
                    <p className="text-[10px] text-gray-400 mb-8 uppercase tracking-wider">
                        {isYearly ? t('Per year') : t('Per month')}
                    </p>
                    <Link
                        href={route('register', { plan: 'pro' })}
                        className="w-full mt-auto py-3 rounded-full bg-white border border-gray-200 text-gray-900 font-bold text-sm hover:bg-gray-50 hover:border-gray-300 transition-all flex items-center justify-center gap-2 shadow-sm"
                    >
                        {t('Go Pro')}
                        <ArrowUpRight size={16} className="text-gray-400 group-hover:text-gray-900 transition-colors" />
                    </Link>
                </div>
            </div>

            {/* Comparison Table Unified Grid */}
            <div className="bg-white rounded-[2.5rem] border border-gray-100 shadow-md">
                <div className="overflow-x-auto overflow-y-visible">
                    <div className="min-w-[800px] lg:min-w-0">
                        {COMPARISON_DATA.map((row, index) => (
                            <div 
                                key={index} 
                                className={`grid grid-cols-4 gap-0 border-b border-gray-50/80 last:border-0 hover:bg-gray-50/40 transition-colors group/row
                                    ${index === 0 ? 'rounded-t-[2.5rem]' : ''}
                                    ${index === COMPARISON_DATA.length - 1 ? 'rounded-b-[2.5rem]' : ''}`}
                            >
                                {/* Feature Name Column */}
                                <div className="p-6 flex items-center gap-3 min-h-[72px]">
                                    <span className="font-bold text-gray-800 text-sm tracking-tight">{t(row.name)}</span>
                                    {row.description && (
                                        <div className="group relative">
                                            <HelpCircle className="w-4 h-4 text-gray-300 cursor-help group-hover:text-gray-500 transition-colors" />
                                            <div className={`absolute ${index === 0 ? 'top-full mt-3' : 'bottom-full mb-3'} left-1/2 -translate-x-1/2 w-56 p-3 bg-gray-900 text-white text-[11px] leading-relaxed rounded-xl shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50`}>
                                                {t(row.description)}
                                                <div className={`absolute ${index === 0 ? 'bottom-full mb-0 border-b-gray-900' : 'top-full mt-0 border-t-gray-900'} left-1/2 -translate-x-1/2 border-[6px] border-transparent`}></div>
                                            </div>
                                        </div>
                                    )}
                                </div>

                                {/* Free Value Column */}
                                <div className="p-6 flex flex-col justify-center items-center border-l border-gray-100/80">
                                    <div className="lg:hidden font-bold text-[10px] uppercase text-gray-400 mb-1">{t('Free')}</div>
                                    <div className="text-gray-600 font-medium transition-colors group-hover/row:text-gray-900">{renderValue(row.free)}</div>
                                </div>

                                {/* Starter Value Column */}
                                <div className="p-6 flex flex-col justify-center items-center border-l border-gray-100/80">
                                    <div className="lg:hidden font-bold text-[10px] uppercase text-gray-400 mb-1">{t('Starter')}</div>
                                    <div className="text-gray-600 font-medium transition-colors group-hover/row:text-gray-900">{renderValue(row.starter)}</div>
                                </div>

                                {/* Pro Value Column */}
                                <div className={`p-6 flex flex-col justify-center items-center border-l border-gray-100/80 bg-[#F0F7FF]/30 relative`}>
                                    {/* Subtle vertical indicator */}
                                    <div className="absolute inset-y-0 left-0 w-px bg-blue-500/5"></div>
                                    <div className="lg:hidden font-bold text-[10px] uppercase text-gray-400 mb-1">{t('Pro')}</div>
                                    <div className="font-bold text-gray-900 drop-shadow-sm">{renderValue(row.pro)}</div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            <div className="mt-16 text-center">
                <div className="inline-flex items-center gap-2 px-4 py-2 bg-gray-50 rounded-full border border-gray-100 text-xs font-medium text-gray-500 italic shadow-sm">
                    <Check className="w-3 h-3 text-emerald-500" />
                    {t('Sign up for any plan to get started immediately')}
                </div>
            </div>
        </div>
    );
}
