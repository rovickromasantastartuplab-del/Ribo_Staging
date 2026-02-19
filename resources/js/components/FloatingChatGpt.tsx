import { useState, useEffect } from 'react';
import { Brain } from 'lucide-react';
import { ChatGptModal } from '@/components/chatgpt';
import { Button } from '@/components/ui/button';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { hasPlanFeature } from '@/utils/planFeatures';

export function FloatingChatGpt() {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);
  const [generatedContent, setGeneratedContent] = useState('');
  const canUseChatGPT = hasPlanFeature('ai_integration');
  
  useEffect(() => {
  }, [isOpen]);
  
  // Don't render if user doesn't have access
  if (!canUseChatGPT) {
    return null;
  }

  const handleGenerate = (content: string) => {
    setGeneratedContent(content);
    // You can add additional logic here if needed
  };

  const handleModalOpen = () => {
    setIsOpen(true);
  };

  const handleModalClose = () => {
    setIsOpen(false);
  };

  return (
    <>
      <div 
        className="fixed bottom-6 rtl:left-6 ltr:right-6 z-[9999]"
        onClickCapture={(e) => {
          e.preventDefault();
          e.stopPropagation();
          e.nativeEvent.stopImmediatePropagation();
          handleModalOpen();
        }}
        onMouseDownCapture={(e) => {
          e.preventDefault();
          e.stopPropagation();
        }}
        onClick={(e) => {
          e.preventDefault();
          e.stopPropagation();
        }}
      >
        <Button
          onClick={(e) => {
            e.preventDefault();
            e.stopPropagation();
            handleModalOpen();
          }}
          className="h-14 w-14 rounded-full shadow-lg hover:shadow-xl transition-shadow"
          size="lg"
        >
          <Brain className="h-6 w-6" />
        </Button>
      </div>

      <ChatGptModal
        isOpen={isOpen}
        onClose={handleModalClose}
        onGenerate={handleGenerate}
        title={t("AI Assistant")}
        placeholder={t("What would you like me to help you generate?")}
      />
    </>
  );
}