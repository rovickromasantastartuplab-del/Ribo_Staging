import { useState, ReactNode } from 'react';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from 'react-i18next';
import { router } from '@inertiajs/react';

interface Staff {
  id: number;
  name: string;
}

interface ReportFiltersProps {
  filters: {
    dateFrom: string;
    dateTo: string;
    staffId?: string;
    [key: string]: any;
  };
  staff?: Staff[];
  additionalFilters?: ReactNode;
}

export function ReportFilters({ filters, staff, additionalFilters }: ReportFiltersProps) {
  const { t } = useTranslation();
  const [dateFrom, setDateFrom] = useState(filters.dateFrom);
  const [dateTo, setDateTo] = useState(filters.dateTo);
  const [staffId, setStaffId] = useState(filters.staffId?.toString() || 'all');

  const handleFilterSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const params: any = {
      date_from: dateFrom,
      date_to: dateTo,
    };
    if (staffId && staffId !== 'all') {
      params.staff_id = staffId;
    }
    router.get(window.location.pathname, params);
  };

  const handleClearFilters = () => {
    const defaultDateFrom = new Date();
    defaultDateFrom.setMonth(defaultDateFrom.getMonth() - 1);
    const defaultDateTo = new Date();
    
    setDateFrom(defaultDateFrom.toISOString().split('T')[0]);
    setDateTo(defaultDateTo.toISOString().split('T')[0]);
    setStaffId('all');
    
    router.get(window.location.pathname);
  };

  return (
    <Card className="mb-6 p-4 print:hidden">
      <form onSubmit={handleFilterSubmit} className="flex flex-col sm:flex-row sm:items-end flex-wrap gap-2 sm:gap-4">
        <div className="w-full sm:w-auto sm:flex-1">
          <Label htmlFor="date_from">{t('From Date')}</Label>
          <Input
            id="date_from"
            type="date"
            value={dateFrom}
            onChange={(e) => setDateFrom(e.target.value)}
          />
        </div>
        <div className="w-full sm:w-auto sm:flex-1">
          <Label htmlFor="date_to">{t('To Date')}</Label>
          <Input
            id="date_to"
            type="date"
            value={dateTo}
            onChange={(e) => setDateTo(e.target.value)}
          />
        </div>
        {staff && staff.length > 0 && (
          <div className="w-full sm:w-auto sm:flex-1 min-w-[200px]">
            <Label htmlFor="staff_id">{t('Staff')}</Label>
            <Select value={staffId} onValueChange={setStaffId}>
              <SelectTrigger id="staff_id">
                <SelectValue placeholder={t('Select Staff')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t('All Staff')}</SelectItem>
                {staff.map((s) => (
                  <SelectItem key={s.id} value={s.id.toString()}>
                    {s.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        )}
        {additionalFilters}
        <Button type="submit" className="w-full sm:w-auto">{t('Apply Filters')}</Button>
        <Button type="button" variant="outline" onClick={handleClearFilters} className="w-full sm:w-auto">{t('Clear Filters')}</Button>
      </form>
    </Card>
  );
}