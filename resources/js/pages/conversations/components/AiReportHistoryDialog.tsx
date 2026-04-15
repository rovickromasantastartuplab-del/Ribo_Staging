import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import axios from 'axios';
import { Download, History, RefreshCw } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

type HistoryRow = {
    id: number;
    ai_report_job_id: number;
    scope: string;
    download_count: number;
    first_downloaded_at: string | null;
    last_downloaded_at: string | null;
    last_downloaded_by: { id: number; name: string } | null;
    created_at: string | null;
};

interface AiReportHistoryDialogProps {
    threadId: number | null | undefined;
}

function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '-';
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return '-';
    }

    return parsed.toLocaleString(undefined, {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function scopeLabel(scope: string): string {
    switch (scope) {
        case 'overall':
            return 'Full History';
        case 'leads-only':
            return 'Leads Only';
        case 'all-opps':
            return 'All Opportunities';
        case 'specific-opportunity':
            return 'Specific Opportunity';
        default:
            return scope;
    }
}

function parseDownloadFilename(headerValue: string | undefined, fallback: string): string {
    if (!headerValue) {
        return fallback;
    }

    const utf8Match = /filename\*=UTF-8''([^;]+)/i.exec(headerValue);
    if (utf8Match?.[1]) {
        return decodeURIComponent(utf8Match[1].replace(/["']/g, ''));
    }

    const basicMatch = /filename="?([^"]+)"?/i.exec(headerValue);
    if (basicMatch?.[1]) {
        return basicMatch[1].trim();
    }

    return fallback;
}

export default function AiReportHistoryDialog({ threadId }: AiReportHistoryDialogProps) {
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [rows, setRows] = useState<HistoryRow[]>([]);
    const [downloadingVersionId, setDownloadingVersionId] = useState<number | null>(null);

    useEffect(() => {
        if (!open || !threadId) {
            return;
        }

        let isCancelled = false;
        setLoading(true);

        axios
            .get(`/ai/reports/history/${threadId}`)
            .then((response) => {
                if (isCancelled) {
                    return;
                }

                const list = Array.isArray(response?.data?.data) ? (response.data.data as HistoryRow[]) : [];
                setRows(list);
            })
            .catch(() => {
                if (!isCancelled) {
                    setRows([]);
                    toast.error('Failed to load summary download history.');
                }
            })
            .finally(() => {
                if (!isCancelled) {
                    setLoading(false);
                }
            });

        return () => {
            isCancelled = true;
        };
    }, [open, threadId]);

    const hasRows = useMemo(() => rows.length > 0, [rows.length]);

    const handleDownload = async (row: HistoryRow) => {
        setDownloadingVersionId(row.id);

        try {
            const response = await axios.get(`/ai/reports/version/${row.id}/download`, {
                responseType: 'blob',
            });

            const blob = new Blob([response.data], { type: 'application/pdf' });
            const url = window.URL.createObjectURL(blob);
            const filename = parseDownloadFilename(
                response.headers['content-disposition'] as string | undefined,
                `AI-Summary-Report-${row.ai_report_job_id}.pdf`,
            );

            const link = document.createElement('a');
            link.style.display = 'none';
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(link);

            setRows((currentRows) =>
                currentRows.map((currentRow) =>
                    currentRow.id === row.id
                        ? {
                              ...currentRow,
                              download_count: currentRow.download_count + 1,
                              last_downloaded_at: new Date().toISOString(),
                          }
                        : currentRow,
                ),
            );
        } catch {
            toast.error('Failed to download selected summary report.');
        } finally {
            setDownloadingVersionId(null);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="h-10 border-indigo-200 bg-white px-3 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 dark:border-indigo-900 dark:bg-slate-900 dark:text-indigo-300"
                    disabled={!threadId}
                >
                    <History className="mr-1.5 h-3.5 w-3.5" />
                    History
                </Button>
            </DialogTrigger>

            <DialogContent className="w-[96vw] max-w-5xl overflow-hidden border-none p-0 shadow-2xl">
                <DialogHeader className="border-b bg-primary/5 px-6 py-4">
                    <DialogTitle className="flex items-center gap-2 text-lg font-semibold text-primary">
                        <History className="h-5 w-5" />
                        Summary Download History
                    </DialogTitle>
                </DialogHeader>

                <div className="p-5">
                    {loading ? (
                        <div className="flex min-h-[180px] items-center justify-center gap-2 text-sm text-muted-foreground">
                            <RefreshCw className="h-4 w-4 animate-spin" />
                            Loading history...
                        </div>
                    ) : !hasRows ? (
                        <div className="flex min-h-[180px] items-center justify-center text-sm text-muted-foreground">
                            No summary downloads recorded yet.
                        </div>
                    ) : (
                        <div className="overflow-x-auto rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Generated at</TableHead>
                                        <TableHead>Scope</TableHead>
                                        <TableHead className="text-center">Download count</TableHead>
                                        <TableHead>Last downloaded at</TableHead>
                                        <TableHead>Last downloaded by</TableHead>
                                        <TableHead className="w-[130px] text-right">Download</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rows.map((row) => {
                                        const isDownloading = downloadingVersionId === row.id;

                                        return (
                                            <TableRow key={row.id}>
                                                <TableCell className="text-xs">{formatDateTime(row.created_at ?? row.first_downloaded_at)}</TableCell>
                                                <TableCell className="text-xs">
                                                    <Badge variant="outline" className="border-slate-200 bg-slate-50 text-slate-700">
                                                        {scopeLabel(row.scope)}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-center text-xs font-semibold">{row.download_count}</TableCell>
                                                <TableCell className="text-xs">{formatDateTime(row.last_downloaded_at)}</TableCell>
                                                <TableCell className="text-xs">{row.last_downloaded_by?.name ?? '-'}</TableCell>
                                                <TableCell className="text-right">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        className="h-8 text-xs"
                                                        onClick={() => handleDownload(row)}
                                                        disabled={isDownloading}
                                                    >
                                                        {isDownloading ? <RefreshCw className="h-3.5 w-3.5 animate-spin" /> : <Download className="h-3.5 w-3.5" />}
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
