import React, { useState, useEffect } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { CheckCircle2, CreditCard, Zap, Info, Crown, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Switch } from '@/components/ui/switch';
import { Checkbox } from '@/components/ui/checkbox';
import axios from 'axios';
import { PlanSubscriptionModal } from '@/components/plan-subscription-modal';
import { formatPaymentMethods } from '@/utils/payment-methods';
import { toast } from '@/components/custom-toast';

interface Plan {
  id: number;
  name: string;
  price: string | number;
  yearly_price?: string | number;
  monthly_price?: string | number;
  formatted_yearly_price?: string;
  formatted_monthly_price?: string;
  duration: string;
  description?: string;
  features?: string[];
  max_users?: number;
  max_projects?: number;
  max_contacts?: number;
  max_accounts?: number;
  storage_limit?: number;
  enable_branding?: string;
  enable_chatgpt?: string;
  module?: string[];
  is_trial?: string;
  trial_day?: number;
  is_active?: boolean;
  is_current?: boolean;
  is_default?: boolean;
  is_trial_available?: boolean;
}

interface UpgradePlanModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: (planId: number, duration: string) => void;
  plans: Plan[];
  currentPlanId?: number;
  companyName: string;
  showHideOption?: boolean;
  directUpgrade?: boolean;
  userTrialUsed?: boolean;
}

