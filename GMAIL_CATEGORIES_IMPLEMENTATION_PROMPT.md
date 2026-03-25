# Gmail Custom Categories Implementation Prompt

## **STRICT INSTRUCTIONS - READ CAREFULLY**

### **Core Requirements**
Implement Gmail category-based synchronization similar to Brevo's feature, allowing users to choose which Gmail categories to sync emails from.

### **MANDATORY CONSTRAINTS**

#### **1. NO OVER-ENGINEERING**
- **DO NOT** create new database tables unless absolutely necessary
- **DO NOT** refactor existing working code
- **DO NOT** implement complex caching mechanisms
- **DO NOT** add unnecessary abstractions or design patterns
- Keep it SIMPLE and MINIMAL

#### **2. ZERO IMPACT ON OTHER MODULES**
- **DO NOT** modify any existing CRM functionality
- **DO NOT** change payment gateway integrations
- **DO NOT** alter user management system
- **DO NOT** touch any other email services
- Only modify Gmail-related components

#### **3. BACKWARD COMPATIBILITY**
- All existing Gmail functionality MUST continue working
- Existing users without category settings should sync ALL categories (current behavior)
- **DO NOT** break existing API endpoints
- **DO NOT** change database column names or types

### **IMPLEMENTATION SCOPE**

#### **Database Changes (MINIMAL)**
1. **Add to `gmail_accounts` table ONLY:**
   ```sql
   sync_categories JSON NULL -- Stores array of categories to sync
   sync_strategy ENUM ['all', 'categories', 'contacts'] DEFAULT 'all'
   ```

2. **Migration Requirements:**
   - Use Laravel migration with proper up/down methods
   - Add indexes if necessary for performance
   - Make columns nullable to maintain backward compatibility

#### **Backend Changes**

##### **1. GmailService.php Modifications**
- **ONLY** modify these existing methods:
  - `listThreads()` - Add category filtering
  - `syncThreads()` - Respect category settings
- **DO NOT** touch other methods
- **DO NOT** change method signatures

##### **2. GmailController.php Updates**
- **ONLY** add new methods:
  - `updateSyncSettings()` - POST endpoint for settings
  - `getSyncSettings()` - GET endpoint for current settings
- **DO NOT** modify existing methods

##### **3. Settings Integration**
- Add category settings to existing integrations settings page
- **DO NOT** create new pages/routes
- Use existing settings infrastructure

#### **Frontend Changes**
- **ONLY** modify: `resources/js/pages/settings/components/integrations-settings.tsx`
- Add category selection UI components
- **DO NOT** create new React components unnecessarily
- Use existing UI library components (Radix UI)

### **GMAIL CATEGORY MAPPING**

#### **Supported Categories**
```javascript
const GMAIL_CATEGORIES = {
    'PRIMARY': 'Primary',
    'SOCIAL': 'Social', 
    'PROMOTIONS': 'Promotions',
    'UPDATES': 'Updates',
    'FORUMS': 'Forums'
}
```

#### **Gmail Query Implementation**
```php
// Example query for category filtering
$categoryQueries = [
    'PRIMARY' => 'category:primary',
    'SOCIAL' => 'category:social',
    'PROMOTIONS' => 'category:promotions',
    'UPDATES' => 'category:updates', 
    'FORUMS' => 'category:forums'
];
```

### **IMPLEMENTATION STEPS**

#### **Step 1: Database Migration**
```bash
php artisan make:migration add_gmail_category_settings_to_gmail_accounts_table
```

#### **Step 2: Model Updates**
- **ONLY** update `GmailAccount.php` model:
  - Add `$fillable` properties for new columns
  - Add casting for JSON column
  - **DO NOT** add relationships or scopes

#### **Step 3: Service Layer**
- **ONLY** modify query building in `GmailService.php`:
  ```php
  // In listThreads() method
  if ($this->account->sync_strategy === 'categories' && !empty($this->account->sync_categories)) {
      $categoryQueries = array_map(fn($cat) => "category:" . strtolower($cat), $this->account->sync_categories);
      $params['q'] = '(' . implode(' OR ', $categoryQueries) . ') AND (in:inbox OR in:sent)';
  }
  ```

#### **Step 4: Controller Endpoints**
- Add settings endpoints with proper validation
- Use existing middleware and permissions
- Return JSON responses consistent with existing API

#### **Step 5: Frontend Integration**
- Add category checkboxes to existing settings form
- Use existing form submission patterns
- Apply existing styling and validation

### **VALIDATION RULES**

#### **Backend Validation**
```php
'sync_strategy' => 'required|in:all,categories,contacts',
'sync_categories' => 'array_if:sync_strategy,categories',
'sync_categories.*' => 'string|in:PRIMARY,SOCIAL,PROMOTIONS,UPDATES,FORUMS'
```

#### **Frontend Validation**
- At least one category must be selected if strategy is 'categories'
- Validate before form submission

### **TESTING REQUIREMENTS**

#### **Unit Tests**
- Test category filtering logic
- Test settings validation
- Test backward compatibility

#### **Integration Tests**
- Test full sync with different category settings
- Test existing functionality still works
- Test settings persistence

#### **Manual Testing**
- Test with real Gmail accounts
- Verify no impact on other CRM features
- Test edge cases (empty categories, invalid settings)

### **PERFORMANCE CONSIDERATIONS**

- **DO NOT** add N+1 queries
- **DO NOT** cache unnecessarily
- Keep Gmail API calls minimal
- Use existing pagination patterns

### **ERROR HANDLING**

- Handle invalid category selections gracefully
- Log category sync errors without breaking sync
- Fallback to 'all' strategy if settings are corrupted

### **SECURITY REQUIREMENTS**

- Sanitize all category inputs
- Use existing authentication/authorization
- **DO NOT** expose Gmail API internals
- Validate all user inputs

### **ROLLBACK PLAN**

#### **Migration Rollback**
```bash
php artisan migrate:rollback --step=1
```

#### **Code Rollback**
- All changes should be contained to specific files
- Easy to identify and revert changes
- No database schema dependencies

### **DELIVERABLES**

1. **Migration file** for database changes
2. **Updated GmailAccount model** with new properties
3. **Modified GmailService** with category filtering
4. **New controller methods** for settings management
5. **Updated frontend settings** component
6. **Tests** covering new functionality
7. **Documentation** of changes made

### **WHAT NOT TO DO**

❌ **DO NOT** modify existing sync logic architecture  
❌ **DO NOT** change webhook handling  
❌ **DO NOT** alter email sending functionality  
❌ **DO NOT** touch other integrations  
❌ **DO NOT** create new service classes  
❌ **DO NOT** implement complex state management  
❌ **DO NOT** change existing API contracts  
❌ **DO NOT** modify user permissions system  

### **SUCCESS CRITERIA**

- ✅ Users can select Gmail categories to sync from
- ✅ Existing Gmail sync functionality unchanged
- ✅ Settings persist correctly
- ✅ No performance degradation
- ✅ All tests pass
- ✅ No impact on other CRM modules
- ✅ Backward compatibility maintained

---

**REMEMBER: Keep it simple, minimal, and focused. Do not over-engineer. The goal is to add category filtering without disrupting the existing robust Gmail integration.**
