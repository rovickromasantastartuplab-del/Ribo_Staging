# Gmail Custom Categories Implementation Prompt
## **BASED ON ACTUAL CODEBASE ANALYSIS**

### **SENIOR DEVELOPER INSTRUCTIONS**
You are a **10+ year senior Laravel/React developer** with extensive experience in:
- Large-scale CRM systems and email integrations
- Gmail API implementation and optimization
- Database schema design and migrations
- React/Inertia.js frontend architecture
- Production deployment and maintenance

**Your approach should be:**
- **Methodical**: Understand existing patterns before modifying
- **Conservative**: Make minimal, targeted changes
- **Quality-focused**: Write production-ready, maintainable code
- **Performance-aware**: Consider API limits and database efficiency
- **Security-conscious**: Validate all inputs and handle edge cases

### **SYSTEM OVERVIEW**
Your system uses:
- **Laravel 12** + **React** with **Inertia.js**
- **Radix UI** components for frontend
- **@inertiajs/react useForm** for form handling
- **GmailService.php** for Gmail API operations
- **IntegrationsSettingsController** for settings management
- **Existing Gmail integration** with threads/messages

### **STRICT IMPLEMENTATION REQUIREMENTS**

#### **1. DATABASE CHANGES ONLY**
Create a single migration file to add columns to `gmail_accounts` table:

```bash
php artisan make:migration add_gmail_sync_categories_to_gmail_accounts_table
```

**Migration Content:**
```php
Schema::table('gmail_accounts', function (Blueprint $table) {
    $table->json('sync_categories')->nullable()->after('sync_error'); // Stores array of categories
    $table->enum('sync_strategy', ['all', 'categories', 'contacts'])->default('all')->after('sync_categories');
});
```

#### **2. MODEL UPDATES - GmailAccount.php ONLY**
Add to `$fillable` array:
```php
'sync_categories',
'sync_strategy',
```

Add to `$casts` array:
```php
'sync_categories' => 'array',
```

#### **3. SERVICE MODIFICATIONS - GmailService.php ONLY**

**Modify ONLY the `listThreads()` method:**
```php
public function listThreads(int $maxResults = 50, ?string $pageToken = null): array
{
    $params = [
        'maxResults' => $maxResults,
    ];

    // Build query based on sync strategy
    $baseQuery = 'in:inbox OR in:sent';
    
    if ($this->account->sync_strategy === 'categories' && !empty($this->account->sync_categories)) {
        $categoryQueries = array_map(function($category) {
            return 'category:' . strtolower($category);
        }, $this->account->sync_categories);
        
        $params['q'] = '(' . implode(' OR ', $categoryQueries) . ') AND (' . $baseQuery . ')';
    } else {
        $params['q'] = $baseQuery;
    }

    if ($pageToken) {
        $params['pageToken'] = $pageToken;
    }

    $response = $this->gmail->users_threads->listUsersThreads('me', $params);

    return [
        'threads' => $response->getThreads() ?? [],
        'nextPageToken' => $response->getNextPageToken(),
        'resultSizeEstimate' => $response->getResultSizeEstimate(),
    ];
}
```

#### **4. CONTROLLER UPDATES - IntegrationsSettingsController.php ONLY**

**Add validation rules to `update()` method:**
```php
'gmail_sync_strategy' => 'nullable|in:all,categories,contacts',
'gmail_sync_categories' => 'array_if:gmail_sync_strategy,categories',
'gmail_sync_categories.*' => 'string|in:PRIMARY,SOCIAL,PROMOTIONS,UPDATES,FORUMS',
```

**Add to validation processing:**
```php
// Handle Gmail sync settings
if (isset($validated['gmail_sync_strategy'])) {
    updateSetting('gmail_sync_strategy', $validated['gmail_sync_strategy']);
    unset($validated['gmail_sync_strategy']);
}

if (isset($validated['gmail_sync_categories'])) {
    updateSetting('gmail_sync_categories', json_encode($validated['gmail_sync_categories']));
    unset($validated['gmail_sync_categories']);
}
```

#### **5. FRONTEND UPDATES - integrations-settings.tsx ONLY**

**Add to interfaces:**
```typescript
interface GmailAccount {
    id: number;
    gmail_address: string;
    last_sync_at: string | null;
    sync_status: string;
    sync_error: string | null;
    sync_strategy?: string;
    sync_categories?: string[];
}
```

**Add to useForm data:**
```typescript
const { data, setData, post, processing } = useForm({
    // ... existing fields
    gmail_sync_strategy: gmailAccount?.sync_strategy || 'all',
    gmail_sync_categories: gmailAccount?.sync_categories || [],
});
```

**Add Gmail category constants:**
```typescript
const GMAIL_CATEGORIES = [
    { value: 'PRIMARY', label: 'Primary' },
    { value: 'SOCIAL', label: 'Social' },
    { value: 'PROMOTIONS', label: 'Promotions' },
    { value: 'UPDATES', label: 'Updates' },
    { value: 'FORUMS', label: 'Forums' },
];
```

