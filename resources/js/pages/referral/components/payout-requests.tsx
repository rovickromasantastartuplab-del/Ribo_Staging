import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from 'react-i18next';
import { useState } from 'react';
import { Plus, Check, X } from 'lucide-react';
import { useForm } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';

interface PayoutRequestsProps {
    userType: string;
    payoutRequests: any;
    settings: any;
    stats: any;
}

export default function PayoutRequests({ userType, payoutRequests, settings, stats }: PayoutRequestsProps) {
    const { t } = useTranslation();

    const [showCreateDialog, setShowCreateDialog] = useState(false);
    const [showRejectDialog, setShowRejectDialog] = useState(false);
    const [selectedRequest, setSelectedRequest] = useState<any>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        amount: '',
    });

    const { data: rejectData, setData: setRejectData, post: postReject, processing: rejectProcessing } = useForm({
        notes: '',
    });

    const handleCreatePayout = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('referral.payout-request.create'), {
            onSuccess: (page) => {
                setShowCreateDialog(false);
                reset();
                if (page.props.flash.success) {
                    toast.success(t(page.props.flash.success));
                } else if (page.props.flash.error) {
                    toast.error(t(page.props.flash.error));
                }
            },
            onError: (errors) => {
                if (typeof errors === 'string') {
                    toast.error(errors);
                } else {
                    toast.error(`Failed to create payout request: ${Object.values(errors).join(', ')}`);
                }
            }
        });
    };

    const handleApprove = (request: any) => {
        toast.loading(t('Approving payout request...'));

        post(route('referral.payout-request.approve', request.id), {
            preserveScroll: true,
            onSuccess: (page) => {
                toast.dismiss();
                if (page.props.flash.success) {
                    toast.success(t(page.props.flash.success));
                } else if (page.props.flash.error) {
                    toast.error(t(page.props.flash.error));
                }
            },
            onError: (errors) => {
                toast.dismiss();
                if (typeof errors === 'string') {
                    toast.error(errors);
                } else {
                    toast.error(`Failed to approve payout request: ${Object.values(errors).join(', ')}`);
                }
            }
        });
    };

    const handleReject = (e: React.FormEvent) => {
        e.preventDefault();
        if (selectedRequest) {
            postReject(route('referral.payout-request.reject', selectedRequest.id), {
                preserveScroll: true,
                onSuccess: (page) => {
                    setShowRejectDialog(false);
                    setSelectedRequest(null);
                    setRejectData('notes', '');
                    if (page.props.flash.success) {
                        toast.success(t(page.props.flash.success));
                    } else if (page.props.flash.error) {
                        toast.error(t(page.props.flash.error));
                    }
                },
                onError: (errors) => {
                    if (typeof errors === 'string') {
                        toast.error(errors);
                    } else {
                        toast.error(`Failed to reject payout request: ${Object.values(errors).join(', ')}`);
                    }
                }
            });
        }
    };

    const getStatusBadge = (status: string) => {
        const colorMap = {
            pending: 'bg-yellow-100 text-yellow-800',
            approved: 'bg-green-100 text-green-800',
            rejected: 'bg-red-100 text-red-800'
        };
        const color = colorMap[status as keyof typeof colorMap] || 'bg-gray-100 text-gray-800';
        return (
            <Badge className={`capitalize ${color}`}>
                {t(status.charAt(0).toUpperCase() + status.slice(1))}
            </Badge>
        );
    };

    return (
        <div className="space-y-6">
            {userType === 'company' && (
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>{t('Create Payout Request')}</CardTitle>
                        <Dialog open={showCreateDialog} onOpenChange={setShowCreateDialog}>
                            <DialogTrigger asChild>
                                <Button disabled={stats.availableBalance < settings.threshold_amount}>
                                    <Plus className="h-4 w-4 mr-2" />
                                    {t('Request Payout')}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>{t('Create Payout Request')}</DialogTitle>
                                </DialogHeader>
                                <form onSubmit={handleCreatePayout} className="space-y-4">
                                    <div>
                                        <Label htmlFor="amount">{t('Amount')}</Label>
                                        <Input
                                            id="amount"
                                            type="number"
                                            step="0.01"
                                            min={settings.threshold_amount}
                                            max={stats.availableBalance}
                                            value={data.amount}
                                            onChange={(e) => setData('amount', e.target.value)}
                                            placeholder={`Min: $${settings.threshold_amount}`}
                                        />
                                        {errors.amount && <p className="text-sm text-red-500">{errors.amount}</p>}
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        <p>{t('Available Balance')}: {window.appSettings?.formatCurrency(stats.availableBalance) || `$${stats.availableBalance}`}</p>
                                        <p>{t('Minimum Amount')}: {window.appSettings?.formatCurrency(settings.threshold_amount) || `$${settings.threshold_amount}`}</p>
                                    </div>
                                    <div className="flex justify-end space-x-2">
                                        <Button type="button" variant="outline" onClick={() => setShowCreateDialog(false)}>
                                            {t('Cancel')}
                                        </Button>
                                        <Button type="submit" disabled={processing}>
                                            {t('Submit Request')}
                                        </Button>
                                    </div>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-muted-foreground">
                            {stats.availableBalance < settings.threshold_amount
                                ? t('You need at least {{amount}} to request a payout', { amount: window.appSettings?.formatCurrency(settings.threshold_amount) || `$${settings.threshold_amount}` })
                                : t('You can request up to {{amount}} for payout', { amount: window.appSettings?.formatCurrency(stats.availableBalance) || `$${stats.availableBalance}` })}
                        </p>
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>
                        {userType === 'superadmin' ? t('All Payout Requests') : t('Your Payout Requests')}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow className="dark:bg-gray-800 dark:border-gray-700">
                                {userType === 'superadmin' && <TableHead>{t('Company')}</TableHead>}
                                <TableHead>{t('Amount')}</TableHead>
                                <TableHead>{t('Status')}</TableHead>
                                <TableHead>{t('Date')}</TableHead>
                                {userType === 'superadmin' && <TableHead>{t('Actions')}</TableHead>}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {payoutRequests.data?.map((request: any) => (
                                <TableRow key={request.id} className="dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-gray-800">
                                    {userType === 'superadmin' && (
                                        <TableCell>
                                            <div>
                                                <p className="font-medium">{request.company?.name}</p>
                                                <p className="text-sm text-muted-foreground dark:text-gray-400">{request.company?.email}</p>
                                            </div>
                                        </TableCell>
                                    )}
                                    <TableCell>{window.appSettings?.formatCurrency(request.amount) || `$${request.amount}`}</TableCell>
                                    <TableCell>{getStatusBadge(request.status)}</TableCell>
                                    <TableCell>{window.appSettings?.formatDateTime(request.created_at, false) || new Date(request.created_at).toLocaleDateString()}</TableCell>
                                    {userType === 'superadmin' && request.status === 'pending' && (
                                        <TableCell>
                                            <div className="flex space-x-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => handleApprove(request)}
                                                >
                                                    <Check className="h-4 w-4 mr-1" />
                                                    {t('Approve')}
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => {
                                                        setSelectedRequest(request);
                                                        setShowRejectDialog(true);
                                                    }}
                                                >
                                                    <X className="h-4 w-4 mr-1" />
                                                    {t('Reject')}
                                                </Button>
                                            </div>
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <Dialog open={showRejectDialog} onOpenChange={setShowRejectDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Reject Payout Request')}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleReject} className="space-y-4">
                        <div>
                            <Label htmlFor="notes">{t('Rejection Reason')}</Label>
                            <Textarea
                                id="notes"
                                value={rejectData.notes}
                                onChange={(e) => setRejectData('notes', e.target.value)}
                                placeholder={t('Enter reason for rejection...')}
                            />
                        </div>
                        <div className="flex justify-end space-x-2">
                            <Button type="button" variant="outline" onClick={() => setShowRejectDialog(false)}>
                                {t('Cancel')}
                            </Button>
                            <Button type="submit" variant="destructive" disabled={rejectProcessing}>
                                {t('Reject Request')}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
