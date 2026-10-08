/*
// Example CURL command for testing the RAG-BI API endpoint
// Note: Use a valid Base64 encoded "username:key"
//   Example: echo -n "RagBI:YOUR_KEY" | base64
curl "http://localhost/clicshopping_test/index.php?mcp&ChatRagBI&action=analyze" \
-H "Authorization: Basic Token" \
-H "Content-Type: application/json" \
-d '{"message":"liste les produits","queryType":"analytics"}'
*/

Authorized values for queryType:
query
analyze
search
report


example
curl -X POST "http://localhost/clicshopping_test/index.php?mcp&ChatRagBI&action=query"   -H "Authorization: Basic UmFnQkk6ZDBhMzZiODM5NzAwYjYwNzI3ZmUxMzk5OGUyMmFhMGFmMTk3YzYxZDhiMzcxZTI2MTE0YzEzM2NhNTFjNDg2NGJkMGRhNzNhZDZkMWU1MDkwYjAyYjU1Y2ZmNDJiOGEwY2QyMzg2NmU2NGU3OGZjODg4NGViNjIyOGQzMmY1ZTlkNzZiZWQ0Njg4NjlkZDg5ZWU2YmI4YTMyMDhjNTA3N2U4ODU2MGQwYmMyMzhmNjdjZmM3MzJlZmNmNTMxM2EwY2IzNjFlMjk3YzI5YzhkODJkMDUwZDc3MGVkN2RlZTk3MmFmNjQ0NWU4MDFmYTlhZjEyZTNkNDc4YmY1MzQ2YQ=="   -H "Content-Type: application/json"   -d '{"message":"liste les produits","queryType":"analytics"}'

To use LmStudio, use the port 41343 - localhost:41343 as server
