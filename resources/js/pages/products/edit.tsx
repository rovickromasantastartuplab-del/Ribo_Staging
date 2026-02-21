import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { ArrowLeft, Upload, X, Image as ImageIcon, Trash2 } from 'lucide-react';
import MediaPicker from '@/components/MediaPicker';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from 'react-i18next';
import { useState, useEffect } from 'react';
import { toast } from '@/components/custom-toast';

export default function ProductEdit() {
  const { t } = useTranslation();
  const { product, categories, brands, taxes, users, auth, mainImage, additionalImages } = usePage().props as any;
  const isCompany = auth?.user?.type === 'company';

  const [formData, setFormData] = useState({
    name: product.name || '',
    sku: product.sku || '',
    description: product.description || '',
    price: product.price || '',
    stock_quantity: product.stock_quantity || '',
    category_id: product.category_id?.toString() || '',
    brand_id: product.brand_id?.toString() || '',
    tax_id: product.tax_id?.toString() || '',
    status: product.status || 'active',
    assigned_to: product.assigned_to?.toString() || ''
  });

  // Validate media IDs - clear if media doesn't exist
  const [mainImageId, setMainImageId] = useState<number | ''>(() => {
    if (product.main_image_id && mainImage) {
      return product.main_image_id;
    }
    return '';
  });
  
  const [additionalImageIds, setAdditionalImageIds] = useState<number[]>(() => {
    if (product.additional_image_ids && additionalImages) {
      // Filter out deleted media IDs
      const validIds = product.additional_image_ids.filter((id: number) => 
        additionalImages.some((img: any) => img.id === id)
      );
      return validIds;
    }
    return [];
  });

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Products'), href: route('products.index') },
    { title: product.name },
    { title: t('Edit') }
  ];

  const handleInputChange = (name: string, value: string) => {
    setFormData(prev => ({ ...prev, [name]: value }));
  };



  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    const submitData = {
      ...formData,
      main_image_id: mainImageId || null,
      additional_image_ids: additionalImageIds.length > 0 ? additionalImageIds : null,
      _method: 'PUT'
    };

    toast.loading(t('Updating product...'));

    router.post(route('products.update', product.id), submitData, {
      onSuccess: (page) => {
        toast.dismiss();
        if (page.props.flash.success) {
          toast.success(t(page.props.flash.success));
        } else if (page.props.flash.error) {
          toast.error(t(page.props.flash.error));
        }
        router.visit(route('products.index'));
      },
      onError: (errors) => {
        toast.dismiss();
        if (typeof errors === 'string') {
          toast.error(errors);
        } else {
          toast.error(`Failed to update product: ${Object.values(errors).join(', ')}`);
        }
      }
    });
  };

  return (
    <PageTemplate
      title={t('Edit Product')}
      breadcrumbs={breadcrumbs}
      actions={[
        {
          label: t('Back to Products'),
          icon: <ArrowLeft className="h-4 w-4 mr-2" />,
          variant: 'outline',
          onClick: () => router.visit(route('products.index'))
        }
      ]}
    >
      <form onSubmit={handleSubmit} className="max-w-4xl mx-auto space-y-6">
        {/* Basic Information */}
        <Card>
          <CardHeader>
            <CardTitle>{t('Basic Information')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">{t('Product Name')} <span className="text-red-500">*</span></label>
                <Input
                  id="name"
                  value={formData.name}
                  onChange={(e) => handleInputChange('name', e.target.value)}
                  required
                />
              </div>
              <div>
                <label htmlFor="sku" className="block text-sm font-medium text-gray-700 mb-1">{t('SKU')} <span className="text-red-500">*</span></label>
                <Input
                  id="sku"
                  value={formData.sku}
                  onChange={(e) => handleInputChange('sku', e.target.value)}
                  required
                />
              </div>
            </div>
            <div>
              <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-1">{t('Description')}</label>
              <Textarea
                id="description"
                value={formData.description}
                onChange={(e) => handleInputChange('description', e.target.value)}
                rows={3}
              />
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label htmlFor="price" className="block text-sm font-medium text-gray-700 mb-1">{t('Price')} <span className="text-red-500">*</span></label>
                <Input
                  id="price"
                  type="number"
                  step="0.01"
                  min="0"
                  value={formData.price}
                  onChange={(e) => handleInputChange('price', e.target.value)}
                  required
                />
              </div>
              <div>
                <label htmlFor="stock_quantity" className="block text-sm font-medium text-gray-700 mb-1">{t('Stock Quantity')} <span className="text-red-500">*</span></label>
                <Input
                  id="stock_quantity"
                  type="number"
                  min="0"
                  value={formData.stock_quantity}
                  onChange={(e) => handleInputChange('stock_quantity', e.target.value)}
                  required
                />
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Images */}
        <Card>
          <CardHeader>
            <CardTitle>{t('Product Images')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-6">
            {/* Main Image */}
            <div>
              <MediaPicker
                label={t('Main Image')}
                value={mainImageId}
                onChange={(value) => setMainImageId(value as number)}
                placeholder={t('Select main image...')}
                showPreview={true}
                returnType="id"
              />
            </div>

            {/* Additional Images */}
            <div>
              <MediaPicker
                label={t('Additional Images')}
                value={additionalImageIds}
                onChange={(value) => setAdditionalImageIds(value as number[])}
                placeholder={t('Select additional images...')}
                multiple={true}
                showPreview={true}
                returnType="id"
              />
            </div>
          </CardContent>
        </Card>

        {/* Categories and Settings */}
        <Card>
          <CardHeader>
            <CardTitle>{t('Categories & Settings')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">{t('Category')}</label>
                <Select value={formData.category_id} onValueChange={(value) => handleInputChange('category_id', value)}>
                  <SelectTrigger>
                    <SelectValue placeholder={t('Select category')} />
                  </SelectTrigger>
                  <SelectContent>
                    {categories?.map((category: any) => (
                      <SelectItem key={category.id} value={category.id.toString()}>
                        {category.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">{t('Brand')}</label>
                <Select value={formData.brand_id} onValueChange={(value) => handleInputChange('brand_id', value)}>
                  <SelectTrigger>
                    <SelectValue placeholder={t('Select brand')} />
                  </SelectTrigger>
                  <SelectContent>
                    {brands?.map((brand: any) => (
                      <SelectItem key={brand.id} value={brand.id.toString()}>
                        {brand.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">{t('Tax')}</label>
                <Select value={formData.tax_id} onValueChange={(value) => handleInputChange('tax_id', value)}>
                  <SelectTrigger>
                    <SelectValue placeholder={t('Select tax')} />
                  </SelectTrigger>
                  <SelectContent>
                    {taxes?.map((tax: any) => (
                      <SelectItem key={tax.id} value={tax.id.toString()}>
                        {tax.name} ({tax.rate}%)
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {isCompany && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">{t('Assign To')}</label>
                  <Select value={formData.assigned_to} onValueChange={(value) => handleInputChange('assigned_to', value)}>
                    <SelectTrigger>
                      <SelectValue placeholder={t('Select user')} />
                    </SelectTrigger>
                    <SelectContent>
                      {users?.map((user: any) => (
                        <SelectItem key={user.id} value={user.id.toString()}>
                          {(user.display_name || user.name)} ({user.email})
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              )}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">{t('Status')}</label>
                <Select value={formData.status} onValueChange={(value) => handleInputChange('status', value)}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="active">{t('Active')}</SelectItem>
                    <SelectItem value="inactive">{t('Inactive')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Submit Button */}
        <div className="flex justify-end space-x-4">
          <Button type="button" variant="outline" onClick={() => router.visit(route('products.index'))}>
            {t('Cancel')}
          </Button>
          <Button type="submit">
            {t('Update Product')}
          </Button>
        </div>
      </form>
    </PageTemplate>
  );
}