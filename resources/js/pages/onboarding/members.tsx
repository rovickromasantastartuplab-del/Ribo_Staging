import { useForm, router } from '@inertiajs/react';
import { Plus, Trash2, UserPlus, Mail, User } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from 'react-i18next';
import OnboardingLayout from '@/layouts/onboarding-layout';
import AuthButton from '@/components/auth/auth-button';
import { useBrand } from '@/contexts/BrandContext';
import { THEME_COLORS } from '@/hooks/use-appearance';

type Member = {
    name: string;
    email: string;
    role_id: string;
};

type MembersForm = {
    members: Member[];
};

interface Role {
    id: number;
    name: string;
    label: string;
}

interface ExistingMember {
    id: number;
    name: string;
    email: string;
}

export default function OnboardingMembers({
    maxUsers,
    currentCount,
    existingMembers,
    companyName,
    roles,
}: {
    maxUsers: number;
    currentCount: number;
    existingMembers: ExistingMember[];
    companyName: string;
    roles: Role[];
}) {
    const { t } = useTranslation();
    const { themeColor, customColor } = useBrand();
    const primaryColor = themeColor === 'custom' ? customColor : THEME_COLORS[themeColor as keyof typeof THEME_COLORS];

    const availableSlots = Math.max(0, maxUsers - currentCount);

    const defaultRoleId = roles.length > 0 ? roles[0].id.toString() : '';

    const { data, setData, post, processing, errors } = useForm<MembersForm>({
        members: [{ name: '', email: '', role_id: defaultRoleId }],
    });

    const addMember = () => {
        if (data.members.length < availableSlots) {
            setData('members', [...data.members, { name: '', email: '', role_id: defaultRoleId }]);
        }
    };

    const removeMember = (index: number) => {
        if (data.members.length > 1) {
            setData('members', data.members.filter((_, i) => i !== index));
        }
    };

    const updateMember = (index: number, field: keyof Member, value: string) => {
        const updated = [...data.members];
        updated[index] = { ...updated[index], [field]: value };
        setData('members', updated);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('onboarding.members.store'));
    };

    const skip = () => {
        router.post(route('onboarding.skip'), { current_step: 'members' });
    };

    return (
        <OnboardingLayout
            currentStep={4}
            title={t("Invite your team")}
            description={t("Add team members to :company. You can add up to :count more member(s) on your current plan.", {
                company: companyName,
                count: availableSlots,
            })}
        >
            {/* Plan info badge */}
            <div className="flex items-center justify-center mb-6">
                <div className="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-full border" style={{
                    color: primaryColor,
                    borderColor: primaryColor + '40',
                    backgroundColor: primaryColor + '08',
                }}>
                    <UserPlus className="w-3.5 h-3.5" />
                    {currentCount}/{maxUsers} {t("members used")}
                </div>
            </div>

            {/* Existing members */}
            {existingMembers.length > 0 && (
                <div className="mb-6">
                    <Label className="text-gray-700 font-medium mb-2 block text-sm">{t("Current members")}</Label>
                    <div className="space-y-2">
                        {existingMembers.map((member) => (
                            <div key={member.id} className="flex items-center gap-3 p-2.5 bg-gray-50 rounded-lg text-sm">
                                <div className="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-medium" style={{ backgroundColor: primaryColor }}>
                                    {member.name.charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <p className="font-medium text-gray-900">{member.name}</p>
                                    <p className="text-gray-500 text-xs">{member.email}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {availableSlots > 0 ? (
                <form className="space-y-4" onSubmit={submit}>
                    {/* Member errors */}
                    {typeof errors.members === 'string' && (
                        <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
                            {errors.members}
                        </div>
                    )}

                    <div className="space-y-4">
                        {data.members.map((member, index) => (
                            <div key={index} className="p-4 border border-gray-200 rounded-lg bg-gray-50/50 relative group">
                                {data.members.length > 1 && (
                                    <button
                                        type="button"
                                        onClick={() => removeMember(index)}
                                        className="absolute top-2 right-2 p-1 text-gray-400 hover:text-red-500 transition-colors opacity-0 group-hover:opacity-100"
                                    >
                                        <Trash2 className="w-4 h-4" />
                                    </button>
                                )}

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <Label className="text-gray-600 text-xs mb-1 block">{t("Name")}</Label>
                                        <div className="relative">
                                            <div className="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                                                <User className="h-3.5 w-3.5 text-gray-400" />
                                            </div>
                                            <Input
                                                type="text"
                                                required
                                                value={member.name}
                                                onChange={(e) => updateMember(index, 'name', e.target.value)}
                                                placeholder={t("Full name")}
                                                className="pl-8 text-sm border-gray-300 rounded-md"
                                                style={{ '--tw-ring-color': primaryColor } as React.CSSProperties}
                                            />
                                        </div>
                                        <InputError message={(errors as any)?.[`members.${index}.name`]} />
                                    </div>

                                    <div>
                                        <Label className="text-gray-600 text-xs mb-1 block">{t("Email")}</Label>
                                        <div className="relative">
                                            <div className="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                                                <Mail className="h-3.5 w-3.5 text-gray-400" />
                                            </div>
                                            <Input
                                                type="email"
                                                required
                                                value={member.email}
                                                onChange={(e) => updateMember(index, 'email', e.target.value)}
                                                placeholder={t("email@company.com")}
                                                className="pl-8 text-sm border-gray-300 rounded-md"
                                                style={{ '--tw-ring-color': primaryColor } as React.CSSProperties}
                                            />
                                        </div>
                                        <InputError message={(errors as any)?.[`members.${index}.email`]} />
                                    </div>
                                </div>

                                {roles.length > 0 && (
                                    <div className="mt-3">
                                        <Label className="text-gray-600 text-xs mb-1 block">{t("Role")}</Label>
                                        <select
                                            value={member.role_id}
                                            onChange={(e) => updateMember(index, 'role_id', e.target.value)}
                                            required
                                            className="w-full text-sm border border-gray-300 rounded-md px-3 py-2 bg-white focus:outline-none focus:ring-2 transition-all"
                                            style={{ '--tw-ring-color': primaryColor } as React.CSSProperties}
                                        >
                                            {roles.map((role) => (
                                                <option key={role.id} value={role.id}>
                                                    {role.label || role.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>

                    {/* Add member button */}
                    {data.members.length < availableSlots && (
                        <button
                            type="button"
                            onClick={addMember}
                            className="w-full py-2 text-sm font-medium rounded-md border-2 border-dashed transition-all duration-200 hover:bg-gray-50"
                            style={{
                                color: primaryColor,
                                borderColor: primaryColor + '60',
                            }}
                        >
                            <Plus className="w-4 h-4 inline-block mr-1" />
                            {t("Add another member")}
                        </button>
                    )}

                    <div className="flex gap-3 pt-2">
                        <button
                            type="button"
                            onClick={skip}
                            className="flex-1 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-md transition-all duration-200"
                        >
                            {t("Skip for now")}
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
            ) : (
                <div className="text-center py-4">
                    <p className="text-gray-500 text-sm mb-6">
                        {t("You've reached your plan's member limit. Upgrade your plan to add more team members.")}
                    </p>
                    <div className="flex gap-3">
                        <button
                            type="button"
                            onClick={skip}
                            className="flex-1 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-md transition-all duration-200"
                        >
                            {t("Skip for now")}
                        </button>
                        <AuthButton
                            processing={false}
                            onClick={() => router.post(route('onboarding.skip'), { current_step: 'members' })}
                            className="flex-1 text-white py-2.5 text-sm font-medium tracking-wide transition-all duration-200 rounded-md shadow-md hover:shadow-lg transform hover:scale-[1.02]"
                            style={{ backgroundColor: primaryColor }}
                        >
                            {t("Continue")}
                        </AuthButton>
                    </div>
                </div>
            )}
        </OnboardingLayout>
    );
}
