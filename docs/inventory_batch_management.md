# Inventory Batch Management System

## Overview

The inventory system has been improved to properly handle batch management with different expiry dates, costs, and suppliers using FIFO (First In, First Out) removal logic.

## Key Improvements

### 1. Enhanced Batch Separation Logic
- **Different expiry dates = Different batches**: When adding inventory with a different expiry date, the system creates a new batch
- **Same expiry date + Same cost + Same supplier = Same batch**: When adding inventory with identical parameters, it adds to existing batch
- **Same expiry date + Different cost = Different batches**: Cost differences >5% create new batches
- **Same expiry date + Different supplier = Different batches**: Different suppliers create new batches
- **Unique batch numbers**: Each batch gets a unique identifier (e.g., MIL202504-001, MIL202504-002)

### 2. FIFO Removal Logic
- **Oldest first**: When removing inventory, the system removes from the oldest batch first (earliest expiry date)
- **Multiple batches**: If removing more than available in one batch, it continues with the next oldest batch
- **Batch tracking**: Shows which batches were used for removal

### 3. Enhanced Add Inventory Process

#### When Adding Inventory:
1. **Check expiry date**: Compare with existing batch expiry date
2. **Check cost difference**: If cost differs by >5%, create new batch
3. **Check supplier**: If supplier is different, create new batch
4. **Same parameters**: Add to existing batch
5. **Different parameters**: Create new batch with new batch number
6. **Generate batch number**: Format: ING[First 3 letters]-YYYYMM-[Sequence]

#### Example Scenarios:
- **Scenario 1**: Same expiry, same cost, same supplier → **Same batch**
  - Batch 1: Milk 20L, Expiry: 2025-04-17, Cost: ₹50/L, Supplier: ABC Dairy
  - Add 10L: Same parameters → Update Batch 1 to 30L

- **Scenario 2**: Same expiry, different cost → **New batch**
  - Batch 1: Milk 20L, Expiry: 2025-04-17, Cost: ₹50/L
  - Add 15L: Same expiry, Cost: ₹60/L → Create Batch 2: MIL202504-002

- **Scenario 3**: Same expiry, different supplier → **New batch**
  - Batch 1: Milk 20L, Expiry: 2025-04-17, Supplier: ABC Dairy
  - Add 15L: Same expiry, Supplier: XYZ Dairy → Create Batch 2: MIL202504-002

### 4. Smart Remove Inventory Process

#### When Removing Inventory:
1. **Find available batches**: Get all active batches with available quantity
2. **Sort by expiry date**: Oldest first (FIFO principle)
3. **Remove from oldest**: Start with the batch that expires first
4. **Continue if needed**: If more quantity needed, move to next oldest batch
5. **Track batches used**: Show which batches were used for removal

#### Example:
- **Batch 1**: Milk 30L, Expiry: 2025-04-17, Cost: ₹50/L
- **Batch 2**: Milk 15L, Expiry: 2025-04-25, Cost: ₹60/L
- **Remove 40L**: 
  - Remove 30L from Batch 1 (oldest)
  - Remove 10L from Batch 2 (next oldest)
  - Result: Batch 1 empty, Batch 2 has 5L remaining

## User Interface Changes

### Adjust Quantity Modal
- **Additional fields for "Add"**:
  - Expiry Date: Set new date to create new batch, leave empty to use existing
  - Cost per Unit: Optional cost tracking for new batches
  - Supplier: Optional supplier information (different supplier = new batch)
- **Dynamic fields**: Additional fields only show when "Add" is selected
- **Smart logic**: System automatically determines if new batch is needed

### Success Messages
- **New batch created**: "New batch created successfully! Batch number: MIL202504-002 Supplier: ABC Dairy"
- **Existing batch updated**: "Inventory quantity added to existing batch successfully! Supplier: ABC Dairy"
- **Removal completed**: "Inventory removed successfully from batches: MIL202504-001, MIL202504-002"

## Database Structure

