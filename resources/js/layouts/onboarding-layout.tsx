import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useBrand } from '@/contexts/BrandContext';
import { useAppearance, THEME_COLORS } from '@/hooks/use-appearance';
import { useFavicon } from '@/hooks/use-favicon';
import { Building2, Users, Shield, CreditCard, Check } from 'lucide-react';

interface OnboardingLayoutProps {
    children: ReactNode;
    currentStep: 1 | 2 | 3 | 4;
    title: string;
    description?: string;
}

const steps = [
    { number: 1, label: 'Company', icon: Building2 },
    { number: 2, label: 'Plan', icon: CreditCard },
    { number: 3, label: 'Roles', icon: Shield },
    { number: 4, label: 'Members', icon: Users },
];

export default function OnboardingLayout({
    children,
    currentStep,
    title,
    description,
}: OnboardingLayoutProps) {
    useFavicon();
    const { t } = useTranslation();
    const { logoLight, logoDark, themeColor, customColor } = useBrand();
    const { appearance } = useAppearance();

    const currentLogo = appearance === 'dark' ? logoLight : logoDark;
    const primaryColor = themeColor === 'custom' ? customColor : THEME_COLORS[themeColor as keyof typeof THEME_COLORS];

    return (
        <div className="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-100 relative overflow-hidden">
            <Head title={title} />

            {/* Subtle background pattern */}
            <div className="absolute inset-0 opacity-30" style={{
                backgroundImage: `radial-gradient(circle at 20% 80%, ${primaryColor}15 1px, transparent 1px), radial-gradient(circle at 80% 20%, ${primaryColor}10 1px, transparent 1px)`,
                backgroundSize: '100px 100px, 60px 60px'
            }} />

            <div className="relative z-10 flex flex-col items-center min-h-screen py-8 px-4">
                {/* Logo */}
                <div className="mb-8">
                    {currentLogo ? (
                        <img src={currentLogo} alt="Logo" className="h-10 w-auto" />
                    ) : (
                        <div className="text-2xl font-bold" style={{ color: primaryColor }}>
                            Ribo CRM
                        </div>
                    )}
                </div>

                {/* Step Indicator */}
                <div className="w-full max-w-lg mb-10">
                    <div className="flex items-center justify-between relative">
                        {/* Progress line background */}
                        <div className="absolute top-5 left-[calc(16.67%)] right-[calc(16.67%)] h-0.5 bg-gray-200" />
                        {/* Progress line filled */}
                        <div
                            className="absolute top-5 left-[calc(16.67%)] h-0.5 transition-all duration-500 ease-out"
                            style={{
                                backgroundColor: primaryColor,
                                width: currentStep === 1 ? '0%' : currentStep === 2 ? '22%' : currentStep === 3 ? '44%' : '66%',
                            }}
                        />

                        {steps.map((step) => {
                            const StepIcon = step.icon;
                            const isCompleted = step.number < currentStep;
                            const isCurrent = step.number === currentStep;

                            return (
                                <div key={step.number} className="flex flex-col items-center relative z-10 flex-1">
                                    <div
                                        className={`w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300 ${isCompleted
                                            ? 'text-white shadow-lg'
                                            : isCurrent
                                                ? 'text-white shadow-lg ring-4 ring-opacity-30'
                                                : 'bg-gray-100 text-gray-400 border-2 border-gray-200'
                                            }`}
                                        style={{
                                            ...(isCompleted || isCurrent
                                                ? { backgroundColor: primaryColor }
                                                : {}),
                                            ...(isCurrent
                                                ? { ringColor: primaryColor }
                                                : {}),
                                        }}
                                    >
                                        {isCompleted ? (
                                            <Check className="w-5 h-5" />
                                        ) : (
                                            <StepIcon className="w-5 h-5" />
                                        )}
                                    </div>
                                    <span
                                        className={`mt-2 text-xs font-medium transition-colors duration-300 ${isCompleted || isCurrent ? 'text-gray-900' : 'text-gray-400'
                                            }`}
                                    >
                                        {t(step.label)}
                                    </span>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Main Card */}
                <div className="w-full max-w-lg">
                    <div className="relative">
                        {/* Corner accents */}
                        <div className="absolute -top-2 -left-2 w-5 h-5 border-l-2 border-t-2 rounded-tl-md" style={{ borderColor: primaryColor }} />
                        <div className="absolute -bottom-2 -right-2 w-5 h-5 border-r-2 border-b-2 rounded-br-md" style={{ borderColor: primaryColor }} />

                        <div className="bg-white border border-gray-200 rounded-xl p-8 shadow-sm">
                            {/* Header */}
                            <div className="text-center mb-6">
                                <h1 className="text-2xl font-semibold text-gray-900 mb-1.5">{title}</h1>
                                <div className="w-12 h-0.5 mx-auto mb-3" style={{ backgroundColor: primaryColor }} />
                                {description && (
                                    <p className="text-gray-500 text-sm">{description}</p>
                                )}
                            </div>

                            {children}
                        </div>
                    </div>
                </div>

                {/* Footer */}
                <div className="mt-8 text-sm text-gray-400">
                    © 2026 Ribo CRM
                </div>
            </div>
        </div>
    );
}
