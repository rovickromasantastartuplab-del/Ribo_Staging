import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Switch } from '@/components/ui/switch';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';
 
interface Module {
    key: string;
    label: string;
    enabled: boolean;
}
 
export default function ModuleVisibilitySettings() {
    const { t } = useTranslation();
    const { moduleVisibility } = usePage().props as any;
    const [toggling, setToggling] = useState<string | null>(null);
 
    const modules: Module[] = moduleVisibility?.modules || [];
 
    const handleToggle = (moduleKey: string, currentEnabled: boolean) => {
        setToggling(moduleKey);
        const newEnabled = !currentEnabled;
 
        router.post(
            route('settings.module-visibility.toggle'),
            { module_key: moduleKey, enabled: newEnabled },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    setToggling(null);
                    const successMessage = (page.props as any).flash?.success;
                    if (successMessage) {
                        toast.success(t(successMessage));
                    } else {
                        toast.success(t('Module visibility updated'));
                    }
                },
                onError: (errors) => {
                    setToggling(null);
                    const errorMessage = Object.values(errors).join(', ') || t('Failed to update module visibility');
                    toast.error(errorMessage);
                },
            }
        );
    };

    return (
        <div>
            <div className="mb-4">
                <h3 className="text-base font-medium text-foreground">{t('Module Visibility')}</h3>
                <p className="text-sm text-muted-foreground mt-1">
                    {t('Toggle CRM modules on or off for all non-superadmin users globally.')}
                </p>
            </div>
 
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {modules.map(module => (
                    <div
                        key={module.key}
                        className="flex items-center justify-between rounded-lg border px-4 py-3"
                    >
                        <span className="text-sm font-medium">{t(module.label)}</span>
                        <Switch
                            checked={module.enabled}
                            disabled={toggling === module.key}
                            onCheckedChange={() => handleToggle(module.key, module.enabled)}
                        />
                    </div>
                ))}
            </div>
        </div>
    );
}
