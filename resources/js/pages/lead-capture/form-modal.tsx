import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Checkbox } from '@/components/ui/checkbox';
import { Button } from '@/components/ui/button';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select';
import { toast } from '@/components/custom-toast';

const FIELD_LABELS: Record<string, string> = {
  name: 'Name',
  email: 'Email',
  phone: 'Phone',
  company: 'Company',
  position: 'Position',
  website: 'Website',
  address: 'Address',
  notes: 'Message / Notes',
};

const NONE = 'none';

interface Props {
  isOpen: boolean;
  onClose: () => void;
  mode: 'create' | 'edit';
  item: any;
  leadSources: any[];
  campaigns: any[];
  leadStatuses: any[];
  users: any[];
  allowedFields: string[];
  defaultFieldsConfig: any[];
  canWhiteLabel: boolean;
}

export function LeadCaptureFormModal({
  isOpen, onClose, mode, item, leadSources, campaigns, leadStatuses, users, allowedFields, defaultFieldsConfig, canWhiteLabel,
}: Props) {
  const { t } = useTranslation();
  const [saving, setSaving] = useState(false);

  const blank = () => ({
    name: '',
    description: '',
    type: 'customer_facing',
    fields_config: defaultFieldsConfig.map((f: any) => ({ ...f })),
    lead_source_id: NONE,
    campaign_id: NONE,
    default_assigned_to: NONE,
    default_lead_status_id: NONE,
    company_name: '',
    submit_button_text: '',
    thank_you_message: '',
    theme: { primary_color: '#2563eb', background_color: '#f8fafc' },
    auto_reply_enabled: false,
    auto_reply_subject: '',
    auto_reply_body: '',
    is_active: true,
  });

  const [form, setForm] = useState<any>(blank());

  useEffect(() => {
    if (!isOpen) return;
    if (mode === 'edit' && item) {
      // Merge stored fields_config onto the allowed-field pool so newly added fields appear.
      const stored: Record<string, any> = {};
      (item.fields_config || []).forEach((f: any) => { stored[f.field] = f; });
      const merged = allowedFields.map((field) => ({
        field,
        visible: field === 'name' ? true : !!stored[field]?.visible,
        required: field === 'name' ? true : !!stored[field]?.required,
      }));
      setForm({
        name: item.name || '',
        description: item.description || '',
        type: item.type || 'customer_facing',
        fields_config: merged,
        lead_source_id: item.lead_source_id ? String(item.lead_source_id) : NONE,
        campaign_id: item.campaign_id ? String(item.campaign_id) : NONE,
        default_assigned_to: item.default_assigned_to ? String(item.default_assigned_to) : NONE,
        default_lead_status_id: item.default_lead_status_id ? String(item.default_lead_status_id) : NONE,
        company_name: item.company_name || '',
        submit_button_text: item.submit_button_text || '',
        thank_you_message: item.thank_you_message || '',
        theme: item.theme || { primary_color: '#2563eb', background_color: '#f8fafc' },
        auto_reply_enabled: !!item.auto_reply_enabled,
        auto_reply_subject: item.auto_reply_subject || '',
        auto_reply_body: item.auto_reply_body || '',
        is_active: item.is_active ?? true,
      });
    } else {
      setForm(blank());
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isOpen, item, mode]);

  const set = (key: string, value: any) => setForm((prev: any) => ({ ...prev, [key]: value }));

  const setFieldConfig = (field: string, prop: 'visible' | 'required', value: boolean) => {
    setForm((prev: any) => ({
      ...prev,
      fields_config: prev.fields_config.map((f: any) =>
        f.field === field ? { ...f, [prop]: value, ...(prop === 'visible' && !value ? { required: false } : {}) } : f,
      ),
    }));
  };

  const submit = () => {
    const payload = {
      ...form,
      lead_source_id: form.lead_source_id === NONE ? null : form.lead_source_id,
      campaign_id: form.campaign_id === NONE ? null : form.campaign_id,
      default_assigned_to: form.default_assigned_to === NONE ? null : form.default_assigned_to,
      default_lead_status_id: form.default_lead_status_id === NONE ? null : form.default_lead_status_id,
      theme: canWhiteLabel ? form.theme : null,
    };

    setSaving(true);
    const opts = {
      preserveScroll: true,
      onSuccess: (page: any) => {
        setSaving(false);
        onClose();
        if (page.props.flash?.success) toast.success(t(page.props.flash.success));
        else if (page.props.flash?.error) toast.error(t(page.props.flash.error));
      },
      onError: (errors: any) => {
        setSaving(false);
        toast.error(t('Failed to save: {{errors}}', { errors: Object.values(errors).join(', ') }));
      },
    };

    if (mode === 'create') router.post(route('lead-capture.forms.store'), payload, opts);
    else router.put(route('lead-capture.forms.update', item.id), payload, opts);
  };

  const renderSelect = (key: string, placeholder: string, options: { value: string; label: string }[]) => (
    <Select value={form[key]} onValueChange={(v) => set(key, v)}>
      <SelectTrigger><SelectValue placeholder={placeholder} /></SelectTrigger>
      <SelectContent>
        <SelectItem value={NONE}>{t('None')}</SelectItem>
        {options.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
      </SelectContent>
    </Select>
  );

  return (
    <Dialog open={isOpen} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{mode === 'create' ? t('Create Capture Form') : t('Edit Capture Form')}</DialogTitle>
        </DialogHeader>

        <Tabs defaultValue="general" className="w-full">
          <TabsList className="grid w-full grid-cols-4">
            <TabsTrigger value="general">{t('General')}</TabsTrigger>
            <TabsTrigger value="fields">{t('Fields')}</TabsTrigger>
            <TabsTrigger value="branding">{t('Branding')}</TabsTrigger>
            <TabsTrigger value="autoreply">{t('Auto-Reply')}</TabsTrigger>
          </TabsList>

          {/* GENERAL */}
          <TabsContent value="general" className="space-y-4 pt-2">
            <div>
              <Label>{t('Form Name')} *</Label>
              <Input value={form.name} onChange={(e) => set('name', e.target.value)} placeholder={t('e.g. Request a Quote')} />
            </div>
            <div>
              <Label>{t('Description')}</Label>
              <Textarea value={form.description} onChange={(e) => set('description', e.target.value)} />
            </div>
            <div>
              <Label>{t('Form Type')}</Label>
              <Select value={form.type} onValueChange={(v) => set('type', v)}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="customer_facing">{t('Customer-Facing')}</SelectItem>
                  <SelectItem value="staff_assisted">{t('Staff-Assisted')}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label>{t('Lead Source / Channel')}</Label>
                {renderSelect('lead_source_id', t('Select source'), leadSources.map((s: any) => ({ value: String(s.id), label: s.name })))}
              </div>
              <div>
                <Label>{t('Campaign')}</Label>
                {renderSelect('campaign_id', t('Select campaign'), campaigns.map((c: any) => ({ value: String(c.id), label: c.name })))}
              </div>
              <div>
                <Label>{t('Default Assigned Staff')}</Label>
                {renderSelect('default_assigned_to', t('Unassigned'), users.map((u: any) => ({ value: String(u.id), label: u.name })))}
              </div>
              <div>
                <Label>{t('Default Lead Status')}</Label>
                {renderSelect('default_lead_status_id', t('Select status'), leadStatuses.map((s: any) => ({ value: String(s.id), label: s.name })))}
              </div>
            </div>
            <div className="flex items-center gap-2">
              <Switch checked={form.is_active} onCheckedChange={(v) => set('is_active', v)} />
              <Label>{t('Active')}</Label>
            </div>
          </TabsContent>

          {/* FIELDS */}
          <TabsContent value="fields" className="space-y-2 pt-2">
            <p className="text-sm text-gray-500">{t('Choose which fields appear on the form and which are required. Name is always shown and required.')}</p>
            <div className="border rounded-md divide-y">
              <div className="grid grid-cols-3 px-3 py-2 text-xs font-medium text-gray-500">
                <span>{t('Field')}</span>
                <span className="text-center">{t('Visible')}</span>
                <span className="text-center">{t('Required')}</span>
              </div>
              {form.fields_config.map((f: any) => {
                const locked = f.field === 'name';
                return (
                  <div key={f.field} className="grid grid-cols-3 px-3 py-2 items-center">
                    <span className="text-sm">{t(FIELD_LABELS[f.field] || f.field)}</span>
                    <div className="flex justify-center">
                      <Switch checked={f.visible} disabled={locked} onCheckedChange={(v) => setFieldConfig(f.field, 'visible', v)} />
                    </div>
                    <div className="flex justify-center">
                      <Checkbox checked={f.required} disabled={locked || !f.visible} onCheckedChange={(v) => setFieldConfig(f.field, 'required', !!v)} />
                    </div>
                  </div>
                );
              })}
            </div>
          </TabsContent>

          {/* BRANDING */}
          <TabsContent value="branding" className="space-y-4 pt-2">
            <div>
              <Label>{t('Company Name')}</Label>
              <Input value={form.company_name} onChange={(e) => set('company_name', e.target.value)} />
            </div>
            <div>
              <Label>{t('Submit Button Text')}</Label>
              <Input value={form.submit_button_text} onChange={(e) => set('submit_button_text', e.target.value)} placeholder={t('Submit')} />
            </div>
            <div>
              <Label>{t('Thank-You Message')}</Label>
              <Textarea value={form.thank_you_message} onChange={(e) => set('thank_you_message', e.target.value)} />
            </div>
            {canWhiteLabel ? (
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <Label>{t('Primary Color')}</Label>
                  <Input type="color" value={form.theme?.primary_color || '#2563eb'} onChange={(e) => set('theme', { ...form.theme, primary_color: e.target.value })} />
                </div>
                <div>
                  <Label>{t('Background Color')}</Label>
                  <Input type="color" value={form.theme?.background_color || '#f8fafc'} onChange={(e) => set('theme', { ...form.theme, background_color: e.target.value })} />
                </div>
              </div>
            ) : (
              <p className="text-xs text-gray-500">{t('White-label theming (colors, fonts, remove “Powered by RIBO”) is available on branding-enabled plans.')}</p>
            )}
          </TabsContent>

          {/* AUTO-REPLY */}
          <TabsContent value="autoreply" className="space-y-4 pt-2">
            <div className="flex items-center gap-2">
              <Switch checked={form.auto_reply_enabled} onCheckedChange={(v) => set('auto_reply_enabled', v)} />
              <Label>{t('Send an auto-reply email to the customer')}</Label>
            </div>
            <div>
              <Label>{t('Subject')}</Label>
              <Input value={form.auto_reply_subject} disabled={!form.auto_reply_enabled} onChange={(e) => set('auto_reply_subject', e.target.value)} />
            </div>
            <div>
              <Label>{t('Body')}</Label>
              <Textarea
                value={form.auto_reply_body}
                disabled={!form.auto_reply_enabled}
                onChange={(e) => set('auto_reply_body', e.target.value)}
                placeholder={t('Hi {lead_name}, thanks for reaching out...')}
              />
              <p className="text-xs text-gray-500 mt-1">{t('Available placeholders: {lead_name}, {company_name}, {form_name}')}</p>
            </div>
          </TabsContent>
        </Tabs>

        <DialogFooter>
          <Button variant="outline" onClick={onClose} disabled={saving}>{t('Cancel')}</Button>
          <Button onClick={submit} disabled={saving || !form.name.trim()}>
            {saving ? t('Saving...') : mode === 'create' ? t('Create Form') : t('Save Changes')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
