import { useState, useEffect } from 'react';
import { MessageSquare, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from 'react-i18next';

export function ChatWidgetToggle() {
    const { t } = useTranslation();
    const [isHidden, setIsHidden] = useState(true); // Default to true so it doesn't overlap pagination

    useEffect(() => {
        // Apply class to body based on state
        if (isHidden) {
            document.body.classList.add('hide-bedesk');
        } else {
            document.body.classList.remove('hide-bedesk');
        }

        // Cleanup on unmount
        return () => {
            document.body.classList.remove('hide-bedesk');
        };
    }, [isHidden]);

    const toggleWidget = () => {
        setIsHidden(!isHidden);
    };

    return (
        <div 
            className="fixed bottom-32 right-0 flex items-center shadow-lg rounded-l-lg transition-transform duration-300"
            style={{ zIndex: 2147483647 }} 
        >
            <Button
                onClick={toggleWidget}
                className={`flex items-center justify-center rounded-none rounded-l-lg transition-all border border-r-0 bg-white hover:bg-gray-50 shadow-xl overflow-hidden group 
                dark:bg-gray-800 dark:border-gray-700 dark:hover:bg-gray-700 
                ${isHidden ? 'h-12 w-12 hover:w-28' : 'h-12 w-10 hover:w-12 opacity-80 hover:opacity-100'}`}
                aria-label={isHidden ? t("Show Chat") : t("Hide Chat")}
                title={isHidden ? t("Show Chat") : t("Hide Chat")}
                style={{
                    backgroundColor: 'var(--primary-color, #ffffff)' 
                }}
            >
                {isHidden ? (
                    <>
                        <MessageSquare className="h-5 w-5 flex-shrink-0" style={{ color: 'var(--brand-color, #3b82f6)' }} />
                        <span className="opacity-0 w-0 group-hover:opacity-100 group-hover:w-auto whitespace-nowrap text-sm font-semibold ml-2 transition-all duration-300" style={{ color: 'var(--brand-color, #3b82f6)' }}>
                            {t("Chat")}
                        </span>
                    </>
                ) : (
                    <ChevronRight className="h-5 w-5 text-gray-500 flex-shrink-0 dark:text-gray-400" />
                )}
            </Button>
        </div>
    );
}