### Inventory Table
```sql
CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `current_quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reserved_quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `available_quantity` decimal(10,2) GENERATED ALWAYS AS (`current_quantity` - `reserved_quantity`) STORED,
  `batch_number` varchar(50) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `batch_sequence` int(11) DEFAULT NULL,
  `status` enum('active','expired','quarantine','hidden') DEFAULT 'active'
);
```

### Inventory History Table
```sql
CREATE TABLE `inventory_history` (
  `id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `previous_quantity` decimal(10,2) NOT NULL,
  `new_quantity` decimal(10,2) NOT NULL,
  `change_type` enum('add','update','remove','expired','damaged','prep_used') NOT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `cost_per_unit` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `changed_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
);
```

## Benefits

### 1. Food Safety & Compliance
- **Proper expiry tracking**: Each batch has its own expiry date
- **FIFO compliance**: Ensures older inventory is used first
- **Batch traceability**: Complete history of each batch
- **Supplier tracking**: Track different suppliers separately

### 2. Financial Accuracy
- **Cost separation**: Different cost batches are kept separate
- **Accurate costing**: Each batch maintains its own cost per unit
- **Profit tracking**: Track profitability by batch
- **Supplier analysis**: Compare costs across suppliers

### 3. Inventory Accuracy
- **No mixed batches**: Different parameters are properly separated
- **Accurate quantities**: Each batch maintains its own quantity
- **Proper alerts**: Low stock and expiry alerts work correctly
- **Clean interface**: Empty batches are hidden

### 4. Business Intelligence
- **Cost analysis**: Track cost variations over time
- **Supplier performance**: Compare supplier costs and quality
- **Usage patterns**: See which batches are used first
- **Wastage analysis**: Track expired batches separately

## Usage Examples

### Scenario 1: Same Parameters (Same Batch)
1. Add 20L milk, expiry: 2025-04-17, cost: ₹50/L, supplier: ABC → Creates Batch MIL202504-001
2. Add 10L milk, expiry: 2025-04-17, cost: ₹50/L, supplier: ABC → Updates Batch MIL202504-001 to 30L

### Scenario 2: Different Cost (New Batch)
1. Add 20L milk, expiry: 2025-04-17, cost: ₹50/L → Creates Batch MIL202504-001
2. Add 15L milk, expiry: 2025-04-17, cost: ₹60/L → Creates Batch MIL202504-002 (cost >5% different)

### Scenario 3: Different Supplier (New Batch)
1. Add 20L milk, expiry: 2025-04-17, supplier: ABC Dairy → Creates Batch MIL202504-001
2. Add 15L milk, expiry: 2025-04-17, supplier: XYZ Dairy → Creates Batch MIL202504-002

### Scenario 4: FIFO Removal
1. Remove 25L milk → Removes 20L from Batch 1, 5L from Batch 2
2. Remove 20L milk → Removes remaining 10L from Batch 2, 10L from Batch 3

## Technical Implementation

### Key Functions
- `process_adjust_quantity.php`: Handles both add and remove operations with enhanced logic
- `process_add_inventory.php`: Creates new inventory records
- `check_inventory_alerts.php`: Generates alerts based on batch status
- `cleanup_inventory.php`: Handles expired and empty batch cleanup

### Batch Uniqueness Logic
```php
// Check expiry date
if ($new_expiry_date !== $existing_expiry_date) {
    create_new_batch();
}

// Check cost difference (>5% threshold)
if (abs($new_cost - $existing_cost) / $existing_cost > 0.05) {
    create_new_batch();
}

// Check supplier difference
if ($new_supplier !== $existing_supplier) {
    create_new_batch();
}
```

### FIFO Removal Logic
```php
// Get batches ordered by expiry date (oldest first)
$stmt = $conn->prepare("
    SELECT * FROM inventory 
    WHERE ingredient_id = ? AND vendor_id = ? AND status = 'active' 
    ORDER BY expiry_date ASC, id ASC
");
```

## Cost Threshold Configuration

The system uses a **5% cost difference threshold** to determine if a new batch should be created. This can be adjusted based on business needs:

- **Lower threshold (e.g., 2%)**: More sensitive to cost changes, creates more batches
- **Higher threshold (e.g., 10%)**: Less sensitive to cost changes, fewer batches

This improved system ensures proper batch management, cost tracking, supplier management, food safety compliance, and accurate inventory tracking for canteen operations. 