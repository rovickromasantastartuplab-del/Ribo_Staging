import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Pagination } from '@/components/ui/pagination';
import { SearchAndFilterBar } from '@/components/ui/search-and-filter-bar';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { Trash2, MessageSquare, Eye } from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';

interface ContactMessage {
    id: number;
    name: string;
    email: string;
    subject: string;
    message: string;
    created_at: string;
}

interface Props {
    contactMessages: {
        data: ContactMessage[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
        links: any[];
    };
}

export default function ContactMessagesIndex({ contactMessages }: Props) {
    const { t } = useTranslation();
    const { filters: pageFilters = {} } = usePage().props as any;
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [currentMessage, setCurrentMessage] = useState<any>(null);

    const handleView = (message: any) => {
        setCurrentMessage(message);
        setIsViewModalOpen(true);
    };

    const handleDelete = (message: any) => {
        setCurrentMessage(message);
        setIsDeleteModalOpen(true);
    };

    const handleDeleteConfirm = () => {
        setDeletingId(currentMessage.id);
        router.delete(route('contact-messages.destroy', currentMessage.id), {
            onSuccess: () => {
                toast.success(t('Contact message deleted successfully'));
                setDeletingId(null);
                setIsDeleteModalOpen(false);
            },
            onError: () => {
                toast.error(t('Failed to delete contact message'));
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

        router.get(route('contact-messages.index'), params, { preserveState: true, preserveScroll: true });
    };

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Landing Page') },
        { title: t('Contact Messages') }
    ];

    const columns = [
        {
            key: 'name',
            label: t('Name'),
            render: (value: string, row: any) => (
                <div>
                    <div className="font-medium">{row.name}</div>
                    <div className="text-sm text-muted-foreground">{row.email}</div>
                </div>
            )
        },
        {
            key: 'subject',
            label: t('Subject'),
            render: (value: string) => <span className="font-medium">{value}</span>
        },
        // {
        //     key: 'message',
        //     label: t('Message'),
        //     render: (value: string) => (
        //         <div className="max-w-xs truncate" title={value}>
        //             {value}
        //         </div>
        //     )
        // },
        {
            key: 'created_at',
            label: t('Received At'),
            render: (value: string) => formatDistanceToNow(new Date(value), { addSuffix: true })
        }
    ];

    return (
        <PageTemplate
            title={t("Contact Messages")}
            url="/contact-messages"
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

                        router.get(route('contact-messages.index'), params, { preserveState: true, preserveScroll: true });
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
                            {contactMessages?.data?.map((message: any) => (
                                <tr key={message.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800">
                                    {columns.map((column) => (
                                        <td key={`${message.id}-${column.key}`} className="px-4 py-3">
                                            {column.render ? column.render(message[column.key], message) : message[column.key]}
                                        </td>
                                    ))}
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-blue-500 hover:text-blue-700"
                                                        onClick={() => handleView(message)}
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>{t("View")}</TooltipContent>
                                            </Tooltip>

                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-red-500 hover:text-red-700"
                                                        onClick={() => handleDelete(message)}
                                                        disabled={deletingId === message.id}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>{t("Delete")}</TooltipContent>
                                            </Tooltip>
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {(!contactMessages?.data || contactMessages.data.length === 0) && (
                                <tr>
                                    <td colSpan={columns.length + 1} className="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                        <div className="flex flex-col items-center">
                                            <MessageSquare className="h-12 w-12 text-gray-400 mb-4" />
                                            <div className="text-lg font-medium mb-2">{t('No contact messages')}</div>
                                            <div>{t('Contact messages from your landing page will appear here.')}</div>
                                        </div>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination
                    from={contactMessages?.from || 0}
                    to={contactMessages?.to || 0}
                    total={contactMessages?.total || 0}
                    links={contactMessages?.links}
                    entityName={t("messages")}
                    onPageChange={(url) => router.get(url)}
                />
            </div>

            {/* View Modal */}
            <Dialog open={isViewModalOpen} onOpenChange={setIsViewModalOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{t('Contact Message Details')}</DialogTitle>
                    </DialogHeader>
                    {currentMessage && (
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {t('Name')}
                                    </label>
                                    <p className="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        {currentMessage.name}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {t('Email')}
                                    </label>
                                    <p className="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        {currentMessage.email}
                                    </p>
                                </div>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {t('Subject')}
                                </label>
                                <p className="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    {currentMessage.subject}
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {t('Message')}
                                </label>
                                <p className="mt-1 text-sm text-gray-900 dark:text-gray-100 whitespace-pre-wrap">
                                    {currentMessage.message}
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {t('Received At')}
                                </label>
                                <p className="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    {formatDistanceToNow(new Date(currentMessage.created_at), { addSuffix: true })}
                                </p>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            {/* Delete Modal */}
            <CrudDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => setIsDeleteModalOpen(false)}
                onConfirm={handleDeleteConfirm}
                itemName={currentMessage?.subject || ''}
                entityName="contact message"
            />
        </PageTemplate>
    );
}
