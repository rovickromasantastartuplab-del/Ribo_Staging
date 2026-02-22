import React from 'react';
import { PageTemplate } from '@/components/page-template';
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { useState } from 'react';

interface CurrencyPrice {
  code: string;
  monthly: number | string;
  yearly: number | string;
}

interface AvailableCurrency {
  id: number;
  code: string;
  symbol: string;
  name: string;
}

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
  availableCurrencies?: AvailableCurrency[];
  currencyPrices?: CurrencyPrice[];
}

export default function PlanForm({ plan, hasDefaultPlan = false, otherDefaultPlanExists = false, availableCurrencies = [], currencyPrices = [] }: Props) {
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

  // Build initial currency prices from props, keyed by code
  const buildInitialCurrencyPrices = (): Record<string, CurrencyPrice> => {
    const map: Record<string, CurrencyPrice> = {};
    for (const c of availableCurrencies) {
      const existing = currencyPrices.find(cp => cp.code === c.code);
      map[c.code] = existing
        ? { code: c.code, monthly: existing.monthly, yearly: existing.yearly ?? '' }
        : { code: c.code, monthly: '', yearly: '' };
    }
    return map;
  };

  const [currencyPriceMap, setCurrencyPriceMap] = useState<Record<string, CurrencyPrice>>(buildInitialCurrencyPrices);

  const handleCurrencyPriceChange = (code: string, field: 'monthly' | 'yearly', value: string) => {
    setCurrencyPriceMap(prev => ({
      ...prev,
      [code]: { ...prev[code], [field]: value },
    }));
  };

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

    // Convert currencyPriceMap to array, only including rows with a monthly price set
    const currency_prices = Object.values(currencyPriceMap)
      .filter(cp => cp.monthly !== '' && Number(cp.monthly) >= 0)
      .map(cp => ({
        code: cp.code,
        monthly: Number(cp.monthly),
        yearly: cp.yearly !== '' ? Number(cp.yearly) : null,
      }));

    const payload = { ...formData, currency_prices };

    if (isEdit) {
      router.put(route('plans.update', plan.id), payload, {
        onFinish: () => setProcessing(false)
      });
    } else {
      router.post(route('plans.store'), payload, {
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
                <Label htmlFor="price">
                  {t("Base Monthly Price")}
                  <span className="text-xs text-muted-foreground ml-1">({t("fallback if no currency price set")})</span>
                </Label>
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
                <Label htmlFor="yearly_price">
                  {t("Base Yearly Price")}
                  <span className="text-sm text-muted-foreground ml-1">({t("Optional")})</span>
                </Label>
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

          {/* Currency Prices */}
          {availableCurrencies.length > 0 && (
            <div className="border rounded-lg p-4 space-y-3">
              <div>
                <h3 className="font-medium">{t("Currency Prices")}</h3>
                <p className="text-xs text-muted-foreground mt-0.5">
                  {t("Set the exact price per currency. Leave empty to fall back to the base price above.")}
                </p>
              </div>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b text-left">
                      <th className="pb-2 font-medium text-muted-foreground w-32">{t("Currency")}</th>
                      <th className="pb-2 font-medium text-muted-foreground">{t("Monthly Price")}</th>
                      <th className="pb-2 font-medium text-muted-foreground">{t("Yearly Price")}</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {availableCurrencies.map(cur => (
                      <tr key={cur.code}>
                        <td className="py-2 pr-4">
                          <span className="font-medium">{cur.code}</span>
                          <span className="text-muted-foreground ml-1 text-xs">{cur.symbol}</span>
                        </td>
                        <td className="py-2 pr-4">
                          <Input
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder={t("e.g. 999")}
                            value={currencyPriceMap[cur.code]?.monthly ?? ''}
                            onChange={e => handleCurrencyPriceChange(cur.code, 'monthly', e.target.value)}
                            className="h-8 w-36"
                          />
                        </td>
                        <td className="py-2">
                          <Input
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder={t("Optional")}
                            value={currencyPriceMap[cur.code]?.yearly ?? ''}
                            onChange={e => handleCurrencyPriceChange(cur.code, 'yearly', e.target.value)}
                            className="h-8 w-36"
                          />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

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