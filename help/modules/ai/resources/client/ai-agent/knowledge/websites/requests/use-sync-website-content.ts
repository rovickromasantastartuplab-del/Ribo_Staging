import {aiAgentQueries} from '@ai/ai-agent/ai-agent-queries';
import {apiClient, queryClient} from '@common/http/query-client';
import {showHttpErrorToast} from '@common/http/show-http-error-toast';
import {useRequiredParams} from '@common/ui/navigation/use-required-params';
import {useMutation} from '@tanstack/react-query';
import {message} from '@ui/i18n/message';
import {toast} from '@ui/toast/toast';

interface Payload {
  websiteId: string | number;
}
export function useSyncWebsiteContent() {
  const {aiAgentId} = useRequiredParams(['aiAgentId']);
  return useMutation({
    mutationFn: async ({websiteId}: Payload) => {
      return apiClient.post(`lc/ai-agent/websites/${websiteId}/sync`, {
        aiAgentId,
      });
    },
    onSuccess: () => {
      toast(message('Website sync started'));
      return queryClient.invalidateQueries({
        queryKey: aiAgentQueries.knowledge.invalidateKey,
      });
    },
    onError: err => showHttpErrorToast(err),
  });
}
