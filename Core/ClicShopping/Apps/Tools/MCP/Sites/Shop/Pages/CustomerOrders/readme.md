# MCP endpoint — customerOrders

Authorized actions: list_orders, read_order, history (read) — cancel_order, send_message (write, POST).
Every action is scoped to `customer_id`: an order that does not belong to that customer returns
"Order not found or access denied." The `customer_id` is supplied by the authenticated MCP client
(trusted CRM/ERP backend).

## Authentication

Basic header (`username:key`, Base64) or `X-MCP-User` + `X-MCP-Key` headers, then re-use the returned
`X-MCP-Token`. If Apache does not forward `Authorization`, use the `X-MCP-*` headers.

```
echo -n "USER_NAME:YOUR_KEY" | base64
```

## Examples

```
curl "http://localhost/clicshopping_test/index.php?mcp&customerOrders&action=list_orders&customer_id=CID&limit=10&offset=0" \
  -H "Authorization: Basic BASE64_USERNAME_COLON_KEY"

curl "http://localhost/clicshopping_test/index.php?mcp&customerOrders&action=read_order&order_id=OID&customer_id=CID" \
  -H "X-MCP-User: USER_NAME" -H "X-MCP-Key: YOUR_KEY"

curl "http://localhost/clicshopping_test/index.php?mcp&customerOrders&action=history&order_id=OID&customer_id=CID" \
  -H "X-MCP-User: USER_NAME" -H "X-MCP-Key: YOUR_KEY"

curl -X POST "http://localhost/clicshopping_test/index.php?mcp&customerOrders&action=cancel_order" \
  -H "X-MCP-User: USER_NAME" -H "X-MCP-Key: YOUR_KEY" \
  -d "order_id=OID&customer_id=CID"

curl -X POST "http://localhost/clicshopping_test/index.php?mcp&customerOrders&action=send_message" \
  -H "X-MCP-User: USER_NAME" -H "X-MCP-Key: YOUR_KEY" \
  -d "order_id=OID&customer_id=CID&message=Hello"
```
