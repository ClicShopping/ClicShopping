




## Permissions


| Permission                | État | Raison                                      |
| ------------------------- | ---- | ------------------------------------------- |
| Sélectionner              | ON   | Lecture produits, commandes, sessions       |
| Mettre à jour les données | ON   | Modification sessions, statut commandes     |
| Créer les données         | ON   | Création sessions, clients                  |
| Supprimer les données     | OFF  | Non nécessaire, réduction surface d’attaque |

---

## Création de compte (test)

```text
userId: AnthropicEcommerce
```

```text
Token:
ROnbW2pB3XvEiei2J5mnGtKJ1pPPK4l2u3Rf1lj020SyBfO94kt9JX5UTkioPovLI3mAhXtFBSPRU3riqHhWzZKxipQ5YjPHvqd9LR6ptggyc3kdZ6pxnYqhvRrEbqXsgpgFvkV5Pk2tjK9OQfjYYSMCS0jeqcU2tK3ZRdEKO2XeFUhjWNUVTzry6OiBy8UzfvTSLilJNlPSP0yRhIsOtGLFhfnro5HKYIE4qwpIDkXWcF3U4knUWStN6RHDI9ZE
```

Permissions appliquées selon la matrice ci-dessus.

---

## Base CLI réutilisable

```bash
BASE="http://localhost/clicshopping_test/index.php?mcp&AnthropicEcommerce"
AUTH="user_name=AnthropicEcommerce&key=ROnbW2pB3XvEiei2J5mnGtKJ1pPPK4l2u3Rf1lj020SyBfO94kt9JX5UTkioPovLI3mAhXtFBSPRU3riqHhWzZKxipQ5YjPHvqd9LR6ptggyc3kdZ6pxnYqhvRrEbqXsgpgFvkV5Pk2tjK9OQfjYYSMCS0jeqcU2tK3ZRdEKO2XeFUhjWNUVTzry6OiBy8UzfvTSLilJNlPSP0yRhIsOtGLFhfnro5HKYIE4qwpIDkXWcF3U4knUWStN6RHDI9ZE"
```

---

## Tests API (curl)

### Stats

```bash
curl -s "$BASE&action=stats&$AUTH"
```

### Produits (limite 5)

```bash
curl -s "$BASE&action=products&limit=5&$AUTH"
```

### Produit id=1

```bash
curl -s "$BASE&action=product&id=1&$AUTH"
```

### Recherche

```bash
curl -s "$BASE&action=search&query=shirt&$AUTH"
```

### Catégories

```bash
curl -s "$BASE&action=categories&$AUTH"
```

### Recommandations

```bash
curl -s "$BASE&action=recommendations&$AUTH"
```

---

## Exemple URL complète

```text
http://localhost/clicshopping_test/index.php?mcp&AnthropicEcommerce&user_name=AnthropicEcommerce&key=ROnbW2pB3XvEiei2J5mnGtKJ1pPPK4l2u3Rf1lj020SyBfO94kt9JX5UTkioPovLI3mAhXtFBSPRU3riqHhWzZKxipQ5YjPHvqd9LR6ptggyc3kdZ6pxnYqhvRrEbqXsgpgFvkV5Pk2tjK9OQfjYYSMCS0jeqcU2tK3ZRdEKO2XeFUhjWNUVTzry6OiBy8UzfvTSLilJNlPSP0yRhIsOtGLFhfnro5HKYIE4qwpIDkXWcF3U4knUWStN6RHDI9ZE
```

---

## MCP Inspector

### Installation

```bash
npx @modelcontextprotocol/inspector
```

Accès :

```text
http://localhost:5173
```

---

## Configuration SSE (endpoint HTTP existant)

