import { useState } from 'react';
import { PageTemplate, PageAction } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Plus, Edit, Trash2, Download, Upload } from 'lucide-react';
import { hasPermission } from '@/utils/authorization';
import { CrudTable } from '@/components/CrudTable';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { ImportModal } from '@/components/ImportModal';
import { WeddingSupplierFormModal } from '@/components/WeddingSupplierFormModal';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Pagination } from '@/components/ui/pagination';
import { SearchAndFilterBar } from '@/components/ui/search-and-filter-bar';
import { useInitials } from '@/hooks/use-initials';
import { WeddingSupplier } from '../../types/wedding-supplier';

export default function WeddingSuppliers() {
    const { t } = useTranslation();
    const { auth, suppliers, categories, filters: pageFilters = {} } = usePage().props as any;
    const permissions = auth?.permissions || [];
    const getInitials = useInitials();

    // State
    const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
    const [selectedCategory, setSelectedCategory] = useState(pageFilters.category_id || 'all');
    const [perPage, setPerPage] = useState(pageFilters.per_page || '10');
    const [showFilters, setShowFilters] = useState(false);
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [isImportModalOpen, setIsImportModalOpen] = useState(false);
    const [currentItem, setCurrentItem] = useState<WeddingSupplier | null>(null);
    const [formMode, setFormMode] = useState<'create' | 'edit'>('create');

    // Check if any filters are active
    const hasActiveFilters = () => {
        return searchTerm !== '' || selectedCategory !== 'all';
    };

    // Count active filters
    const activeFilterCount = () => {
        return (searchTerm ? 1 : 0) + (selectedCategory !== 'all' ? 1 : 0);
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters();
    };

    const applyFilters = () => {
        router.get(route('wedding-suppliers.index'), {
            page: 1,
            search: searchTerm || undefined,
            category_id: selectedCategory !== 'all' ? selectedCategory : undefined,
            per_page: perPage,
        }, { preserveState: true, preserveScroll: true });
    };

    const handleExport = () => {
        window.location.href = route('wedding-suppliers.export');
    };

    const handleSort = (field: string) => {
        // Basic sort implementation or rely on backend default
        // Currently backend only sorts by name
    };

    const handleAction = (action: string, item: any) => {
        setCurrentItem(item);

        switch (action) {
            case 'edit':
                setFormMode('edit');
                setIsFormModalOpen(true);
                break;
            case 'delete':
                setIsDeleteModalOpen(true);
                break;
        }
    };

    const handleAddNew = () => {
        setCurrentItem(null);
        setFormMode('create');
        setIsFormModalOpen(true);
    };

    const handleDeleteConfirm = () => {
        if (!currentItem) return;

        toast.loading(t('Deleting supplier...'));

        router.delete(route('wedding-suppliers.destroy', currentItem.id), {
            onSuccess: (page) => {
                setIsDeleteModalOpen(false);
                toast.dismiss();
                if ((page.props as any).flash?.success) {
                    toast.success(t((page.props as any).flash.success));
                } else if ((page.props as any).flash?.error) {
                    toast.error(t((page.props as any).flash.error));
                }
            },
            onError: (errors) => {
                toast.dismiss();
                toast.error(t('Failed to delete supplier'));
            }
        });
    };

    const handleResetFilters = () => {
        setSearchTerm('');
        setSelectedCategory('all');
        setShowFilters(false);

        router.get(route('wedding-suppliers.index'), {
            page: 1,
            per_page: perPage
        }, { preserveState: true, preserveScroll: true });
    };

    // Define page actions
    const pageActions: PageAction[] = [];

    // Add export button
    if (hasPermission(permissions, 'export-wedding-suppliers')) {
        pageActions.push({
            label: t('Export'),
            icon: <Download className="h-4 w-4 mr-2" />,
            variant: 'outline',
            onClick: () => handleExport()
        });
    }

    // Add import button
    if (hasPermission(permissions, 'import-wedding-suppliers')) {
        pageActions.push({
            label: t('Import'),
            icon: <Upload className="h-4 w-4 mr-2" />,
            variant: 'outline',
            onClick: () => setIsImportModalOpen(true)
        });
    }

    // Add the "Add New" button if user has permission
    if (hasPermission(permissions, 'create-wedding-suppliers')) { // Adjust permission name if specific one exists
        pageActions.push({
            label: t('Add Supplier'),
            icon: <Plus className="h-4 w-4 mr-2" />,
            variant: 'default',
            onClick: () => handleAddNew()
        });
    }

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Wedding Suppliers') }
    ];

    // Define table columns
    const columns = [
        {
            key: 'name',
            label: t('Name'),
            sortable: true,
            render: (value: any, row: any) => {
                return (
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                            {getInitials(row.name)}
                        </div>
                        <div>
                            <div className="font-medium">{row.name}</div>
                            <div className="text-sm text-muted-foreground">{row.email || t('-')}</div>
                        </div>
                    </div>
                );
            }
        },
        {
            key: 'category',
            label: t('Category'),
            render: (value: any) => value?.name || t('-')
        },
        {
            key: 'phone',
            label: t('Phone'),
            render: (value: string) => value || t('-')
        },
        {
            key: 'website',
            label: t('Website'),
            render: (value: string) => value ? <a href={value} target="_blank" rel="noopener noreferrer" className="text-blue-500 hover:underline">{value}</a> : t('-')
        },
        {
            key: 'contacts',
            label: t('Contacts'),
            render: (value: any[]) => value && value.length > 0 ? (
                <div className="flex -space-x-2 overflow-hidden">
                    <span className="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10">
                        {value.length} {value.length === 1 ? t('contact') : t('contacts')}
                    </span>
                </div>
            ) : t('-')
        }
    ];

    // Define table actions
    const actions = [
        {
            label: t('Edit'),
            icon: 'Edit',
            action: 'edit',
            className: 'text-amber-500',
            requiredPermission: 'create-wedding-suppliers' // Adjust permission
        },
        {
            label: t('Delete'),
            icon: 'Trash2',
            action: 'delete',
            className: 'text-red-500',
            requiredPermission: 'create-wedding-suppliers' // Adjust permission
        }
    ];

    return (
        <PageTemplate
            title={t("Wedding Suppliers")}
            description={t("Manage your wedding suppliers")}
            url="/wedding-suppliers"
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            {/* Search and filters section */}
            <div className="bg-white dark:bg-gray-900 rounded-lg shadow mb-4 p-4">
                <SearchAndFilterBar
                    searchTerm={searchTerm}
                    onSearchChange={setSearchTerm}
                    onSearch={handleSearch}
                    filters={[
                        {
                            name: 'category_id',
                            label: t('Category'),
                            type: 'select',
                            value: selectedCategory,
                            onChange: setSelectedCategory,
                            options: [
                                { value: 'all', label: t('All Categories') },
                                ...categories.map((cat: any) => ({
                                    value: cat.id.toString(),
                                    label: cat.name
                                }))
                            ]
                        }
                    ]}
                    showFilters={showFilters}
                    setShowFilters={setShowFilters}
                    hasActiveFilters={hasActiveFilters}
                    activeFilterCount={activeFilterCount}
                    onResetFilters={handleResetFilters}
                    onApplyFilters={applyFilters}
                    currentPerPage={perPage}
                    onPerPageChange={(value) => {
                        setPerPage(value);
                        router.get(route('wedding-suppliers.index'), {
                            page: 1,
                            search: searchTerm || undefined,
                            category_id: selectedCategory !== 'all' ? selectedCategory : undefined,
                            per_page: value,
                        }, { preserveState: true, preserveScroll: true });
                    }}
                />
            </div>

            {/* Content section */}
            <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
                <CrudTable
                    columns={columns}
                    actions={actions}
                    data={suppliers?.data || []}
                    from={suppliers?.from || 1}
                    onAction={handleAction}
                    permissions={permissions}
                    // Assuming blanket permission for now as per controller 'create' check
                    entityPermissions={{
                        view: 'view-any-wedding-suppliers',
                        edit: 'create-wedding-suppliers',
                        delete: 'create-wedding-suppliers'
                    }}
                    showActionsAsIcons={true}
                />

                {/* Pagination section */}
                <Pagination
                    from={suppliers?.from || 1}
                    to={suppliers?.to || suppliers?.data?.length || 0}
                    total={suppliers?.total || suppliers?.data?.length || 0}
                    links={suppliers?.links}
                    entityName={t("suppliers")}
                    onPageChange={(url) => router.get(url)}
                />
            </div>

            <WeddingSupplierFormModal
                isOpen={isFormModalOpen}
                onClose={() => setIsFormModalOpen(false)}
                supplier={currentItem}
                mode={formMode}
                categories={categories}
            />

            <CrudDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => setIsDeleteModalOpen(false)}
                onConfirm={handleDeleteConfirm}
                entityName={t('Supplier')}
                itemName={currentItem?.name || ''}
            />
            {/*ge */}
            <ImportModal
                isOpen={isImportModalOpen}
                onClose={() => {
                    setIsImportModalOpen(false);
                    // Optionally reload to see new data if import was successful (handled by ImportModal internal logic usually, or we reload here)
                    router.reload();
                }}
                title={t('Import Suppliers')}
                importRoute={'wedding-suppliers.import'}
                parseRoute={'wedding-suppliers.parse'}
                // templateRoute={'wedding-suppliers.download.template'} // Ensure this prop exists if used, checking ImportModal again... samplePath is the prop
                samplePath={route('wedding-suppliers.download.template')}
                importNotes={t('Supported file types: CSV, Excel. Ensure the first row contains headers.')}
                databaseFields={[
                    { key: 'name', required: true },
                    { key: 'category', required: false },
                    { key: 'email', required: false },
                    { key: 'phone', required: false },
                    { key: 'telephone', required: false },
                    { key: 'website', required: false },
                    { key: 'address', required: false },
                    { key: 'facebook', required: false },
                    { key: 'tiktok', required: false },
                    { key: 'available_contact_time', required: false },
                    { key: 'contact_name', required: false },
                    { key: 'contact_position', required: false },
                    { key: 'contact_phone', required: false },
                    { key: 'contact_email', required: false },
                ]}
            />
        </PageTemplate>
    );
}
