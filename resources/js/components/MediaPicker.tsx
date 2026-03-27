import React, { useState, useEffect } from 'react';
import { Button } from './ui/button';
import { Input } from './ui/input';
import { Label } from './ui/label';
import MediaLibraryModal from './MediaLibraryModal';
import { Image as ImageIcon, X, Trash2 } from 'lucide-react';

interface MediaPickerProps {
    label?: string;
    value?: string | number | number[];
    onChange: (value: string | number | number[]) => void;
    multiple?: boolean;
    placeholder?: string;
    showPreview?: boolean;
    returnType?: 'url' | 'id';
    allowedTypes?: string[] | string;
}

export default function MediaPicker({
    label,
    value = '',
    onChange,
    multiple = false,
    placeholder = 'Select image...',
    showPreview = true,
    returnType = 'url',
    allowedTypes
}: MediaPickerProps) {
    const [isModalOpen, setIsModalOpen] = useState(false);

    const handleSelect = (selectedData: string | number | number[]) => {
        onChange(selectedData);
    };

    const handleClear = () => {
        if (multiple) {
            onChange([]);
        } else {
            onChange(returnType === 'id' ? '' : '');
        }
        setImageUrls([]);
        setImageNames([]);
    };

    const [imageUrls, setImageUrls] = useState<string[]>([]);
    const [imageNames, setImageNames] = useState<string[]>([]);

    // Fetch image URLs and names when using ID return type
    useEffect(() => {
        if (returnType === 'id' && value) {
            const ids = Array.isArray(value) ? value : [value].filter(Boolean);
            if (ids.length > 0) {
                // Fetch each media item individually so we can look up
                // attachments from any model/collection (not just the media library)
                Promise.all(
                    ids.map(id =>
                        fetch(route('api.media.show', id), {
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        })
                            .then(r => r.ok ? r.json() : null)
                            .catch(() => null)
                    )
                ).then(results => {
                    const urls: string[] = [];
                    const names: string[] = [];
                    results.forEach((mediaItem, i) => {
                        if (mediaItem) {
                            urls.push(mediaItem.url || '');
                            names.push(mediaItem.file_name || mediaItem.name || `File ${ids[i]}`);
                        }
                    });
                    setImageUrls(urls);
                    setImageNames(names);
                });
            } else {
                setImageUrls([]);
                setImageNames([]);
            }
        } else if (returnType === 'url') {
            const valueStr = Array.isArray(value) ? value.join(',') : String(value || '');
            const urls = valueStr ? valueStr.split(',').map(url => url.trim()).filter(Boolean) : [];
            setImageUrls(urls);
            setImageNames(urls.map((url, index) => `Image ${index + 1}`));
        }
    }, [value, returnType]);


    // displayValue = imageUrls.length > 0 ? imageUrls.join(', ') : (Array.isArray(value) ? value.join(',') : String(value || ''));

    const displayValue = imageNames.length > 0
        ? imageNames.join(', ')
        : imageUrls.map((img) => {
            const imagePathArr = String(img || '').split('?')[0].split('/');
            return imagePathArr[imagePathArr.length - 1];
        }).join(', ');

    return (
        <div className="space-y-2">
            {label && <Label>{label}</Label>}

            <div className="flex gap-2">
                <Input
                    value={displayValue}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={placeholder}
                    readOnly={multiple}
                />
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => setIsModalOpen(true)}
                >
                    <ImageIcon className="h-4 w-4 mr-2" />
                    Browse
                </Button>
                {(imageNames.length > 0 || displayValue) && (
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        onClick={handleClear}
                    >
                        <X className="h-4 w-4" />
                    </Button>
                )}
            </div>

            {/* Preview */}
            {showPreview && imageUrls.length > 0 && (
                <div className="grid grid-cols-2 md:grid-cols-2 md:grid-cols-4 gap-2 mt-2">
                    {imageUrls.map((url, index) => (
                        <div key={index} className="relative">
                            <img
                                src={url}
                                alt={`Preview ${index + 1}`}
                                className="w-full h-20 object-cover rounded border"
                                onError={(e) => {
                                    e.currentTarget.style.display = 'none';
                                }}
                            />
                            {multiple && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        if (Array.isArray(value)) {
                                            const newValue = value.filter((_, i) => i !== index);
                                            onChange(newValue);
                                        }
                                    }}
                                    className="absolute top-1 right-1 h-6 w-6 p-0 bg-red-500 text-white hover:bg-red-600"
                                >
                                    <Trash2 className="h-3 w-3" />
                                </Button>
                            )}
                        </div>
                    ))}
                </div>
            )}

            <MediaLibraryModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                onSelect={handleSelect}
                multiple={multiple}
                returnType={returnType}
                preSelected={Array.isArray(value) ? value : (value ? [value] : [])}
                allowedTypes={allowedTypes}
            />
        </div>
    );
}
