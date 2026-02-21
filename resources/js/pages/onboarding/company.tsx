import { useForm } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from 'react-i18next';
import OnboardingLayout from '@/layouts/onboarding-layout';
import AuthButton from '@/components/auth/auth-button';
import { useBrand } from '@/contexts/BrandContext';
import { THEME_COLORS } from '@/hooks/use-appearance';

type CompanyForm = {
    company_name: string;
};

export default function OnboardingCompany({ companyName, isLegacy }: { companyName?: string, isLegacy?: boolean }) {
    const { t } = useTranslation();
    const { themeColor, customColor } = useBrand();
    const primaryColor = themeColor === 'custom' ? customColor : THEME_COLORS[themeColor as keyof typeof THEME_COLORS];

    const { data, setData, post, processing, errors } = useForm<CompanyForm>({
        company_name: companyName || '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('onboarding.company.store'));
    };

    return (
        <OnboardingLayout
            currentStep={1}
            isLegacy={isLegacy}
            title={t("What's your company name?")}
            description={t("This will be used as your workspace name throughout the CRM.")}
        >
            <form className="space-y-6" onSubmit={submit}>
                <div>
                    <Label htmlFor="company_name" className="text-gray-700 dark:text-gray-300 font-medium mb-2 block">
                        {t("Company Name")}
                    </Label>
                    <div className="relative">
                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <Building2 className="h-4 w-4 text-gray-400" />
                        </div>
                        <Input
                            id="company_name"
                            type="text"
                            required
                            autoFocus
                            autoComplete="organization"
                            value={data.company_name}
                            onChange={(e) => setData('company_name', e.target.value)}
                            placeholder={t("e.g. Acme Corporation")}
                            className="w-full pl-10 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg transition-all duration-200"
                            style={{ '--tw-ring-color': primaryColor } as React.CSSProperties}
                        />
                    </div>
                    <InputError message={errors.company_name} />
                </div>

                <AuthButton
                    tabIndex={2}
                    processing={processing}
                    className="w-full text-white py-2.5 text-sm font-medium tracking-wide transition-all duration-200 rounded-md shadow-md hover:shadow-lg transform hover:scale-[1.02]"
                    style={{ backgroundColor: primaryColor }}
                >
                    {t("Continue")}
                </AuthButton>
            </form>
        </OnboardingLayout>
    );
}