| Champ     | Valeur                                                                                                                                                                                                            |
| --------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Transport | SSE                                                                                                                                                                                                               |
| URL       | [http://localhost/clicshopping_test/index.php?mcp&AnthropicEcommerce&user_name=AnthropicEcommerce&key=](http://localhost/clicshopping_test/index.php?mcp&AnthropicEcommerce&user_name=AnthropicEcommerce&key=)... |

### Limite structurelle

MCP Inspector attend du **JSON-RPC 2.0** (`initialize`, `tools/list`, `tools/call`).
Le backend actuel est en **REST action-based** (`action=products`, `action=search`, etc.).

Conclusion :

* SSE seul insuffisant sans adaptateur
* incompatibilité protocolaire directe

---

## Option fonctionnelle

### STDIO bridge (recommandé pour MCP Inspector)

* MCP Inspector ↔ JSON-RPC STDIO
* Script PHP pont ↔ REST ClicShopping

Configuration :

| Champ     | Valeur              |
| --------- | ------------------- |
| Transport | STDIO               |
| Command   | php                 |
| Arguments | /tmp/mcp_bridge.php |

---

## Comportement attendu

Le bridge expose les tools MCP suivants :

* get_stats
* get_products
* get_product
* search_products
* get_categories
* get_orders
* get_order
* create_session
* get_session
* complete_session
* cancel_session

Chaque tool :

* traduit vers `action=...`
* exécute HTTP GET/POST
* renvoie résultat encapsulé JSON-RPC

---

## Point critique

Sans bridge :

* MCP Inspector ne peut pas exécuter les tools
* seulement inspection réseau possible

Avec bridge :

* compatibilité complète MCP tools/call
* test interactif des endpoints ClicShopping




/*
// Example CURL commands for testing the AnthropicEcommerce MCP endpoints
// Note: Use a valid Base64 encoded "username:key"
//   Example: echo -n "USER_NAME:YOUR_KEY" | base64

# --- PRODUCTS ---
curl "http://localhost/clicshopping/index.php?mcp&AnthropicEcommerce&action=products&limit=10" \
-H "Authorization: Basic BASE64_TOKEN"
curl "http://localhost/clicshopping/index.php?mcp&AnthropicEcommerce&action=product&id=5" \
-H "Authorization: Basic BASE64_TOKEN"
curl "http://localhost/clicshopping/index.php?mcp&AnthropicEcommerce&action=search&query=shirt" \
-H "Authorization: Basic BASE64_TOKEN"
curl "http://localhost/clicshopping/index.php?mcp&AnthropicEcommerce&action=categories" \
-H "Authorization: Basic BASE64_TOKEN"
curl "http://localhost/clicshopping/index.php?mcp&AnthropicEcommerce&action=recommendations" \
-H "Authorization: Basic BASE64_TOKEN"
curl "http://localhost/clicshopping/index.php?mcp&AnthropicEcommerce&action=stats" \
-H "Authorization: Basic BASE64_TOKEN"

# --- SESSIONS ---
curl -X POST "http://localhost/clicshopping/index.php?mcp&AnthropicEcommerce&action=session_create" \
-H "Authorization: Basic BASE64_TOKEN" \
-H "Content-Type: application/json" \
-d '{"items":[{"product_id":"5","quantity":1}],"buyer":{"email":"test@example.com"}}'
curl "http://localhost/clicshopping/index.php?mcp&AnthropicEcommerce&action=session_get&session_id=cs_xxx" \
-H "Authorization: Basic BASE64_TOKEN"
curl -X POST "http://localhost/clicshopping/index.php?mcp&AnthropicEcommerce&action=session_complete&session_id=cs_xxx" \
-H "Authorization: Basic BASE64_TOKEN" \
-H "Content-Type: application/json" \
-d '{"payment":{"provider":"stripe","payment_intent_id":"pi_xxx"}}'

# --- ORDERS ---
curl "http://localhost/clicshopping/index.php?mcp&AnthropicEcommerce&action=orders" \
-H "Authorization: Basic BASE64_TOKEN"
curl "http://localhost/clicshopping/index.php?mcp&AnthropicEcommerce&action=order&id=42" \
-H "Authorization: Basic BASE64_TOKEN"
curl "http://localhost/clicshopping/index.php?mcp&AnthropicEcommerce&action=order_history&id=42" \
-H "Authorization: Basic BASE64_TOKEN"
curl -X POST "http://localhost/clicshopping/index.php?mcp&AnthropicEcommerce&action=order_cancel&id=42" \
-H "Authorization: Basic BASE64_TOKEN"
curl -X POST "http://localhost/clicshopping/index.php?mcp&AnthropicEcommerce&action=order_message&id=42" \
-H "Authorization: Basic BASE64_TOKEN" \
-H "Content-Type: application/json" \
-d '{"message":"Where is my package?"}'

# --- CUSTOMERS ---
curl "http://localhost/clicshopping/index.php?mcp&AnthropicEcommerce&action=customer&id=10" \
-H "Authorization: Basic BASE64_TOKEN"
curl "http://localhost/clicshopping/index.php?mcp&AnthropicEcommerce&action=countries" \
-H "Authorization: Basic BASE64_TOKEN"
*/
---
