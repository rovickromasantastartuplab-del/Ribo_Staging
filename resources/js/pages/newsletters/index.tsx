import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Pagination } from '@/components/ui/pagination';
import { SearchAndFilterBar } from '@/components/ui/search-and-filter-bar';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { Trash2, Mail } from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';

interface Newsletter {
    id: number;
    email: string;
    created_at: string;
}

interface Props {
    newsletters: {
        data: Newsletter[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
        links: any[];
    };
}

export default function NewslettersIndex({ newsletters }: Props) {
    const { t } = useTranslation();
    const { filters: pageFilters = {} } = usePage().props as any;
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [currentNewsletter, setCurrentNewsletter] = useState<any>(null);

    const handleDelete = (newsletter: any) => {
        setCurrentNewsletter(newsletter);
        setIsDeleteModalOpen(true);
    };

    const handleDeleteConfirm = () => {
        setDeletingId(currentNewsletter.id);
        router.delete(route('newsletters.destroy', currentNewsletter.id), {
            onSuccess: () => {
                toast.success(t('Newsletter subscription deleted successfully'));
                setDeletingId(null);
                setIsDeleteModalOpen(false);
            },
            onError: () => {
                toast.error(t('Failed to delete newsletter subscription'));
                setDeletingId(null);
                setIsDeleteModalOpen(false);
            }
        });
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters();
    };

    const applyFilters = () => {
        const params: any = { page: 1 };

        if (searchTerm) {
            params.search = searchTerm;
        }

        if (pageFilters.per_page) {
            params.per_page = pageFilters.per_page;
        }

        router.get(route('newsletters.index'), params, { preserveState: true, preserveScroll: true });
    };

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Landing Page') },
        { title: t('Newsletters') }
    ];

    const columns = [
        {
            key: 'email',
            label: t('Email'),
            render: (value: string) => (
                <div className="flex items-center gap-2">
                    <Mail className="h-4 w-4 text-gray-400" />
                    <span className="font-medium">{value}</span>
                </div>
            )
        },
        {
            key: 'created_at',
            label: t('Subscribed At'),
            render: (value: string) => formatDistanceToNow(new Date(value), { addSuffix: true })
        }
    ];

    return (
        <PageTemplate
            title={t("Newsletters")}
            url="/newsletters"
            breadcrumbs={breadcrumbs}
            noPadding
        >
            {/* Search section */}
            <div className="bg-white dark:bg-gray-900 rounded-lg shadow mb-4 p-4">
                <SearchAndFilterBar
                    searchTerm={searchTerm}
                    onSearchChange={setSearchTerm}
                    onSearch={handleSearch}
                    filters={[]}
                    showFilters={false}
                    setShowFilters={() => {}}
                    hasActiveFilters={() => false}
                    activeFilterCount={() => 0}
                    onResetFilters={() => {}}
                    onApplyFilters={applyFilters}
                    currentPerPage={pageFilters.per_page?.toString() || "10"}
                    onPerPageChange={(value) => {
                        const params: any = { page: 1, per_page: parseInt(value) };

                        if (searchTerm) {
                            params.search = searchTerm;
                        }

                        router.get(route('newsletters.index'), params, { preserveState: true, preserveScroll: true });
                    }}
                    showViewToggle={false}
                    activeView="list"
                    onViewChange={() => {}}
                />
            </div>

            <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                                {columns.map((column) => (
                                    <th key={column.key} className="px-4 py-3 text-left font-medium text-gray-500">
                                        {column.label}
                                    </th>
                                ))}
                                <th className="px-4 py-3 text-right font-medium text-gray-500">
                                    {t("Actions")}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {newsletters?.data?.map((newsletter: any) => (
                                <tr key={newsletter.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800">
                                    {columns.map((column) => (
                                        <td key={`${newsletter.id}-${column.key}`} className="px-4 py-3">
                                            {column.render ? column.render(newsletter[column.key], newsletter) : newsletter[column.key]}
                                        </td>
                                    ))}
                                    <td className="px-4 py-3 text-right">
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-red-500 hover:text-red-700"
                                                    onClick={() => handleDelete(newsletter)}
                                                    disabled={deletingId === newsletter.id}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>{t("Delete")}</TooltipContent>
                                        </Tooltip>
                                    </td>
                                </tr>
                            ))}

                            {(!newsletters?.data || newsletters.data.length === 0) && (
                                <tr>
                                    <td colSpan={columns.length + 1} className="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                        <div className="flex flex-col items-center">
                                            <Mail className="h-12 w-12 text-gray-400 mb-4" />
                                            <div className="text-lg font-medium mb-2">{t('No newsletter subscriptions')}</div>
                                            <div>{t('Newsletter subscriptions from your landing page will appear here.')}</div>
                                        </div>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination
                    from={newsletters?.from || 0}
                    to={newsletters?.to || 0}
                    total={newsletters?.total || 0}
                    links={newsletters?.links}
                    entityName={t("subscriptions")}
                    onPageChange={(url) => router.get(url)}
                />
            </div>

            {/* Delete Modal */}
            <CrudDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => setIsDeleteModalOpen(false)}
                onConfirm={handleDeleteConfirm}
                itemName={currentNewsletter?.email || ''}
                entityName="newsletter subscription"
            />
        </PageTemplate>
    );
}
