import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Plus, Download } from 'lucide-react';
import { hasPermission } from '@/utils/authorization';
import { CrudTable } from '@/components/CrudTable';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Pagination } from '@/components/ui/pagination';
import { SearchAndFilterBar } from '@/components/ui/search-and-filter-bar';
import { LeadCaptureFormModal } from '../form-modal';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

export default function LeadCaptureForms() {
  const { t } = useTranslation();
  const {
    auth,
    forms,
    leadSources = [],
    campaigns = [],
    leadStatuses = [],
    users = [],
    allowedFields = [],
    defaultFieldsConfig = [],
    planLimits = null,
    canWhiteLabel = false,
    filters: pageFilters = {},
  } = usePage().props as any;
  const permissions = auth?.permissions || [];

  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
  const [selectedType, setSelectedType] = useState(pageFilters.type || 'all');
  const [showFilters, setShowFilters] = useState(false);
  const [isFormModalOpen, setIsFormModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [currentItem, setCurrentItem] = useState<any>(null);
  const [formMode, setFormMode] = useState<'create' | 'edit'>('create');
  const [isQrModalOpen, setIsQrModalOpen] = useState(false);

  const hasActiveFilters = () => searchTerm !== '' || selectedType !== 'all';
  const activeFilterCount = () => (searchTerm ? 1 : 0) + (selectedType !== 'all' ? 1 : 0);

  const applyFilters = () => {
    router.get(route('lead-capture.forms.index'), {
      page: 1,
      search: searchTerm || undefined,
      type: selectedType !== 'all' ? selectedType : undefined,
      per_page: pageFilters.per_page,
    }, { preserveState: true, preserveScroll: true });
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilters();
  };

  const handleSort = (field: string) => {
    const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'desc' ? 'asc' : 'desc';
    router.get(route('lead-capture.forms.index'), {
      sort_field: field,
      sort_direction: direction,
      page: 1,
      search: searchTerm || undefined,
      type: selectedType !== 'all' ? selectedType : undefined,
      per_page: pageFilters.per_page,
    }, { preserveState: true, preserveScroll: true });
  };

  const handleAddNew = () => {
    if (planLimits && !planLimits.can_create) {
      toast.error(t('Form limit reached. Your plan allows maximum {{max}} capture forms.', { max: planLimits.max_forms }));
      return;
    }
    setCurrentItem(null);
    setFormMode('create');
    setIsFormModalOpen(true);
  };

  const copyLink = (item: any) => {
    navigator.clipboard?.writeText(item.public_url).then(
      () => toast.success(t('Public link copied to clipboard.')),
      () => toast.error(t('Could not copy link.')),
    );
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
      case 'toggle-status':
        router.put(route('lead-capture.forms.toggle-status', item.id), {}, {
          preserveScroll: true,
          onSuccess: (page: any) => {
            if (page.props.flash?.success) toast.success(t(page.props.flash.success));
          },
        });
        break;
      case 'copy-link':
        copyLink(item);
        break;
      case 'download-qr':
        setIsQrModalOpen(true);
        break;
      case 'open':
        window.open(item.public_url, '_blank');
        break;
    }
  };

  const handleDeleteConfirm = () => {
    toast.loading(t('Deleting form...'));
    router.delete(route('lead-capture.forms.destroy', currentItem.id), {
      preserveScroll: true,
      onSuccess: (page: any) => {
        setIsDeleteModalOpen(false);
        toast.dismiss();
        if (page.props.flash?.success) toast.success(t(page.props.flash.success));
        else if (page.props.flash?.error) toast.error(t(page.props.flash.error));
      },
      onError: () => {
        toast.dismiss();
        toast.error(t('Failed to delete form.'));
      },
    });
  };

  const handleResetFilters = () => {
    setSearchTerm('');
    setSelectedType('all');
    setShowFilters(false);
    router.get(route('lead-capture.forms.index'), { page: 1, per_page: pageFilters.per_page }, { preserveState: true, preserveScroll: true });
  };

  const pageActions = [];
  if (hasPermission(permissions, 'manage-lead-capture')) {
    pageActions.push({
      label: t('Create Form'),
      icon: <Plus className="h-4 w-4 mr-2" />,
      variant: 'default' as const,
      onClick: handleAddNew,
    });
  }

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Lead Capture') },
    { title: t('Forms') },
  ];

  const columns = [
    { key: 'name', label: t('Name'), sortable: true },
    {
      key: 'type',
      label: t('Type'),
      render: (value: string) => value === 'staff_assisted' ? t('Staff-Assisted') : t('Customer-Facing'),
    },
    {
      key: 'lead_source',
      label: t('Source'),
      render: (_v: any, row: any) => row.lead_source?.name || t('-'),
    },
    {
      key: 'submissions_count',
      label: t('Submissions'),
      render: (_v: any, row: any) => `${row.submissions_count ?? 0} (${row.new_leads_count ?? 0} ${t('new')})`,
    },
    {
      key: 'is_active',
      label: t('Status'),
      render: (value: boolean) => (
        <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${value
          ? 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20'
          : 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20'}`}>
          {value ? t('Active') : t('Inactive')}
        </span>
      ),
    },
  ];

  const actions = [
    { label: t('Open Form'), icon: 'ExternalLink', action: 'open', className: 'text-blue-500', requiredPermission: 'manage-lead-capture' },
    { label: t('Copy Link'), icon: 'Copy', action: 'copy-link', className: 'text-blue-500', requiredPermission: 'manage-lead-capture' },
    { label: t('View QR Code'), icon: 'QrCode', action: 'download-qr', className: 'text-blue-500', requiredPermission: 'manage-lead-capture' },
    { label: t('Edit'), icon: 'Edit', action: 'edit', className: 'text-amber-500', requiredPermission: 'manage-lead-capture' },
    { label: t('Toggle Status'), icon: 'Lock', action: 'toggle-status', className: 'text-amber-500', requiredPermission: 'manage-lead-capture' },
    { label: t('Delete'), icon: 'Trash2', action: 'delete', className: 'text-red-500', requiredPermission: 'manage-lead-capture' },
  ];

  return (
    <PageTemplate title={t('Lead Capture Forms')} url="/lead-capture/forms" actions={pageActions} breadcrumbs={breadcrumbs} noPadding>
      {planLimits && (
        <div className="mb-4 text-sm text-gray-600 dark:text-gray-300">
          {t('Forms used')}: <span className="font-semibold">{planLimits.current_forms}/{planLimits.max_forms}</span>
        </div>
      )}

      <div className="bg-white dark:bg-gray-900 rounded-lg shadow mb-4 p-4">
        <SearchAndFilterBar
          searchTerm={searchTerm}
          onSearchChange={setSearchTerm}
          onSearch={handleSearch}
          filters={[
            {
              name: 'type',
              label: t('Type'),
              type: 'select',
              value: selectedType,
              onChange: setSelectedType,
              options: [
                { value: 'all', label: t('All Types') },
                { value: 'customer_facing', label: t('Customer-Facing') },
                { value: 'staff_assisted', label: t('Staff-Assisted') },
              ],
            },
          ]}
          showFilters={showFilters}
          setShowFilters={setShowFilters}
          hasActiveFilters={hasActiveFilters}
          activeFilterCount={activeFilterCount}
          onResetFilters={handleResetFilters}
          onApplyFilters={applyFilters}
          currentPerPage={pageFilters.per_page?.toString() || '10'}
          onPerPageChange={(value) => {
            router.get(route('lead-capture.forms.index'), {
              page: 1,
              per_page: parseInt(value),
              search: searchTerm || undefined,
              type: selectedType !== 'all' ? selectedType : undefined,
            }, { preserveState: true, preserveScroll: true });
          }}
        />
      </div>

      <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
        <CrudTable
          columns={columns}
          actions={actions}
          data={forms?.data || []}
          from={forms?.from || 1}
          onAction={handleAction}
          sortField={pageFilters.sort_field}
          sortDirection={pageFilters.sort_direction}
          onSort={handleSort}
          permissions={permissions}
          entityPermissions={{
            view: 'manage-lead-capture',
            edit: 'manage-lead-capture',
            delete: 'manage-lead-capture',
          }}
        />
        <Pagination
          from={forms?.from || 0}
          to={forms?.to || 0}
          total={forms?.total || 0}
          links={forms?.links}
          entityName={t('forms')}
          onPageChange={(url) => router.get(url)}
        />
      </div>

      <LeadCaptureFormModal
        isOpen={isFormModalOpen}
        onClose={() => setIsFormModalOpen(false)}
        mode={formMode}
        item={currentItem}
        leadSources={leadSources}
        campaigns={campaigns}
        leadStatuses={leadStatuses}
        users={users}
        allowedFields={allowedFields}
        defaultFieldsConfig={defaultFieldsConfig}
        canWhiteLabel={canWhiteLabel}
      />

      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={currentItem?.name || ''}
        entityName={t('capture form')}
      />

      <Dialog open={isQrModalOpen} onOpenChange={setIsQrModalOpen}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle>{currentItem?.name || t('QR Code')}</DialogTitle>
          </DialogHeader>
          <div className="flex flex-col items-center gap-2 py-2">
            {currentItem && (
              <img
                src={route('lead-capture.forms.qr', currentItem.id)}
                alt={t('QR Code')}
                className="h-56 w-56 rounded-lg border bg-white p-2"
              />
            )}
            <p className="text-center text-xs text-muted-foreground break-all">
              {currentItem?.public_url}
            </p>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsQrModalOpen(false)}>
              {t('Close')}
            </Button>
            <Button
              onClick={() => {
                if (currentItem) window.location.href = route('lead-capture.forms.qr', currentItem.id);
              }}
            >
              <Download className="mr-2 h-4 w-4" />
              {t('Download')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </PageTemplate>
  );
}
