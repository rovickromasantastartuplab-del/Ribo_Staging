import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { useTranslation } from 'react-i18next';
import { Link2, LayoutTemplate, MessageSquare, Loader2, CheckCircle2, Plus, Trash2, Settings2, ChevronDown, ChevronUp, Mail, Eye, EyeOff } from 'lucide-react';
import { useForm, usePage, router } from '@inertiajs/react';
import axios from 'axios';

interface SocialAccount {
    id: number;
    provider: string;
    provider_id: string;
    provider_name: string;
}

interface GmailAccount {
    id: number;
    gmail_address: string;
    last_sync_at: string | null;
    sync_status: string;
    sync_error: string | null;
    sync_strategy?: string;
    sync_categories?: string[];
}

const GMAIL_CATEGORIES = [
    { value: 'PRIMARY', label: 'Primary' },
    { value: 'SOCIAL', label: 'Social' },
    { value: 'PROMOTIONS', label: 'Promotions' },
    { value: 'UPDATES', label: 'Updates' },
    { value: 'FORUMS', label: 'Forums' },
];

interface FieldMappingRow {
    external_field: string;
    crm_field: string;
    default_value: string;
}

interface Props {
    settings: any;
    socialAccounts?: SocialAccount[];
    fieldMappings?: FieldMappingRow[];
    gmailAccount?: GmailAccount | null;
    googleSettings?: {
        google_client_id: string;
        google_client_secret: string;
        google_redirect_uri: string;
        google_gmail_pub_sub_topic: string;
        pusher_app_id: string;
        pusher_app_key: string;
        pusher_app_secret: string;
        pusher_app_cluster: string;
    } | null;
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

export default function IntegrationsSettings({ settings, socialAccounts = [], fieldMappings: initialMappings = [], gmailAccount = null, googleSettings = null }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const isSuperAdmin = auth?.user?.type === 'superadmin' || auth?.user?.type === 'super admin';
    const [isGenerating, setIsGenerating] = useState(false);
    const [showFieldMapping, setShowFieldMapping] = useState(false);
    const [mappingRows, setMappingRows] = useState<FieldMappingRow[]>([]);
    const [savingMappings, setSavingMappings] = useState(false);
    const [mappingSaved, setMappingSaved] = useState(false);
    const [showClientSecret, setShowClientSecret] = useState(false);

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
        google_client_id: googleSettings?.google_client_id || '',
        google_client_secret: googleSettings?.google_client_secret || '',
        google_redirect_uri: googleSettings?.google_redirect_uri || '',
        google_gmail_pub_sub_topic: googleSettings?.google_gmail_pub_sub_topic || '',
        pusher_app_id: googleSettings?.pusher_app_id || '',
        pusher_app_key: googleSettings?.pusher_app_key || '',
        pusher_app_secret: googleSettings?.pusher_app_secret || '',
        pusher_app_cluster: googleSettings?.pusher_app_cluster || '',
        gmail_sync_strategy: gmailAccount?.sync_strategy || 'all',
        gmail_sync_categories: gmailAccount?.sync_categories || [],
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

    const handleGmailSync = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(route('settings.gmail.sync'), {}, {
            preserveScroll: true,
        });
    };

