import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Loader2, CreditCard } from 'lucide-react';
import { toast } from '@/components/custom-toast';

interface HitPayPaymentFormProps {
    planId: number;
    couponCode: string;
    billingCycle: 'monthly' | 'yearly';
    planPrice: number;
    currency: string;
    onSuccess: () => void;
    onCancel: () => void;
}

export function HitpayPaymentForm({
    planId,
    couponCode,
    billingCycle,
    planPrice,
    currency,
    onSuccess,
    onCancel
}: HitPayPaymentFormProps) {
    const { t } = useTranslation();
    const [isProcessing, setIsProcessing] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsProcessing(true);

        try {
            const response = await fetch(route('hitpay.payment'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    plan_id: planId,
                    billing_cycle: billingCycle,
                    coupon_code: couponCode,
                })
            });

            const data = await response.json();

            if (data.success && data.checkoutUrl) {
                // Redirect the user to HitPay's hosted checkout page
                window.location.href = data.checkoutUrl;
            } else {
                toast.error(data.error || t('Payment failed'));
                setIsProcessing(false);
            }

        } catch (error: any) {
            console.error('HitPay payment error:', error);
            const errorMsg = error?.message || error?.toString() || 'Unknown fetch error';
            toast.error(t('Payment failed') + ': ' + errorMsg);
            setIsProcessing(false);
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <CreditCard className="h-5 w-5" />
                    {t('HitPay Payment')}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <p className="text-sm text-muted-foreground">
                        {t('You will be redirected to HitPay to complete the payment securely.')}
                    </p>

                    <div className="bg-muted p-3 rounded-lg">
                        <div className="flex justify-between items-center">
                            <span className="text-sm font-medium">{t('Amount')}</span>
                            <span className="text-sm font-bold">{currency} {planPrice}</span>
                        </div>
                        <p className="text-xs text-muted-foreground mt-1">
                            {t('Secure payment processing via HitPay')}
                        </p>
                    </div>

                    <div className="flex gap-3 pt-4">
                        <Button type="button" variant="outline" onClick={onCancel} className="flex-1">
                            {t('Cancel')}
                        </Button>
                        <Button
                            type="submit"
                            disabled={isProcessing || planPrice <= 0}
                            className="flex-1"
                        >
                            {isProcessing ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    {t('Redirecting...')}
                                </>
                            ) : (
                                t('Pay {{amount}}', { amount: `${currency} ${planPrice}` })
                            )}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
