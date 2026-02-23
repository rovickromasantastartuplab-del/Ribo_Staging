import { useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { WeddingSupplier, WeddingSupplierCategory } from '@/types/wedding-supplier';
import { Plus, Trash2 } from 'lucide-react';
import { Separator } from '@/components/ui/separator';


interface WeddingSupplierFormModalProps {
    isOpen: boolean;
    onClose: () => void;
    supplier: WeddingSupplier | null;
    mode: 'create' | 'edit';
    categories: WeddingSupplierCategory[];
}

export function WeddingSupplierFormModal({ isOpen, onClose, supplier, mode, categories }: WeddingSupplierFormModalProps) {
    const { t } = useTranslation();

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        category_id: '',
        email: '',
        phone: '',
        telephone: '',
        website: '',
        address: '',
        facebook: '',
        tiktok: '',
        available_contact_time: '',
        contacts: [] as { id?: number; name: string; position: string; phone: string; email: string }[]
    });

    useEffect(() => {
        if (isOpen) {
            if (supplier && mode === 'edit') {
                setData({
                    name: supplier.name || '',
                    category_id: supplier.category_id?.toString() || '',
                    email: supplier.email || '',
                    phone: supplier.phone || '',
                    telephone: supplier.telephone || '',
                    website: supplier.website || '',
                    address: supplier.address || '',
                    facebook: supplier.facebook || '',
                    tiktok: supplier.tiktok || '',
                    available_contact_time: supplier.available_contact_time || '',
                    contacts: supplier.contacts ? supplier.contacts.map(c => ({
                        id: c.id,
                        name: c.name,
                        position: c.position || '',
                        phone: c.phone || '',
                        email: c.email || ''
                    })) : []
                });
            } else {
                reset();
            }
        }
    }, [isOpen, supplier, mode]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (mode === 'create') {
            post(route('wedding-suppliers.store'), {
                onSuccess: () => {
                    reset();
                    onClose();
                },
                onError: () => {
                    // console.error(errors); // Optional for debugging
                }
            });
        } else {
            if (!supplier) return;
            put(route('wedding-suppliers.update', supplier.id), {
                onSuccess: () => {
                    onClose();
                },
                onError: () => {
                    // console.error(errors);
                }
            });
        }
    };

    const addContact = () => {
        setData('contacts', [...data.contacts, { name: '', position: '', phone: '', email: '' }]);
    };

    const removeContact = (index: number) => {
        const newContacts = [...data.contacts];
        newContacts.splice(index, 1);
        setData('contacts', newContacts);
    };

    const updateContact = (index: number, field: string, value: string) => {
        const newContacts = [...data.contacts];
        newContacts[index] = { ...newContacts[index], [field]: value };
        setData('contacts', newContacts);
    };

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{mode === 'create' ? t('Add Supplier') : t('Edit Supplier')}</DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="name">{t('Name')} *</Label>
                            <Input id="name" value={data.name} onChange={e => setData('name', e.target.value)} required />
                            {errors.name && <span className="text-red-500 text-xs">{errors.name}</span>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="category_id">{t('Category')} *</Label>
                            <select
                                id="category_id"
                                value={data.category_id}
                                onChange={e => setData('category_id', e.target.value)}
                                className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                required
                            >
                                <option value="">{t('Select Category')}</option>
                                {categories.map((cat: WeddingSupplierCategory) => (
                                    <option key={cat.id} value={cat.id.toString()}>{cat.name}</option>
                                ))}
                            </select>
                            {errors.category_id && <span className="text-red-500 text-xs">{errors.category_id}</span>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="email">{t('Email')}</Label>
                            <Input id="email" type="email" value={data.email} onChange={e => setData('email', e.target.value)} />
                            {errors.email && <span className="text-red-500 text-xs">{errors.email}</span>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="phone">{t('Mobile')}</Label>
                            <Input id="phone" value={data.phone} onChange={e => setData('phone', e.target.value)} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="telephone">{t('Telephone')}</Label>
                            <Input id="telephone" value={data.telephone} onChange={e => setData('telephone', e.target.value)} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="website">{t('Website')}</Label>
                            <Input id="website" value={data.website} onChange={e => setData('website', e.target.value)} placeholder="https://" />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="available_contact_time">{t('Available Contact Time')}</Label>
                            <Input
                                id="available_contact_time"
                                value={data.available_contact_time}
                                onChange={(e) => setData('available_contact_time', e.target.value)}
                                placeholder="e.g. 9:00 AM - 5:00 PM"
                            />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="address">{t('Address')}</Label>
                        <Textarea id="address" value={data.address} onChange={e => setData('address', e.target.value)} />
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="facebook">{t('Facebook')}</Label>
                            <Input id="facebook" value={data.facebook} onChange={e => setData('facebook', e.target.value)} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="tiktok">{t('TikTok')}</Label>
                            <Input id="tiktok" value={data.tiktok} onChange={e => setData('tiktok', e.target.value)} />
                        </div>
                    </div>

                    <Separator />

                    <div>
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="text-lg font-medium">{t('Contact Persons')}</h3>
                            <Button type="button" variant="outline" size="sm" onClick={addContact}>
                                <Plus className="h-4 w-4 mr-2" /> {t('Add Contact')}
                            </Button>
                        </div>

                        <div className="space-y-4">
                            {data.contacts.map((contact, index) => (
                                <div key={index} className="flex flex-col gap-2 bg-gray-50 p-3 rounded-md border">
                                    <div className="flex gap-2 items-start">
                                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 flex-1">
                                            <div className="space-y-1">
                                                <Input
                                                    placeholder={t('Name')}
                                                    value={contact.name}
                                                    onChange={e => updateContact(index, 'name', e.target.value)}
                                                    required
                                                />
                                                {errors[`contacts.${index}.name`] && <span className="text-red-500 text-xs">{errors[`contacts.${index}.name`]}</span>}
                                            </div>
                                            <div className="space-y-1">
                                                <Input
                                                    placeholder={t('Position')}
                                                    value={contact.position}
                                                    onChange={e => updateContact(index, 'position', e.target.value)}
                                                />
                                                {errors[`contacts.${index}.position`] && <span className="text-red-500 text-xs">{errors[`contacts.${index}.position`]}</span>}
                                            </div>
                                            <div className="space-y-1">
                                                <Input
                                                    placeholder={t('Phone')}
                                                    value={contact.phone}
                                                    onChange={e => updateContact(index, 'phone', e.target.value)}
                                                />
                                                {errors[`contacts.${index}.phone`] && <span className="text-red-500 text-xs">{errors[`contacts.${index}.phone`]}</span>}
                                            </div>
                                            <div className="space-y-1">
                                                <Input
                                                    type="email"
                                                    placeholder={t('Email')}
                                                    value={contact.email}
                                                    onChange={e => updateContact(index, 'email', e.target.value)}
                                                />
                                                {errors[`contacts.${index}.email`] && <span className="text-red-500 text-xs">{errors[`contacts.${index}.email`]}</span>}
                                            </div>
                                        </div>
                                        <Button type="button" variant="ghost" size="icon" className="text-red-500 hover:text-red-700 hover:bg-red-50" onClick={() => removeContact(index)}>
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                            {data.contacts.length === 0 && (
                                <p className="text-gray-500 text-sm italic">{t('No contacts added yet.')}</p>
                            )}
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>{t('Cancel')}</Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? t('Saving...') : (mode === 'create' ? t('Create') : t('Update'))}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
