import React from 'react';
import { PageTemplate } from '@/components/page-template';
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { router } from '@inertiajs/react';
import { useState } from 'react';


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
  enable_branding: string;
  enable_chatgpt: string;
  enable_wedding_suppliers: string;
  module: string[] | null;
  is_trial: string | null;
  trial_day: number;
  is_plan_enable: string;
  is_default: boolean;
}

interface Props {
  plan?: Plan;
  hasDefaultPlan?: boolean;
  otherDefaultPlanExists?: boolean;
}

export default function PlanForm({ plan, hasDefaultPlan = false, otherDefaultPlanExists = false }: Props) {
  const { t } = useTranslation();
  const [processing, setProcessing] = useState(false);

  const isEdit = !!plan;

  const [formData, setFormData] = useState({
    name: plan?.name || '',
    price: plan?.price || 0,
    yearly_price: plan?.yearly_price || undefined,
    duration: plan?.duration || 'monthly',
    description: plan?.description || '',
    max_users: plan?.max_users || 0,
    max_projects: plan?.max_projects || 0,
    max_contacts: plan?.max_contacts || 0,
    max_accounts: plan?.max_accounts || 0,
    storage_limit: plan?.storage_limit || 0,
    enable_branding: plan?.enable_branding || 'on',
    enable_chatgpt: plan?.enable_chatgpt || 'off',
    enable_wedding_suppliers: plan?.enable_wedding_suppliers || 'off',
    is_trial: plan?.is_trial || null,
    trial_day: plan?.trial_day || 0,
    is_plan_enable: plan?.is_plan_enable || 'on',
    is_default: plan?.is_default || false,
  });

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleSwitchChange = (name: string, checked: boolean) => {
    setFormData(prev => ({ ...prev, [name]: checked ? 'on' : 'off' }));
  };

  const handleDefaultChange = (checked: boolean) => {
    setFormData(prev => ({ ...prev, is_default: checked }));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setProcessing(true);

    if (isEdit) {
      router.put(route('plans.update', plan.id), formData, {
        onFinish: () => setProcessing(false)
      });
    } else {
      router.post(route('plans.store'), formData, {
        onFinish: () => setProcessing(false)
      });
    }
  };

  return (
    <PageTemplate
      title={t(isEdit ? "Edit Plan" : "Create Plan")}
      description={t(isEdit ? "Update subscription plan details" : "Add a new subscription plan")}
      url={isEdit ? route('plans.update', plan.id) : "/plans/create"}
      breadcrumbs={[
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Plans'), href: route('plans.index') },
        { title: t(isEdit ? 'Edit Plan' : 'Create Plan') }
      ]}
    >
      <div className="bg-white rounded-lg shadow p-6">
        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="space-y-4">
              <div>
                <Label htmlFor="name">{t("Plan Name")}</Label>
                <Input
                  id="name"
                  name="name"
                  value={formData.name}
                  onChange={handleChange}
                  required
                />
              </div>

              <div>
                <Label htmlFor="price">{t("Monthly Price")}</Label>
                <Input
                  id="price"
                  name="price"
                  type="number"
                  step="0.01"
                  value={formData.price}
                  onChange={handleChange}
                  required
                />
              </div>

              <div>
                <Label htmlFor="yearly_price">{t("Yearly Price")} <span className="text-sm text-muted-foreground">({t("Optional")})</span></Label>
                <Input
                  id="yearly_price"
                  name="yearly_price"
                  type="number"
                  step="0.01"
                  value={formData.yearly_price || ''}
                  onChange={handleChange}
                  placeholder={t("Leave empty for 20% discount")}
                />
                <p className="text-xs text-muted-foreground mt-1">
                  {t("If left empty, yearly price will be calculated as 80% of monthly price × 12")}
                </p>
              </div>

              <div>
                <Label htmlFor="description">{t("Description")}</Label>
                <Textarea
                  id="description"
                  name="description"
                  value={formData.description}
                  onChange={handleChange}
                  rows={3}
                />
              </div>
            </div>

            <div className="space-y-4">
              <div>
                <Label htmlFor="max_users">{t("Maximum Users")}</Label>
                <Input
                  id="max_users"
                  name="max_users"
                  type="number"
                  value={formData.max_users}
                  onChange={handleChange}
                  required
                />
              </div>

              <div>
                <Label htmlFor="max_projects">{t("Maximum Projects")}</Label>
                <Input
                  id="max_projects"
                  name="max_projects"
                  type="number"
                  value={formData.max_projects}
                  onChange={handleChange}
                  required
                />
              </div>

              <div>
                <Label htmlFor="max_contacts">{t("Maximum Contacts")}</Label>
                <Input
                  id="max_contacts"
                  name="max_contacts"
                  type="number"
                  value={formData.max_contacts}
                  onChange={handleChange}
                  required
                />
              </div>

              <div>
                <Label htmlFor="max_accounts">{t("Maximum Accounts")}</Label>
                <Input
                  id="max_accounts"
                  name="max_accounts"
                  type="number"
                  value={formData.max_accounts}
                  onChange={handleChange}
                  required
                />
              </div>

              <div>
                <Label htmlFor="storage_limit">{t("Storage Limit (GB)")}</Label>
                <Input
                  id="storage_limit"
                  name="storage_limit"
                  type="number"
                  step="0.01"
                  value={formData.storage_limit}
                  onChange={handleChange}
                  required
                />
              </div>

              <div>
                <Label htmlFor="trial_day">{t("Trial Days")}</Label>
                <Input
                  id="trial_day"
                  name="trial_day"
                  type="number"
                  value={formData.trial_day}
                  onChange={handleChange}
                />
              </div>
            </div>
          </div>

          <div className="border rounded-lg p-4 space-y-4">
            <h3 className="font-medium">{t("Features")}</h3>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="flex items-center justify-between">
                <Label htmlFor="enable_branding">{t("Enable Branding")}</Label>
                <Switch
                  id="enable_branding"
                  checked={formData.enable_branding === 'on'}
                  onCheckedChange={(checked) => handleSwitchChange('enable_branding', checked)}
                />
              </div>

              <div className="flex items-center justify-between">
                <Label htmlFor="enable_chatgpt">{t("AI Integration")}</Label>
                <Switch
                  id="enable_chatgpt"
                  checked={formData.enable_chatgpt === 'on'}
                  onCheckedChange={(checked) => handleSwitchChange('enable_chatgpt', checked)}
                />
              </div>

              <div className="flex items-center justify-between">
                <Label htmlFor="is_trial">{t("Enable Trial")}</Label>
                <Switch
                  id="is_trial"
                  checked={formData.is_trial === 'on'}
                  onCheckedChange={(checked) => handleSwitchChange('is_trial', checked)}
                />
              </div>

              <div className="flex items-center justify-between">
                <Label htmlFor="enable_wedding_suppliers">{t("Wedding Suppliers")}</Label>
                <Switch
                  id="enable_wedding_suppliers"
                  checked={formData.enable_wedding_suppliers === 'on'}
                  onCheckedChange={(checked) => handleSwitchChange('enable_wedding_suppliers', checked)}
                />
              </div>
            </div>


          </div>

          <div className="border rounded-lg p-4 space-y-4">
            <h3 className="font-medium">{t("Settings")}</h3>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="flex items-center justify-between">
                <Label htmlFor="is_plan_enable">{t("Active")}</Label>
                <Switch
                  id="is_plan_enable"
                  checked={formData.is_plan_enable === 'on'}
                  onCheckedChange={(checked) => handleSwitchChange('is_plan_enable', checked)}
                />
              </div>

              <div className="flex items-center justify-between">
                <div>
                  <Label htmlFor="is_default">{t("Default Plan")}</Label>
                  {(isEdit ? !plan?.is_default : hasDefaultPlan) && (
                    <p className="text-xs text-amber-600 mt-1">
                      {t("Setting this as default will remove default status from the current default plan.")}
                    </p>
                  )}
                </div>
                <Switch
                  id="is_default"
                  checked={formData.is_default}
                  onCheckedChange={handleDefaultChange}
                />
              </div>
            </div>
          </div>

          <div className="flex justify-end gap-3">
            <Button
              type="button"
              variant="outline"
              onClick={() => router.get(route('plans.index'))}
            >
              {t("Cancel")}
            </Button>
            <Button
              type="submit"
              disabled={processing}
            >
              {processing ? t(isEdit ? "Updating..." : "Creating...") : t(isEdit ? "Update Plan" : "Create Plan")}
            </Button>
          </div>
        </form>
      </div>
    </PageTemplate>
  );
}