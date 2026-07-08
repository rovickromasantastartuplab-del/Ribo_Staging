import { ReactNode, useState } from 'react';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from 'react-i18next';
import { router } from '@inertiajs/react';

interface StaffOption {
  id: number;
  name: string;
}

interface ReportFiltersProps {
  filters: {
    dateFrom: string;
    dateTo: string;
    staffId?: number | string | null;
    [key: string]: any;
  };
  staffList?: StaffOption[];
  additionalFilters?: ReactNode;
}

const ALL_STAFF = 'all';

export function ReportFilters({ filters, staffList, additionalFilters }: ReportFiltersProps) {
  const { t } = useTranslation();
  const [dateFrom, setDateFrom] = useState(filters.dateFrom);
  const [dateTo, setDateTo] = useState(filters.dateTo);
  const [staffId, setStaffId] = useState(
    filters.staffId != null && filters.staffId !== '' ? String(filters.staffId) : ALL_STAFF,
  );

  const handleFilterSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const params: Record<string, string> = {
      date_from: dateFrom,
      date_to: dateTo,
    };
    if (staffId !== ALL_STAFF) {
      params.staff_id = staffId;
    }
    router.get(window.location.pathname, params);
  };

  const handleClearFilters = () => {
    setStaffId(ALL_STAFF);
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
        {staffList && staffList.length > 0 && (
          <div className="w-full sm:w-auto sm:flex-1">
            <Label htmlFor="staff_id">{t('Assigned Staff')}</Label>
            <Select value={staffId} onValueChange={setStaffId}>
              <SelectTrigger id="staff_id">
                <SelectValue placeholder={t('All Staff')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL_STAFF}>{t('All Staff')}</SelectItem>
                {staffList.map((staff) => (
                  <SelectItem key={staff.id} value={String(staff.id)}>
                    {staff.name}
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