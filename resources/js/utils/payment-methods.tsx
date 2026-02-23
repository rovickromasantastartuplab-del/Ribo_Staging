import React from 'react';
import {
    Banknote,
    CreditCard,
    IndianRupee,
    Wallet,
    Coins
} from 'lucide-react';

export const formatPaymentMethods = (paymentSettings: any, t: (key: string) => string) => {
    const methods = [];

    if (paymentSettings?.is_bank_enabled === true || paymentSettings?.is_bank_enabled === '1') {
        methods.push({ id: 'bank', name: t('Bank Transfer'), icon: <Banknote className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_stripe_enabled === true || paymentSettings?.is_stripe_enabled === '1') {
        methods.push({ id: 'stripe', name: t('Stripe'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_paypal_enabled === true || paymentSettings?.is_paypal_enabled === '1') {
        methods.push({ id: 'paypal', name: t('PayPal'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_razorpay_enabled === true || paymentSettings?.is_razorpay_enabled === '1') {
        methods.push({ id: 'razorpay', name: t('Razorpay'), icon: <IndianRupee className="h-5 w-5" />, enabled: true });
    }
    if ((paymentSettings?.is_mercadopago_enabled === true || paymentSettings?.is_mercadopago_enabled === '1')) {
        methods.push({ id: 'mercadopago', name: t('MercadoPago'), icon: <Wallet className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_paystack_enabled === true || paymentSettings?.is_paystack_enabled === '1') {
        methods.push({ id: 'paystack', name: t('Paystack'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_flutterwave_enabled === true || paymentSettings?.is_flutterwave_enabled === '1') {
        methods.push({ id: 'flutterwave', name: t('Flutterwave'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_paytabs_enabled === true || paymentSettings?.is_paytabs_enabled === '1') {
        methods.push({ id: 'paytabs', name: t('PayTabs'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_skrill_enabled === true || paymentSettings?.is_skrill_enabled === '1') {
        methods.push({ id: 'skrill', name: t('Skrill'), icon: <Wallet className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_coingate_enabled === true || paymentSettings?.is_coingate_enabled === '1') {
        methods.push({ id: 'coingate', name: t('CoinGate'), icon: <Coins className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_payfast_enabled === true || paymentSettings?.is_payfast_enabled === '1') {
        methods.push({ id: 'payfast', name: t('Payfast'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_tap_enabled === true || paymentSettings?.is_tap_enabled === '1') {
        methods.push({ id: 'tap', name: t('Tap'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_xendit_enabled === true || paymentSettings?.is_xendit_enabled === '1') {
        methods.push({ id: 'xendit', name: t('Xendit'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_paytr_enabled === true || paymentSettings?.is_paytr_enabled === '1') {
        methods.push({ id: 'paytr', name: t('PayTR'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_mollie_enabled === true || paymentSettings?.is_mollie_enabled === '1') {
        methods.push({ id: 'mollie', name: t('Mollie'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_toyyibpay_enabled === true || paymentSettings?.is_toyyibpay_enabled === '1') {
        methods.push({ id: 'toyyibpay', name: t('toyyibPay'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_cashfree_enabled === true || paymentSettings?.is_cashfree_enabled === '1') {
        methods.push({ id: 'cashfree', name: t('Cashfree'), icon: <IndianRupee className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_khalti_enabled === true || paymentSettings?.is_khalti_enabled === '1') {
        methods.push({ id: 'khalti', name: t('Khalti'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_iyzipay_enabled === true || paymentSettings?.is_iyzipay_enabled === '1') {
        methods.push({ id: 'iyzipay', name: t('Iyzipay'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_benefit_enabled === true || paymentSettings?.is_benefit_enabled === '1') {
        methods.push({ id: 'benefit', name: t('Benefit'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_ozow_enabled === true || paymentSettings?.is_ozow_enabled === '1') {
        methods.push({ id: 'ozow', name: t('Ozow'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_easebuzz_enabled === true || paymentSettings?.is_easebuzz_enabled === '1') {
        methods.push({ id: 'easebuzz', name: t('Easebuzz'), icon: <IndianRupee className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_authorizenet_enabled === true || paymentSettings?.is_authorizenet_enabled === '1') {
        methods.push({ id: 'authorizenet', name: t('AuthorizeNet'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_fedapay_enabled === true || paymentSettings?.is_fedapay_enabled === '1') {
        methods.push({ id: 'fedapay', name: t('FedaPay'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_payhere_enabled === true || paymentSettings?.is_payhere_enabled === '1') {
        methods.push({ id: 'payhere', name: t('PayHere'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_cinetpay_enabled === true || paymentSettings?.is_cinetpay_enabled === '1') {
        methods.push({ id: 'cinetpay', name: t('CinetPay'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_paiement_enabled === true || paymentSettings?.is_paiement_enabled === '1') {
        methods.push({ id: 'paiement', name: t('Paiement Pro'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_nepalste_enabled === true || paymentSettings?.is_nepalste_enabled === '1') {
        methods.push({ id: 'nepalste', name: t('Nepalste'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_yookassa_enabled === true || paymentSettings?.is_yookassa_enabled === '1') {
        methods.push({ id: 'yookassa', name: t('YooKassa'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_aamarpay_enabled === true || paymentSettings?.is_aamarpay_enabled === '1') {
        methods.push({ id: 'aamarpay', name: t('Aamarpay'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_midtrans_enabled === true || paymentSettings?.is_midtrans_enabled === '1') {
        methods.push({ id: 'midtrans', name: t('Midtrans'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_paymentwall_enabled === true || paymentSettings?.is_paymentwall_enabled === '1') {
        methods.push({ id: 'paymentwall', name: t('PaymentWall'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_sspay_enabled === true || paymentSettings?.is_sspay_enabled === '1') {
        methods.push({ id: 'sspay', name: t('SSPay'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }
    if (paymentSettings?.is_hitpay_enabled === true || paymentSettings?.is_hitpay_enabled === '1') {
        methods.push({ id: 'hitpay', name: t('HitPay'), icon: <CreditCard className="h-5 w-5" />, enabled: true });
    }

    return methods;
};
