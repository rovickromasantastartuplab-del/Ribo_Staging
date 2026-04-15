const fs = require('fs');
const path = require('path');

const dirPath = path.join(__dirname, 'resources', 'js', 'pages');

function processFile(filepath) {
    let content = fs.readFileSync(filepath, 'utf8');
    let modified = false;

    // Detect if we already have isBulkDeleteModalOpen
    if (content.includes('isBulkDeleteModalOpen')) {
        console.log(`Skipping (already has bulk modal): ${filepath}`);
        return false;
    }

    // Determine the entity route name from the file path
    // e.g., resources/js/pages/accounts/index.tsx -> 'accounts'
    const parts = filepath.split(path.sep);
    const entityDir = parts[parts.length - 2];
    const routePrefix = entityDir;

    // 1. Inject State Variables
    const stateRegex = /const \[isDeleteModalOpen, setIsDeleteModalOpen\] = useState\(false\);/;
    if (stateRegex.test(content)) {
        content = content.replace(
            stateRegex,
            `const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);\n  const [isBulkDeleteModalOpen, setIsBulkDeleteModalOpen] = useState(false);\n  const [selectedIdsForBulkDelete, setSelectedIdsForBulkDelete] = useState<any[]>([]);`
        );
        modified = true;
    } else {
        console.log(`Failed to find state injection point in: ${filepath}`);
        return false;
    }

    // 2. Update handleBulkAction
    // We need to replace the `if (confirm(...))` block with our new state update
    const handleBulkRegex = /const handleBulkAction = \(action: string, selectedIds: any\[\]\) => {([\s\S]*?)};\n\n {2}const handleDeleteConfirm/m;
    const match = content.match(handleBulkRegex);
    
    if (match) {
        let bulkActionBody = match[1];
        
        // We want to replace everything after `if (!hasPermission(...) { return; }` up to the end of the action block.
        // Or simply replace the whole function body using a safer string replacement
        const newBulkActionBody = `\n    if (action === 'bulk_delete') {\n      if (!hasPermission(permissions, 'delete-${routePrefix}')) {\n        toast.error(t('Permission denied.'));\n        return;\n      }\n\n      setSelectedIdsForBulkDelete(selectedIds);\n      setIsBulkDeleteModalOpen(true);\n    }\n  `;

        const bulkFunctionReplacement = `const handleBulkAction = (action: string, selectedIds: any[]) => {${newBulkActionBody}};\n\n  const handleBulkDeleteConfirm = () => {\n    setIsBulkDeleteModalOpen(false);\n    toast.loading(t('Deleting records...'));\n\n    router.delete(route('${routePrefix}.bulk-delete'), {\n      data: { ids: selectedIdsForBulkDelete },\n      onSuccess: (page: any) => {\n        toast.dismiss();\n        if (page.props.flash?.success) {\n          toast.success(t(page.props.flash.success));\n        } else if (page.props.flash?.error) {\n          toast.error(t(page.props.flash.error));\n        }\n      },\n      onError: () => {\n        toast.dismiss();\n        toast.error(t('Failed to delete records.'));\n      }\n    });\n  };\n\n  const handleDeleteConfirm`;
        
        content = content.replace(handleBulkRegex, bulkFunctionReplacement);
    } else {
        console.log(`Failed to find handleBulkAction block in: ${filepath}`);
        return false;
    }

    // 3. Inject the UI Component
    // Look for the Delete Modal block
    // It looks like:
    // <CrudDeleteModal
    //   isOpen={isDeleteModalOpen}
    //   ...
    //   entityName={t('something')}
    // />
    
    // We will find the closing tag `/>` after `isOpen={isDeleteModalOpen}`
    const deleteModalStartIdx = content.indexOf('isOpen={isDeleteModalOpen}');
    if (deleteModalStartIdx !== -1) {
        const deleteModalParams = content.substring(deleteModalStartIdx, deleteModalStartIdx + 300);
        
        // Extract the entity name string for translations
        let entitySingular = routePrefix;
        const entityNameMatch = deleteModalParams.match(/entityName=\{t\('([^']+)'\)\}/);
        if (entityNameMatch) {
            entitySingular = entityNameMatch[1];
        }

        // Just blindly inject it after the Delete Modal
        const deleteModalFullTextMatch = content.match(/<CrudDeleteModal\s+isOpen={isDeleteModalOpen}[\s\S]*?\/>/);
        
        if (deleteModalFullTextMatch) {
            let entityPlural = routePrefix.replace(/-/g, ' ');

            const bulkDeleteModalJSX = `\n\n      <CrudDeleteModal\n        isOpen={isBulkDeleteModalOpen}\n        onClose={() => setIsBulkDeleteModalOpen(false)}\n        onConfirm={handleBulkDeleteConfirm}\n        itemName={t('the selected {{count}} records', { count: selectedIdsForBulkDelete.length })}\n        entityName={t('${entityPlural}')}\n      />`;
            
            content = content.replace(deleteModalFullTextMatch[0], deleteModalFullTextMatch[0] + bulkDeleteModalJSX);
        } else {
            console.log(`Failed to find Delete Modal JSX in: ${filepath}`);
            return false;
        }
    } else {
        console.log(`Failed to find isDeleteModalOpen usage in: ${filepath}`);
        return false;
    }

    if (modified) {
        fs.writeFileSync(filepath, content, 'utf8');
        return true;
    }
    return false;
}

let count = 0;

function walkDir(dir) {
    const files = fs.readdirSync(dir);
    for (const file of files) {
        const filepath = path.join(dir, file);
        const stat = fs.statSync(filepath);
        if (stat.isDirectory()) {
            walkDir(filepath);
        } else if (file === 'index.tsx') {
            if (processFile(filepath)) {
                console.log(`Successfully applied bulk delete UI to ${filepath}`);
                count++;
            }
        }
    }
}

walkDir(dirPath);
console.log(`Total files updated: ${count}`);
