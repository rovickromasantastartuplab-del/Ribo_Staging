import React, { useState, useEffect, useCallback } from 'react';
import { X, ArrowLeft, ExternalLink, Loader2 } from 'lucide-react';

interface HelpArticlePanelProps {
    isOpen: boolean;
    articlePath: string | null;
    onClose: () => void;
}

function HelpArticlePanel({ isOpen, articlePath, onClose }: HelpArticlePanelProps) {
    const [loading, setLoading] = useState(true);

    const externalUrl = articlePath
        ? `https://help.ribo.com.ph/hc${articlePath}`
        : null;

    // Reset loading state when article changes
    useEffect(() => {
        if (isOpen && articlePath) {
            setLoading(true);
        }
    }, [isOpen, articlePath]);

    if (!isOpen || !externalUrl) return null;

    return (
        <>
            {/* Backdrop */}
            <div
                className="fixed inset-0 bg-black/30 z-[9998]"
                onClick={onClose}
            />

            {/* Side Panel */}
            <div
                className="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-xl z-[9999] flex flex-col"
                style={{ animation: 'slideInFromRight 0.25s ease-out' }}
            >
                {/* Header */}
                <div className="flex items-center gap-3 px-5 py-4 border-b border-gray-200 flex-shrink-0">
                    <button
                        onClick={onClose}
                        className="flex items-center gap-2 text-gray-600 hover:text-gray-900 font-medium text-sm transition-colors"
                    >
                        <ArrowLeft className="w-4 h-4" />
                        Back
                    </button>
                    <div className="flex-1" />
                    <a
                        href={externalUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-gray-400 hover:text-gray-600 transition-colors"
                        title="Open in help center"
                    >
                        <ExternalLink className="w-4 h-4" />
                    </a>
                    <button
                        onClick={onClose}
                        className="text-gray-400 hover:text-gray-600 transition-colors ml-1"
                        title="Close"
                    >
                        <X className="w-4 h-4" />
                    </button>
                </div>

                {/* Loading overlay */}
                {loading && (
                    <div className="absolute inset-0 top-[57px] flex items-center justify-center bg-white z-10">
                        <Loader2 className="w-6 h-6 animate-spin text-gray-400" />
                    </div>
                )}

                {/* Article iframe */}
                <iframe
                    key={externalUrl}
                    src={externalUrl}
                    className="flex-1 w-full border-none"
                    onLoad={() => setLoading(false)}
                    title="Help Article"
                />
            </div>

            <style>{`
                @keyframes slideInFromRight {
                    from { transform: translateX(100%); }
                    to { transform: translateX(0); }
                }
            `}</style>
        </>
    );
}

// Global setter function — wired up by HelpArticlePanelRoot on mount
let _setPanelState: ((state: { isOpen: boolean; path: string | null }) => void) | null = null;

export function openHelpArticlePanel(path: string) {
    if (_setPanelState) {
        _setPanelState({ isOpen: true, path });
    }
}

export function HelpArticlePanelRoot() {
    const [state, setState] = useState<{ isOpen: boolean; path: string | null }>({
        isOpen: false,
        path: null,
    });

    useEffect(() => {
        // Register module-level setter (used by app.tsx Inertia intercept)
        _setPanelState = setState;

        // Register global window function (used by app.blade.php pushState/click interceptors)
        (window as any).__helpArticlePanelOpen = (path: string) => {
            setState({ isOpen: true, path });
        };

        return () => {
            _setPanelState = null;
            delete (window as any).__helpArticlePanelOpen;
        };
    }, []);

    const handleClose = useCallback(() => {
        setState({ isOpen: false, path: null });
    }, []);

    return (
        <HelpArticlePanel
            isOpen={state.isOpen}
            articlePath={state.path}
            onClose={handleClose}
        />
    );
}
