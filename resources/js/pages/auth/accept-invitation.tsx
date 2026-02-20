import { useForm } from '@inertiajs/react';
import { Lock, Shield, Building2 } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from 'react-i18next';
import AuthLayout from '@/layouts/auth-layout';
import AuthButton from '@/components/auth/auth-button';
import { useBrand } from '@/contexts/BrandContext';
import { THEME_COLORS } from '@/hooks/use-appearance';

type AcceptInvitationForm = {
    token: string;
    password: string;
    password_confirmation: string;
};

export default function AcceptInvitation({
    token,
    userName,
    userEmail,
    companyName,
}: {
    token: string;
    userName: string;
    userEmail: string;
    companyName: string;
}) {
    const { t } = useTranslation();
    const { themeColor, customColor } = useBrand();
    const primaryColor = themeColor === 'custom' ? customColor : THEME_COLORS[themeColor as keyof typeof THEME_COLORS];

    const { data, setData, post, processing, errors } = useForm<AcceptInvitationForm>({
        token,
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('invitation.complete'));
    };

    return (
        <AuthLayout
            title={t("Set up your account")}
            description={t("You've been invited to join :company. Set your password to get started.", { company: companyName })}
        >
            <form className="space-y-5" onSubmit={submit}>
                {/* Invitation info card */}
                <div className="p-4 rounded-lg border border-gray-200 bg-gray-50/50 space-y-2">
                    <div className="flex items-center gap-2 text-sm">
                        <Shield className="w-4 h-4 text-gray-400" />
                        <span className="text-gray-500">{t("Name")}</span>
                        <span className="font-medium text-gray-900 ml-auto">{userName}</span>
                    </div>
                    <div className="flex items-center gap-2 text-sm">
                        <Building2 className="w-4 h-4 text-gray-400" />
                        <span className="text-gray-500">{t("Email")}</span>
                        <span className="font-medium text-gray-900 ml-auto">{userEmail}</span>
                    </div>
                </div>

                <div className="space-y-4">
                    <div>
                        <Label htmlFor="password" className="text-gray-700 dark:text-gray-300 font-medium mb-2 block">{t("Create a password")}</Label>
                        <div className="relative">
                            <Input
                                id="password"
                                type="password"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="new-password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder={t("Enter your password")}
                                className="w-full border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg transition-all duration-200"
                                style={{ '--tw-ring-color': primaryColor } as React.CSSProperties}
                            />
                        </div>
                        <InputError message={errors.password} />
                    </div>

                    <div>
                        <Label htmlFor="password_confirmation" className="text-gray-700 dark:text-gray-300 font-medium mb-2 block">{t("Confirm password")}</Label>
                        <div className="relative">
                            <Input
                                id="password_confirmation"
                                type="password"
                                required
                                tabIndex={2}
                                autoComplete="new-password"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                placeholder={t("Confirm your password")}
                                className="w-full border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg transition-all duration-200"
                                style={{ '--tw-ring-color': primaryColor } as React.CSSProperties}
                            />
                        </div>
                        <InputError message={errors.password_confirmation} />
                    </div>
                </div>

                <InputError message={errors.token} />

                <AuthButton
                    tabIndex={3}
                    processing={processing}
                    className="w-full text-white py-2.5 text-sm font-medium tracking-wide transition-all duration-200 rounded-md shadow-md hover:shadow-lg transform hover:scale-[1.02]"
                    style={{ backgroundColor: primaryColor }}
                >
                    {t("Verify & Activate Account")}
                </AuthButton>
            </form>
        </AuthLayout>
    );
}
