// components/CrudTable.tsx
import { Button } from '@/components/ui/button';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { ChevronDown, ChevronUp, ChevronsUpDown } from 'lucide-react';
import * as LucidIcons from 'lucide-react';
import { hasPermission } from '@/utils/authorization';
import { TableColumn, TableAction } from '@/types/crud';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Checkbox } from '@/components/ui/checkbox';
import { useState, useEffect } from 'react';
import { DragDropContext, Droppable, Draggable, DropResult } from '@hello-pangea/dnd';

interface CrudTableProps {
  columns: TableColumn[];
  actions: TableAction[];
  data: any[];
  from: number;
  onAction: (action: string, row: any) => void;
  sortField?: string;
  sortDirection?: 'asc' | 'desc';
  onSort?: (field: string) => void;
  statusColors?: Record<string, string>;
  permissions: string[];
  entityPermissions?: {
    view: string;
    edit: string;
    delete: string;
  };
  showActionsAsIcons?: boolean;
  onBulkAction?: (action: string, selectedIds: any[]) => void;
  bulkActions?: { label: string; action: string; icon?: string; variant?: "default" | "destructive" | "outline" | "secondary" | "ghost" | "link" }[];
  onDragEnd?: (result: DropResult) => void;
}

export function CrudTable({
  columns,
  actions,
  data,
  from,
  onAction,
  sortField,
  sortDirection,
  onSort,
  statusColors = {},
  permissions,
  entityPermissions,
  onBulkAction,
  bulkActions = [],
  onDragEnd
}: CrudTableProps) {
  const { t } = useTranslation();
  const [selectedRows, setSelectedRows] = useState<any[]>([]);

  // Clear selection when data changes (e.g., page change)
  useEffect(() => {
    setSelectedRows([]);
  }, [data]);

  const toggleAll = () => {
    if (selectedRows.length === data.length) {
      setSelectedRows([]);
    } else {
      setSelectedRows(data.map(item => item.id));
    }
  };

  const toggleRow = (id: any) => {
    if (selectedRows.includes(id)) {
      setSelectedRows(selectedRows.filter(rowId => rowId !== id));
    } else {
      setSelectedRows([...selectedRows, id]);
    }
  };

  const hasBulkActions = bulkActions && bulkActions.length > 0 && onBulkAction;
  const renderSortIcon = (column: TableColumn) => {
    if (!column.sortable) return null;

    if (sortField === column.key) {
      return sortDirection === 'asc' ?
        <ChevronUp className="ml-1 h-4 w-4" /> :
        <ChevronDown className="ml-1 h-4 w-4" />;
    }

    return <ChevronsUpDown className="ml-1 h-4 w-4 opacity-50" />;
  };

  const handleSort = (column: TableColumn) => {
    if (!column.sortable || !onSort) return;
    onSort(column.key);
  };

  // Check if any actions have permissions
  const hasAnyActionPermission = actions.some((action) => {
    const permissionKey =
      action.requiredPermission ||
      (entityPermissions &&
        (action.action === 'view'
          ? entityPermissions.view
          : action.action === 'edit'
            ? entityPermissions.edit
            : action.action === 'delete'
              ? entityPermissions.delete
              : action.permission));

    return !permissionKey || hasPermission(permissions, permissionKey);
  });

  const renderActionButtons = (row: any) => {
    return (
      <div className="flex items-center justify-end space-x-2">
        {actions.map((action, index) => {
          // Skip if user doesn't have permission
          const permissionKey = action.requiredPermission || (
            entityPermissions && (
              action.action === 'view'
                ? entityPermissions.view
                : action.action === 'edit'
                  ? entityPermissions.edit
                  : action.action === 'delete'
                    ? entityPermissions.delete
                    : action.permission
            )
          );

          if (permissionKey && !hasPermission(permissions, permissionKey)) {
            return null;
          }

          // Skip if condition function returns false
          if (action.condition && !action.condition(row)) {
            return null;
          }

          const IconComponent = (LucidIcons as any)[action.icon] as React.ElementType;

          // Handle link actions
          if (action.href) {
            const href = typeof action.href === 'function'
              ? action.href(row)
              : action.href.replace(':id', row.id);

            return (
              <TooltipProvider key={index}>
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Link href={href} target={action.openInNewTab ? '_blank' : undefined}>
                      <Button
                        variant="ghost"
                        size="icon"
                        className={cn("h-8 w-8", action.className)}
                      >
                        <IconComponent size={16} />
                      </Button>
                    </Link>
                  </TooltipTrigger>
                  <TooltipContent>
                    <p>{action.label}</p>
                  </TooltipContent>
                </Tooltip>
              </TooltipProvider>
            );
          }

          // Handle regular action buttons
          return (
            <TooltipProvider key={index}>
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="icon"
                    className={cn("h-8 w-8", action.className)}
                    onClick={() => onAction(action.action, row)}
                  >
                    <IconComponent size={16} />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>
                  <p>{action.label}</p>
                </TooltipContent>
              </Tooltip>
            </TooltipProvider>
          );
        })}
      </div>
    );
  };

  // Helper function to get nested property value using dot notation
  const getNestedValue = (obj: any, path: string) => {
    if (!obj || !path) return null;

    const keys = path.split('.');
    return keys.reduce((acc, key) => {
      return acc && acc[key] !== undefined ? acc[key] : null;
    }, obj);
  };

  const renderCellContent = (row: any, col: TableColumn) => {
    // Get value using dot notation for nested properties
    const value = getNestedValue(row, col.key);

    // If column has custom render function, use it
    if (col.render) {
      return col.render(value, row);
    }

    // Handle different column types
    switch (col.type) {
      case 'badge':
        return (
          <Badge className={cn("capitalize", statusColors[value])}>
            {value}
          </Badge>
        );

      case 'image':
        if (!value) {
          return <div className="text-center text-gray-400">{t("No image")}</div>;
        }
        return (
          <div className="flex justify-center">
            <img
              src={value.startsWith && value.startsWith('http')
                ? value
                : `/storage/${value}`}
              alt={row.name || 'Image'}
              className={col.className || "h-16 w-20 rounded-md object-cover shadow-sm"}
              onError={(e) => {
                e.currentTarget.src = 'https://placehold.co/200x150?text=Image+Not+Found';
              }}
            />
          </div>
        );

      case 'date':
        return value ? <span className="text-sm">{new Date(value).toLocaleDateString()}</span> : <span>-</span>;

      case 'currency':
        return <span className="text-sm">{typeof value === 'number' ?
          value.toLocaleString('en-US', { style: 'currency', currency: 'USD' }) :
          value}</span>;

      case 'boolean':
        return <span className="text-sm">{value ? 'Yes' : 'No'}</span>;

      case 'link':
        if (!value) return <span>-</span>;

        const href = col.href
          ? (typeof col.href === 'function' ? col.href(row) : col.href.replace(':id', row.id))
          : '#';

        return (
          <Link
            href={href}
            className={col.linkClassName || "text-blue-600 hover:underline"}
            target={col.openInNewTab ? '_blank' : undefined}
          >
            {value}
          </Link>
        );

      default:
        return <span className="text-sm font-medium">{value || '-'}</span>;
    }
  };

  return (
    <div className="flex flex-col gap-3 w-full">
      {/* Bulk Actions Toolbar */}
      {hasBulkActions && selectedRows.length > 0 && (
        <div className="flex items-center justify-between p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-900 rounded-lg animate-in slide-in-from-top-2">
          <div className="text-sm font-medium text-blue-800 dark:text-blue-300">
            {selectedRows.length} {t('items selected')}
          </div>
          <div className="flex items-center gap-2">
            {bulkActions.map((action) => {
              const IconComponent = action.icon ? (LucidIcons as any)[action.icon] : null;
              return (
                <Button
                  key={action.action}
                  variant={action.variant || 'outline'}
                  size="sm"
                  onClick={() => onBulkAction(action.action, selectedRows)}
                >
                  {IconComponent && <IconComponent className="mr-2 h-4 w-4" />}
                  {t(action.label)}
                </Button>
              );
            })}
            <Button variant="ghost" size="sm" onClick={() => setSelectedRows([])}>
              {t('Cancel')}
            </Button>
          </div>
        </div>
      )}

      <div className="border-collapse dark:bg-gray-900 overflow-x-auto w-full rounded-md border">
        <Table className="w-full max-w-full">
          <TableHeader>
            <TableRow className="bg-gray-50 dark:bg-gray-800 border-b">
              {hasBulkActions && (
                <TableHead className="w-12 text-center py-2.5">
                  <Checkbox
                    checked={data.length > 0 && selectedRows.length === data.length}
                    onCheckedChange={toggleAll}
                    aria-label="Select all"
                  />
                </TableHead>
              )}
              <TableHead className="w-10 py-2.5 font-semibold">#</TableHead>
              {columns.map((column) => (
                <TableHead
                  key={column.key}
                  className={cn(
                    "py-2.5 font-semibold",
                    column.sortable && "cursor-pointer select-none",
                    column.className
                  )}
                  onClick={() => handleSort(column)}
                >
                  <div className="flex items-center whitespace-nowrap">
                    {column.label}
                    {renderSortIcon(column)}
                  </div>
                </TableHead>
              ))}
              {hasAnyActionPermission && <TableHead className="w-48 py-2.5 text-right font-semibold">{t('Actions')}</TableHead>}
            </TableRow>
          </TableHeader>
          {onDragEnd ? (
            <DragDropContext onDragEnd={onDragEnd}>
              <Droppable droppableId="crud-table-droppable">
                {(provided) => (
                  <TableBody ref={provided.innerRef} {...provided.droppableProps}>
                    {data.length > 0 ? (
                      data.map((row, index) => (
                        <Draggable key={row.id?.toString() || index.toString()} draggableId={row.id?.toString() || index.toString()} index={index}>
                          {(provided, snapshot) => (
                            <TableRow
                              ref={provided.innerRef}
                              {...provided.draggableProps}
                              className={cn(
                                "hover:bg-gray-50 dark:hover:bg-gray-700 dark:bg-gray-900 border-b",
                                selectedRows.includes(row.id) && "bg-blue-50/50 dark:bg-blue-900/10",
                                snapshot.isDragging && "bg-gray-100 shadow-md ring-1 ring-gray-300 z-50 rounded-md"
                              )}
                              style={{ ...provided.draggableProps.style }}
                            >
                              {hasBulkActions && (
                                <TableCell className="text-center py-2.5">
                                  <Checkbox
                                    checked={selectedRows.includes(row.id)}
                                    onCheckedChange={() => toggleRow(row.id)}
                                    aria-label={`Select row ${row.id}`}
                                  />
                                </TableCell>
                              )}
                              <TableCell className="font-medium py-2.5 w-10">
                                <span {...provided.dragHandleProps} className="cursor-grab hover:text-blue-500 inline-block p-1">
                                  <LucidIcons.GripVertical className="h-4 w-4 mr-2 inline" />
                                </span>
                                {from + index}
                              </TableCell>
                              {columns.map((col) => {
                                const cellContent = renderCellContent(row, col);
                                const rawValue = typeof cellContent === 'string' ? cellContent : undefined;
                                return (
                                  <TableCell
                                    key={col.key}
                                    className={cn("py-2.5", col.className)}
                                    title={rawValue}
                                  >
                                    <div className="break-words whitespace-normal">
                                      {cellContent}
                                    </div>
                                  </TableCell>
                                );
                              })}
                              {hasAnyActionPermission && <TableCell className="py-2.5 text-right w-48">{renderActionButtons(row)}</TableCell>}
                            </TableRow>
                          )}
                        </Draggable>
                      ))
                    ) : (
                      <TableRow>
                        <TableCell colSpan={columns.length + (hasAnyActionPermission ? 2 : 1) + (hasBulkActions ? 1 : 0)} className="text-muted-foreground h-24 text-center dark:text-gray-400">
                          {t('No results found.')}
                        </TableCell>
                      </TableRow>
                    )}
                    {provided.placeholder}
                  </TableBody>
                )}
              </Droppable>
            </DragDropContext>
          ) : (
            <TableBody>
              {data.length > 0 ? (
                data.map((row, index) => (
                  <TableRow
                    key={row.id || index}
                    className={cn(
                      "hover:bg-gray-50 dark:hover:bg-gray-700 dark:bg-gray-900 border-b",
                      selectedRows.includes(row.id) && "bg-blue-50/50 dark:bg-blue-900/10"
                    )}
                  >
                    {hasBulkActions && (
                      <TableCell className="text-center py-2.5">
                        <Checkbox checked={selectedRows.includes(row.id)} onCheckedChange={() => toggleRow(row.id)} aria-label={`Select row ${row.id}`} />
                      </TableCell>
                    )}
                    <TableCell className="font-medium py-2.5 w-10">{from + index}</TableCell>
                    {columns.map((col) => {
                      const cellContent = renderCellContent(row, col);
                      const rawValue = typeof cellContent === 'string' ? cellContent : undefined;
                      return (
                        <TableCell key={col.key} className={cn("py-2.5", col.className)} title={rawValue}>
                          <div className="break-words whitespace-normal">{cellContent}</div>
                        </TableCell>
                      );
                    })}
                    {hasAnyActionPermission && <TableCell className="py-2.5 text-right w-48">{renderActionButtons(row)}</TableCell>}
                  </TableRow>
                ))
              ) : (
                <TableRow>
                  <TableCell colSpan={columns.length + (hasAnyActionPermission ? 2 : 1) + (hasBulkActions ? 1 : 0)} className="text-muted-foreground h-24 text-center dark:text-gray-400">
                    {t('No results found.')}
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          )}
        </Table>
      </div>
    </div>
  );
}