import { PageTemplate } from '@/components/page-template';
import { usePage } from '@inertiajs/react';
import { ArrowLeft, Building, MapPin, Globe, Phone, Mail, Clock, Facebook, User, Briefcase, Video } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from 'react-i18next';
import { WeddingSupplier } from '../../types/wedding-supplier';

export default function WeddingSupplierShow() {
    const { t } = useTranslation();
    const { supplier } = usePage().props as any;

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Wedding Suppliers'), href: route('wedding-suppliers.index') },
        { title: supplier.name }
    ];

    const formatDate = (dateString: string) => {
        if (!dateString) return '-';
        return window.appSettings?.formatDateTime(dateString, false) || new Date(dateString).toLocaleDateString();
    };

    return (
        <PageTemplate
            title={supplier.name}
            description={t('View supplier details')}
            url={`/wedding-suppliers/${supplier.id}`}
            breadcrumbs={breadcrumbs}
            actions={[
                {
                    label: t('Back to Suppliers'),
                    icon: <ArrowLeft className="h-4 w-4 mr-2" />,
                    variant: 'outline',
                    onClick: () => window.history.back()
                }
            ]}
        >
            <div className="mx-auto space-y-6">
                {/* Header Section */}
                <div className="bg-white rounded-lg shadow-sm border p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-lg font-bold text-gray-900">{supplier.name}</h1>
                            <p className="text-sm text-gray-600 mt-2">
                                {supplier.category ? supplier.category.name : t('Uncategorized')}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Primary Information */}
                <Card className="shadow-sm">
                    <CardHeader className="bg-gray-50 border-b">
                        <CardTitle className="text-lg font-semibold">{t('Supplier Information')}</CardTitle>
                    </CardHeader>
                    <CardContent className="p-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="space-y-6">
                                <div className="flex items-center space-x-3">
                                    <Mail className="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">{t('Email')}</label>
                                        <p className="text-sm mt-1">
                                            {supplier.email ? (
                                                <a href={`mailto:${supplier.email}`} className="text-blue-600 hover:text-blue-800 hover:underline">
                                                    {supplier.email}
                                                </a>
                                            ) : (
                                                t('-')
                                            )}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-center space-x-3">
                                    <Phone className="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">{t('Phone')}</label>
                                        <p className="text-sm mt-1">{supplier.phone || supplier.telephone || t('-')}</p>
                                    </div>
                                </div>
                                <div className="flex items-center space-x-3">
                                    <Clock className="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">{t('Availability')}</label>
                                        <p className="text-sm mt-1">{supplier.available_contact_time || t('-')}</p>
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-6">
                                <div className="flex items-center space-x-3">
                                    <Globe className="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">{t('Website')}</label>
                                        <p className="text-sm mt-1">
                                            {supplier.website ? (
                                                <a href={supplier.website.startsWith('http') ? supplier.website : `https://${supplier.website}`} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:text-blue-800 hover:underline block">
                                                    {supplier.website.replace(/^https?:\/\//, '')}
                                                </a>
                                            ) : (
                                                t('-')
                                            )}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-center space-x-3">
                                    <Facebook className="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">{t('Facebook')}</label>
                                        <p className="text-sm mt-1">
                                            {supplier.facebook ? (
                                                <a href={supplier.facebook.startsWith('http') ? supplier.facebook : `https://${supplier.facebook}`} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:text-blue-800 hover:underline block">
                                                    {t('View Profile')}
                                                </a>
                                            ) : (
                                                t('-')
                                            )}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-center space-x-3">
                                    <Video className="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">{t('TikTok')}</label>
                                        <p className="text-sm mt-1">{supplier.tiktok || t('-')}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Address Information */}
                {supplier.address && (
                    <Card className="shadow-sm">
                        <CardHeader className="bg-gray-50 border-b">
                            <CardTitle className="flex items-center text-lg font-semibold">
                                <MapPin className="h-5 w-5 mr-3 text-muted-foreground" />
                                {t('Address')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-6">
                            <p className="text-sm whitespace-pre-line">{supplier.address}</p>
                        </CardContent>
                    </Card>
                )}

                {/* Contact Persons */}
                <Card className="shadow-sm">
                    <CardHeader className="bg-gray-50 border-b">
                        <CardTitle className="flex items-center text-lg font-semibold">
                            <User className="h-5 w-5 mr-3 text-muted-foreground" />
                            {t('Contact Persons')} ({supplier.contacts?.length || 0})
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-6">
                        {supplier.contacts && supplier.contacts.length > 0 ? (
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {supplier.contacts.map((contact: any, index: number) => (
                                    <div key={contact.id || index} className="p-4 bg-gray-50 rounded-lg border border-gray-200">
                                        <div className="flex items-start justify-between">
                                            <div>
                                                <p className="text-base font-semibold text-gray-900">{contact.name}</p>
                                                {contact.position && (
                                                    <div className="flex items-center mt-1 text-sm text-gray-500">
                                                        <Briefcase className="h-3.5 w-3.5 mr-1" />
                                                        {contact.position}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                        <div className="mt-4 space-y-2">
                                            {contact.phone && (
                                                <div className="flex items-center text-sm">
                                                    <Phone className="h-4 w-4 text-muted-foreground mr-2" />
                                                    {contact.phone}
                                                </div>
                                            )}
                                            {contact.email && (
                                                <div className="flex items-center text-sm">
                                                    <Mail className="h-4 w-4 text-muted-foreground mr-2" />
                                                    <a href={`mailto:${contact.email}`} className="text-blue-600 hover:text-blue-800 hover:underline">
                                                        {contact.email}
                                                    </a>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="py-8 text-center text-muted-foreground">
                                <User className="h-10 w-10 mx-auto mb-3 text-gray-300" />
                                <p className="text-sm">{t('No contact persons recorded.')}</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Timestamps */}
                <Card className="shadow-sm">
                    <CardHeader className="bg-gray-50 border-b">
                        <CardTitle className="text-lg font-semibold">{t('Record Information')}</CardTitle>
                    </CardHeader>
                    <CardContent className="p-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">{t('Created At')}</label>
                                <p className="text-sm mt-1">{formatDate(supplier.created_at)}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">{t('Updated At')}</label>
                                <p className="text-sm mt-1">{formatDate(supplier.updated_at)}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </PageTemplate>
    );
}
