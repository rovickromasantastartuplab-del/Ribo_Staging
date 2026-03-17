import { Toaster } from '@/components/ui/sonner';
import { toast as sonnerToast } from 'sonner';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const isDemoMode = (): boolean => {
  return (window as any).isDemo || false;
};

const demoModeMessage = 'This action is disabled in demo mode. You can only create new data, not modify existing demo data.';

// Override router methods
const originalPut = router.put;
const originalDelete = router.delete;
const originalPatch = router.patch;

router.put = function(url: string, data?: any, options?: any) {
  if (isDemoMode()) {
    sonnerToast.error(demoModeMessage);
    return;
  }
  return originalPut.call(this, url, data, options);
};

router.delete = function(url: string, options?: any) {
  if (isDemoMode()) {
    sonnerToast.error(demoModeMessage);
    return;
  }
  return originalDelete.call(this, url, options);
};

router.patch = function(url: string, data?: any, options?: any) {
  if (isDemoMode()) {
    sonnerToast.error(demoModeMessage);
    return;
  }
  return originalPatch.call(this, url, data, options);
};

export const toast = {
  ...sonnerToast,
  loading: (message: string, options?: any) => {
    if(isDemoMode()){
        return;
    }
    if (isDemoMode() && (message.includes('Delet') || message.includes('Updat') || message.includes('Reset') || message.includes('Modif') || message.includes('Acti') || message.includes('Deacti'))) {
      return;
    }
    return sonnerToast.loading(message, options);
  },
};

export const CustomToast = () => {
    const { flash = {} } = usePage().props as any;
    const [lastFlash, setLastFlash] = useState({ success: null, error: null });

    useEffect(() => {
        // Only show toast if the flash message has changed or is new
        if (flash.success && flash.success !== lastFlash.success) {
            sonnerToast.success(flash.success);
            setLastFlash(prev => ({ ...prev, success: flash.success }));
        }
        if (flash.error && flash.error !== lastFlash.error) {
            sonnerToast.error(flash.error);
            setLastFlash(prev => ({ ...prev, error: flash.error }));
        }
    }, [flash, lastFlash]);

    return <Toaster position="top-right" duration={4000} richColors closeButton />;
};
