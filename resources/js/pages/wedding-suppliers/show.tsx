import { PageTemplate } from '@/components/page-template';
import { usePage } from '@inertiajs/react';
import { ArrowLeft, Building, MapPin, Globe, Phone, Mail, Clock, Facebook, User, Briefcase } from 'lucide-react';
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
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h1 className="text-xl font-bold">{supplier.name}</h1>
                            <div className="flex items-center text-sm text-muted-foreground mt-2">
                                <MapPin className="h-4 w-4 mr-1" />
                                {supplier.address || t('No address provided')}
                            </div>
                        </div>
                        <div className="text-right">
                            {supplier.category && (
                                <span className="inline-flex items-center rounded-md px-3 py-1 text-sm font-medium bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20">
                                    {supplier.category.name}
                                </span>
                            )}
                        </div>
                    </div>
                </div>

                {/* Primary Contact Info */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <Card className="border-l-4 border-l-green-500">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium text-muted-foreground mb-2">{t('Phone')}</p>
                                    <h3 className="text-lg font-bold text-green-600 truncate leading-tight">
                                        {supplier.phone || supplier.telephone || t('-')}
                                    </h3>
                                </div>
                                <div className="rounded-full bg-green-100 p-3 ml-3">
                                    <Phone className="h-4 w-4 text-green-600" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-l-4 border-l-blue-500">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium text-muted-foreground mb-2">{t('Email')}</p>
                                    <h3 className="text-lg font-bold text-blue-600 truncate leading-tight">
                                        {supplier.email || t('-')}
                                    </h3>
                                </div>
                                <div className="rounded-full bg-blue-100 p-3 ml-3">
                                    <Mail className="h-4 w-4 text-blue-600" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-l-4 border-l-purple-500">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium text-muted-foreground mb-2">{t('Availability')}</p>
                                    <h3 className="text-lg font-bold text-purple-600 truncate leading-tight">
                                        {supplier.available_contact_time || t('-')}
                                    </h3>
                                </div>
                                <div className="rounded-full bg-purple-100 p-3 ml-3">
                                    <Clock className="h-4 w-4 text-purple-600" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Detailed Information */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Company & Social Information */}
                    <Card className="shadow-sm">
                        <CardHeader className="bg-gray-50 border-b">
                            <CardTitle className="flex items-center text-lg font-semibold">
                                <Building className="h-5 w-5 mr-3 text-muted-foreground" />
                                {t('Company Information')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-6">
                            <div className="space-y-6">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">{t('Website')}</label>
                                        <div className="flex items-center mt-1">
                                            <Globe className="h-4 w-4 text-muted-foreground mr-2" />
                                            {supplier.website ? (
                                                <a href={supplier.website.startsWith('http') ? supplier.website : `https://${supplier.website}`} target="_blank" rel="noopener noreferrer" className="text-sm text-blue-600 hover:underline">
                                                    {supplier.website.replace(/^https?:\/\//, '')}
                                                </a>
                                            ) : (
                                                <p className="text-sm">{t('-')}</p>
                                            )}
                                        </div>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">{t('Facebook')}</label>
                                        <div className="flex items-center mt-1">
                                            <Facebook className="h-4 w-4 text-muted-foreground mr-2" />
                                            {supplier.facebook ? (
                                                <a href={supplier.facebook.startsWith('http') ? supplier.facebook : `https://${supplier.facebook}`} target="_blank" rel="noopener noreferrer" className="text-sm text-blue-600 hover:underline">
                                                    {t('View Profile')}
                                                </a>
                                            ) : (
                                                <p className="text-sm">{t('-')}</p>
                                            )}
                                        </div>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">{t('TikTok')}</label>
                                        <p className="text-sm mt-1">{supplier.tiktok || t('-')}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">{t('Telephone')}</label>
                                        <p className="text-sm mt-1">{supplier.telephone || t('-')}</p>
                                    </div>
                                </div>

                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">{t('Full Address')}</label>
                                    <p className="text-sm mt-1 whitespace-pre-line">{supplier.address || t('-')}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Contact Persons */}
                    <Card className="shadow-sm border-t-0 lg:border-t h-full">
                        <CardHeader className="bg-gray-50 border-b">
                            <CardTitle className="flex items-center text-lg font-semibold">
                                <User className="h-5 w-5 mr-3 text-muted-foreground" />
                                {t('Contact Persons')} ({supplier.contacts?.length || 0})
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            {supplier.contacts && supplier.contacts.length > 0 ? (
                                <div className="divide-y">
                                    {supplier.contacts.map((contact: any, index: number) => (
                                        <div key={contact.id || index} className="p-6 hover:bg-gray-50 transition-colors">
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
                                            <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                {contact.phone && (
                                                    <div className="flex items-center text-sm">
                                                        <Phone className="h-4 w-4 text-muted-foreground mr-2" />
                                                        {contact.phone}
                                                    </div>
                                                )}
                                                {contact.email && (
                                                    <div className="flex items-center text-sm">
                                                        <Mail className="h-4 w-4 text-muted-foreground mr-2" />
                                                        <a href={`mailto:${contact.email}`} className="text-blue-600 hover:underline">
                                                            {contact.email}
                                                        </a>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="p-8 text-center text-muted-foreground">
                                    <User className="h-12 w-12 mx-auto mb-3 text-gray-300" />
                                    <p>{t('No contact persons recorded.')}</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </PageTemplate>
    );
}
