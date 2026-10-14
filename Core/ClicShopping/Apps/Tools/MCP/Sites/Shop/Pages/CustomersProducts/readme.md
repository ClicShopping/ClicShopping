

// Example CURL commands for testing the API endpoints
# TAuthorized actions: products, product, search, stats, categories, recommendations
# Example CURL commands:
# Note: Use a valid Base64 encoded "username:key" (preferred)
#   Example:

Step 1 :
echo -n "USER_NAME:YOUR_KEY" | base64
Step 2 :
curl "http://localhost:3001/clicshopping_test/index.php?mcp&customersProducts&action=products&limit=5" \
-H "Authorization: Basic BASE64_USERNAME_COLON_KEY"
curl -i -X GET "http://localhost/clicshopping_test/index.php?mcp&customersProducts&action=product&id=5" \
-H "Authorization: Basic BASE64_USERNAME_COLON_KEY"
curl "http://localhost:3001/clicshopping_test/index.php?mcp&customersProducts&action=search&query=lavette" \
-H "Authorization: Basic BASE64_USERNAME_COLON_KEY"
curl "http://localhost:3001/clicshopping_test/index.php?mcp&customersProducts&action=stats" \
-H "Authorization: Basic BASE64_USERNAME_COLON_KEY"
curl "http://localhost:3001/clicshopping_test/index.php?mcp&customersProducts&action=categories" \
-H "Authorization: Basic BASE64_USERNAME_COLON_KEY"
curl "http://localhost:3001/clicshopping_test/index.php?mcp&customersProducts&action=recommendations" \
-H "Authorization: Basic BASE64_USERNAME_COLON_KEY"

# Legacy fallback still supported: raw key in Basic header (not recommended)
# curl "http://localhost/clicshopping_test/index.php?mcp&customersProducts&action=products&limit=5" \
#   -H "Authorization: Basic YOUR_RAW_KEY"
*/