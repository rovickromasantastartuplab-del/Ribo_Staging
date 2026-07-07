import React, { useEffect, useRef, useState } from 'react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Button } from '@/components/ui/button';
import { Type, Plus, Trash2, Info, ListTree, GripVertical } from 'lucide-react';
import { useBrand } from '@/contexts/BrandContext';
import { THEME_COLORS } from '@/hooks/use-appearance';
import { defaultLandingPageSections } from './templates/default-sections';

export default function PlansSection({ data, setData, errors, handleInputChange, getSectionData, updateSectionData, updateSectionVisibility, t = (key) => key }) {
  const { themeColor, customColor } = useBrand();
  const brandColor = themeColor === 'custom' ? customColor : THEME_COLORS[themeColor as keyof typeof THEME_COLORS];

  const PROPERTY_KEYS = [
    { label: 'Maximum Users', value: 'max_users' },
    { label: 'Maximum Projects', value: 'max_projects' },
    { label: 'Maximum Contacts', value: 'max_contacts' },
    { label: 'Maximum Accounts', value: 'max_accounts' },
    { label: 'Storage Limit', value: 'storage_limit' },
    { label: 'Trial Days', value: 'trial_day' },
  ];

  const MODULE_KEYS = [
    { label: 'E-commerce', value: 'ecommerce' },
    { label: 'Wedding Suppliers', value: 'wedding_suppliers_module' },
  ];

  const METHOD_KEYS = [
    { label: 'Enable Branding', value: 'enable_branding' },
  ];

  const sectionData = getSectionData('plans');
  
  // Get default features from template
  const defaultFeatures = defaultLandingPageSections.sections.find(s => s.key === 'plans')?.comparison_features || [];

  // Auto-initialize comparison_features with defaults if not yet set or empty
  useEffect(() => {
    const existing = sectionData.comparison_features;
    if (!existing || (Array.isArray(existing) && existing.length === 0)) {
      updateSectionData('plans', { comparison_features: defaultFeatures });
    }
  }, []);

  // Drag-and-drop state
  const dragIndex = useRef<number | null>(null);
  const [draggingOver, setDraggingOver] = useState<number | null>(null);

  const handleDragStart = (index: number) => {
    dragIndex.current = index;
  };

  const handleDragOver = (e: React.DragEvent, index: number) => {
    e.preventDefault();
    setDraggingOver(index);
  };

  const handleDrop = (dropIndex: number) => {
    const fromIndex = dragIndex.current;
    if (fromIndex === null || fromIndex === dropIndex) {
      setDraggingOver(null);
      return;
    }
    const reordered = [...comparisonFeatures];
    const [moved] = reordered.splice(fromIndex, 1);
    reordered.splice(dropIndex, 0, moved);
    updateSectionData('plans', { comparison_features: reordered });
    dragIndex.current = null;
    setDraggingOver(null);
  };

  const handleDragEnd = () => {
    dragIndex.current = null;
    setDraggingOver(null);
  };

  const existing = sectionData.comparison_features;
  const comparisonFeatures = (existing && Array.isArray(existing) && existing.length > 0) ? existing : defaultFeatures;

  return (
    <div className="space-y-6">
      <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-violet-100 rounded-lg">
              <Type className="h-5 w-5 text-violet-600" />
            </div>
            <div>
              <h3 className="text-lg font-semibold text-gray-900">{t('Plans Section Content')}</h3>
              <p className="text-sm text-gray-500">{t('Pricing section title and description')}</p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <Label className="text-sm">{t('Enable Section')}</Label>
            <Switch
              checked={data.config_sections?.section_visibility?.plans !== false}
              onCheckedChange={(checked) => updateSectionVisibility('plans', checked)}
            />
          </div>
        </div>

        <div className="grid grid-cols-1 gap-4">
          <div className="space-y-3">
            <Label htmlFor="plans_title">{t("Section Title")}</Label>
            <Input
              id="plans_title"
              value={sectionData.title || ''}
              onChange={(e) => updateSectionData('plans', { title: e.target.value })}
              placeholder={t("Choose Your Plan")}
            />
          </div>

          <div className="space-y-3">
            <Label htmlFor="plans_subtitle">{t("Section Subtitle")}</Label>
            <Textarea
              id="plans_subtitle"
              value={sectionData.subtitle || ''}
              onChange={(e) => updateSectionData('plans', { subtitle: e.target.value })}
              placeholder={t("Start with our free plan and upgrade as your business grows.")}
              rows={3}
            />
          </div>

          <div className="space-y-3">
            <Label htmlFor="plans_faq_text">{t('FAQ Text')}</Label>
            <Input
              id="plans_faq_text"
              value={sectionData.faq_text || ''}
              onChange={(e) => updateSectionData('plans', { faq_text: e.target.value })}
              placeholder={t("Have questions about our plans? Contact our sales team")}
            />
          </div>
        </div>
      </div>

      <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        <div className="flex items-center gap-3 mb-6">
          <div className="p-2 bg-indigo-100 rounded-lg">
            <ListTree className="h-5 w-5 text-indigo-600" />
          </div>
          <div>
            <h3 className="text-lg font-semibold text-gray-900">{t('Comparison Table Features')}</h3>
            <p className="text-sm text-gray-500">{t('Manage rows in the plan comparison table')}</p>
          </div>
        </div>

        <div className="space-y-4">
          {comparisonFeatures.map((feature, index) => (
            <div
              key={index}
              draggable
              onDragStart={() => handleDragStart(index)}
              onDragOver={(e) => handleDragOver(e, index)}
              onDrop={() => handleDrop(index)}
              onDragEnd={handleDragEnd}
              className={`bg-gray-50 border rounded-xl p-5 transition-all ${
                draggingOver === index
                  ? 'border-indigo-400 shadow-md scale-[1.01]'
                  : 'border-gray-200'
              }`}
              style={{ cursor: 'grab' }}
            >
              <div className="flex items-center justify-between mb-4">
                <h4 className="font-semibold text-gray-900 flex items-center gap-2">
                  <GripVertical className="h-4 w-4 text-gray-400 cursor-grab" />
                  <span className="w-6 h-6 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-xs font-bold">{index + 1}</span>
                  {t('Feature')} {index + 1}
                </h4>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  className="text-red-600 hover:text-red-700 hover:bg-red-50 border-red-200"
                  onClick={() => {
                    const newFeatures = comparisonFeatures.filter((_, i) => i !== index);
                    updateSectionData('plans', { comparison_features: newFeatures });
                  }}
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-3">
                  <Label>{t("Feature Name")}</Label>
                  <Input
                    value={feature.name || ''}
                    onChange={(e) => {
                      const newFeatures = [...comparisonFeatures];
                      newFeatures[index] = { ...newFeatures[index], name: e.target.value };
                      updateSectionData('plans', { comparison_features: newFeatures });
                    }}
                    placeholder={t("e.g. Max Users")}
                  />
                </div>

                <div className="space-y-3">
                  <Label>{t("Description (Tooltip)")}</Label>
                  <Input
                    value={feature.description || ''}
                    onChange={(e) => {
                      const newFeatures = [...comparisonFeatures];
                      newFeatures[index] = { ...newFeatures[index], description: e.target.value };
                      updateSectionData('plans', { comparison_features: newFeatures });
                    }}
                    placeholder={t("Brief explanation of the feature")}
                  />
                </div>

                <div className="space-y-3">
                  <Label>{t("Data Source Type")}</Label>
                  <select
                    value={feature.type || 'property'}
                    onChange={(e) => {
                      const newFeatures = [...comparisonFeatures];
                      const newType = e.target.value;
                      newFeatures[index] = { ...newFeatures[index], type: newType, key: '' };
                      updateSectionData('plans', { comparison_features: newFeatures });
                    }}
                    className="w-full p-2 border border-gray-300 rounded-md"
                  >
                    <option value="property">Plan Property (Numeric/Text)</option>
                    <option value="module">Module Access (Checkmark)</option>
                    <option value="method">System Method (Checkmark)</option>
                  </select>
                </div>

                <div className="space-y-3">
                  <Label>{t("Data Key")}</Label>
                  <select
                    value={feature.key || ''}
                    onChange={(e) => {
                      const newFeatures = [...comparisonFeatures];
                      newFeatures[index] = { ...newFeatures[index], key: e.target.value };
                      updateSectionData('plans', { comparison_features: newFeatures });
                    }}
                    className="w-full p-2 border border-gray-300 rounded-md"
                  >
                    <option value="">Select a key...</option>
                    {feature.type === 'property' && PROPERTY_KEYS.map(k => <option key={k.value} value={k.value}>{k.label}</option>)}
                    {feature.type === 'module' && MODULE_KEYS.map(k => <option key={k.value} value={k.value}>{k.label}</option>)}
                    {feature.type === 'method' && METHOD_KEYS.map(k => <option key={k.value} value={k.value}>{k.label}</option>)}
                  </select>
                </div>
              </div>
            </div>
          ))}

          <Button
            type="button"
            variant="outline"
            className="w-full border-2"
            style={{ color: brandColor, borderColor: brandColor }}
            onClick={() => {
              const newFeatures = [...comparisonFeatures, { name: '', description: '', type: 'property', key: '' }];
              updateSectionData('plans', { comparison_features: newFeatures });
            }}
          >
            <Plus className="h-4 w-4 mr-2" />
            {t('Add Comparison Feature')}
          </Button>
        </div>
      </div>

      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div className="flex items-start">
          <Info className="h-5 w-5 text-blue-600 mr-2 mt-0.5" />
          <div>
            <h4 className="text-sm font-medium mb-1" style={{ color: brandColor }}>{t('Plans Management')}</h4>
            <p className="text-sm" style={{ color: brandColor + 'cc' }}>
              {t('The actual plans displayed on the landing page are managed through the Plans module. Go to Plans section to create, edit, or manage your subscription plans.')}
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
