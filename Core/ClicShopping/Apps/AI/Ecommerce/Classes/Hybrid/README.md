# E-commerce Hybrid Query Processing

This directory contains e-commerce domain-specific implementations for hybrid query processing.

## Architecture

### Agnostic Layer (Core/AI/)
```
Core/ClicShopping/AI/DomainsAI/Hybrid/Processor/ResultAggregator.php
```
- **Generic aggregation logic** (framework and domain-agnostic)
- Semantic + analytics aggregation
- Default aggregation for unknown combinations
- Source collection and deduplication
- Failed query handling
- **Hooks for domain-specific logic**

### E-commerce Layer (Apps/AI/Ecommerce/)
```
Core/ClicShopping/Apps/AI/Ecommerce/Classes/Hybrid/
├── EcommerceResultAggregator.php
└── EntityDataExtractor.php
```

## Classes

### EcommerceResultAggregator
**Extends**: `ResultAggregator`  
**Purpose**: E-commerce specific result aggregation

**Responsibilities**:
- Product data extraction from analytics results
- Price comparison aggregation (internal vs competitor prices)
- Product display formatting
- Integration with WebSearchTool for price comparison

**Key Methods**:
- `aggregatePriceComparison()` - Aggregate price comparison results
- `extractPriceComparisonData()` - Extract product and web search data
- `performPriceComparison()` - Compare prices using WebSearchTool
- `buildBasicPriceComparison()` - Fallback price comparison
- `formatProductDisplay()` - Format product information for display

### EntityDataExtractor
**Purpose**: Extract entity data from database rows

**Responsibilities**:
- Detect entity type from row keys (products, orders, customers, etc.)
- Extract fields using EntityConfig
- Provide fallback strategies for missing fields
- Cache EntityConfig lookups for performance

**Key Methods**:
- `extractFromRow()` - Extract entity data from database row
- `detectEntityType()` - Detect entity type from row keys
- `extractField()` - Extract field value with fallbacks
- `clearCache()` - Clear EntityConfig cache

## Usage

### Basic Usage
```php
use ClicShopping\Apps\AI\Ecommerce\Classes\Hybrid\EcommerceResultAggregator;

// Create aggregator
$aggregator = new EcommerceResultAggregator($debug = true);

// Aggregate results
$result = $aggregator->process([
    'successful' => $successfulResults,
    'failed' => $failedResults
], $context);
```

### Entity Data Extraction
```php
use ClicShopping\Apps\AI\Ecommerce\Classes\Hybrid\EntityDataExtractor;

// Create extractor
$extractor = new EntityDataExtractor($debug = true);

// Extract from database row
$productData = $extractor->extractFromRow([
    'products_id' => 1,
    'products_name' => 'Test Product',
    'products_price' => 99.99,
    'products_model' => 'TEST-001'
]);

// Result:
// [
//     'entity_id' => 1,
//     'name' => 'Test Product',
//     'price' => 99.99,
//     'model' => 'TEST-001',
//     'entity_type' => 'products',
//     'available_fields' => [...],
//     'description_fields' => [...]
// ]
```

## Integration Points

### With Analytics Agent
```php
// In EcommerceAnalyticsAgent or similar
use ClicShopping\Apps\AI\Ecommerce\Classes\Hybrid\EcommerceResultAggregator;

$aggregator = new EcommerceResultAggregator($this->debug);
$result = $aggregator->process($hybridResults, $context);
```

### With EntityConfig
```php
// EntityDataExtractor automatically uses EntityConfig
// from the active domain (configured via DomainConfig)

// EntityConfig provides:
// - getDescriptionFields($entityType)
// - getEntityTypes()
// - getIdColumn($entityType)
```

## Benefits

### Agnostic Core
- ✅ Core/AI/ is truly framework-agnostic
- ✅ Can be reused for other domains (HR, Finance, etc.)
- ✅ No e-commerce dependencies in core

### Maintainability
- ✅ Clear separation of responsibilities
- ✅ E-commerce code in Apps/AI/Ecommerce/
- ✅ Easier to test and maintain

### Extensibility
- ✅ Easy to add other domains (HRResultAggregator, etc.)
- ✅ Each domain can have its own logic
- ✅ No pollution of core with business logic

### Performance
- ✅ No unnecessary code loaded for other domains
- ✅ Domain-specific caching

## Migration from Old Code

### Before (2025-12-14 to 2026-04-27)
```php
// All code in Core/AI/ (not agnostic)
use ClicShopping\AI\DomainsAI\Hybrid\Processor\ResultAggregator;

$aggregator = new ResultAggregator($debug);
// Had hardcoded e-commerce logic
```

### After (2026-04-28+)
```php
// Use domain-specific class
use ClicShopping\Apps\AI\Ecommerce\Classes\Hybrid\EcommerceResultAggregator;

$aggregator = new EcommerceResultAggregator($debug);
// E-commerce logic in domain layer
```

## Testing

### Unit Tests
```bash
# Test entity data extraction
php unit_test/2026_04_28/test_entity_data_extractor.php

# Test e-commerce aggregation
php unit_test/2026_04_28/test_ecommerce_result_aggregator.php

# Test agnostic core
php unit_test/2026_04_28/test_result_aggregator_agnostic.php
```

## References

- **Architecture**: `Agents/AGENTS.md` - Section "AI Architecture — Multi-Domain, Multi-LLM, Agnostic"
- **Refactoring**: `kiro_documentation/2026_04_28/analysis_result_aggregator_agnosticity.md`
- **Core Class**: `Core/ClicShopping/AI/DomainsAI/Hybrid/Processor/ResultAggregator.php`