export function UpgradePlanModal({
  isOpen,
  onClose,
  onConfirm,
  plans,
  currentPlanId,
  companyName,
  showHideOption = false,
  directUpgrade = false,
  userTrialUsed = false
}: UpgradePlanModalProps) {
  const { t } = useTranslation();
  const [isYearly, setIsYearly] = useState(false);
  const [hideNextTime, setHideNextTime] = useState(false);
  const [isSubscriptionModalOpen, setIsSubscriptionModalOpen] = useState(false);
  const [paymentMethods, setPaymentMethods] = useState<any[]>([]);
  const [selectedPlanDetails, setSelectedPlanDetails] = useState<any>(null);
  const [processing, setProcessing] = useState(false);

  // Check if current user is on a trial (find current plan and check if it has trial days attached)
  const currentPlan = plans?.find(plan => plan.is_current === true || plan.id === currentPlanId);
  const isUserOnTrial = currentPlan?.is_trial === 'on' && currentPlan?.trial_day && currentPlan.trial_day > 0;

  const handleCancelTrial = async (e: React.MouseEvent) => {
    e.stopPropagation();
    if (!confirm(t('Are you sure you want to cancel your trial? You will be reverted to the free plan.'))) {
      return;
    }

    setProcessing(true);
    toast.loading(t('Cancelling trial...'));
    try {
      const response = await axios.post('/plans/cancel-trial');
      if (response.data.success) {
        toast.success(response.data.message || t('Trial cancelled successfully.'));
        window.location.reload();
      } else {
        toast.error(response.data.error || t('Failed to cancel trial'));
        setProcessing(false);
      }
    } catch (error: any) {
      toast.dismiss();
      toast.error(error?.response?.data?.error || t('Failed to cancel trial'));
      setProcessing(false);
    }
  };

  const handleStartTrial = async (plan: Plan) => {
    setProcessing(true);
    toast.loading(t('Starting trial...'));
    try {
      const response = await axios.post('/payments/hitpay/trial', {
        plan_id: plan.id,
      });
      toast.dismiss();

      if (response.data.success && response.data.checkoutUrl) {
        window.location.href = response.data.checkoutUrl;
      } else {
        toast.error(response.data.error || t('Failed to start trial'));
        setProcessing(false);
      }
    } catch (error: any) {
      toast.dismiss();
      toast.error(error?.response?.data?.error || t('Failed to start trial'));
      setProcessing(false);
    }
  };

  const handleSubscribe = async (plan: Plan) => {
    if (directUpgrade) {
      onConfirm(plan.id, isYearly ? 'yearly' : 'monthly');
      return;
    }

    setProcessing(true);
    try {
      const response = await fetch('/payment-methods');
      const methods = await response.json();
      const formattedMethods = formatPaymentMethods(methods, (key: string) => key);
      setPaymentMethods(formattedMethods);

      const planForCheckout = {
        ...plan,
        price: isYearly ? plan.yearly_price : plan.monthly_price,
        paymentMethods: methods
      };

      setSelectedPlanDetails(planForCheckout);
      setIsSubscriptionModalOpen(true);
    } catch (error) {
      console.error('Failed to load payment methods', error);
      toast.error(t('Failed to load payment methods'));
    } finally {
      setProcessing(false);
    }
  };

  const handleClose = () => {
    if (showHideOption && hideNextTime) {
      axios.post('/user/hide-plan-modal').finally(() => {
        onClose();
      });
    } else {
      onClose();
    }
  };

  const renderCardActions = (plan: Plan) => {
    const isCurrent = plan.is_current || plan.id === currentPlanId;

    if (isCurrent) {
      return (
        <div className="space-y-2 mt-4 pt-4 border-t border-gray-200">
          <Button disabled className="w-full bg-blue-100/50 text-blue-800 border-blue-200 hover:bg-blue-100/50">
            <Crown className="h-4 w-4 mr-2" />
            {t('Current Plan')}
          </Button>
          {isUserOnTrial && (
            <Button
              onClick={handleCancelTrial}
              disabled={processing}
              variant="destructive"
              className="w-full"
            >
              {t('Cancel Trial')}
            </Button>
          )}
        </div>
      );
    }

    // Checking `is_trial_available` or manually verifying `is_trial` / `trial_day` based on the provided object attributes
    const trialDaysAvailable = plan.trial_day || 0;
    const isTrialAvailable = (plan.is_trial_available ?? (plan.is_trial === 'on' && trialDaysAvailable > 0)) && !userTrialUsed;

    if (isTrialAvailable) {
      return (
        <div className="space-y-2 mt-4 pt-4 border-t border-gray-200">
          <Button
            onClick={(e) => { e.stopPropagation(); handleStartTrial(plan); }}
            disabled={processing}
            variant="outline"
            className="w-full relative overflow-hidden group hover:border-primary/50 transition-all"
          >
            <Zap className="h-4 w-4 mr-2 text-amber-500 group-hover:scale-110 transition-transform" />
            {t('Connect Bank Account ({{days}} Day Free Trial)', { days: trialDaysAvailable })}
            <div className="absolute inset-0 bg-primary/5 transform -translate-x-full group-hover:translate-x-0 transition-transform duration-300 pointer-events-none"></div>
          </Button>
          <div className="flex items-start gap-1.5 px-1 pb-1">
            <Info className="h-3.5 w-3.5 text-amber-600 mt-0.5 flex-shrink-0" />
            <p className="text-[10px] leading-tight text-amber-700">
              {t('A temporary hold of 20.00 may be applied to verify your account. You will not be charged until your trial ends.')}
            </p>
          </div>
          <Button
            onClick={(e) => { e.stopPropagation(); handleSubscribe(plan); }}
            disabled={processing}
            className="w-full"
          >
            {t('Subscribe Now')}
          </Button>
        </div>
      );
    }

    // Default Upgrade action
    return (
      <div className="mt-4 pt-4 border-t border-gray-200">
        <Button
          onClick={(e) => { e.stopPropagation(); handleSubscribe(plan); }}
          disabled={processing}
          className="w-full"
        >
          {t('Subscribe Now')}
        </Button>
      </div>
    );
  };

  return (
    <>
      <Dialog open={isOpen} onOpenChange={handleClose}>
        <DialogContent className="sm:max-w-5xl max-h-[90vh] flex flex-col p-0 overflow-hidden">
          <div className="px-6 py-6 pb-2">
            <DialogHeader>
              <DialogTitle className="text-2xl font-bold">{t("Upgrade Plan for")} {companyName}</DialogTitle>
              <DialogDescription className="text-base text-muted-foreground">
                {t("Select a new plan to unlock more features for your business.")}
              </DialogDescription>
            </DialogHeader>

            {/* Billing Period Toggle */}
            <div className="flex items-center justify-center space-x-4 mt-6 py-4 bg-slate-50 border border-slate-100 rounded-xl px-4">
              <span className={`text-sm font-semibold transition-colors ${!isYearly ? 'text-primary' : 'text-slate-500'}`}>
                {t('Monthly Billing')}
              </span>
              <Switch
                checked={isYearly}
                onCheckedChange={setIsYearly}
                className="data-[state=checked]:bg-primary"
              />
              <span className={`flex items-center text-sm font-semibold transition-colors ${isYearly ? 'text-primary' : 'text-slate-500'}`}>
                {t('Yearly Billing')}
                <Badge variant="secondary" className="ml-2 bg-emerald-100 text-emerald-800 border-none px-2 py-0.5 shadow-sm">
                  {t('Save up to 20%')}
                </Badge>
              </span>
            </div>
          </div>

          <div className="flex-1 overflow-y-auto px-6 pb-6">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 pt-4">
              {plans.length > 0 ? plans.map((plan) => {
                const isCurrent = plan.is_current || plan.id === currentPlanId;
                return (
                  <div
                    key={plan.id}
                    className={`relative flex flex-col rounded-2xl border p-6 transition-all duration-300 shadow-sm
                      ${isCurrent
                        ? 'bg-blue-50/20 border-blue-200 ring-1 ring-blue-100'
                        : 'bg-white border-slate-200 hover:border-slate-300 hover:shadow-md'
                      }
                    `}
                  >
                    {isCurrent && (
                      <div className="absolute -top-3 left-1/2 -translate-x-1/2 z-10">
                        <Badge className="bg-blue-500 text-white border-none shadow-sm px-3 py-1 text-xs">
                          {t("Current Plan")}
                        </Badge>
                      </div>
                    )}

                    <div className="flex-1">
                      <h3 className="text-xl font-bold text-slate-900 mb-1">{plan.name}</h3>
                      <p className="text-sm text-slate-500 mb-4 h-10 line-clamp-2">{plan.description}</p>

                      <div className="flex items-end gap-1 mb-6">
                        <span className="text-3xl font-extrabold tracking-tight text-slate-900">
                          {isYearly ? plan.formatted_yearly_price : plan.formatted_monthly_price}
                        </span>
                        <span className="text-sm text-slate-500 mb-1 font-medium">/ {isYearly ? t('yr') : t('mo')}</span>
                      </div>

                      <div className="space-y-4 mb-2">
                        <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-500 flex items-center">
                          <span className="bg-slate-200 h-px flex-1 mr-3"></span>
                          {t('Plan Limits')}
                          <span className="bg-slate-200 h-px flex-1 ml-3"></span>
                        </h4>

                        <div className="grid grid-cols-2 gap-2">
                          <div className="bg-slate-50 p-2.5 rounded-lg border border-slate-100 text-center flex flex-col justify-center transition-colors hover:bg-slate-100/80">
                            <div className="text-lg font-bold text-slate-700">{plan.max_users === 0 ? '∞' : plan.max_users}</div>
                            <div className="text-[10px] text-slate-500 font-medium uppercase mt-0.5">{t('Users')}</div>
                          </div>
                          <div className="bg-slate-50 p-2.5 rounded-lg border border-slate-100 text-center flex flex-col justify-center transition-colors hover:bg-slate-100/80">
                            <div className="text-lg font-bold text-slate-700">{plan.max_projects === 0 ? '∞' : plan.max_projects}</div>
                            <div className="text-[10px] text-slate-500 font-medium uppercase mt-0.5">{t('Projects')}</div>
                          </div>
                          <div className="bg-slate-50 p-2.5 rounded-lg border border-slate-100 text-center flex flex-col justify-center transition-colors hover:bg-slate-100/80">
                            <div className="text-lg font-bold text-slate-700">{plan.max_contacts === 0 ? '∞' : plan.max_contacts}</div>
                            <div className="text-[10px] text-slate-500 font-medium uppercase mt-0.5">{t('Contacts')}</div>
                          </div>
                          <div className="bg-slate-50 p-2.5 rounded-lg border border-slate-100 text-center flex flex-col justify-center transition-colors hover:bg-slate-100/80">
                            <div className="text-lg font-bold text-slate-700">{plan.max_accounts === 0 ? '∞' : plan.max_accounts}</div>
                            <div className="text-[10px] text-slate-500 font-medium uppercase mt-0.5">{t('Accounts')}</div>
                          </div>
                          <div className="bg-slate-50 p-2.5 rounded-lg border border-slate-100 text-center flex flex-col justify-center col-span-2 transition-colors hover:bg-slate-100/80">
                            <div className="text-lg font-bold text-slate-700">{plan.storage_limit ? `${plan.storage_limit}GB` : '1GB'}</div>
                            <div className="text-[10px] text-slate-500 font-medium uppercase mt-0.5">{t('Storage')}</div>
                          </div>
                        </div>
                      </div>
                    </div>

                    {renderCardActions(plan)}
                  </div>
                );
              }) : (
                <div className="col-span-full flex flex-col items-center justify-center py-16 text-slate-500 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                  <CreditCard className="h-12 w-12 text-slate-300 mb-4" />
                  <p className="text-lg font-medium">{t('No plans available for')} {isYearly ? t('yearly') : t('monthly')} {t('billing')}</p>
                </div>
              )}
            </div>
          </div>

          <div className="bg-slate-50 border-t border-slate-200 px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-4 shrink-0">
            {showHideOption ? (
              <div className="flex items-center space-x-2">
                <Checkbox
                  id="hide-modal"
                  checked={hideNextTime}
                  onCheckedChange={(checked) => setHideNextTime(checked as boolean)}
                />
                <label htmlFor="hide-modal" className="text-sm font-medium text-slate-600 cursor-pointer select-none">
                  {t("Don't show this again")}
                </label>
              </div>
            ) : <div />}
            <Button variant="ghost" onClick={handleClose} disabled={processing} className="text-slate-600 hover:text-slate-900 border border-slate-200 bg-white">
              {t("Cancel / Close")}
            </Button>
          </div>
        </DialogContent>
      </Dialog >

      {selectedPlanDetails && (
        <PlanSubscriptionModal
          isOpen={isSubscriptionModalOpen}
          onClose={() => setIsSubscriptionModalOpen(false)}
          plan={selectedPlanDetails as any}
          billingCycle={isYearly ? 'yearly' : 'monthly'}
          paymentMethods={paymentMethods as any}
        />
      )}
    </>
  );
}
