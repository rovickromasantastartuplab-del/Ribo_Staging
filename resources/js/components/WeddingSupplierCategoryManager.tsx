import { useState } from 'react';
import { router } from '@inertiajs/react';
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
import { WeddingSupplierCategory } from '@/types/wedding-supplier';
import { Pencil, Trash2, Plus, Check, X } from 'lucide-react';

interface WeddingSupplierCategoryManagerProps {
    isOpen: boolean;
    onClose: () => void;
    categories: WeddingSupplierCategory[];
}

export function WeddingSupplierCategoryManager({ isOpen, onClose, categories }: WeddingSupplierCategoryManagerProps) {
    const { t } = useTranslation();
    const [newCategoryName, setNewCategoryName] = useState('');
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editingName, setEditingName] = useState('');
    const [processing, setProcessing] = useState(false);

    const handleAdd = () => {
        const name = newCategoryName.trim();
        if (!name) return;
        setProcessing(true);
        router.post(route('wedding-supplier-categories.store'), { name }, {
            preserveScroll: true,
            onSuccess: () => {
                setNewCategoryName('');
                setProcessing(false);
            },
            onError: () => setProcessing(false),
        });
    };

    const handleStartEdit = (cat: WeddingSupplierCategory) => {
        setEditingId(cat.id);
        setEditingName(cat.name);
    };

    const handleCancelEdit = () => {
        setEditingId(null);
        setEditingName('');
    };

    const handleSaveEdit = (cat: WeddingSupplierCategory) => {
        const name = editingName.trim();
        if (!name || name === cat.name) {
            handleCancelEdit();
            return;
        }
        setProcessing(true);
        router.put(route('wedding-supplier-categories.update', cat.id), { name }, {
            preserveScroll: true,
            onSuccess: () => {
                setEditingId(null);
                setEditingName('');
                setProcessing(false);
            },
            onError: () => setProcessing(false),
        });
    };

    const handleDelete = (cat: WeddingSupplierCategory) => {
        if (!confirm(t('Are you sure you want to delete "{{name}}"?', { name: cat.name }))) return;
        setProcessing(true);
        router.delete(route('wedding-supplier-categories.destroy', cat.id), {
            preserveScroll: true,
            onSuccess: () => setProcessing(false),
            onError: () => setProcessing(false),
        });
    };

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{t('Manage Categories')}</DialogTitle>
                </DialogHeader>

                <div className="py-2 space-y-4">
                    {/* Add new category */}
                    <div className="flex gap-2">
                        <Input
                            value={newCategoryName}
                            onChange={e => setNewCategoryName(e.target.value)}
                            placeholder={t('New category name')}
                            onKeyDown={e => e.key === 'Enter' && handleAdd()}
                            disabled={processing}
                        />
                        <Button
                            type="button"
                            size="sm"
                            onClick={handleAdd}
                            disabled={processing || !newCategoryName.trim()}
                        >
                            <Plus className="h-4 w-4 mr-1" />
                            {t('Add')}
                        </Button>
                    </div>

                    {/* Category list */}
                    <div className="space-y-1 max-h-64 overflow-y-auto">
                        {categories.length === 0 && (
                            <p className="text-sm text-gray-400 italic text-center py-4">
                                {t('No categories yet. Add one above.')}
                            </p>
                        )}
                        {categories.map(cat => (
                            <div key={cat.id} className="flex items-center gap-2 p-2 rounded-md hover:bg-gray-50 group">
                                {editingId === cat.id ? (
                                    <>
                                        <Input
                                            value={editingName}
                                            onChange={e => setEditingName(e.target.value)}
                                            onKeyDown={e => {
                                                if (e.key === 'Enter') handleSaveEdit(cat);
                                                if (e.key === 'Escape') handleCancelEdit();
                                            }}
                                            className="h-7 text-sm"
                                            autoFocus
                                            disabled={processing}
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="h-7 w-7 text-green-600 hover:text-green-700 hover:bg-green-50"
                                            onClick={() => handleSaveEdit(cat)}
                                            disabled={processing}
                                        >
                                            <Check className="h-3.5 w-3.5" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="h-7 w-7 text-gray-400 hover:text-gray-600"
                                            onClick={handleCancelEdit}
                                            disabled={processing}
                                        >
                                            <X className="h-3.5 w-3.5" />
                                        </Button>
                                    </>
                                ) : (
                                    <>
                                        <span className="flex-1 text-sm">{cat.name}</span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="h-7 w-7 opacity-0 group-hover:opacity-100 text-amber-500 hover:text-amber-600 hover:bg-amber-50"
                                            onClick={() => handleStartEdit(cat)}
                                            disabled={processing}
                                        >
                                            <Pencil className="h-3.5 w-3.5" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="h-7 w-7 opacity-0 group-hover:opacity-100 text-red-500 hover:text-red-600 hover:bg-red-50"
                                            onClick={() => handleDelete(cat)}
                                            disabled={processing}
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </Button>
                                    </>
                                )}
                            </div>
                        ))}
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>{t('Close')}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
