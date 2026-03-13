import { useState, useEffect, useCallback } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Plus, Eye, Edit, Trash2, MoreHorizontal, Building2, User, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuSub, DropdownMenuSubContent, DropdownMenuSubTrigger, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { hasPermission } from '@/utils/authorization';
import { CrudTable } from '@/components/CrudTable';
import { CrudFormModal } from '@/components/CrudFormModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Pagination } from '@/components/ui/pagination';
import { SearchAndFilterBar } from '@/components/ui/search-and-filter-bar';
import { useInitials } from '@/hooks/use-initials';
import { Input } from '@/components/ui/input';
import { formatCurrency } from '@/utils/currency';


export default function Opportunities() {
  const { t } = useTranslation();
  const { auth, opportunities, accounts = [], contacts = [], products = [], opportunityStages = [], opportunitySources = [], users = [], filters: pageFilters = {} } = usePage().props as any;
  const permissions = auth?.permissions || [];
  const isCompany = auth?.user?.type === 'company' || auth?.user?.type === 'staff';
  const canManageOpportunities = hasPermission(permissions, 'manage-opportunities');
  const getInitials = useInitials();

  // State
  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
  const [selectedAccount, setSelectedAccount] = useState(pageFilters.account_id || 'all');
  const [selectedStage, setSelectedStage] = useState(pageFilters.opportunity_stage_id || 'all');
  const [selectedSource, setSelectedSource] = useState(pageFilters.opportunity_source_id || 'all');
  const [selectedStatus, setSelectedStatus] = useState(pageFilters.status || 'all');
  const [selectedAssignee, setSelectedAssignee] = useState(pageFilters.assigned_to || 'all');
  const [showFilters, setShowFilters] = useState(false);
  const [isFormModalOpen, setIsFormModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [currentItem, setCurrentItem] = useState<any>(null);
  const [formMode, setFormMode] = useState<'create' | 'edit' | 'view'>('create');
  const [activeView, setActiveView] = useState(pageFilters.view || 'kanban');
  const [kanbanData, setKanbanData] = useState<any>(null);
  const [kanbanDataRef, setKanbanDataRef] = useState<any>(null);
  const [isLoadingKanban, setIsLoadingKanban] = useState(false);
  const [prefilledOpportunityStage, setPrefilledOpportunityStage] = useState<string>('');
  const [selectedFormAccountId, setSelectedFormAccountId] = useState<string | null>(null);

  // Check if any filters are active
  const hasActiveFilters = () => {
    return searchTerm !== '' || selectedAccount !== 'all' || selectedStage !== 'all' || selectedSource !== 'all' || selectedStatus !== 'all' || selectedAssignee !== 'all';
  };

  // Count active filters
  const activeFilterCount = () => {
    return (searchTerm ? 1 : 0) + (selectedAccount !== 'all' ? 1 : 0) + (selectedStage !== 'all' ? 1 : 0) + (selectedSource !== 'all' ? 1 : 0) + (selectedStatus !== 'all' ? 1 : 0) + (selectedAssignee !== 'all' ? 1 : 0);
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilters();
  };

  const applyFilters = () => {
    router.get(route('opportunities.index'), {
      view: activeView,
      page: 1,
      search: searchTerm || undefined,
      account_id: selectedAccount !== 'all' ? selectedAccount : undefined,
      opportunity_stage_id: selectedStage !== 'all' ? selectedStage : undefined,
      opportunity_source_id: selectedSource !== 'all' ? selectedSource : undefined,
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
      assigned_to: selectedAssignee !== 'all' ? selectedAssignee : undefined
    }, { preserveState: true, preserveScroll: true });
  };

  const handleSort = (field: string) => {
    const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'desc' ? 'asc' : 'desc';

    router.get(route('opportunities.index'), {
      sort_field: field,
      sort_direction: direction,
      page: 1,
      search: searchTerm || undefined,
      account_id: selectedAccount !== 'all' ? selectedAccount : undefined,
      opportunity_stage_id: selectedStage !== 'all' ? selectedStage : undefined,
      opportunity_source_id: selectedSource !== 'all' ? selectedSource : undefined,
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
      assigned_to: selectedAssignee !== 'all' ? selectedAssignee : undefined,
      ...(pageFilters.per_page && pageFilters.per_page !== 10 && { per_page: pageFilters.per_page })
    }, { preserveState: true, preserveScroll: true });
  };

  const handleMoveToStage = async (opportunity: any, stageId: number) => {
    if (!hasPermission(permissions, 'edit-opportunities')) {
      toast.error(t('Permission denied.'));
      return;
    }

    // Find destination stage
    const destStage = opportunityStages.find((s: any) => s.id === stageId);
    if (!destStage) return;

    // --- Optimistic update ---
    const prevKanbanData = kanbanData;
    const sourceStageId = opportunity.opportunity_stage_id?.toString() || opportunity.opportunity_stage?.id?.toString();
    const updatedOpportunity = { ...opportunity, opportunity_stage_id: stageId, opportunity_stage: destStage };

    setKanbanData((prev: any) => {
      if (!prev) return prev;
      const next = { ...prev };
      // Remove from source column
      if (sourceStageId && next[sourceStageId]) {
        next[sourceStageId] = {
          ...next[sourceStageId],
          items: next[sourceStageId].items.filter((o: any) => o.id !== opportunity.id)
        };
      }
      // Add to destination column
      const destKey = stageId.toString();
      if (next[destKey]) {
        next[destKey] = {
          ...next[destKey],
          items: [...next[destKey].items, updatedOpportunity]
        };
      }
      return next;
    });
    // --- End optimistic update ---

    toast.loading(t('Moving opportunity...'));
    try {
      const response = await fetch(route('opportunities.update-status', opportunity.id), {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({ opportunity_stage_id: stageId })
      });
      const data = await response.json();
      toast.dismiss();
      if (!response.ok) throw new Error(data.message || t('Failed to move opportunity'));
      toast.success(data.message || t('Opportunity moved successfully'));
    } catch (error) {
      // Rollback on failure
      setKanbanData(prevKanbanData);
      toast.dismiss();
      toast.error(error instanceof Error ? error.message : t('Failed to move opportunity'));
    }
  };

  const handleAction = (action: string, item: any) => {
    setCurrentItem(item);

    switch (action) {
      case 'view':
        router.get(route('opportunities.show', item.id));
        break;
      case 'edit':
        setFormMode('edit');
        setSelectedFormAccountId(item.account_id?.toString() || null);
        setIsFormModalOpen(true);
        break;
      case 'delete':
        setIsDeleteModalOpen(true);
        break;
      case 'toggle-status':
        handleToggleStatus(item);
        break;
    }
  };

  const handleAddNew = () => {
    setCurrentItem(null);
    setFormMode('create');
    setSelectedFormAccountId(null);
    setIsFormModalOpen(true);
  };

  const handleAddOpportunity = (stageId: string) => {
    setCurrentItem(null);
    setFormMode('create');
    setPrefilledOpportunityStage(stageId);
    setSelectedFormAccountId(null);
    setIsFormModalOpen(true);
  };

  const handleFormSubmit = (formData: any) => {
    // Calculate total amount from products if products are selected
    if (formData.products && formData.products.length > 0) {
      const totalAmount = formData.products.reduce((total: number, product: any) => {
        const quantity = parseFloat(product.quantity) || 0;
        const unitPrice = parseFloat(product.unit_price) || 0;
        return total + (quantity * unitPrice);
      }, 0);
      formData.amount = totalAmount;
    }

    if (formMode === 'create') {
      toast.loading(t('Creating opportunity...'));

      router.post(route('opportunities.store'), formData, {
        onSuccess: (page) => {
          setIsFormModalOpen(false);
          toast.dismiss();
          if (page.props.flash.success) {
            toast.success(t(page.props.flash.success));
          } else if (page.props.flash.error) {
            toast.error(t(page.props.flash.error));
          } else if (page.props.flash.warning) {
            toast.warning(t(page.props.flash.warning));
          }
          if (activeView === 'kanban') {
            loadKanbanData();
          }
        },
        onError: (errors) => {
          toast.dismiss();
          if (typeof errors === 'string') {
            toast.error(errors);
          } else {
            toast.error(t('Failed to create: {{errors}}', { errors: Object.values(errors).join(', ') }));
          }
        }
      });
    } else if (formMode === 'edit') {
      if (!hasPermission(permissions, 'edit-opportunities')) {
        toast.error(t('Permission denied.'));
        return;
      }

      toast.loading(t('Updating opportunity...'));

      router.put(route('opportunities.update', currentItem.id), formData, {
        onSuccess: (page) => {
          setIsFormModalOpen(false);
          toast.dismiss();
          if (page.props.flash.success) {
            toast.success(t(page.props.flash.success));
          } else if (page.props.flash.error) {
            toast.error(t(page.props.flash.error));
          }
          // Don't reload kanban data for edit operations as drag-drop handles it
        },
        onError: (errors) => {
          toast.dismiss();
          if (typeof errors === 'string') {
            toast.error(errors);
          } else {
            toast.error(t('Failed to update: {{errors}}', { errors: Object.values(errors).join(', ') }));
          }
        }
      });
    }
  };


  const handleBulkAction = (action: string, selectedIds: any[]) => {
    if (action === 'bulk_delete') {
      if (!hasPermission(permissions, 'delete-opportunities')) {
        toast.error(t('Permission denied.'));
        return;
      }

      if (confirm(t('Are you sure you want to delete the selected {{count}} records? This action cannot be undone.', { count: selectedIds.length }))) {
        toast.loading(t('Deleting records...'));

        router.delete(route('opportunities.bulk-delete'), {
          data: { ids: selectedIds },
          onSuccess: (page: any) => {
            toast.dismiss();
            if (page.props.flash?.success) {
              toast.success(t(page.props.flash.success));
            } else if (page.props.flash?.error) {
              toast.error(t(page.props.flash.error));
            }
          },
          onError: () => {
            toast.dismiss();
            toast.error(t('Failed to delete records.'));
          }
        });
      }
    }
  };

  const handleDeleteConfirm = () => {
    toast.loading(t('Deleting opportunity...'));

    router.delete(route('opportunities.destroy', currentItem.id), {
      onSuccess: (page) => {
        setIsDeleteModalOpen(false);
        toast.dismiss();
        if (page.props.flash.success) {
          toast.success(t(page.props.flash.success));
        } else if (page.props.flash.error) {
          toast.error(t(page.props.flash.error));
        }
        if (activeView === 'kanban') {
          loadKanbanData();
        }
      },
      onError: (errors) => {
        toast.dismiss();
        if (typeof errors === 'string') {
          toast.error(errors);
        } else {
          toast.error(t('Failed to delete: {{errors}}', { errors: Object.values(errors).join(', ') }));
        }
      }
    });
  };

  const handleToggleStatus = (opportunity: any) => {
    if (!hasPermission(permissions, 'toggle-status-opportunities')) {
      toast.error(t('Permission denied.'));
      return;
    }

    const newStatus = opportunity.status === 'active' ? 'inactive' : 'active';
    toast.loading(t('{{action}} opportunity...', { action: newStatus === 'active' ? t('Activating') : t('Deactivating') }));

    router.put(route('opportunities.toggle-status', opportunity.id), {}, {
      onSuccess: (page) => {
        toast.dismiss();
        if (page.props.flash.success) {
          toast.success(t(page.props.flash.success));
        } else if (page.props.flash.error) {
          toast.error(t(page.props.flash.error));
        }
        if (activeView === 'kanban') {
          loadKanbanData();
        }
      },
      onError: (errors) => {
        toast.dismiss();
        if (typeof errors === 'string') {
          toast.error(errors);
        } else {
          toast.error(t('Failed to update: {{errors}}', { errors: Object.values(errors).join(', ') }));
        }
      }
    });
  };

  const handleResetFilters = () => {
    setSearchTerm('');
    setSelectedAccount('all');
    setSelectedStage('all');
    setSelectedSource('all');
    setSelectedStatus('all');
    setSelectedAssignee('all');
    setShowFilters(false);

    router.get(route('opportunities.index'), {
      view: activeView,
      page: 1
    }, { preserveState: true, preserveScroll: true });
  };

  // Build a stable key that changes when stages are reordered (not just added/removed)
  const opportunityStageOrderKey = JSON.stringify(opportunityStages.map((s: any) => s.id));

  const loadKanbanData = useCallback(() => {
    if (activeView !== 'kanban') return;

    setIsLoadingKanban(true);

    // Use existing opportunities data to structure kanban
    const opportunitiesData = opportunities?.data || [];
    const structuredData = {};

    opportunityStages.forEach(stage => {
      structuredData[stage.id] = {
        status: stage,
        items: opportunitiesData.filter(opportunity => {
          const matchesStage = opportunity.opportunity_stage?.id === stage.id;
          const matchesSearch = !searchTerm ||
            opportunity.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
            opportunity.description?.toLowerCase().includes(searchTerm.toLowerCase());
          const matchesAccount = selectedAccount === 'all' || opportunity.account_id?.toString() === selectedAccount;
          const matchesSource = selectedSource === 'all' || opportunity.opportunity_source_id?.toString() === selectedSource;
          const matchesStatus = selectedStatus === 'all' || opportunity.status === selectedStatus;

          return matchesStage && matchesSearch && matchesAccount && matchesSource && matchesStatus;
        })
      };
    });

    setKanbanData(structuredData);
    setKanbanDataRef(structuredData);
    setIsLoadingKanban(false);
  }, [activeView, opportunities?.data, searchTerm, selectedAccount, selectedSource, selectedStatus, opportunityStageOrderKey]);

  useEffect(() => {
    if (activeView === 'kanban') {
      loadKanbanData();
    }
  }, [loadKanbanData]);

  // Define page actions
  const pageActions = [];

  // Add the "Add New Opportunity" button if user has permission
  if (hasPermission(permissions, 'create-opportunities')) {
    pageActions.push({
      label: t('Add Opportunity'),
      icon: <Plus className="h-4 w-4 mr-2" />,
      variant: 'default',
      onClick: () => handleAddNew()
    });
  }

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Opportunity Management') },
    { title: t('Opportunities') }
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
            <div className="flex shrink-0 h-10 w-10 items-center justify-center rounded-full bg-primary text-white">
              {getInitials(row.name)}
            </div>
            <div className="min-w-0">
              <div className="font-medium">{row.name}</div>
              <div className="text-sm text-muted-foreground">{row.account?.name || t('No account')}</div>
            </div>
          </div>
        );
      }
    },
    {
      key: 'amount',
      label: t('Amount'),
      sortable: true,
      render: (value: any) => value ? (formatCurrency(parseFloat(value))) : '-'
    },
    {
      key: 'close_date',
      label: t('Close Date'),
      sortable: true,
      render: (value: string) => window.appSettings?.formatDateTime(value, false) || new Date(value).toLocaleDateString()
    },
    {
      key: 'opportunity_stage',
      label: t('Stage'),
      render: (value: any) => value ? (
        <div className="flex items-center gap-2">
          <div
            className="w-3 h-3 rounded-full"
            style={{ backgroundColor: value.color }}
          ></div>
          <span>{value.name}</span>
        </div>
      ) : '-'
    },
    {
      key: 'opportunity_source',
      label: t('Source'),
      render: (value: any) => value?.name || t('-')
    },
    {
      key: 'contact',
      label: t('Contact'),
      render: (value: any) => value?.name || t('-')
    },
    {
      key: 'assigned_user',
      label: t('Assigned To'),
      render: (value: any) => value?.name || t('Unassigned')
    },
    {
      key: 'status',
      label: t('Status'),
      render: (value: string) => {
        return (
          <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${value === 'active'
            ? 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20'
            : 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20'
            }`}>
            {value === 'active' ? t('Active') : t('Inactive')}
          </span>
        );
      }
    },
    {
      key: 'created_at',
      label: t('Created At'),
      sortable: true,
      render: (value: string) => window.appSettings?.formatDateTime(value, false) || new Date(value).toLocaleDateString()
    }
  ];

  // Define table actions
  const actions = [
    {
      label: t('View'),
      icon: 'Eye',
      action: 'view',
      className: 'text-blue-500',
      requiredPermission: 'view-opportunities'
    },
    {
      label: t('Edit'),
      icon: 'Edit',
      action: 'edit',
      className: 'text-amber-500',
      requiredPermission: 'edit-opportunities'
    },
    {
      label: t('Toggle Status'),
      icon: 'Lock',
      action: 'toggle-status',
      className: 'text-amber-500',
      requiredPermission: 'toggle-status-opportunities'
    },
    {
      label: t('Delete'),
      icon: 'Trash2',
      action: 'delete',
      className: 'text-red-500',
      requiredPermission: 'delete-opportunities'
    }
  ];

  return (
    <PageTemplate
      title={t("Opportunities")}
      url="/opportunities"
      actions={pageActions}
      breadcrumbs={breadcrumbs}
      noPadding
      className={activeView === 'kanban' ? 'overflow-hidden' : ''}
    >
      {/* Search and filters section */}
      <div className="bg-white dark:bg-gray-900 rounded-lg shadow mb-4 p-4">
        <SearchAndFilterBar
          searchTerm={searchTerm}
          onSearchChange={setSearchTerm}
          onSearch={handleSearch}
          filters={[
            {
              name: 'account_id',
              label: t('Account'),
              type: 'select',
              value: selectedAccount,
              onChange: setSelectedAccount,
              options: [
                { value: 'all', label: t('All Accounts') },
                ...accounts.map((account: any) => ({
                  value: account.id.toString(),
                  label: account.name
                }))
              ]
            },
            {
              name: 'opportunity_stage_id',
              label: t('Stage'),
              type: 'select',
              value: selectedStage,
              onChange: setSelectedStage,
              options: [
                { value: 'all', label: t('All Stages') },
                ...opportunityStages.map((stage: any) => ({
                  value: stage.id.toString(),
                  label: stage.name
                }))
              ]
            },
            {
              name: 'opportunity_source_id',
              label: t('Source'),
              type: 'select',
              value: selectedSource,
              onChange: setSelectedSource,
              options: [
                { value: 'all', label: t('All Sources') },
                ...opportunitySources.map((source: any) => ({
                  value: source.id.toString(),
                  label: source.name
                }))
              ]
            },
            {
              name: 'status',
              label: t('Status'),
              type: 'select',
              value: selectedStatus,
              onChange: setSelectedStatus,
              options: [
                { value: 'all', label: t('All Status') },
                { value: 'active', label: t('Active') },
                { value: 'inactive', label: t('Inactive') }
              ]
            },
            ...(isCompany || canManageOpportunities ? [{
              name: 'assigned_to',
              label: t('Assigned To'),
              type: 'select',
              value: selectedAssignee,
              onChange: setSelectedAssignee,
              options: [
                { value: 'all', label: t('All Users') },
                ...users.map((user: any) => ({
                  value: user.id.toString(),
                  label: (user.display_name || user.name)
                }))
              ]
            }] : [])
          ]}
          showFilters={showFilters}
          setShowFilters={setShowFilters}
          hasActiveFilters={hasActiveFilters}
          activeFilterCount={activeFilterCount}
          onResetFilters={handleResetFilters}
          onApplyFilters={applyFilters}
          {...(activeView !== 'kanban' && {
            currentPerPage: pageFilters.per_page?.toString() || "10",
            onPerPageChange: (value) => {
              const params = {
                page: 1,
                search: searchTerm || undefined,
                account_id: selectedAccount !== 'all' ? selectedAccount : undefined,
                opportunity_stage_id: selectedStage !== 'all' ? selectedStage : undefined,
                opportunity_source_id: selectedSource !== 'all' ? selectedSource : undefined,
                status: selectedStatus !== 'all' ? selectedStatus : undefined,
                assigned_to: selectedAssignee !== 'all' ? selectedAssignee : undefined
              };
              if (parseInt(value) !== 10) {
                params.per_page = parseInt(value);
              }
              params.view = activeView;
              router.get(route('opportunities.index'), params, { preserveState: true, preserveScroll: true });
            }
          })}
          showViewToggle={true}
          activeView={activeView}
          onViewChange={(view) => {
            router.get(route('opportunities.index'), {
              view: view,
              search: searchTerm || undefined,
              account_id: selectedAccount !== 'all' ? selectedAccount : undefined,
              opportunity_stage_id: selectedStage !== 'all' ? selectedStage : undefined,
              opportunity_source_id: selectedSource !== 'all' ? selectedSource : undefined,
              status: selectedStatus !== 'all' ? selectedStatus : undefined,
              assigned_to: selectedAssignee !== 'all' ? selectedAssignee : undefined
            }, {
              preserveState: true,
              preserveScroll: true,
              onSuccess: () => setActiveView(view)
            });
          }}
          viewOptions={[
            { value: 'list', label: t('List View'), icon: 'List' },
            { value: 'kanban', label: t('Kanban View'), icon: 'Columns' },
            { value: 'grid', label: t('Grid View'), icon: 'Grid3X3' }
          ]}
        />
      </div>

      {/* Content section */}
      {activeView === 'list' ? (
        <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
          <CrudTable
            columns={columns}
            actions={actions}
            data={opportunities?.data || []}
            from={opportunities?.from || 1}
            onAction={handleAction}
            sortField={pageFilters.sort_field}
            sortDirection={pageFilters.sort_direction}
            onSort={handleSort}
            permissions={permissions}
            entityPermissions={{
              view: 'view-opportunities',
              create: 'create-opportunities',
              edit: 'edit-opportunities',
              delete: 'delete-opportunities'
            }}

            onBulkAction={handleBulkAction}
            bulkActions={[
              {
                label: 'Delete Selected',
                action: 'bulk_delete',
                icon: 'Trash2',
                variant: 'destructive'
              }
            ]}
          />

          {/* Pagination section */}
          <Pagination
            from={opportunities?.from || 1}
            to={opportunities?.to || opportunities?.data?.length || 0}
            total={opportunities?.total || opportunities?.data?.length || 0}
            links={opportunities?.links}
            entityName={t("opportunities")}
            onPageChange={(url) => router.get(url)}
          />
        </div>
      ) : activeView === 'kanban' ? (
        <>
          {/* Kanban Board */}
          <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
            <div className="bg-gray-50 p-4 rounded-lg overflow-hidden">
              <style>{`
                .kanban-scroll {
                  overflow-x: auto;
                  overflow-y: hidden;
                }
                .kanban-scroll::-webkit-scrollbar {
                  height: 8px;
                }
                .kanban-scroll::-webkit-scrollbar-track {
                  background: #f1f5f9;
                  border-radius: 4px;
                }
                .kanban-scroll::-webkit-scrollbar-thumb {
                  background: #cbd5e1;
                  border-radius: 4px;
                }
                .kanban-scroll::-webkit-scrollbar-thumb:hover {
                  background: #94a3b8;
                }
                main {
                  max-width: 100vw;
                  overflow-x: hidden;
                }
                body {
                  overflow-x: hidden !important;
                }
              `}</style>
              {isLoadingKanban ? (
                <div className="flex items-center justify-center h-full">
                  <div className="text-center">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"></div>
                    <p className="text-gray-500 dark:text-gray-400">{t('Loading kanban board...')}</p>
                  </div>
                </div>
              ) : kanbanData ? (
                <div className="flex gap-4 overflow-x-auto pb-4 kanban-scroll" style={{ height: 'calc(100vh - 280px)' }}>
                  {opportunityStages.map((stage) => {
                    const stageOpportunities = Object.values(kanbanData).find((column: any) => column.status?.id === stage.id)?.items || [];
                    return (
                      <div
                        key={stage.id}
                        className="flex-shrink-0"
                        style={{ minWidth: '300px', width: '300px' }}
                        onDrop={(e) => {
                          e.preventDefault();
                          e.currentTarget.classList.remove('bg-blue-50');
                          const opportunityId = e.dataTransfer.getData('opportunityId');
                          if (opportunityId) {
                            // Check permission before updating
                            if (!hasPermission(permissions, 'edit-opportunities')) {
                              toast.error(t('Permission denied.'));
                              return;
                            }

                            // Find the opportunity to get current data
                            const currentOpportunity = Object.values(kanbanData)
                              .flatMap((column: any) => column.items)
                              .find((opportunity: any) => opportunity.id.toString() === opportunityId);

                            if (currentOpportunity) {
                              const currentStageId = currentOpportunity.opportunity_stage_id || currentOpportunity.opportunity_stage?.id;

                              if (parseInt(currentStageId) !== parseInt(stage.id)) {
                                toast.loading(t('Updating opportunity stage...'));
                                router.put(route('opportunities.update-status', opportunityId), {
                                  opportunity_stage_id: stage.id
                                }, {
                                  preserveState: true,
                                  preserveScroll: true,
                                  onSuccess: (page) => {
                                    toast.dismiss();
                                    if (page.props.flash?.success) {
                                      toast.success(t(page.props.flash.success));
                                    } else if (page.props.flash.error) {
                                      toast.error(t(page.props.flash.error));
                                    }
                                    router.reload({ only: ['opportunities'] });
                                  },
                                  onError: () => {
                                    toast.dismiss();
                                    toast.error(t('Failed to update opportunity stage'));
                                    loadKanbanData();
                                  }
                                });
                              }
                            }
                          }
                        }}
                        onDragOver={(e) => {
                          e.preventDefault();
                          e.currentTarget.classList.add('bg-blue-50');
                        }}
                        onDragLeave={(e) => {
                          e.currentTarget.classList.remove('bg-blue-50');
                        }}
                      >
                        <div className="bg-gray-100 rounded-lg h-full flex flex-col">
                          <div className="p-3 border-b border-gray-200">
                            <div className="flex items-center justify-between mb-2">
                              <div className="flex items-center gap-2">
                                <div className="w-3 h-3 rounded-full" style={{ backgroundColor: stage.color }}></div>
                                <h3 className="font-semibold text-sm text-gray-700">{stage.name}</h3>
                              </div>
                              <span className="text-xs text-gray-500 bg-gray-200 px-2 py-1 rounded-full">
                                {stageOpportunities.length}
                              </span>
                            </div>
                            {hasPermission(permissions, 'create-opportunities') && (
                              <button
                                onClick={() => handleAddOpportunity(stage.id.toString())}
                                className="w-full text-xs text-gray-600 hover:text-blue-600 hover:bg-blue-50 py-2 px-3 rounded-md border border-dashed border-gray-300 hover:border-blue-300 transition-all duration-200 flex items-center justify-center gap-1"
                              >
                                <Plus className="h-3 w-3" />
                                {t('Add Opportunity')}
                              </button>
                            )}
                          </div>
                          <div className="p-2 space-y-2 overflow-y-auto flex-1" style={{ maxHeight: 'calc(100vh - 350px)' }}>
                            {stageOpportunities.map((opportunity) => (
                              <div
                                key={opportunity.id}
                                draggable={hasPermission(permissions, 'edit-opportunities')}
                                onDragStart={(e) => {
                                  if (!hasPermission(permissions, 'edit-opportunities')) {
                                    e.preventDefault();
                                    return;
                                  }
                                  e.dataTransfer.setData('opportunityId', opportunity.id.toString());
                                  e.currentTarget.classList.add('opacity-50', 'scale-95');
                                }}
                                onDragEnd={(e) => {
                                  e.currentTarget.classList.remove('opacity-50', 'scale-95');
                                }}
                                className={`transition-all duration-200 ${hasPermission(permissions, 'edit-opportunities') ? 'cursor-move' : 'cursor-default'}`}
                              >
                                <Card className="hover:shadow-md transition-all duration-200 border-l-4 hover:scale-105" style={{ borderLeftColor: stage.color }}>
                                  <div className="p-3">
                                    <div className="space-y-2">
                                      <div className="flex items-start justify-between">
                                        <h4
                                          className="font-medium text-sm line-clamp-2 hover:text-blue-600 transition-colors cursor-pointer flex-1"
                                          onClick={() => handleAction('view', opportunity)}
                                        >
                                          {opportunity.name}
                                        </h4>
                                        <DropdownMenu>
                                          <DropdownMenuTrigger asChild>
                                            <Button variant="ghost" size="sm" className="h-6 w-6 p-0 text-gray-400 hover:text-gray-600">
                                              <MoreHorizontal className="h-4 w-4" />
                                            </Button>
                                          </DropdownMenuTrigger>
                                          <DropdownMenuContent align="end" className="w-40">
                                            <DropdownMenuItem onClick={() => handleAction('view', opportunity)}>
                                              <Eye className="h-4 w-4 mr-2" />
                                              {t('View')}
                                            </DropdownMenuItem>
                                            {hasPermission(permissions, 'edit-opportunities') && (
                                              <DropdownMenuItem onClick={() => handleAction('edit', opportunity)}>
                                                <Edit className="h-4 w-4 mr-2" />
                                                {t('Edit')}
                                              </DropdownMenuItem>
                                            )}
                                            {hasPermission(permissions, 'edit-opportunities') && opportunityStages.filter((s: any) => s.id !== opportunity.opportunity_stage_id).length > 0 && (
                                              <>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuSub>
                                                  <DropdownMenuSubTrigger className="cursor-pointer">
                                                    <ArrowRight className="h-4 w-4 mr-2" />
                                                    {t('Move to')}
                                                  </DropdownMenuSubTrigger>
                                                  <DropdownMenuSubContent className="w-44">
                                                    {opportunityStages
                                                      .filter((s: any) => s.id !== opportunity.opportunity_stage_id)
                                                      .map((s: any) => (
                                                        <DropdownMenuItem key={s.id} onClick={() => handleMoveToStage(opportunity, s.id)}>
                                                          <div className="w-2.5 h-2.5 rounded-full mr-2 flex-shrink-0" style={{ backgroundColor: s.color }} />
                                                          {s.name}
                                                        </DropdownMenuItem>
                                                      ))}
                                                  </DropdownMenuSubContent>
                                                </DropdownMenuSub>
                                              </>
                                            )}
                                            {hasPermission(permissions, 'delete-opportunities') && (
                                              <>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem onClick={() => handleAction('delete', opportunity)} className="text-red-600">
                                                  <Trash2 className="h-4 w-4 mr-2" />
                                                  {t('Delete')}
                                                </DropdownMenuItem>
                                              </>
                                            )}
                                          </DropdownMenuContent>
                                        </DropdownMenu>
                                      </div>

                                      <div className="text-xs text-gray-600">
                                        {opportunity.account?.name && <div>{opportunity.account.name}</div>}
                                        {opportunity.contact?.name && <div>{opportunity.contact.name}</div>}
                                      </div>

                                      <div className="flex items-center justify-between">
                                        <div className="text-xs text-gray-500">
                                          {opportunity.amount ? (formatCurrency(parseFloat(opportunity.amount))) : t('No amount')}
                                        </div>
                                        {opportunity.assigned_user && (
                                          <div className="flex h-5 w-5 items-center justify-center rounded-full bg-primary text-white text-xs">
                                            {getInitials((opportunity.assigned_user.display_name || opportunity.assigned_user.name))}
                                          </div>
                                        )}
                                      </div>

                                      <div className="flex justify-between items-center text-xs text-gray-500">
                                        <div className="flex items-center gap-2">
                                          <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${opportunity.status === 'active'
                                            ? 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20'
                                            : 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20'
                                            }`}>
                                            {opportunity.status === 'active' ? t('Active') : t('Inactive')}
                                          </span>
                                        </div>
                                        <span>
                                          {opportunity.close_date
                                            ? (window.appSettings?.formatDateTime(opportunity.close_date, false)
                                              || new Date(opportunity.close_date).toLocaleDateString())
                                            : (window.appSettings?.formatDateTime(opportunity.created_at, false)
                                              || new Date(opportunity.created_at).toLocaleDateString())
                                          }
                                        </span>
                                      </div>
                                    </div>
                                  </div>
                                </Card>
                              </div>
                            ))}
                            {stageOpportunities.length === 0 && (
                              <div className="text-center py-8 text-gray-400">
                                <Building2 className="h-8 w-8 mx-auto mb-2" />
                                <p className="text-sm">{t('No opportunities')}</p>
                              </div>
                            )}
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </div>
              ) : (
                <div className="flex items-center justify-center h-full">
                  <p className="text-gray-500 dark:text-gray-400">{t('No data available')}</p>
                </div>
              )}
            </div>
          </div>


        </>
      ) : (
        <div>
          {/* Grid View */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {opportunities?.data?.map((opportunity: any) => (
              <Card key={opportunity.id} className="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg shadow">
                <div className="p-6">
                  <div className="flex items-start justify-between mb-4">
                    <div className="flex items-start space-x-4">
                      <div className="h-16 w-16 rounded-full bg-primary text-white flex items-center justify-center text-lg font-bold">
                        {getInitials(opportunity.name)}
                      </div>
                      <div className="flex-1 min-w-0">
                        <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-2">{opportunity.name}</h3>
                        <p className="text-sm text-gray-600 dark:text-gray-300 mb-3">{opportunity.account?.name || t('No account')}</p>
                        <div className="flex items-center">
                          <div className={`h-2 w-2 rounded-full mr-2 ${opportunity.status === 'active' ? 'bg-green-500' : 'bg-gray-400'
                            }`}></div>
                          <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {opportunity.status === 'active' ? t('Active') : t('Inactive')}
                          </span>
                        </div>
                      </div>
                    </div>

                    {/* Actions dropdown */}
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-gray-400 hover:text-gray-600 dark:text-gray-400 dark:hover:text-gray-300">
                          <MoreHorizontal className="h-4 w-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end" className="w-48 z-50" sideOffset={5}>
                        {hasPermission(permissions, 'view-opportunities') && (
                          <DropdownMenuItem onClick={() => handleAction('view', opportunity)}>
                            <Eye className="h-4 w-4 mr-2" />
                            <span>{t("View Opportunity")}</span>
                          </DropdownMenuItem>
                        )}
                        {hasPermission(permissions, 'toggle-status-opportunities') && (
                          <DropdownMenuItem onClick={() => handleAction('toggle-status', opportunity)}>
                            <span>{opportunity.status === 'active' ? t("Deactivate") : t("Activate")}</span>
                          </DropdownMenuItem>
                        )}
                        <DropdownMenuSeparator />
                        {hasPermission(permissions, 'edit-opportunities') && (
                          <DropdownMenuItem onClick={() => handleAction('edit', opportunity)} className="text-amber-600">
                            <Edit className="h-4 w-4 mr-2" />
                            <span>{t("Edit")}</span>
                          </DropdownMenuItem>
                        )}
                        {hasPermission(permissions, 'delete-opportunities') && (
                          <DropdownMenuItem onClick={() => handleAction('delete', opportunity)} className="text-rose-600">
                            <Trash2 className="h-4 w-4 mr-2" />
                            <span>{t("Delete")}</span>
                          </DropdownMenuItem>
                        )}
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>

                  {/* Opportunity info */}
                  <div className="border border-gray-200 dark:border-gray-700 rounded-md p-3 mb-4">
                    <div className="mb-2">
                      <span className="text-sm text-gray-600 dark:text-gray-400">
                        {t('Amount')}: {opportunity.amount ? (formatCurrency(parseFloat(opportunity.amount))) : t('-')}
                      </span>
                    </div>
                    <div className="mb-2">
                      <span className="text-sm text-gray-600 dark:text-gray-400">
                        {t('Close Date')}: {opportunity.close_date || opportunity.created_at
                          ? (window.appSettings?.formatDateTime(opportunity.close_date || opportunity.created_at, false)
                            || new Date(opportunity.close_date || opportunity.created_at).toLocaleDateString())
                          : t('-')}
                      </span>
                    </div>
                    <div className="flex flex-wrap gap-1">
                      {opportunity.opportunity_stage && (
                        <span className="inline-flex items-center rounded-md bg-blue-50 dark:bg-blue-900/30 px-2 py-1 text-xs font-medium text-blue-700 dark:text-blue-300">
                          <div
                            className="w-2 h-2 rounded-full mr-1"
                            style={{ backgroundColor: opportunity.opportunity_stage.color }}
                          ></div>
                          {opportunity.opportunity_stage.name}
                        </span>
                      )}
                      {opportunity.opportunity_source && (
                        <span className="inline-flex items-center rounded-md bg-purple-50 dark:bg-purple-900/30 px-2 py-1 text-xs font-medium text-purple-700 dark:text-purple-300">
                          {opportunity.opportunity_source.name}
                        </span>
                      )}
                    </div>
                  </div>

                  {/* Created date */}
                  <div className="text-xs text-gray-500 dark:text-gray-400 mb-4">
                    {t("Created:")} {window.appSettings?.formatDateTime(opportunity.created_at, false) || new Date(opportunity.created_at).toLocaleDateString()}
                  </div>

                  {/* Action buttons */}
                  <div className="flex gap-2">
                    {hasPermission(permissions, 'edit-opportunities') && (
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleAction('edit', opportunity)}
                        className="flex-1 h-9 text-sm border-gray-300 dark:border-gray-600 dark:text-gray-200"
                      >
                        <Edit className="h-4 w-4 mr-2" />
                        {t("Edit")}
                      </Button>
                    )}

                    {hasPermission(permissions, 'view-opportunities') && (
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleAction('view', opportunity)}
                        className="flex-1 h-9 text-sm border-gray-300 dark:border-gray-600 dark:text-gray-200"
                      >
                        <Eye className="h-4 w-4 mr-2" />
                        {t("View")}
                      </Button>
                    )}

                    {hasPermission(permissions, 'delete-opportunities') && (
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleAction('delete', opportunity)}
                        className="flex-1 h-9 text-sm text-gray-700 border-gray-300 dark:border-gray-600 dark:text-gray-200"
                      >
                        <Trash2 className="h-4 w-4 mr-2" />
                        {t("Delete")}
                      </Button>
                    )}
                  </div>
                </div>
              </Card>
            ))}
          </div>

          {/* Pagination for grid view */}
          <div className="mt-6 bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
            <Pagination
              from={opportunities?.from || 1}
              to={opportunities?.to || opportunities?.data?.length || 0}
              total={opportunities?.total || opportunities?.data?.length || 0}
              links={opportunities?.links}
              entityName={t("opportunities")}
              onPageChange={(url) => router.get(url)}
            />
          </div>
        </div>
      )}

      {/* Form Modal */}
      <CrudFormModal
        isOpen={isFormModalOpen}
        onClose={() => setIsFormModalOpen(false)}
        onSubmit={handleFormSubmit}
        formConfig={{
          productOptions: products,
          fields: [
            { name: 'name', label: t('Opportunity Name'), type: 'text', required: true },
            { name: 'description', label: t('Description'), type: 'textarea' },
            { name: 'close_date', label: t('Close Date'), type: 'date' },
            {
              name: formMode === 'view' ? 'account_name' : 'account_id',
              label: t('Account'),
              type: formMode === 'view' ? 'text' : 'select',
              required: formMode !== 'view',
              readOnly: formMode === 'view',
              options: formMode === 'view' ? [] : accounts.map((account: any) => ({
                value: account.id.toString(),
                label: account.name
              })),
              onChange: (val: string) => setSelectedFormAccountId(val)
            },
            {
              name: formMode === 'view' ? 'contact_name' : 'contact_id',
              label: t('Contact'),
              type: formMode === 'view' ? 'text' : 'select',
              readOnly: formMode === 'view',
              options: formMode === 'view' ? [] : [
                ...contacts
                  .filter((contact: any) => !selectedFormAccountId || contact.account_id?.toString() === selectedFormAccountId)
                  .map((contact: any) => ({
                    value: contact.id.toString(),
                    label: contact.name
                  }))
              ]
            },
            {
              name: formMode === 'view' ? 'opportunity_stage_name' : 'opportunity_stage_id',
              label: t('Stage'),
              type: formMode === 'view' ? 'text' : 'select',
              required: formMode !== 'view',
              readOnly: formMode === 'view',
              options: formMode === 'view' ? [] : opportunityStages.map((stage: any) => ({
                value: stage.id,
                label: stage.name
              })),
              defaultValue: prefilledOpportunityStage || undefined,
              hidden: formMode === 'create' && prefilledOpportunityStage
            },
            {
              name: formMode === 'view' ? 'opportunity_source_name' : 'opportunity_source_id',
              label: t('Source'),
              type: formMode === 'view' ? 'text' : 'select',
              required: formMode !== 'view',
              readOnly: formMode === 'view',
              options: formMode === 'view' ? [] : opportunitySources.map((source: any) => ({
                value: source.id,
                label: source.name
              }))
            },
            {
              name: 'products',
              label: t('Products'),
              type: 'array',
              productOptions: products,
              renderFooter: (arrayValue: any[]) => {
                let subtotal = 0;
                let totalTax = 0;

                arrayValue.forEach((item: any) => {
                  const quantity = parseFloat(item.quantity) || 0;
                  const unitPrice = parseFloat(item.unit_price) || 0;
                  const lineTotal = quantity * unitPrice;
                  subtotal += lineTotal;

                  const product = products?.find((p: any) => p.id == item.product_id);
                  if (product?.tax) {
                    totalTax += (lineTotal * product.tax.rate) / 100;
                  }
                });

                return (
                  <>
                    <tr className="bg-gray-50">
                      <td colSpan={4} className="border border-gray-200 px-3 py-2 text-sm text-right">
                        {t('Subtotal')}:
                      </td>
                      <td className="border border-gray-200 px-3 py-2 text-sm text-right">
                        {formatCurrency(subtotal)}
                      </td>
                    </tr>
                    <tr className="bg-gray-50">
                      <td colSpan={4} className="border border-gray-200 px-3 py-2 text-sm text-right">
                        {t('Total Tax')}:
                      </td>
                      <td className="border border-gray-200 px-3 py-2 text-sm text-right">
                        {formatCurrency(totalTax)}
                      </td>
                    </tr>
                    <tr className="bg-gray-100 font-bold">
                      <td colSpan={4} className="border border-gray-200 px-3 py-2 text-sm text-right">
                        {t('Grand Total')}:
                      </td>
                      <td className="border border-gray-200 px-3 py-2 text-sm text-right">
                        {formatCurrency(subtotal + totalTax)}
                      </td>
                    </tr>
                  </>
                );
              },
              renderSummary: (arrayValue: any[]) => {
                let subtotal = 0;
                let totalTax = 0;

                arrayValue.forEach((item: any) => {
                  const quantity = parseFloat(item.quantity) || 0;
                  const unitPrice = parseFloat(item.unit_price) || 0;
                  const lineTotal = quantity * unitPrice;
                  subtotal += lineTotal;

                  const product = products?.find((p: any) => p.id == item.product_id);
                  if (product?.tax) {
                    totalTax += (lineTotal * product.tax.rate) / 100;
                  }
                });

                return (
                  <div className="space-y-2">
                    <div className="flex justify-between items-center">
                      <span className="font-medium text-gray-700">{t('Subtotal')}:</span>
                      <span className="font-medium">{formatCurrency(subtotal)}</span>
                    </div>
                    <div className="flex justify-between items-center">
                      <span className="font-medium text-gray-700">{t('Total Tax')}:</span>
                      <span className="font-medium">{formatCurrency(totalTax)}</span>
                    </div>
                    <div className="flex justify-between items-center border-t pt-2">
                      <span className="font-bold text-gray-900">{t('Grand Total')}:</span>
                      <span className="font-bold text-lg text-primary">{formatCurrency(subtotal + totalTax)}</span>
                    </div>
                  </div>
                );
              },
              fields: [
                {
                  name: 'product_id',
                  label: t('Product'),
                  type: 'select',
                  required: true,
                  options: products.map((product: any) => ({
                    value: product.id,
                    label: `${product.name} - ${formatCurrency(parseFloat(product.price || 0))}${product.tax ? ` (Tax: ${product.tax.name} - ${product.tax.rate}%)` : ''}`
                  }))
                },
                {
                  name: 'quantity',
                  label: t('Quantity'),
                  type: 'number',
                  required: true,
                  min: '1',
                  defaultValue: 1
                },
                {
                  name: 'unit_price',
                  label: t('Unit Price'),
                  type: 'number',
                  required: true,
                  step: '0.01',
                  min: '0'
                },
                {
                  name: 'tax_amount',
                  label: t('Tax Amount'),
                  type: 'calculated',
                  calculate: (item: any) => {
                    const product = products?.find((p: any) => p.id == item.product_id);
                    const quantity = parseFloat(item.quantity) || 0;
                    const unitPrice = parseFloat(item.unit_price) || 0;
                    const lineTotal = quantity * unitPrice;
                    const taxAmount = product?.tax ? (lineTotal * product.tax.rate) / 100 : 0;
                    return formatCurrency(taxAmount);
                  }
                },
                {
                  name: 'line_total',
                  label: t('Line Total'),
                  type: 'calculated',
                  calculate: (item: any) => {
                    const quantity = parseFloat(item.quantity) || 0;
                    const unitPrice = parseFloat(item.unit_price) || 0;
                    const lineTotal = quantity * unitPrice;
                    return formatCurrency(lineTotal);
                  }
                }
              ]
            },

            { name: 'notes', label: t('Notes'), type: 'textarea' },
            ...(isCompany ? [{
              name: formMode === 'view' ? 'assigned_user_name' : 'assigned_to',
              label: t('Assign To'),
              type: formMode === 'view' ? 'text' : 'select',
              options: formMode === 'view' ? [] : [
                ...users.map((user: any) => ({ value: user.id, label: `${(user.display_name || user.name)} (${user.email})` }))
              ],
              readOnly: formMode === 'view',
              hidden: !isCompany || currentItem?.assigned_to === auth?.user?.id
            }] : []),
            {
              name: 'status',
              label: t('Status'),
              type: 'select',
              options: [
                { value: 'active', label: t('Active') },
                { value: 'inactive', label: t('Inactive') }
              ],
              defaultValue: 'active'
            }
          ],
          modalSize: 'xl'
        }}
        initialData={currentItem ? {
          ...currentItem,
          assigned_user_name: currentItem.assigned_user?.name || t('Unassigned'),
          account_name: currentItem.account?.name || t('-'),
          contact_name: currentItem.contact?.name || t('-'),
          opportunity_stage_name: currentItem.opportunity_stage?.name || t('-'),
          opportunity_source_name: currentItem.opportunity_source?.name || t('-'),
          products: currentItem.products?.map((product: any) => ({
            product_id: product.id,
            quantity: parseInt(product.pivot.quantity),
            unit_price: parseFloat(product.pivot.unit_price)
          })) || []
        } : null}
        title={
          formMode === 'create'
            ? t('Add New Opportunity')
            : formMode === 'edit'
              ? t('Edit Opportunity')
              : t('View Opportunity')
        }
        mode={formMode}
      />

      {/* Delete Modal */}
      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={currentItem?.name || ''}
        entityName={t('opportunity')}
      />
    </PageTemplate>
  );
}
