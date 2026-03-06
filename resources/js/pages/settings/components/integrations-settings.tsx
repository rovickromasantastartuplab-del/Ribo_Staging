import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTranslation } from 'react-i18next';
import { Link2, LayoutTemplate, MessageSquare, Loader2, CheckCircle2, Plus, Trash2, Settings2, ChevronDown, ChevronUp } from 'lucide-react';
import { useForm } from '@inertiajs/react';
import axios from 'axios';

interface SocialAccount {
    id: number;
    provider: string;
    provider_id: string;
    provider_name: string;
}

interface FieldMappingRow {
    external_field: string;
    crm_field: string;
    default_value: string;
}

interface Props {
    settings: any;
    socialAccounts?: SocialAccount[];
    fieldMappings?: FieldMappingRow[];
}

// Available CRM fields that Facebook form fields can map to
const CRM_FIELDS = [
    { value: 'name', label: 'Name' },
    { value: 'email', label: 'Email' },
    { value: 'phone', label: 'Phone' },
    { value: 'company', label: 'Company' },
    { value: 'position', label: 'Position' },
    { value: 'address', label: 'Address' },
    { value: 'website', label: 'Website' },
    { value: 'notes', label: 'Notes' },
    { value: 'value', label: 'Lead Value' },
    { value: 'status', label: 'Status' },
];

