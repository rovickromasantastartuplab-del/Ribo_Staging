import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Loader2 } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import axios from 'axios';

interface InvoiceHitpayPaymentFormProps {
    invoiceId: number;
    amount: number;
    paymentType: string;
    currency: string;
    onSuccess: () => void;
    onCancel: () => void;
}

export function InvoiceHitpayPaymentForm({
    invoiceId,
    amount,
    paymentType,
    currency,
    onSuccess,
    onCancel
}: InvoiceHitpayPaymentFormProps) {
    const { t } = useTranslation();
    const [isProcessing, setIsProcessing] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsProcessing(true);

        try {
            const response = await axios.post(route('invoice.hitpay.payment'), {
                invoice_id: invoiceId,
                amount: amount,
                payment_type: paymentType,
                currency: currency,
            });

            if (response.data.success && response.data.checkoutUrl) {
                // Redirect the user to HitPay's hosted checkout page
                window.location.href = response.data.checkoutUrl;
            } else {
                toast.error(response.data.error || t('Payment failed'));
                setIsProcessing(false);
            }

        } catch (error: any) {
            console.error('HitPay invoice payment error:', error);
            const errorMsg = error?.response?.data?.error || error?.message || error?.toString() || 'Unknown request error';
            toast.error(t('Payment failed') + ': ' + errorMsg);
            setIsProcessing(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <p className="text-sm text-muted-foreground mb-4">
                {t('You will be redirected to HitPay to complete your payment securely.')}
            </p>

            <div className="flex gap-3 pt-4">
                <Button type="button" variant="outline" onClick={onCancel} className="flex-1">
                    {t('Cancel')}
                </Button>
                <Button
                    type="submit"
                    disabled={isProcessing || amount <= 0}
                    className="flex-1"
                >
                    {isProcessing ? (
                        <>
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            {t('Redirecting...')}
                        </>
                    ) : (
                        t('Pay {{amount}}', { amount: `${currency} ${amount.toFixed(2)}` })
                    )}
                </Button>
            </div>
        </form>
    );
}
