import { useTranslation } from 'react-i18next';

interface Staff {
  id: number;
  name: string;
}

interface ReportHeaderProps {
  title: string;
  filters: {
    dateFrom: string;
    dateTo: string;
    staffId?: string;
  };
  staff?: Staff[];
}

export function ReportHeader({ title, filters, staff }: ReportHeaderProps) {
  const { t } = useTranslation();
  
  const selectedStaff = staff?.find(s => s.id.toString() === filters.staffId?.toString());

  return (
    <div className="hidden print:block mb-8 border-b pb-4">
      <h1 className="text-3xl font-bold text-gray-900 mb-2">{title}</h1>
      <div className="flex flex-wrap gap-x-8 gap-y-2 text-sm text-gray-600">
        <div>
          <span className="font-semibold text-gray-800">{t('Date Range')}:</span>{' '}
          {filters.dateFrom} {t('to')} {filters.dateTo}
        </div>
        {selectedStaff && (
          <div>
            <span className="font-semibold text-gray-800">{t('Staff')}:</span>{' '}
            {selectedStaff.name}
          </div>
        )}
      </div>
    </div>
  );
}
