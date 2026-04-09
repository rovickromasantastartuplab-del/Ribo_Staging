import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useState, useEffect } from 'react';
import { Save } from 'lucide-react';
import { SettingsSection } from '@/components/settings-section';
import { useTranslation } from 'react-i18next';
import { router, usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';

interface ChatGptSettingsProps {
  settings?: Record<string, string>;
}

interface ChatGptSettingsFormState {
  chatgptKey: string;
  chatgptModel: string;
  ai_conversation_enabled: boolean;
  ai_conversation_api_key: string;
  ai_conversation_model: string;
  ai_conversation_timeout_seconds: string;
}

export default function ChatGptSettings({ settings = {} }: ChatGptSettingsProps) {
  const { t } = useTranslation();
  const pageProps = usePage().props as any;
  
  // Default settings
  const defaultSettings = {
    chatgptKey: '',
    chatgptModel: 'gpt-3.5-turbo',
    ai_conversation_enabled: false,
    ai_conversation_api_key: '',
    ai_conversation_model: 'gpt-5.4-mini',
    ai_conversation_timeout_seconds: '30',
  };
  
  // Combine settings from props and page props
  const settingsData = Object.keys(settings).length > 0 
    ? settings 
    : (pageProps.settings || {});
  const hasStoredConversationApiKey = Boolean(settingsData.ai_conversation_api_key);
  
  // Initialize state with merged settings
  const [chatgptSettings, setChatgptSettings] = useState<ChatGptSettingsFormState>(() => ({
    chatgptKey: settingsData.chatgptKey || defaultSettings.chatgptKey,
    chatgptModel: settingsData.chatgptModel || defaultSettings.chatgptModel,
    ai_conversation_enabled: (settingsData.ai_conversation_enabled || '0') === '1',
    ai_conversation_api_key: defaultSettings.ai_conversation_api_key,
    ai_conversation_model: settingsData.ai_conversation_model || defaultSettings.ai_conversation_model,
    ai_conversation_timeout_seconds: settingsData.ai_conversation_timeout_seconds || defaultSettings.ai_conversation_timeout_seconds,
  }));
  
  // Update state when settings change
  useEffect(() => {
    if (Object.keys(settingsData).length > 0) {
      const mergedSettings = Object.keys(defaultSettings).reduce((acc, key) => {
        if (key === 'ai_conversation_enabled') {
          acc[key] = (settingsData[key] || '0') === '1';
          return acc;
        }

        if (key === 'ai_conversation_api_key') {
          acc[key] = '';
          return acc;
        }

        acc[key] = settingsData[key] || defaultSettings[key];
        return acc;
      }, {} as Record<string, string | boolean>);
      
      setChatgptSettings(prevSettings => ({
        ...prevSettings,
        ...mergedSettings as ChatGptSettingsFormState
      }));
    }
  }, [settingsData]);

  // Handle form changes
  const handleSettingsChange = (field: keyof ChatGptSettingsFormState, value: string | boolean) => {
    setChatgptSettings(prev => ({
      ...prev,
      [field]: value
    }));
  };

  // Handle form submission
  const submitChatgptSettings = (e: React.FormEvent) => {
    e.preventDefault();
    
    router.post(route('settings.chatgpt.update'), chatgptSettings, {
      preserveScroll: true,
      onSuccess: (page) => {
        const successMessage = page.props.flash?.success;
        const errorMessage = page.props.flash?.error;
        
        if (successMessage) {
          toast.success(successMessage);
        } else if (errorMessage) {
          toast.error(errorMessage);
        }
      },
      onError: (errors) => {
        const errorMessage = errors.error || Object.values(errors).join(', ') || t('Failed to update Chat GPT settings');
        toast.error(errorMessage);
      }
    });
  };

  return (
    <SettingsSection
      title={t("Chat GPT Settings")}
      description={t("Configure Chat GPT integration settings for AI-powered features")}
      action={
        <Button type="submit" form="chatgpt-settings-form" size="sm">
          <Save className="h-4 w-4 mr-2" />
          {t("Save Changes")}
        </Button>
      }
    >
      <form id="chatgpt-settings-form" onSubmit={submitChatgptSettings} className="space-y-6">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="grid gap-2 md:col-span-2">
            <Label htmlFor="chatgptKey">{t("Chat GPT Key")}</Label>
            <Input
              id="chatgptKey"
              type="password"
              value={chatgptSettings.chatgptKey}
              onChange={(e) => handleSettingsChange('chatgptKey', e.target.value)}
              placeholder={t("Enter your OpenAI API key")}
            />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="chatgptModel">{t("Chat GPT Model Name")}</Label>
            <Select 
              value={chatgptSettings.chatgptModel} 
              onValueChange={(value) => handleSettingsChange('chatgptModel', value)}
            >
              <SelectTrigger>
                <SelectValue placeholder={t("Select Chat GPT model")} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="gpt-3.5-turbo">GPT-3.5 Turbo</SelectItem>
                <SelectItem value="gpt-3.5-turbo-16k">GPT-3.5 Turbo 16K</SelectItem>
                <SelectItem value="gpt-4">GPT-4</SelectItem>
                <SelectItem value="gpt-4-turbo">GPT-4 Turbo</SelectItem>
                <SelectItem value="gpt-4o">GPT-4o</SelectItem>
                <SelectItem value="gpt-4o-mini">GPT-4o Mini</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="grid gap-2 md:col-span-2">
            <div className="flex items-center justify-between rounded-md border p-3">
              <Label htmlFor="ai_conversation_enabled">{t("Enable Conversation AI")}</Label>
              <Switch
                id="ai_conversation_enabled"
                checked={chatgptSettings.ai_conversation_enabled}
                onCheckedChange={(checked) => handleSettingsChange('ai_conversation_enabled', checked)}
              />
            </div>
          </div>

          <div className="grid gap-2 md:col-span-2">
            <Label htmlFor="ai_conversation_api_key">{t("Conversation AI API Key")}</Label>
            <Input
              id="ai_conversation_api_key"
              type="password"
              value={chatgptSettings.ai_conversation_api_key}
              onChange={(e) => handleSettingsChange('ai_conversation_api_key', e.target.value)}
              placeholder={hasStoredConversationApiKey ? t("Stored securely. Enter new key to replace.") : t("Enter conversation AI API key")}
            />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="ai_conversation_model">{t("Conversation AI Model")}</Label>
            <Input
              id="ai_conversation_model"
              value={chatgptSettings.ai_conversation_model}
              onChange={(e) => handleSettingsChange('ai_conversation_model', e.target.value)}
              placeholder={t("gpt-5.4-mini")}
            />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="ai_conversation_timeout_seconds">{t("Conversation AI Timeout (seconds)")}</Label>
            <Input
              id="ai_conversation_timeout_seconds"
              type="number"
              min={5}
              max={120}
              value={chatgptSettings.ai_conversation_timeout_seconds}
              onChange={(e) => handleSettingsChange('ai_conversation_timeout_seconds', e.target.value)}
            />
          </div>
        </div>
      </form>
    </SettingsSection>
  );
}
