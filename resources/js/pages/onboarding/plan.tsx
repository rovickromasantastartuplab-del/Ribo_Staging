import { useForm, router } from '@inertiajs/react';
import { Check, Crown, Info, Sparkles } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import { useTranslation } from 'react-i18next';
import OnboardingLayout from '@/layouts/onboarding-layout';
import AuthButton from '@/components/auth/auth-button';
import { useBrand } from '@/contexts/BrandContext';
import { THEME_COLORS } from '@/hooks/use-appearance';

interface Plan {
    id: number;
    name: string;
    price: number;
    yearly_price: number | null;
    duration: string;
    description: string | null;
    max_users: number;
    max_projects: number;
    max_contacts: number;
    max_accounts: number;
    storage_limit: number;
    is_default: boolean;
    is_trial: boolean;
    trial_day: number | null;
    module: string[] | null;
}

interface Props {
    plans: Plan[];
    currentPlanId: number | null;
    defaultPlanId: number | null;
}

export default function OnboardingPlan({ plans, currentPlanId, defaultPlanId }: Props) {
    const { t } = useTranslation();
    const { themeColor, customColor } = useBrand();
    const primaryColor = themeColor === 'custom' ? customColor : THEME_COLORS[themeColor as keyof typeof THEME_COLORS];

    const [selectedPlanId, setSelectedPlanId] = useState<number>(currentPlanId || defaultPlanId || plans[0]?.id);

    const { data, setData, post, processing } = useForm({
        plan_id: selectedPlanId,
    });

    const selectPlan = (planId: number) => {
        setSelectedPlanId(planId);
        setData('plan_id', planId);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('onboarding.plan.store'));
    };

    const handleSkip = () => {
        router.post(route('onboarding.skip'), { current_step: 'plan' });
    };

    const formatFeatures = (plan: Plan) => {
        const features: string[] = [];
        features.push(plan.max_users === -1 ? t('Unlimited users') : `${plan.max_users} ${t('users')}`);
        features.push(plan.max_projects === -1 ? t('Unlimited projects') : `${plan.max_projects} ${t('projects')}`);
        features.push(plan.max_contacts === -1 ? t('Unlimited contacts') : `${plan.max_contacts} ${t('contacts')}`);
        features.push(plan.max_accounts === -1 ? t('Unlimited accounts') : `${plan.max_accounts} ${t('accounts')}`);
        if (plan.storage_limit) {
            features.push(`${plan.storage_limit} GB ${t('storage')}`);
        }
        return features;
    };

    return (
        <OnboardingLayout
            currentStep={2}
            title={t("Choose your plan")}
            description={t("Select a plan that fits your business needs. You can always change it later.")}
        >
            <form onSubmit={submit}>
                <div className="space-y-3 mb-6 max-h-[400px] overflow-y-auto pr-1">
                    {plans.map((plan) => {
                        const isSelected = selectedPlanId === plan.id;
                        const isDefault = plan.id === defaultPlanId;
                        const features = formatFeatures(plan);

                        return (
                            <div
                                key={plan.id}
                                onClick={() => selectPlan(plan.id)}
                                className={`relative border-2 rounded-xl p-4 cursor-pointer transition-all duration-200 hover:shadow-md ${isSelected
                                    ? 'shadow-md'
                                    : 'border-gray-200 bg-white hover:border-gray-300'
                                    }`}
                                style={isSelected ? {
                                    borderColor: primaryColor,
                                    backgroundColor: primaryColor + '08',
                                } : {}}
                            >
                                {/* Badges */}
                                <div className="absolute top-3 right-3 flex gap-1.5">
                                    {isDefault && (
                                        <span className="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-green-100 text-green-700">
                                            {t("FREE")}
                                        </span>
                                    )}
                                    {!isDefault && plan.price > 0 && (
                                        <span className="text-[10px] font-semibold px-2 py-0.5 rounded-full" style={{
                                            backgroundColor: primaryColor + '15',
                                            color: primaryColor,
                                        }}>
                                            <Crown className="w-3 h-3 inline-block mr-0.5 -mt-0.5" />
                                            {t("PRO")}
                                        </span>
                                    )}
                                </div>

                                <div className="flex items-start gap-3">
                                    {/* Radio indicator */}
                                    <div
                                        className={`mt-0.5 w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0 transition-all ${isSelected ? 'border-transparent' : 'border-gray-300'
                                            }`}
                                        style={isSelected ? { backgroundColor: primaryColor } : {}}
                                    >
                                        {isSelected && <Check className="w-3 h-3 text-white" />}
                                    </div>

                                    <div className="flex-1 min-w-0">
                                        {/* Plan name & price */}
                                        <div className="flex items-baseline gap-2 mb-1">
                                            <h3 className="text-sm font-semibold text-gray-900">{plan.name}</h3>
                                            <div className="flex items-baseline gap-0.5">
                                                {plan.price > 0 ? (
                                                    <>
                                                        <span className="text-lg font-bold" style={{ color: primaryColor }}>
                                                            ${plan.price}
                                                        </span>
                                                        <span className="text-xs text-gray-400">/{t("mo")}</span>
                                                    </>
                                                ) : (
                                                    <span className="text-lg font-bold text-green-600">{t("Free")}</span>
                                                )}
                                            </div>
                                        </div>

                                        {plan.description && (
                                            <p className="text-xs text-gray-500 mb-2">{plan.description}</p>
                                        )}

                                        {/* Features */}
                                        <div className="flex flex-wrap gap-x-3 gap-y-1">
                                            {features.map((feature, idx) => (
                                                <span key={idx} className="text-[11px] text-gray-500 flex items-center gap-1">
                                                    <Sparkles className="w-3 h-3" style={{ color: primaryColor }} />
                                                    {feature}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Approval notice for paid plans */}
                {(() => {
                    const selectedPlan = plans.find(p => p.id === selectedPlanId);
                    const isPaid = selectedPlan && !selectedPlan.is_default && selectedPlan.price > 0;
                    return isPaid ? (
                        <div className="mb-4 p-3 rounded-lg bg-amber-50 border border-amber-200">
                            <div className="flex items-start gap-2">
                                <Info className="w-4 h-4 text-amber-600 mt-0.5 flex-shrink-0" />
                                <p className="text-xs text-amber-700">
                                    {t("Paid plans require admin approval. You'll start on the free plan and be upgraded once approved.")}
                                </p>
                            </div>
                        </div>
                    ) : null;
                })()}

                {/* Action buttons */}
                <div className="flex gap-3">
                    <button
                        type="button"
                        onClick={handleSkip}
                        className="flex-1 py-2.5 text-sm font-medium text-gray-500 bg-gray-100 rounded-md transition-all hover:bg-gray-200"
                    >
                        {t("Skip")}
                    </button>
                    <AuthButton
                        processing={processing}
                        className="flex-1 text-white py-2.5 text-sm font-medium tracking-wide transition-all duration-200 rounded-md shadow-md hover:shadow-lg transform hover:scale-[1.02]"
                        style={{ backgroundColor: primaryColor }}
                    >
                        {t("Continue")}
                    </AuthButton>
                </div>
            </form>
        </OnboardingLayout>
    );
}
