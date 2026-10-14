# Patterns Directory - Shop Context

## Purpose

This directory is reserved for **pattern classes** for the Shop context (future catalog automations).

## Important Notes

⚠️ **FUTURE USE**: This directory is prepared for future Shop context implementation (Task 5.3 and Phase 8).

The Shop context will be used for:
- Public-facing catalog automations
- Product recommendations
- Dynamic pricing
- SEO optimization
- Personalization

## Shop vs Admin Context

### Shop Context (This Directory - Future)
- **Operations**: SELECT only (read-only)
- **Max Results**: 100 (more restrictive)
- **Forbidden Tables**: System + customer sensitive data
- **Use Case**: Public-facing catalog automations
- **Security**: More restrictive than Admin

### Admin Context (ClicShoppingAdmin/Patterns/)
- **Operations**: SELECT, INSERT, UPDATE
- **Max Results**: 10,000
- **Forbidden Tables**: System tables only
- **Use Case**: Internal admin analysis
- **Security**: More permissive than Shop

## Pattern Classes (Future)

When Shop patterns are needed, they should be organized by category:

### Catalog Automation Patterns
- `ProductRecommendationPattern.php` - Product recommendation patterns
- `DynamicPricingPattern.php` - Dynamic pricing patterns
- `SEOOptimizationPattern.php` - SEO optimization patterns
- `PersonalizationPattern.php` - Personalization patterns

### Customer Behavior Patterns
- `BrowsingBehaviorPattern.php` - Browsing behavior patterns
- `PurchaseIntentPattern.php` - Purchase intent patterns
- `AbandonmentPattern.php` - Cart abandonment patterns

### Content Generation Patterns
- `ProductDescriptionPattern.php` - Product description generation
- `MetaTagPattern.php` - Meta tag generation
- `SchemaMarkupPattern.php` - Schema.org markup generation

## Current Status

🚧 **NOT IMPLEMENTED YET**

This directory is prepared for future implementation in:
- **Task 5.3**: Create GuardrailsConfig class for Shop (optional)
- **Phase 8**: Shop Catalog Automations (future)

## Migration from Existing Features

Existing features to be integrated:
- SEO hooks from `Apps/Marketing/SEO/Module/Hooks/`
- Dynamic pricing features
- Product recommendations
- Personalization engine

## See Also

- `Core/ClicShopping/Apps/AI/Ecommerce/Classes/Shop/GuardrailsConfig.php` - Shop guardrails (future)
- `Core/ClicShopping/Apps/AI/Ecommerce/Classes/ClicShoppingAdmin/` - Admin context (current)
- `.kiro/specs/active/rag-multi-domain-evolution/tasks.md` - Implementation plan
