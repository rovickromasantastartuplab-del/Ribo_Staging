import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Switch } from '@/components/ui/switch';
import { useTranslation } from 'react-i18next';

interface Module {
    key: string;
    label: string;
    enabled: boolean;
}

export default function ModuleVisibilitySettings() {
    const { t } = useTranslation();
    const [modules, setModules] = useState<Module[]>([]);
    const [loading, setLoading] = useState(true);
    const [toggling, setToggling] = useState<string | null>(null);

    useEffect(() => {
        fetch(route('settings.module-visibility.index'))
            .then(res => res.json())
            .then(data => {
                setModules(data.modules || []);
                setLoading(false);
            })
            .catch(() => setLoading(false));
    }, []);

    const handleToggle = (moduleKey: string, currentEnabled: boolean) => {
        setToggling(moduleKey);
        const newEnabled = !currentEnabled;

        router.post(
            route('settings.module-visibility.toggle'),
            { module_key: moduleKey, enabled: newEnabled },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setModules(prev =>
                        prev.map(m =>
                            m.key === moduleKey ? { ...m, enabled: newEnabled } : m
                        )
                    );
                    setToggling(null);
                },
                onError: () => {
                    setToggling(null);
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

            {loading ? (
                <p className="text-sm text-muted-foreground">{t('Loading modules...')}</p>
            ) : (
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
            )}
        </div>
    );
}
