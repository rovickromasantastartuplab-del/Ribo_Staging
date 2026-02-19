import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Input } from '@/components/ui/input';
import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { router } from '@inertiajs/react';
import { Save, Link, Key, Phone, User, MessageSquare, Send, Bell, AlertCircle } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { SettingsSection } from '@/components/settings-section';
import axios from 'axios';
import { toast } from '@/components/custom-toast';

interface NotificationItem {
    name: string;
    label: string;
    description?: string;
}

export default function TwilioNotificationSettings() {
    const { t } = useTranslation();
    const [notifications, setNotifications] = useState<Record<string, boolean>>({});
    const [availableNotifications, setAvailableNotifications] = useState<NotificationItem[]>([]);
    const [twilioSettings, setTwilioSettings] = useState({
        twilio_sid: '',
        twilio_token: '',
        twilio_from: ''
    });
    const [testPhone, setTestPhone] = useState('');
    const [isSendingSMS, setIsSendingSMS] = useState(false);
    const [testSMSResult, setTestSMSResult] = useState<{ success: boolean, message: string } | null>(null);

    useEffect(() => {
        // Load available notifications
        axios.get(route('settings.twilio-notifications.available'))
            .then(response => {
                setAvailableNotifications(response.data);
            })
            .catch(error => {
                console.error('Failed to load available notifications:', error);
            });

        // Load current settings
        axios.get(route('settings.twilio-notifications.get'))
            .then(response => {
                setNotifications(response.data);
            })
            .catch(error => {
                console.error('Failed to load Twilio notification settings:', error);
            });

        // Load Twilio configuration
        axios.get(route('settings.twilio-config.get'))
            .then(response => {
                setTwilioSettings(response.data);
            })
            .catch(error => {
                console.error('Failed to load Twilio configuration:', error);
            });
    }, []);

    const handleToggle = (key: string, enabled: boolean) => {
        setNotifications(prev => ({
            ...prev,
            [key]: enabled
        }));
    };

    const handleConfigChange = (key: string, value: string) => {
        setTwilioSettings(prev => ({
            ...prev,
            [key]: value
        }));
    };

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();
        const data = {
            ...notifications,
            ...twilioSettings
        };

        toast.loading(t('Saving twilio settings...'));

        router.post(route('settings.twilio-notifications.update'), data, {
            preserveScroll: true,
            onSuccess: (page) => {
                toast.dismiss();
                const successMessage = page.props.flash?.success;
                const errorMessage = page.props.flash?.error;

                if (successMessage) {
                    toast.success(successMessage);
                } else if (errorMessage) {
                    toast.error(errorMessage);
                } else {
                    toast.success('Twilio settings updated successfully.');
                }
            },
            onError: () => {
                toast.error('Failed to update Twilio settings.');
            }
        });
    };

    const handleTestSMS = (e: React.FormEvent) => {
        e.preventDefault();
        if (!testPhone) {
            toast.error(t('Please enter a phone number'));
            return;
        }

        setIsSendingSMS(true);
        setTestSMSResult(null);
        toast.loading(t('Sending test SMS...'));

        router.post(route('settings.sms.test'), { phone: testPhone }, {
            preserveScroll: true,
            onSuccess: (page) => {
                setIsSendingSMS(false);
                toast.dismiss();
                const successMessage = page.props.flash?.success;
                const errorMessage = page.props.flash?.error;

                if (successMessage) {
                    toast.success(successMessage);
                    setTestSMSResult({ success: true, message: successMessage });
                } else if (errorMessage) {
                    toast.error(errorMessage);
                    setTestSMSResult({ success: false, message: errorMessage });
                } else {
                    const message = t('Test SMS sent successfully to {{phone}}', { phone: testPhone });
                    toast.success(message);
                    setTestSMSResult({ success: true, message });
                }

                // Reset result after 5 seconds
                setTimeout(() => {
                    setTestSMSResult(null);
                }, 5000);
            },
            onError: (errors) => {
                setIsSendingSMS(false);
                toast.dismiss();
                const errorMessage = errors.error || Object.values(errors).join(', ') || t('Failed to send test SMS');
                toast.error(errorMessage);
                setTestSMSResult({ success: false, message: errorMessage });

                // Reset result after 5 seconds
                setTimeout(() => {
                    setTestSMSResult(null);
                }, 5000);
            }
        });
    };

    return (
        <SettingsSection
            title={t("Twilio SMS Settings")}
            description={t("Configure Twilio settings for SMS notifications and communications")}
            action={
                <Button onClick={handleSave} size="sm">
                    <Save className="h-4 w-4 mr-2" />
                    {t("Save Changes")}
                </Button>
            }
        >
            <div className="space-y-4">
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left Column - Configuration */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Credentials */}
                        <div className="bg-white border border-gray-200 rounded-lg p-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <Label className="flex items-center gap-2 text-sm font-medium text-gray-700 mb-2">
                                        <Key className="h-4 w-4" />
                                        {t("Account SID")}
                                    </Label>
                                    <Input
                                        value={twilioSettings.twilio_sid}
                                        onChange={(e) => handleConfigChange('twilio_sid', e.target.value)}
                                        placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                        className="font-mono"
                                    />
                                </div>
                                <div>
                                    <Label className="flex items-center gap-2 text-sm font-medium text-gray-700 mb-2">
                                        <Key className="h-4 w-4" />
                                        {t("Auth Token")}
                                    </Label>
                                    <Input
                                        type="password"
                                        value={twilioSettings.twilio_token}
                                        onChange={(e) => handleConfigChange('twilio_token', e.target.value)}
                                        placeholder="••••••••••••••••••••"
                                        className="font-mono"
                                    />
                                </div>
                                <div>
                                    <Label className="flex items-center gap-2 text-sm font-medium text-gray-700 mb-2">
                                        <Phone className="h-4 w-4" />
                                        {t("From Phone Number")}
                                    </Label>
                                    <Input
                                        value={twilioSettings.twilio_from}
                                        onChange={(e) => handleConfigChange('twilio_from', e.target.value)}
                                        placeholder="+1234567890"
                                        className="font-mono"
                                    />
                                </div>
                            </div>

                            {/* Setup Instructions */}
                            <div className="bg-blue-50 border border-blue-200 rounded-lg p-6 mt-4 mb-4">
                                <div className="flex items-center gap-2 mb-4">
                                    <MessageSquare className="h-5 w-5 text-blue-600" />
                                    <h3 className="font-medium text-blue-900">{t("Twilio Setup Instructions")}</h3>
                                </div>
                                <ol className="space-y-2 text-sm text-blue-800">
                                    <li>{t("1. Sign up for a Twilio account at")} <span className="text-blue-600 underline"><a href="https://www.twilio.com">twilio.com</a> </span></li>
                                    <li>{t("2. Get your Account SID and Auth Token from the Twilio Console")}</li>
                                    <li>{t("3. Purchase a phone number or use a trial number")}</li>
                                    <li>{t("4. Enter your credentials and admin phone number")}</li>
                                </ol>
                            </div>

                            {/* SMS Notification Settings */}
                            <div className="flex items-center gap-2 mb-6">
                                <Bell className="h-5 w-5 text-emerald-500" />
                                <h3 className="font-medium text-gray-900">{t("SMS Notification Settings")}</h3>
                            </div>
                            {availableNotifications.length > 0 ? (
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    {availableNotifications.map(item => (
                                        <div key={item.name} className="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                            <Label htmlFor={item.name} className="text-sm font-medium">{t(item.label)}</Label>
                                            <Switch
                                                id={item.name}
                                                checked={notifications[item.name] || false}
                                                onCheckedChange={(checked) => handleToggle(item.name, checked)}
                                            />
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-8 text-gray-500">
                                    {t("No notification template available")}
                                </div>
                            )}
                        </div>

                    </div>

                    {/* Right Column - Test Configuration */}
                    <div className="bg-white border border-gray-200 rounded-lg p-6 h-fit">
                        <div className="flex items-center gap-2 mb-4">
                            <Send className="h-4 w-4 text-emerald-500" />
                            <h3 className="font-medium text-gray-900">{t("Test SMS Configuration")}</h3>
                        </div>
                        <form onSubmit={handleTestSMS} className="space-y-4">
                            <div>
                                <Label className="text-sm font-medium text-gray-700 block">{t("Send Test To")}</Label>
                                <Input
                                    value={testPhone}
                                    onChange={(e) => setTestPhone(e.target.value)}
                                    placeholder="+1234567890"
                                    className="font-mono"
                                    required
                                />
                                <p className="text-xs text-gray-500 mt-1">
                                    {t("Enter a phone number with country code")} {t("e.g., +1234567890")}
                                </p>
                            </div>

                            {testSMSResult && (
                                <Alert variant={testSMSResult.success ? "default" : "destructive"} className="py-2">
                                    <AlertCircle className="h-4 w-4" />
                                    <AlertDescription>
                                        {testSMSResult.message}
                                    </AlertDescription>
                                </Alert>
                            )}

                            <Button
                                type="submit"
                                className="w-full bg-emerald-500 hover:bg-emerald-600 text-white"
                                disabled={isSendingSMS || !testPhone}
                            >
                                {isSendingSMS ? (
                                    <>
                                        <span className="animate-spin mr-2">◌</span>
                                        {t("Sending...")}
                                    </>
                                ) : (
                                    <>
                                        <Send className="h-4 w-4 mr-2" />
                                        {t("Send Test SMS")}
                                    </>
                                )}
                            </Button>
                        </form>
                    </div>
                </div>
            </div>
        </SettingsSection>
    );
}
