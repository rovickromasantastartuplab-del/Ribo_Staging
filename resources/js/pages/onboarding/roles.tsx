import { useForm, router } from '@inertiajs/react';
import { Shield, ChevronDown, ChevronUp, Check, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { useTranslation } from 'react-i18next';
import OnboardingLayout from '@/layouts/onboarding-layout';
import AuthButton from '@/components/auth/auth-button';
import { useBrand } from '@/contexts/BrandContext';
import { THEME_COLORS } from '@/hooks/use-appearance';

interface Permission {
    id: number;
    name: string;
    label: string;
    module: string;
}

interface Role {
    id: number;
    name: string;
    label: string;
    description: string | null;
    created_by: number | null;
    permissions: Permission[];
}

interface Member {
    id: number;
    name: string;
    email: string;
    roles: { id: number; name: string; label: string }[];
}

type CustomRole = {
    name: string;
    description: string;
    permissions: number[];
};

type MemberRoleAssignment = {
    user_id: number;
    role_id: number;
};

type RolesForm = {
    custom_roles: CustomRole[];
    member_roles: MemberRoleAssignment[];
};

export default function OnboardingRoles({
    roles,
    permissions,
    members,
    companyName,
}: {
    roles: Role[];
    permissions: Record<string, Permission[]>;
    members: Member[];
    companyName: string;
}) {
    const { t } = useTranslation();
    const { themeColor, customColor } = useBrand();
    const primaryColor = themeColor === 'custom' ? customColor : THEME_COLORS[themeColor as keyof typeof THEME_COLORS];

    const [expandedModules, setExpandedModules] = useState<Record<string, boolean>>({});
    const [showCreateRole, setShowCreateRole] = useState(false);
    const [savedRoles, setSavedRoles] = useState<Record<number, boolean>>({});

    const saveCustomRole = (index: number) => {
        const role = data.custom_roles[index];
        if (role.name.trim() && role.permissions.length > 0) {
            setSavedRoles((prev) => ({ ...prev, [index]: true }));
        }
    };

    const editCustomRole = (index: number) => {
        setSavedRoles((prev) => ({ ...prev, [index]: false }));
    };

    const { data, setData, post, processing, errors } = useForm<RolesForm>({
        custom_roles: [],
        member_roles: members.map((m) => ({
            user_id: m.id,
            role_id: m.roles?.[0]?.id || 0,
        })),
    });

    const toggleModule = (module: string) => {
        setExpandedModules((prev) => ({ ...prev, [module]: !prev[module] }));
    };

    const addCustomRole = () => {
        setData('custom_roles', [...data.custom_roles, { name: '', description: '', permissions: [] }]);
        setShowCreateRole(true);
    };

    const removeCustomRole = (index: number) => {
        setData('custom_roles', data.custom_roles.filter((_, i) => i !== index));
    };

    const updateCustomRole = (index: number, field: keyof CustomRole, value: any) => {
        const updated = [...data.custom_roles];
        updated[index] = { ...updated[index], [field]: value };
        setData('custom_roles', updated);
    };

    const togglePermission = (roleIndex: number, permissionId: number) => {
        const updated = [...data.custom_roles];
        const perms = updated[roleIndex].permissions;
        if (perms.includes(permissionId)) {
            updated[roleIndex].permissions = perms.filter((p) => p !== permissionId);
        } else {
            updated[roleIndex].permissions = [...perms, permissionId];
        }
        setData('custom_roles', updated);
    };

    const toggleAllModulePermissions = (roleIndex: number, module: string) => {
        const modulePerms = permissions[module] || [];
        const permIds = modulePerms.map((p) => p.id);
        const updated = [...data.custom_roles];
        const currentPerms = updated[roleIndex].permissions;
        const allSelected = permIds.every((id) => currentPerms.includes(id));

        if (allSelected) {
            updated[roleIndex].permissions = currentPerms.filter((p) => !permIds.includes(p));
        } else {
            updated[roleIndex].permissions = [...new Set([...currentPerms, ...permIds])];
        }
        setData('custom_roles', updated);
    };

    const updateMemberRole = (memberIndex: number, roleId: number) => {
        const updated = [...data.member_roles];
        updated[memberIndex] = { ...updated[memberIndex], role_id: roleId };
        setData('member_roles', updated);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        // Filter out empty custom roles and unassigned member roles before submitting
        setData({
            custom_roles: data.custom_roles.filter((r) => r.name.trim() !== ''),
            member_roles: data.member_roles.filter((m) => m.role_id > 0),
        });
        post(route('onboarding.roles.store'));
    };

    const skip = () => {
        router.post(route('onboarding.skip'), { current_step: 'roles' });
    };

    // Combine existing + about-to-be-created roles for member assignment
    const allRoles = [
        ...roles,
        ...data.custom_roles
            .filter((r) => r.name.trim() !== '')
            .map((r, i) => ({
                id: -(i + 1), // Negative IDs for unsaved roles
                name: r.name,
                label: r.name,
            })),
    ];

    return (
        <OnboardingLayout
            currentStep={3}
            title={t("Set up roles & permissions")}
            description={t("Configure roles for your team at :company.", { company: companyName })}
        >
            <form className="space-y-6" onSubmit={submit}>
                {/* Existing default roles */}
                {roles.length > 0 && (
                    <div>
                        <Label className="text-gray-700 font-medium mb-3 block text-sm">{t("Default roles")}</Label>
                        <div className="space-y-2">
                            {roles.map((role) => (
                                <div key={role.id} className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-100">
                                    <div className="w-8 h-8 rounded-full flex items-center justify-center" style={{ backgroundColor: primaryColor + '15', color: primaryColor }}>
                                        <Shield className="w-4 h-4" />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className="font-medium text-gray-900 text-sm">{role.label || role.name}</p>
                                        {role.description && (
                                            <p className="text-gray-500 text-xs truncate">{role.description}</p>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Custom roles */}
                {data.custom_roles.length > 0 && (
                    <div>
                        <Label className="text-gray-700 font-medium mb-3 block text-sm">{t("Custom roles")}</Label>
                        <div className="space-y-4">
                            {data.custom_roles.map((customRole, roleIndex) => (
                                <div key={roleIndex} className="border border-gray-200 rounded-lg overflow-hidden">
                                    {savedRoles[roleIndex] ? (
                                        /* Saved/collapsed view */
                                        <div className="flex items-center gap-3 p-3 bg-gray-50">
                                            <div className="w-8 h-8 rounded-full flex items-center justify-center" style={{ backgroundColor: primaryColor + '15', color: primaryColor }}>
                                                <Check className="w-4 h-4" />
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <p className="font-medium text-gray-900 text-sm">{customRole.name}</p>
                                                {customRole.description && (
                                                    <p className="text-gray-500 text-xs truncate">{customRole.description}</p>
                                                )}
                                            </div>
                                            <span className="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                                                {customRole.permissions.length} {t("perms")}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => editCustomRole(roleIndex)}
                                                className="text-xs font-medium px-2 py-1 rounded transition-colors hover:bg-gray-200"
                                                style={{ color: primaryColor }}
                                            >
                                                {t("Edit")}
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => removeCustomRole(roleIndex)}
                                                className="p-1 text-gray-400 hover:text-red-500 transition-colors"
                                            >
                                                <Trash2 className="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    ) : (
                                        /* Editing view */
                                        <div className="p-4 bg-white">
                                            <div className="flex items-center gap-3 mb-3">
                                                <Input
                                                    type="text"
                                                    value={customRole.name}
                                                    onChange={(e) => updateCustomRole(roleIndex, 'name', e.target.value)}
                                                    placeholder={t("Role name")}
                                                    className="flex-1 text-sm border-gray-300 rounded-md"
                                                    style={{ '--tw-ring-color': primaryColor } as React.CSSProperties}
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => removeCustomRole(roleIndex)}
                                                    className="p-1.5 text-gray-400 hover:text-red-500 transition-colors"
                                                >
                                                    <Trash2 className="w-4 h-4" />
                                                </button>
                                            </div>
                                            <InputError message={(errors as any)?.[`custom_roles.${roleIndex}.name`]} />

                                            {/* Permission modules */}
                                            <div className="space-y-1 max-h-60 overflow-y-auto">
                                                {Object.entries(permissions).map(([module, modulePerms]) => {
                                                    const permIds = modulePerms.map((p) => p.id);
                                                    const selectedCount = permIds.filter((id) => customRole.permissions.includes(id)).length;
                                                    const allSelected = selectedCount === permIds.length;

                                                    return (
                                                        <div key={module} className="border border-gray-100 rounded-md">
                                                            <button
                                                                type="button"
                                                                onClick={() => toggleModule(`${roleIndex}-${module}`)}
                                                                className="w-full flex items-center justify-between px-3 py-2 hover:bg-gray-50 transition-colors"
                                                            >
                                                                <div className="flex items-center gap-2">
                                                                    <Checkbox
                                                                        checked={allSelected}
                                                                        onClick={(e) => {
                                                                            e.stopPropagation();
                                                                            toggleAllModulePermissions(roleIndex, module);
                                                                        }}
                                                                        className="w-3.5 h-3.5"
                                                                    />
                                                                    <span className="text-xs font-medium text-gray-700 capitalize">
                                                                        {module.replace(/_/g, ' ')}
                                                                    </span>
                                                                    {selectedCount > 0 && (
                                                                        <span className="text-[10px] px-1.5 py-0.5 rounded-full font-medium" style={{
                                                                            color: primaryColor,
                                                                            backgroundColor: primaryColor + '15',
                                                                        }}>
                                                                            {selectedCount}/{permIds.length}
                                                                        </span>
                                                                    )}
                                                                </div>
                                                                {expandedModules[`${roleIndex}-${module}`] ? (
                                                                    <ChevronUp className="w-3.5 h-3.5 text-gray-400" />
                                                                ) : (
                                                                    <ChevronDown className="w-3.5 h-3.5 text-gray-400" />
                                                                )}
                                                            </button>

                                                            {expandedModules[`${roleIndex}-${module}`] && (
                                                                <div className="px-3 pb-2 grid grid-cols-2 gap-1">
                                                                    {modulePerms.map((perm) => (
                                                                        <label
                                                                            key={perm.id}
                                                                            className="flex items-center gap-1.5 py-1 px-1 rounded hover:bg-gray-50 cursor-pointer"
                                                                        >
                                                                            <Checkbox
                                                                                checked={customRole.permissions.includes(perm.id)}
                                                                                onClick={() => togglePermission(roleIndex, perm.id)}
                                                                                className="w-3 h-3"
                                                                            />
                                                                            <span className="text-[11px] text-gray-600">
                                                                                {perm.label}
                                                                            </span>
                                                                        </label>
                                                                    ))}
                                                                </div>
                                                            )}
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                            <InputError message={(errors as any)?.[`custom_roles.${roleIndex}.permissions`]} />

                                            {/* Save Role button */}
                                            <button
                                                type="button"
                                                onClick={() => saveCustomRole(roleIndex)}
                                                disabled={!customRole.name.trim() || customRole.permissions.length === 0}
                                                className="mt-3 w-full py-2 text-sm font-medium text-white rounded-md transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                                style={{ backgroundColor: primaryColor }}
                                            >
                                                <Check className="w-4 h-4 inline-block mr-1" />
                                                {t("Save Role")}
                                            </button>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Add custom role button */}
                <button
                    type="button"
                    onClick={addCustomRole}
                    className="w-full py-2 text-sm font-medium rounded-md border-2 border-dashed transition-all duration-200 hover:bg-gray-50"
                    style={{
                        color: primaryColor,
                        borderColor: primaryColor + '60',
                    }}
                >
                    <Plus className="w-4 h-4 inline-block mr-1" />
                    {t("Create custom role")}
                </button>

                {/* Assign roles to members */}
                {members.length > 0 && (
                    <div>
                        <Label className="text-gray-700 font-medium mb-3 block text-sm">{t("Assign roles to members")}</Label>
                        <div className="space-y-2">
                            {members.map((member, index) => (
                                <div key={member.id} className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                    <div className="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-medium" style={{ backgroundColor: primaryColor }}>
                                        {member.name.charAt(0).toUpperCase()}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className="font-medium text-gray-900 text-sm truncate">{member.name}</p>
                                        <p className="text-gray-500 text-xs truncate">{member.email}</p>
                                    </div>
                                    <select
                                        value={data.member_roles[index]?.role_id || 0}
                                        onChange={(e) => updateMemberRole(index, parseInt(e.target.value))}
                                        className="text-xs border border-gray-300 rounded-md px-2 py-1.5 bg-white focus:outline-none focus:ring-2 min-w-[120px]"
                                        style={{ '--tw-ring-color': primaryColor } as React.CSSProperties}
                                    >
                                        <option value={0}>{t("No role")}</option>
                                        {roles.map((role) => (
                                            <option key={role.id} value={role.id}>
                                                {role.label || role.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Actions */}
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
        </OnboardingLayout>
    );
}
