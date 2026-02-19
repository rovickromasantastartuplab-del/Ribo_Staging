import { usePage } from '@inertiajs/react';

type PlanFeatureKey = 'branding' | 'ai_integration' | 'trial';

export function usePlanFeatures() {
  const { auth } = usePage().props as any;
  return auth?.plan_features;
}

export function hasPlanFeature(feature: PlanFeatureKey): boolean {
  const { auth } = usePage().props as any;
  const planFeatures = auth?.plan_features;

  if (!planFeatures) return false;
  if (planFeatures.is_superadmin) return true;

  const features: unknown = planFeatures.features;
  if (Array.isArray(features) && features.includes(feature)) {
    return true;
  }

  const flags = planFeatures.flags || {};

  if (feature === 'branding') return !!flags.enable_branding;
  if (feature === 'ai_integration') return !!flags.enable_chatgpt;
  if (feature === 'trial') return !!flags.is_trial;

  return false;
}
