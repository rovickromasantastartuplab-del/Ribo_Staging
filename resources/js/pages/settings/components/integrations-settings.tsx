import React, { useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTranslation } from 'react-i18next';
import { Link2, LayoutTemplate, MessageSquare, Loader2 } from 'lucide-react';
import { useForm } from '@inertiajs/react';

interface Props {
    settings: any;
}

export default function IntegrationsSettings({ settings }: Props) {
    const { t } = useTranslation();
    const [isGenerating, setIsGenerating] = useState(false);

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
        // Simple client side generation for showcase, ideally from backend
        const newKey = 'ribo_wpset_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
        setData('wordpress_api_key', newKey);
        setTimeout(() => setIsGenerating(false), 500);
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

                    {/* Social Connectors Section - UI Mockups for MVP */}
                    <div className="space-y-4">
                        <h3 className="text-lg font-medium">{t('Social Channels')}</h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">

                            {/* Facebook Lead Ads & Messenger */}
                            <div className="border rounded-lg p-5 flex flex-col justify-between">
                                <div>
                                    <div className="flex items-center gap-3 mb-2">
                                        <div className="bg-[#1877F2] p-2 rounded-md">
                                            <LayoutTemplate className="h-5 w-5 text-white" />
                                        </div>
                                        <div>
                                            <h4 className="font-semibold">{t('Facebook Lead Ads')}</h4>
                                        </div>
                                    </div>
                                    <p className="text-sm text-muted-foreground mt-2">
                                        {t('Automatically sync leads from Facebook forms to RIBO CRM.')}
                                    </p>
                                </div>
                                <div className="mt-4 pt-4 border-t flex items-center justify-between">
                                    <span className="text-sm font-medium text-amber-600 bg-amber-100 px-2 py-1 rounded">Not Connected</span>
                                    <Button variant="outline" size="sm" type="button" onClick={() => alert('OAuth flow will be initiated here.')}>{t('Connect Meta')}</Button>
                                </div>
                            </div>

                            {/* WhatsApp Cloud API */}
                            <div className="border rounded-lg p-5 flex flex-col justify-between">
                                <div>
                                    <div className="flex items-center gap-3 mb-2">
                                        <div className="bg-[#25D366] p-2 rounded-md">
                                            <MessageSquare className="h-5 w-5 text-white" />
                                        </div>
                                        <div>
                                            <h4 className="font-semibold">{t('WhatsApp Business')}</h4>
                                        </div>
                                    </div>
                                    <p className="text-sm text-muted-foreground mt-2">
                                        {t('Capture inbound messages as Lead Events on the timeline.')}
                                    </p>
                                </div>
                                <div className="mt-4 pt-4 border-t flex items-center justify-between">
                                    <span className="text-sm font-medium text-amber-600 bg-amber-100 px-2 py-1 rounded">Not Connected</span>
                                    <Button variant="outline" size="sm" type="button" onClick={() => alert('OAuth flow will be initiated here.')}>{t('Connect Number')}</Button>
                                </div>
                            </div>
                        </div>
                    </div>

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