**Add category selection component INSIDE Gmail section (after line 300):**
```typescript
{gmailAccount && (
    <div className="mt-4 pt-4 border-t space-y-4">
        <div>
            <h4 className="text-sm font-medium mb-2">Sync Settings</h4>
            <div className="space-y-3">
                <div className="flex items-center space-x-2">
                    <input
                        type="radio"
                        id="sync_all"
                        name="sync_strategy"
                        value="all"
                        checked={data.gmail_sync_strategy === 'all'}
                        onChange={(e) => setData('gmail_sync_strategy', e.target.value)}
                        className="h-4 w-4 text-primary"
                    />
                    <Label htmlFor="sync_all" className="text-sm">All new emails</Label>
                </div>
                
                <div className="flex items-center space-x-2">
                    <input
                        type="radio"
                        id="sync_categories"
                        name="sync_strategy"
                        value="categories"
                        checked={data.gmail_sync_strategy === 'categories'}
                        onChange={(e) => setData('gmail_sync_strategy', e.target.value)}
                        className="h-4 w-4 text-primary"
                    />
                    <Label htmlFor="sync_categories" className="text-sm">From selected categories</Label>
                </div>
                
                {data.gmail_sync_strategy === 'categories' && (
                    <div className="ml-6 space-y-2">
                        <p className="text-xs text-muted-foreground">Select Gmail categories to sync:</p>
                        <div className="grid grid-cols-2 gap-2">
                            {GMAIL_CATEGORIES.map((category) => (
                                <div key={category.value} className="flex items-center space-x-2">
                                    <Checkbox
                                        id={`category_${category.value}`}
                                        checked={data.gmail_sync_categories?.includes(category.value) || false}
                                        onCheckedChange={(checked) => {
                                            const current = data.gmail_sync_categories || [];
                                            if (checked) {
                                                setData('gmail_sync_categories', [...current, category.value]);
                                            } else {
                                                setData('gmail_sync_categories', current.filter((c: string) => c !== category.value));
                                            }
                                        }}
                                    />
                                    <Label htmlFor={`category_${category.value}`} className="text-sm">
                                        {category.label}
                                    </Label>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </div>
    </div>
)}
```

#### **6. BACKEND SETTINGS HANDLING**

**Add Gmail account loading in SettingsController.php index() method:**
```php
// Load Gmail account for category settings
$gmailAccount = GmailAccount::where('user_id', $user->creatorId())->first();

return Inertia::render('settings', [
    // ... existing data
    'gmailAccount' => $gmailAccount ? [
        'id' => $gmailAccount->id,
        'gmail_address' => $gmailAccount->gmail_address,
        'sync_strategy' => $gmailAccount->sync_strategy,
        'sync_categories' => $gmailAccount->sync_categories,
    ] : null,
]);
```

**Add Gmail settings update route in routes/settings.php:**
```php
Route::post('settings/gmail/categories', [GmailController::class, 'updateSyncSettings'])->name('settings.gmail.categories.update');
```

**Add updateSyncSettings method to GmailController.php:**
```php
public function updateSyncSettings(Request $request)
{
    $user = auth()->user();
    $companyId = $user->creatorId();

    $gmailAccount = GmailAccount::where('user_id', $companyId)->first();

    if (!$gmailAccount) {
        return redirect()->back()->with('error', 'No Gmail account connected.');
    }

    $validated = $request->validate([
        'sync_strategy' => 'required|in:all,categories,contacts',
        'sync_categories' => 'array_if:sync_strategy,categories',
        'sync_categories.*' => 'string|in:PRIMARY,SOCIAL,PROMOTIONS,UPDATES,FORUMS',
    ]);

    $gmailAccount->update([
        'sync_strategy' => $validated['sync_strategy'],
        'sync_categories' => $validated['sync_categories'] ?? null,
    ]);

    return redirect()->back()->with('success', 'Gmail sync settings updated successfully.');
}
```

### **IMPLEMENTATION SEQUENCE**

1. **Create migration** and run it
2. **Update GmailAccount model** with new fillable/casts
3. **Add GmailController updateSyncSettings method**
4. **Add route** in routes/settings.php
5. **Update SettingsController** to pass Gmail account data
6. **Modify GmailService listThreads method**
7. **Update integrations-settings.tsx** frontend
8. **Update IntegrationsSettingsController** validation

### **TESTING REQUIREMENTS**

1. **Migration works** and rolls back cleanly
2. **Existing Gmail functionality** unchanged when strategy is 'all'
3. **Category filtering** works when strategy is 'categories'
4. **Frontend form** validates and saves correctly
5. **Settings persist** after page refresh

### **CRITICAL CONSTRAINTS**

- **DO NOT modify** any other service methods
- **DO NOT change** existing database schema except adding 2 columns
- **DO NOT break** existing Gmail sync functionality
- **DO NOT modify** any other controllers
- **DO NOT create** new components - use existing UI components
- **DO NOT change** existing form handling patterns

### **EXPECTED BEHAVIOR**

- **Default**: All emails sync (current behavior)
- **Categories**: Only emails from selected Gmail categories sync
- **Contacts**: Future enhancement (not implemented now)

This implementation adds category filtering while maintaining full backward compatibility and using your existing architecture patterns.
