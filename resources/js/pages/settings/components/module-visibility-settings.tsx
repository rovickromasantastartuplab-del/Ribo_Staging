import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Switch } from '@/components/ui/switch';
import { Button } from '@/components/ui/button';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';

interface Module {
    key: string;
    label: string;
    enabled: boolean;
    [key: string]: any;
}

export default function ModuleVisibilitySettings() {
    const { t } = useTranslation();
    const { moduleVisibility } = usePage().props as any;
    const initialModules: Module[] = moduleVisibility?.modules || [];
    
    const [localModules, setLocalModules] = useState<Module[]>(initialModules);
    const [isSaving, setIsSaving] = useState(false);

    useEffect(() => {
        setLocalModules(moduleVisibility?.modules || []);
    }, [moduleVisibility]);

    const handleToggle = (moduleKey: string) => {
        setLocalModules(prev => prev.map(m => 
            m.key === moduleKey ? { ...m, enabled: !m.enabled } : m
        ));
    };

    const handleSave = () => {
        setIsSaving(true);
        router.post(
            route('settings.module-visibility.update-all'),
            { modules: localModules },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    setIsSaving(false);
                    const successMessage = (page.props as any).flash?.success;
                    const errorMessage = (page.props as any).flash?.error;
                    
                    if (successMessage) {
                        toast.success(successMessage);
                    } else if (errorMessage) {
                        toast.error(errorMessage);
                    } else {
                        toast.success(t('Module visibility updated'));
                    }
                },
                onError: (errors) => {
                    setIsSaving(false);
                    const errorMessage = errors.error || Object.values(errors).join(', ') || t('Failed to update module visibility');
                    toast.error(errorMessage);
                },
                onFinish: () => setIsSaving(false)
            }
        );
    };

    return (
        <div>
            <div className="flex items-center justify-between mb-4">
                <div>
                    <h3 className="text-base font-medium text-foreground">{t('Module Visibility')}</h3>
                    <p className="text-sm text-muted-foreground mt-1">
                        {t('Toggle CRM modules on or off for all non-superadmin users globally.')}
                    </p>
                </div>
                <Button 
                    onClick={handleSave} 
                    disabled={isSaving}
                    size="sm"
                >
                    {isSaving ? t('Saving...') : t('Save Changes')}
                </Button>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {localModules.map(module => (
                    <div
                        key={module.key}
                        className="flex items-center justify-between rounded-lg border px-4 py-3"
                    >
                        <span className="text-sm font-medium">{t(module.label)}</span>
                        <Switch
                            checked={module.enabled}
                            disabled={isSaving}
                            onCheckedChange={() => handleToggle(module.key)}
                        />
                    </div>
                ))}
            </div>
        </div>
    );
}