export default function IntegrationsSettings({ settings, socialAccounts = [], fieldMappings: initialMappings = [] }: Props) {
    const { t } = useTranslation();
    const [isGenerating, setIsGenerating] = useState(false);
    const [showFieldMapping, setShowFieldMapping] = useState(false);
    const [mappingRows, setMappingRows] = useState<FieldMappingRow[]>([]);
    const [savingMappings, setSavingMappings] = useState(false);
    const [mappingSaved, setMappingSaved] = useState(false);

    // Find connected accounts by provider
    const facebookAccount = socialAccounts.find((a: SocialAccount) => a.provider === 'facebook');
    const whatsappAccount = socialAccounts.find((a: SocialAccount) => a.provider === 'whatsapp');

    // Initialize mapping rows from props
    useEffect(() => {
        if (initialMappings && initialMappings.length > 0) {
            setMappingRows(initialMappings.map(m => ({
                external_field: m.external_field || '',
                crm_field: m.crm_field || '',
                default_value: m.default_value || '',
            })));
        }
    }, []);

    const { data, setData, post, processing } = useForm({
        wordpress_api_key: settings?.wordpress_api_key || '',
        ai_intent_enabled: settings?.ai_intent_enabled === 'true' || false,
        ai_auto_apply_threshold: settings?.ai_auto_apply_threshold || '85',
    });

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('settings.company.integrations.update'), {
            preserveScroll: true,
            onSuccess: () => alert('Integrations settings updated successfully!'),
        });
    };

    const generateApiKey = () => {
        setIsGenerating(true);
        const newKey = 'ribo_wpset_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
        setData('wordpress_api_key', newKey);
        setTimeout(() => setIsGenerating(false), 500);
    };

    // Field Mapping Handlers
    const addMappingRow = () => {
        setMappingRows([...mappingRows, { external_field: '', crm_field: '', default_value: '' }]);
    };

    const removeMappingRow = (index: number) => {
        setMappingRows(mappingRows.filter((_, i) => i !== index));
    };

    const updateMappingRow = (index: number, field: keyof FieldMappingRow, value: string) => {
        const updated = [...mappingRows];
        updated[index] = { ...updated[index], [field]: value };
        setMappingRows(updated);
    };

    const saveFieldMappings = async () => {
        // Filter out empty rows
        const validMappings = mappingRows.filter(row => row.external_field && row.crm_field);
        if (validMappings.length === 0) {
            alert('Please add at least one mapping with both fields filled.');
            return;
        }

        setSavingMappings(true);
        try {
            await axios.post(route('settings.field-mappings.save', { provider: 'facebook' }), {
                mappings: validMappings,
            });
            setMappingSaved(true);
            setTimeout(() => setMappingSaved(false), 3000);
        } catch (error: any) {
            alert('Failed to save field mappings: ' + (error.response?.data?.message || error.message));
        } finally {
            setSavingMappings(false);
        }
    };

    return (
        <Card className="shadow-none rounded-xl bg-card border">
            <CardHeader className="border-b bg-muted/20">
                <CardTitle className="text-xl font-semibold flex items-center gap-2">
                    <Link2 className="h-5 w-5 text-primary" />
                    {t('Integrations & Omnichannel Capture')}
                </CardTitle>
                <CardDescription>
                    {t('Manage connections for Facebook Lead Ads, WhatsApp, and WordPress to capture external leads into the CRM.')}
                </CardDescription>
            </CardHeader>
            <CardContent className="p-6">
                <form onSubmit={handleSave} className="space-y-8">

                    {/* Social Connectors Section */}
                    <div className="space-y-4">
                        <h3 className="text-lg font-medium">{t('Social Channels')}</h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">

                            {/* Facebook Lead Ads & Messenger */}
                            <div className={`border rounded-lg p-5 flex flex-col justify-between ${facebookAccount ? 'border-green-300 bg-green-50/30 dark:border-green-800 dark:bg-green-950/20' : ''}`}>
                                <div>
                                    <div className="flex items-center gap-3 mb-2">
                                        <div className="bg-[#1877F2] p-2 rounded-md">
                                            <LayoutTemplate className="h-5 w-5 text-white" />
                                        </div>
                                        <div>
                                            <h4 className="font-semibold">{t('Facebook Lead Ads')}</h4>
                                            {facebookAccount && (
                                                <p className="text-xs text-muted-foreground">{facebookAccount.provider_name} (ID: {facebookAccount.provider_id})</p>
                                            )}
                                        </div>
                                    </div>
                                    <p className="text-sm text-muted-foreground mt-2">
                                        {t('Automatically sync leads from Facebook forms to RIBO CRM.')}
                                    </p>
                                </div>
                                <div className="mt-4 pt-4 border-t flex items-center justify-between">
                                    {facebookAccount ? (
                                        <span className="text-sm font-medium text-green-700 bg-green-100 dark:text-green-400 dark:bg-green-900/40 px-2 py-1 rounded flex items-center gap-1">
                                            <CheckCircle2 className="h-3.5 w-3.5" /> Connected
                                        </span>
                                    ) : (
                                        <span className="text-sm font-medium text-amber-600 bg-amber-100 px-2 py-1 rounded">Not Connected</span>
                                    )}
                                    <div className="flex items-center gap-2">
                                        {facebookAccount && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                type="button"
                                                onClick={() => setShowFieldMapping(!showFieldMapping)}
                                                className="flex items-center gap-1"
                                            >
                                                <Settings2 className="h-3.5 w-3.5" />
                                                {t('Field Mapping')}
                                                {showFieldMapping ? <ChevronUp className="h-3.5 w-3.5" /> : <ChevronDown className="h-3.5 w-3.5" />}
                                            </Button>
                                        )}
                                        <a href={route('social.redirect', { provider: 'facebook' })}>
                                            <Button variant={facebookAccount ? 'ghost' : 'outline'} size="sm" type="button">
                                                {facebookAccount ? t('Reconnect') : t('Connect Meta')}
                                            </Button>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            {/* WhatsApp Cloud API */}
                            <div className={`border rounded-lg p-5 flex flex-col justify-between ${whatsappAccount ? 'border-green-300 bg-green-50/30 dark:border-green-800 dark:bg-green-950/20' : ''}`}>
                                <div>
                                    <div className="flex items-center gap-3 mb-2">
                                        <div className="bg-[#25D366] p-2 rounded-md">
                                            <MessageSquare className="h-5 w-5 text-white" />
                                        </div>
                                        <div>
                                            <h4 className="font-semibold">{t('WhatsApp Business')}</h4>
                                            {whatsappAccount && (
                                                <p className="text-xs text-muted-foreground">{whatsappAccount.provider_name} (ID: {whatsappAccount.provider_id})</p>
                                            )}
                                        </div>
                                    </div>
                                    <p className="text-sm text-muted-foreground mt-2">
                                        {t('Capture inbound messages as Lead Events on the timeline.')}
                                    </p>
                                </div>
                                <div className="mt-4 pt-4 border-t flex items-center justify-between">
                                    {whatsappAccount ? (
                                        <span className="text-sm font-medium text-green-700 bg-green-100 dark:text-green-400 dark:bg-green-900/40 px-2 py-1 rounded flex items-center gap-1">
                                            <CheckCircle2 className="h-3.5 w-3.5" /> Connected
                                        </span>
                                    ) : (
                                        <span className="text-sm font-medium text-amber-600 bg-amber-100 px-2 py-1 rounded">Not Connected</span>
                                    )}
                                    <Button variant={whatsappAccount ? 'ghost' : 'outline'} size="sm" type="button" onClick={() => alert('WhatsApp Cloud API connection coming soon.')}>
                                        {whatsappAccount ? t('Reconnect') : t('Connect Number')}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Field Mapping Section (only shows when Facebook is connected and user clicks Field Mapping) */}
                    {facebookAccount && showFieldMapping && (
                        <div className="space-y-4 pt-4 border-t">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h3 className="text-lg font-medium">{t('Facebook Lead Ads — Field Mapping')}</h3>
                                    <p className="text-sm text-muted-foreground">
                                        {t('Map your Facebook form fields to CRM columns. Use the exact field names from your Facebook Lead Ad form.')}
                                    </p>
                                </div>
                                <Button type="button" variant="outline" size="sm" onClick={addMappingRow} className="flex items-center gap-1">
                                    <Plus className="h-3.5 w-3.5" />
                                    {t('Add Row')}
                                </Button>
                            </div>

                            {/* Mapping Table */}
                            <div className="space-y-2">
                                {/* Header */}
                                <div className="grid grid-cols-12 gap-2 text-sm font-medium text-muted-foreground px-1">
                                    <div className="col-span-4">{t('Facebook Form Field')}</div>
                                    <div className="col-span-4">{t('CRM Field')}</div>
                                    <div className="col-span-3">{t('Default Value')}</div>
                                    <div className="col-span-1"></div>
                                </div>

                                {/* Rows */}
                                {mappingRows.length === 0 ? (
                                    <div className="text-center py-8 text-sm text-muted-foreground border rounded-lg bg-muted/10">
                                        {t('No field mappings configured. Click "Add Row" to start mapping your Facebook form fields.')}
                                    </div>
                                ) : (
                                    mappingRows.map((row, index) => (
                                        <div key={index} className="grid grid-cols-12 gap-2 items-center">
                                            <div className="col-span-4">
                                                <Input
                                                    placeholder={t('e.g. full_name, email, budget')}
                                                    value={row.external_field}
                                                    onChange={(e) => updateMappingRow(index, 'external_field', e.target.value)}
                                                    className="text-sm"
                                                />
                                            </div>
                                            <div className="col-span-4">
                                                <select
                                                    value={row.crm_field}
                                                    onChange={(e) => updateMappingRow(index, 'crm_field', e.target.value)}
                                                    className="w-full h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                                >
                                                    <option value="">{t('Select CRM field...')}</option>
                                                    {CRM_FIELDS.map((f) => (
                                                        <option key={f.value} value={f.value}>{f.label}</option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div className="col-span-3">
                                                <Input
                                                    placeholder={t('Optional fallback')}
                                                    value={row.default_value}
                                                    onChange={(e) => updateMappingRow(index, 'default_value', e.target.value)}
                                                    className="text-sm"
                                                />
                                            </div>
                                            <div className="col-span-1 flex justify-center">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => removeMappingRow(index)}
                                                    className="text-destructive hover:text-destructive/80 h-8 w-8 p-0"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>

                            {/* Save Mappings Button */}
                            {mappingRows.length > 0 && (
                                <div className="flex justify-end pt-2">
                                    <Button
                                        type="button"
                                        onClick={saveFieldMappings}
                                        disabled={savingMappings}
                                        variant="default"
                                        size="sm"
                                    >
                                        {savingMappings ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                                        {mappingSaved ? (
                                            <span className="flex items-center gap-1"><CheckCircle2 className="h-4 w-4" /> {t('Saved!')}</span>
                                        ) : (
                                            t('Save Field Mappings')
                                        )}
                                    </Button>
                                </div>
                            )}
                        </div>
                    )}

                    {/* WordPress API Section */}
                    <div className="space-y-4 pt-4 border-t">
                        <h3 className="text-lg font-medium">{t('WordPress Form Integration')}</h3>
                        <p className="text-sm text-muted-foreground">
                            {t('Use this API key in your RIBO WordPress Plugin to push form submissions directly to this account.')}
                        </p>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="space-y-2">
                                <Label htmlFor="wordpress_api_key">{t('WordPress API Key')}</Label>
                                <div className="flex gap-2">
                                    <Input
                                        id="wordpress_api_key"
                                        value={data.wordpress_api_key}
                                        onChange={(e) => setData('wordpress_api_key', e.target.value)}
                                        placeholder={t('Generate a new key...')}
                                        readOnly
                                        className="bg-muted/50 font-mono text-sm"
                                    />
                                    <Button type="button" variant="secondary" onClick={generateApiKey} disabled={isGenerating}>
                                        {isGenerating ? <Loader2 className="h-4 w-4 animate-spin" /> : t('Generate')}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* AI Intent Settings */}
                    <div className="space-y-4 pt-4 border-t">
                        <div className="flex items-center justify-between">
                            <div>
                                <h3 className="text-lg font-medium">{t('AI Lead Classification')}</h3>
                                <p className="text-sm text-muted-foreground">
                                    {t('Use ChatGPT to automatically determine the intent of incoming social leads.')}
                                </p>
                            </div>
                            <Switch
                                checked={data.ai_intent_enabled}
                                onCheckedChange={(checked) => setData('ai_intent_enabled', checked)}
                            />
                        </div>

                        {data.ai_intent_enabled && (
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                                <div className="space-y-2">
                                    <Label htmlFor="ai_auto_apply_threshold">{t('Auto-Apply Threshold (%)')}</Label>
                                    <Input
                                        id="ai_auto_apply_threshold"
                                        type="number"
                                        min="1"
                                        max="100"
                                        value={data.ai_auto_apply_threshold}
                                        onChange={(e) => setData('ai_auto_apply_threshold', e.target.value)}
                                        placeholder="85"
                                    />
                                    <p className="text-xs text-muted-foreground text-left mt-1">
                                        {t('Only set pipeline stage if AI confidence is above this threshold.')}
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="flex justify-end pt-6 border-t">
                        <Button type="submit" disabled={processing}>
                            {processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                            {t('Save Changes')}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

