import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Info, AlertTriangle } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';

interface SkippedRow {
  row: number;
  name: string;
  email: string;
  reason: string;
}

interface ColumnMappingModalProps {
  isOpen: boolean;
  onClose: () => void;
  excelColumns: string[];
  databaseFields: { key: string; required?: boolean }[];
  importRoute: string;
  tempFile: string;
  previewData?: Record<string, string>[];
}

export function ColumnMappingModal({
  isOpen,
  onClose,
  excelColumns,
  databaseFields,
  importRoute,
  tempFile,
  previewData = []
}: ColumnMappingModalProps) {
  const { t } = useTranslation();
  const [mapping, setMapping] = useState<Record<string, string>>({});
  const [isImporting, setIsImporting] = useState(false);
  const [showSkippedRows, setShowSkippedRows] = useState(false);
  const [skippedRows, setSkippedRows] = useState<SkippedRow[]>([]);

  useEffect(() => {
    if (isOpen && excelColumns.length > 0) {
      const autoMapping: Record<string, string> = {};
      databaseFields.forEach(field => {
        const match = excelColumns.find(col =>
          col.toLowerCase().replace(/[_\s]/g, '') === field.key.toLowerCase().replace(/[_\s]/g, '')
        );
        if (match) {
          autoMapping[field.key] = match;
        }
      });
      setMapping(autoMapping);
    }
  }, [isOpen, excelColumns, databaseFields]);

  const handleSubmit = () => {
    if (!tempFile) {
      toast.error(t('No file available for import. Please re-upload.'));
      return;
    }

    const requiredFields = databaseFields.filter(f => f.required);
    const missingFields = requiredFields.filter(f => !mapping[f.key]);

    if (missingFields.length > 0) {
      toast.error(t('Please map all required fields: {{fields}}', { fields: missingFields.map(f => f.key).join(', ') }));
      return;
    }

    setIsImporting(true);
    toast.loading(t('Importing...'));

    // Send only the mapping and temp file reference — server handles all data processing
    router.post(route(importRoute), {
      mapping: mapping,
      tempFile: tempFile
    }, {
      preserveState: true,
      onSuccess: (page) => {
        setIsImporting(false);
        toast.dismiss();

        const flash = page.props.flash as any;

        if (flash.success) {
          toast.success(t(flash.success));
        } else if (flash.error) {
          toast.error(t(flash.error));
        }

        // Check for skipped rows
        if (flash.skippedRows && Array.isArray(flash.skippedRows) && flash.skippedRows.length > 0) {
          setSkippedRows(flash.skippedRows);
          onClose();
          setShowSkippedRows(true);
        } else {
          onClose();
        }
      },
      onError: (errors) => {
        setIsImporting(false);
        toast.dismiss();
        if (typeof errors === 'string') {
          toast.error(errors);
        } else {
          toast.error(t('Failed to import'));
        }
      }
    });
  };

  const handleCloseSkippedRows = () => {
    setShowSkippedRows(false);
    setSkippedRows([]);
  };

  return (
    <>
      <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()} modal={false}>
        <DialogContent className="max-w-6xl max-h-[90vh] overflow-hidden flex flex-col">
          <DialogHeader>
            <DialogTitle>{t('Import Customers')}</DialogTitle>
          </DialogHeader>

          <Alert className="bg-amber-50 border-amber-200">
            <Info className="h-4 w-4 text-amber-600" />
            <AlertDescription className="text-amber-800">
              {t('Map your CSV columns to database fields')}
            </AlertDescription>
          </Alert>

          <div className="flex-1 overflow-auto">
            <h3 className="text-sm font-semibold mb-3">{t('Map Excel Columns to Database Fields')}</h3>
            <div className="border rounded-lg">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 border-b">
                  <tr>
                    {databaseFields.map((field) => (
                      <th key={field.key} className="px-4 py-2 text-left font-medium text-gray-700">
                        <div className="space-y-1">
                          <div>
                            {field.key}
                            {field.required && <span className="text-red-500 ml-1">*</span>}
                          </div>
                          <Select
                            value={mapping[field.key] || '__unselect__'}
                            onValueChange={(value) => {
                              setMapping(prev => {
                                const newMapping = { ...prev };
                                if (value === '__unselect__') {
                                  delete newMapping[field.key];
                                } else {
                                  // Remove this column from other mappings
                                  Object.keys(newMapping).forEach(key => {
                                    if (newMapping[key] === value) delete newMapping[key];
                                  });
                                  newMapping[field.key] = value;
                                }
                                return newMapping;
                              });
                            }}
                          >
                            <SelectTrigger className="h-8 text-xs w-full">
                              <SelectValue placeholder={t('Select column...')} />
                            </SelectTrigger>
                            <SelectContent position="popper" className="z-[9999]">
                              <SelectItem value="__unselect__">{t('Select column...')}</SelectItem>
                              {excelColumns.map(col => {
                                const isUsed = Object.values(mapping).includes(col) && mapping[field.key] !== col;
                                return (
                                  <SelectItem key={col} value={col} disabled={isUsed}>
                                    {col}
                                  </SelectItem>
                                );
                              })}
                            </SelectContent>
                          </Select>
                        </div>
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {previewData.slice(0, 2).map((row, idx) => (
                    <tr key={idx} className="border-b">
                      {databaseFields.map(field => (
                        <td key={field.key} className="px-4 py-2 text-gray-600">
                          {mapping[field.key] ? row[mapping[field.key]] || t('No data') : t('-')}
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose} disabled={isImporting}>
              {t('Back')}
            </Button>
            <Button type="button" onClick={handleSubmit} disabled={isImporting}>
              {t('Import Data')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Skipped Rows Summary Dialog */}
      <Dialog open={showSkippedRows} onOpenChange={(open) => !open && handleCloseSkippedRows()}>
        <DialogContent className="max-w-2xl max-h-[80vh] overflow-hidden flex flex-col">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <AlertTriangle className="h-5 w-5 text-amber-500" />
              {t('Skipped Rows Summary')}
            </DialogTitle>
          </DialogHeader>

          <Alert className="bg-amber-50 border-amber-200">
            <AlertTriangle className="h-4 w-4 text-amber-600" />
            <AlertDescription className="text-amber-800">
              {t('{{count}} row(s) were skipped during import. See details below.', { count: skippedRows.length })}
            </AlertDescription>
          </Alert>

          <div className="flex-1 overflow-auto">
            <div className="border rounded-lg">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 border-b sticky top-0">
                  <tr>
                    <th className="px-4 py-2 text-left font-medium text-gray-700 w-16">{t('Row')}</th>
                    <th className="px-4 py-2 text-left font-medium text-gray-700">{t('Name')}</th>
                    <th className="px-4 py-2 text-left font-medium text-gray-700">{t('Email')}</th>
                    <th className="px-4 py-2 text-left font-medium text-gray-700">{t('Reason')}</th>
                  </tr>
                </thead>
                <tbody>
                  {skippedRows.map((row, idx) => (
                    <tr key={idx} className="border-b hover:bg-gray-50">
                      <td className="px-4 py-2 text-gray-600">{row.row}</td>
                      <td className="px-4 py-2 text-gray-600">{row.name || <span className="text-gray-400 italic">{t('Empty')}</span>}</td>
                      <td className="px-4 py-2 text-gray-600">{row.email || <span className="text-gray-400 italic">{t('Empty')}</span>}</td>
                      <td className="px-4 py-2">
                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${row.reason.includes('Duplicate') ? 'bg-orange-100 text-orange-800' : 'bg-red-100 text-red-800'
                          }`}>
                          {t(row.reason)}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          <DialogFooter>
            <Button type="button" onClick={handleCloseSkippedRows}>
              {t('Close')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