    const handleGmailDisconnect = (e: React.FormEvent) => {
        e.preventDefault();
        if (confirm(t('Are you sure you want to disconnect Gmail? This will remove all synced emails.'))) {
            router.post(route('settings.gmail.disconnect'), {}, {
                preserveScroll: true,
            });
        }
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

                    {/* Social Connectors Section (Company Only) */}
                    {!isSuperAdmin && (
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
                                            {auth.user?.type === 'company' && (
                                                <a href={route('social.redirect', { provider: 'facebook' })}>
                                                    <Button variant={facebookAccount ? 'ghost' : 'outline'} size="sm" type="button">
                                                        {facebookAccount ? t('Reconnect') : t('Connect Meta')}
                                                    </Button>
                                                </a>
                                            )}
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
                                        {auth.user?.type === 'company' && (
                                            <Button variant={whatsappAccount ? 'ghost' : 'outline'} size="sm" type="button" onClick={() => alert('WhatsApp Cloud API connection coming soon.')}>
                                                {whatsappAccount ? t('Reconnect') : t('Connect Number')}
                                            </Button>
                                        )}
                                    </div>
                                </div>

                                {/* Gmail / Google Workspace */}
                                <div className={`border rounded-lg p-5 flex flex-col justify-between ${gmailAccount ? 'border-red-300 bg-red-50/30 dark:border-red-800 dark:bg-red-950/20' : ''}`}>
                                    <div>
                                        <div className="flex items-center gap-3 mb-2">
                                            <div className="bg-[#EA4335] p-2 rounded-md">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-white"><path d="M4 7V17A2 2 0 0 0 6 19H18A2 2 0 0 0 20 17V7"></path><path d="M4 7l8 5 8-5"></path></svg>
                                            </div>
                                            <div>
                                                <h4 className="font-semibold">{t('Gmail Integration')}</h4>
                                                {gmailAccount && (
                                                    <p className="text-xs text-muted-foreground">{gmailAccount.gmail_address}</p>
                                                )}
                                            </div>
                                        </div>
                                        <p className="text-sm text-muted-foreground mt-2">
                                            {t('Sync email conversations and link them directly to Leads and Contacts.')}
                                        </p>
                                        
                                        {gmailAccount && gmailAccount.sync_status === 'error' && (
                                            <div className="mt-2 text-xs text-red-600 bg-red-100 p-2 rounded">
                                                {t('Sync Error')}: {gmailAccount.sync_error || t('Authentication failed. Please reconnect.')}
                                            </div>
                                        )}

                                        {gmailAccount && (
                                            <div className="mt-4 pt-4 border-t space-y-4">
                                                <div>
                                                    <h4 className="text-sm font-medium mb-2">{t('Sync Settings')}</h4>
                                                    <div className="space-y-3">
                                                        <div className="flex items-center space-x-2">
                                                            <input
                                                                type="radio"
                                                                id="sync_all"
                                                                name="sync_strategy"
                                                                value="all"
                                                                checked={data.gmail_sync_strategy === 'all'}
                                                                onChange={(e) => setData('gmail_sync_strategy', e.target.value)}
                                                                className="h-4 w-4 text-primary"
                                                            />
                                                            <Label htmlFor="sync_all" className="text-sm">All new emails</Label>
                                                        </div>
                                                        
                                                        <div className="flex items-center space-x-2">
                                                            <input
                                                                type="radio"
                                                                id="sync_categories"
                                                                name="sync_strategy"
                                                                value="categories"
                                                                checked={data.gmail_sync_strategy === 'categories'}
                                                                onChange={(e) => setData('gmail_sync_strategy', e.target.value)}
                                                                className="h-4 w-4 text-primary"
                                                            />
                                                            <Label htmlFor="sync_categories" className="text-sm">From selected categories</Label>
                                                        </div>
                                                        
                                                        {data.gmail_sync_strategy === 'categories' && (
                                                            <div className="ml-6 space-y-2">
                                                                <p className="text-xs text-muted-foreground">Select Gmail categories to sync:</p>
                                                                <div className="grid grid-cols-2 gap-2">
                                                                    {GMAIL_CATEGORIES.map((category) => (
                                                                        <div key={category.value} className="flex items-center space-x-2">
                                                                            <Checkbox
                                                                                id={`category_${category.value}`}
                                                                                checked={data.gmail_sync_categories?.includes(category.value) || false}
                                                                                onCheckedChange={(checked) => {
                                                                                    const current = data.gmail_sync_categories || [];
                                                                                    if (checked) {
                                                                                        setData('gmail_sync_categories', [...current, category.value]);
                                                                                    } else {
                                                                                        setData('gmail_sync_categories', current.filter((c: string) => c !== category.value));
                                                                                    }
                                                                                }}
                                                                            />
                                                                            <Label htmlFor={`category_${category.value}`} className="text-sm">
                                                                                {category.label}
                                                                            </Label>
                                                                        </div>
                                                                    ))}
                                                                </div>
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                    <div className="mt-4 pt-4 border-t flex items-center justify-between">
                                        <div className="flex flex-col gap-1">
                                            {gmailAccount ? (
                                                <>
                                                    <span className="text-sm font-medium text-red-700 bg-red-100 dark:text-red-400 dark:bg-red-900/40 px-2 py-1 rounded w-fit flex items-center gap-1">
                                                        {gmailAccount.sync_status === 'syncing' ? (
                                                            <><Loader2 className="h-3.5 w-3.5 animate-spin" /> {t('Syncing...')}</>
                                                        ) : (
                                                            <><CheckCircle2 className="h-3.5 w-3.5" /> {t('Connected')}</>
                                                        )}
                                                    </span>
                                                    {gmailAccount.last_sync_at && (
                                                        <span className="text-[10px] text-muted-foreground">
                                                            {t('Last sync')}: {new Date(gmailAccount.last_sync_at).toLocaleString()}
                                                        </span>
                                                    )}
                                                </>
                                            ) : (
                                                <span className="text-sm font-medium text-amber-600 bg-amber-100 px-2 py-1 rounded w-fit">Not Connected</span>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {gmailAccount ? (
                                                <>
                                                    {auth.user?.type === 'company' && (
                                                        <Button 
                                                            variant="outline" 
                                                            size="sm" 
                                                            type="button" 
                                                            onClick={handleGmailSync}
                                                            disabled={gmailAccount.sync_status === 'syncing' || processing}
                                                        >
                                                            {gmailAccount.sync_status === 'syncing' ? <Loader2 className="h-3.5 w-3.5 animate-spin mr-1" /> : null}
                                                            {t('Sync Now')}
                                                        </Button>
                                                    )}
                                                    {auth.user?.type === 'company' && (
                                                        <Button 
                                                            variant="ghost" 
                                                            size="sm" 
                                                            type="button" 
                                                            onClick={handleGmailDisconnect}
                                                            disabled={processing}
                                                            className="text-red-600 hover:text-red-700 hover:bg-red-50"
                                                        >
                                                            {t('Disconnect')}
                                                        </Button>
                                                    )}
                                                </>
                                            ) : (
                                                auth.user?.type === 'company' && (
                                                    <a href={route('social.redirect', { provider: 'google' })}>
                                                        <Button variant="outline" size="sm" type="button">
                                                            {t('Connect Gmail')}
                                                        </Button>
                                                    </a>
                                                )
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

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
                                <div className="grid grid-cols-1 md:grid-cols-12 gap-2 text-sm font-medium text-muted-foreground px-1">
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
                                        <div key={index} className="grid grid-cols-1 md:grid-cols-12 gap-2 items-center">
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

                    {/* WordPress API Section (Company Only) */}
                    {!isSuperAdmin && (
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
                    )}

                    {/* Google / Gmail App Credentials (Superadmin Only) */}
                    {isSuperAdmin && (
                        <div className="space-y-4 pt-4 border-t">
                            <div className="flex items-center gap-2">
                                <div className="bg-white border rounded-md p-1.5">
                                    <svg viewBox="0 0 24 24" className="h-4 w-4" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                                    </svg>
                                </div>
                                <h3 className="text-lg font-medium">{t('Google / Gmail App')}</h3>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {t('Enter your Google Cloud OAuth 2.0 credentials. Company users will use these credentials when connecting their Gmail accounts.')}
                            </p>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-2">
                                    <Label htmlFor="google_client_id">{t('Google Client ID')}</Label>
                                    <Input
                                        id="google_client_id"
                                        value={data.google_client_id}
                                        onChange={(e) => setData('google_client_id', e.target.value)}
                                        placeholder="xxxx.apps.googleusercontent.com"
                                        className="font-mono text-sm"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="google_client_secret">{t('Google Client Secret')}</Label>
                                    <div className="relative">
                                        <Input
                                            id="google_client_secret"
                                            type={showClientSecret ? 'text' : 'password'}
                                            value={data.google_client_secret}
                                            onChange={(e) => setData('google_client_secret', e.target.value)}
                                            placeholder="GOCSPX-..."
                                            className="font-mono text-sm pr-10"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowClientSecret(!showClientSecret)}
                                            className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                        >
                                            {showClientSecret
                                                ? <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                                : <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                            }
                                        </button>
                                    </div>
                                </div>
                                <div className="space-y-2 md:col-span-2">
                                    <Label htmlFor="google_redirect_uri">{t('Authorized Redirect URI')}</Label>
                                    <Input
                                        id="google_redirect_uri"
                                        value={data.google_redirect_uri}
                                        onChange={(e) => setData('google_redirect_uri', e.target.value)}
                                        placeholder="https://yourdomain.com/auth/callback/google"
                                        className="font-mono text-sm"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {t('Copy this URI and add it to your Google Cloud Console → OAuth 2.0 Credentials → Authorized redirect URIs.')}
                                    </p>
                                </div>
                                <div className="space-y-2 md:col-span-2">
                                    <Label htmlFor="google_gmail_pub_sub_topic">{t('Google Pub/Sub Topic')}</Label>
                                    <Input
                                        id="google_gmail_pub_sub_topic"
                                        value={data.google_gmail_pub_sub_topic}
                                        onChange={(e) => setData('google_gmail_pub_sub_topic', e.target.value)}
                                        placeholder="projects/your-project-id/topics/ribo-gmail-webhooks"
                                        className="font-mono text-sm"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {t('Enter the full topic name from Google Cloud Pub/Sub. Company users will use this topic to start "watching" their Gmail inbox for real-time sync.')}
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Pusher Real-time Credentials (Superadmin Only) */}
                    {isSuperAdmin && (
                        <div className="space-y-4 pt-4 border-t">
                            <div className="flex items-center gap-2">
                                <div className="bg-primary/10 p-2 rounded-md">
                                    <Settings2 className="h-4 w-4 text-primary" />
                                </div>
                                <h3 className="text-lg font-medium">{t('Pusher / Webhook Real-time')}</h3>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {t('Enable real-time updates for the Conversations Hub. If configured, new emails will pop up automatically without refreshing.')}
                            </p>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-2">
                                    <Label htmlFor="pusher_app_id">{t('Pusher App ID')}</Label>
                                    <Input
                                        id="pusher_app_id"
                                        value={data.pusher_app_id}
                                        onChange={(e) => setData('pusher_app_id', e.target.value)}
                                        placeholder="2129375"
                                        className="font-mono text-sm"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="pusher_app_key">{t('Pusher App Key')}</Label>
                                    <Input
                                        id="pusher_app_key"
                                        value={data.pusher_app_key}
                                        onChange={(e) => setData('pusher_app_key', e.target.value)}
                                        placeholder="b68274ea07af131c3f40"
                                        className="font-mono text-sm"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="pusher_app_secret">{t('Pusher App Secret')}</Label>
                                    <div className="relative">
                                        <Input
                                            id="pusher_app_secret"
                                            type={showClientSecret ? 'text' : 'password'}
                                            value={data.pusher_app_secret}
                                            onChange={(e) => setData('pusher_app_secret', e.target.value)}
                                            placeholder="fcf4bf5391c3cf6d0f28"
                                            className="font-mono text-sm pr-10"
                                        />
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="pusher_app_cluster">{t('Pusher App Cluster')}</Label>
                                    <Input
                                        id="pusher_app_cluster"
                                        value={data.pusher_app_cluster}
                                        onChange={(e) => setData('pusher_app_cluster', e.target.value)}
                                        placeholder="ap1"
                                        className="font-mono text-sm"
                                    />
                                </div>
                            </div>
                        </div>
                    )}

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
