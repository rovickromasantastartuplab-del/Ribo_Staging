import React, { useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from 'react-i18next';
import { 
    Mail, 
    Plus, 
    RefreshCw, 
    Trash2, 
    Loader2, 
    CheckCircle2, 
    AlertCircle, 
    Settings, 
    ShieldCheck, 
    Server,
    ExternalLink,
    Key
} from 'lucide-react';
import { useForm, router } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

interface ChannelAccount {
    id: number;
    type: string;
    email_address: string;
    sync_status: string;
    sync_error: string | null;
    last_sync_at: string | null;
}

interface Props {
    accounts: ChannelAccount[];
}

export default function MailboxSettings({ accounts = [] }: Props) {
    const { t } = useTranslation();
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);
    const [connectType, setConnectType] = useState<'gmail' | 'smtp_imap' | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        email_address: '',
        imap_host: '',
        imap_port: '993',
        imap_encryption: 'ssl',
        imap_username: '',
        imap_password: '',
        smtp_host: '',
        smtp_port: '465',
        smtp_encryption: 'ssl',
        smtp_username: '',
        smtp_password: '',
    });

    const handleConnectSmtpImap = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('settings.channels.store'), {
            onSuccess: () => {
                setIsAddModalOpen(false);
                reset();
                setConnectType(null);
            },
        });
    };

    const handleDelete = (id: number) => {
        if (confirm(t('Are you sure you want to disconnect this mailbox? This will stop all synchronization.'))) {
            router.delete(route('settings.channels.destroy', id), {
                preserveScroll: true,
            });
        }
    };

    const handleSyncNow = (id: number) => {
        router.post(route('settings.channels.sync', id), {}, {
            preserveScroll: true,
        });
    };

    return (
        <Card className="shadow-none rounded-xl bg-card border">
            <CardHeader className="border-b bg-muted/20">
                <div className="flex items-center justify-between">
                    <div>
                        <CardTitle className="text-xl font-semibold flex items-center gap-2">
                            <Mail className="h-5 w-5 text-primary" />
                            {t('Mailboxes & Omnichannel Inbox')}
                        </CardTitle>
                        <CardDescription>
                            {t('Connect and manage your email accounts for unified communication.')}
                        </CardDescription>
                    </div>
                    
                    <Dialog open={isAddModalOpen} onOpenChange={setIsAddModalOpen}>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button className="flex items-center gap-2">
                                    <Plus className="h-4 w-4" />
                                    {t('Connect Mailbox')}
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem onClick={() => {
                                    window.location.href = route('social.redirect', { provider: 'google' });
                                }}>
                                    <div className="flex items-center gap-2">
                                        <div className="bg-[#EA4335] p-1 rounded-sm">
                                            <svg viewBox="0 0 24 24" className="h-3 w-3 fill-white" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
                                                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                                            </svg>
                                        </div>
                                        <span>{t('GSuite / Gmail')}</span>
                                    </div>
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => {
                                    setConnectType('smtp_imap');
                                    setIsAddModalOpen(true);
                                }}>
                                    <div className="flex items-center gap-2">
                                        <div className="bg-primary p-1 rounded-sm">
                                            <Server className="h-3 w-3 text-white" />
                                        </div>
                                        <span>{t('Private Email (IMAP/SMTP)')}</span>
                                    </div>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <DialogContent className="max-w-2xl">
                            <form onSubmit={handleConnectSmtpImap}>
                                <DialogHeader>
                                    <DialogTitle>{t('Connect Private Mailbox')}</DialogTitle>
                                    <DialogDescription>
                                        {t('Enter your IMAP and SMTP details to connect your custom email account.')}
                                    </DialogDescription>
                                </DialogHeader>

                                <div className="grid gap-6 py-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="email_address">{t('Email Address')}</Label>
                                        <Input 
                                            id="email_address" 
                                            placeholder="you@company.com" 
                                            value={data.email_address}
                                            onChange={e => setData('email_address', e.target.value)}
                                            required
                                        />
                                        {errors.email_address && <p className="text-xs text-red-500">{errors.email_address}</p>}
                                    </div>

                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="space-y-4">
                                            <h4 className="font-medium flex items-center gap-2 text-sm">
                                                <ShieldCheck className="h-4 w-4 text-primary" />
                                                {t('Incoming (IMAP)')}
                                            </h4>
                                            <div className="grid gap-2">
                                                <Label className="text-xs">{t('Host')}</Label>
                                                <Input 
                                                    value={data.imap_host} 
                                                    onChange={e => setData('imap_host', e.target.value)}
                                                    placeholder="imap.example.com" 
                                                    required
                                                />
                                            </div>
                                            <div className="grid grid-cols-2 gap-2">
                                                <div>
                                                    <Label className="text-xs">{t('Port')}</Label>
                                                    <Input 
                                                        value={data.imap_port}
                                                        onChange={e => setData('imap_port', e.target.value)}
                                                    />
                                                </div>
                                                <div>
                                                    <Label className="text-xs">{t('Encryption')}</Label>
                                                    <select 
                                                        value={data.imap_encryption}
                                                        onChange={e => setData('imap_encryption', e.target.value)}
                                                        className="w-full flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                                                    >
                                                        <option value="ssl">SSL/TLS</option>
                                                        <option value="tls">STARTTLS</option>
                                                        <option value="none">None</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div className="grid gap-2">
                                                <Label className="text-xs">{t('Username')}</Label>
                                                <Input 
                                                    value={data.imap_username}
                                                    onChange={e => setData('imap_username', e.target.value)}
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label className="text-xs">{t('Password')}</Label>
                                                <Input 
                                                    type="password"
                                                    value={data.imap_password}
                                                    onChange={e => setData('imap_password', e.target.value)}
                                                />
                                            </div>
                                        </div>

                                        <div className="space-y-4">
                                            <h4 className="font-medium flex items-center gap-2 text-sm">
                                                <ExternalLink className="h-4 w-4 text-primary" />
                                                {t('Outgoing (SMTP)')}
                                            </h4>
                                            <div className="grid gap-2">
                                                <Label className="text-xs">{t('Host')}</Label>
                                                <Input 
                                                    value={data.smtp_host}
                                                    onChange={e => setData('smtp_host', e.target.value)}
                                                    placeholder="smtp.example.com"
                                                    required
                                                />
                                            </div>
                                            <div className="grid grid-cols-2 gap-2">
                                                <div>
                                                    <Label className="text-xs">{t('Port')}</Label>
                                                    <Input 
                                                        value={data.smtp_port}
                                                        onChange={e => setData('smtp_port', e.target.value)}
                                                    />
                                                </div>
                                                <div>
                                                    <Label className="text-xs">{t('Encryption')}</Label>
                                                    <select 
                                                        value={data.smtp_encryption}
                                                        onChange={e => setData('smtp_encryption', e.target.value)}
                                                        className="w-full flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                                                    >
                                                        <option value="ssl">SSL/TLS</option>
                                                        <option value="tls">STARTTLS</option>
                                                        <option value="none">None</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div className="grid gap-2">
                                                <Label className="text-xs">{t('Username')}</Label>
                                                <Input 
                                                    value={data.smtp_username}
                                                    onChange={e => setData('smtp_username', e.target.value)}
                                                    placeholder={t('(Same as IMAP if empty)')}
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label className="text-xs">{t('Password')}</Label>
                                                <Input 
                                                    type="password"
                                                    value={data.smtp_password}
                                                    onChange={e => setData('smtp_password', e.target.value)}
                                                    placeholder={t('(Same as IMAP if empty)')}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <DialogFooter>
                                    <Button type="button" variant="ghost" onClick={() => setIsAddModalOpen(false)}>
                                        {t('Cancel')}
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                        {t('Verify & Connect')}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                {accounts.length === 0 ? (
                    <div className="flex flex-col items-center justify-center p-12 text-center">
                        <div className="h-16 w-16 bg-muted rounded-full flex items-center justify-center mb-4">
                            <Mail className="h-8 w-8 text-muted-foreground" />
                        </div>
                        <h3 className="text-lg font-medium">{t('No Mailboxes Connected')}</h3>
                        <p className="text-muted-foreground max-w-md mt-2 mb-6">
                            {t('Connect your business email accounts to manage all your conversations in one place.')}
                        </p>
                    </div>
                ) : (
                    <div className="divide-y">
                        {accounts.map((account) => (
                            <div key={account.id} className="flex items-center justify-between p-6">
                                <div className="flex items-center gap-4">
                                    <div className={`p-2 rounded-lg ${account.type === 'gmail' ? 'bg-[#EA4335]/10 text-[#EA4335]' : 'bg-primary/10 text-primary'}`}>
                                        {account.type === 'gmail' ? (
                                            <svg viewBox="0 0 24 24" className="h-5 w-5 fill-current" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
                                                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                                            </svg>
                                        ) : <Server className="h-5 w-5" />}
                                    </div>
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <h4 className="font-semibold">{account.email_address}</h4>
                                            <Badge variant={account.sync_status === 'error' ? 'destructive' : 'secondary'} className="text-[10px] px-1.5 py-0 capitalize">
                                                {account.type}
                                            </Badge>
                                        </div>
                                        <div className="flex items-center gap-3 mt-1 underline-none">
                                            <div className="flex items-center gap-1.5 text-xs">
                                                {account.sync_status === 'active' ? (
                                                    <span className="flex items-center gap-1 text-green-600">
                                                        <CheckCircle2 className="h-3 w-3" /> {t('Active')}
                                                    </span>
                                                ) : account.sync_status === 'syncing' ? (
                                                    <span className="flex items-center gap-1 text-primary">
                                                        <RefreshCw className="h-3 w-3 animate-spin" /> {t('Syncing...')}
                                                    </span>
                                                ) : (
                                                    <span className="flex items-center gap-1 text-red-500">
                                                        <AlertCircle className="h-3 w-3" /> {t('Error')}
                                                    </span>
                                                )}
                                            </div>
                                            {account.last_sync_at && (
                                                <span className="text-[10px] text-muted-foreground italic">
                                                    {t('Last sync')}: {new Date(account.last_sync_at).toLocaleString()}
                                                </span>
                                            )}
                                        </div>
                                        {account.sync_error && (
                                            <p className="text-[10px] text-red-500 mt-1 max-w-sm truncate" title={account.sync_error}>
                                                {account.sync_error}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="flex items-center gap-2">
                                    <Button 
                                        variant="ghost" 
                                        size="sm" 
                                        onClick={() => handleSyncNow(account.id)}
                                        disabled={account.sync_status === 'syncing'}
                                    >
                                        <RefreshCw className={`h-4 w-4 ${account.sync_status === 'syncing' ? 'animate-spin' : ''}`} />
                                    </Button>
                                    <Button 
                                        variant="ghost" 
                                        size="sm" 
                                        onClick={() => handleDelete(account.id)}
                                        className="text-red-500 hover:text-red-600 hover:bg-red-50"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
