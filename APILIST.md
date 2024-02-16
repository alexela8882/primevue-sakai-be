# API LISTS

1. `/menu`
- method: `GET`
- response body: `object`
- response sample: [menu.json](./api-lists/menu.json)

2. `/modules`
- method: `GET`
- response body `array`
- response sample: [modules.json](./api-lists/modules.json)

3. `/<module_name>` eg. `/leads` `/inquiries`
- method: `GET`
- response body: `object`
- response sample: [leads.json](./api-lists/leads.json)

4. `/modules/{id}`
- method: `GET`
- response body: `object`
- response sample: [modules-id.json](./api-lists/modules-id.json)

5. `/<module_name>-item/{id}` eg. `/leads-item/{id}`
- method: `GET`
- response body: `object`
- response sample: [leads-item-id.json](./api-lists/leads-item-id.json)

5. `/<module_name>-related` eg. `/accounts-related`
- method: `GET`
- response body: `array`
- response sample: [accounts-related.json](./api-lists/accounts-related.json)

6. `/picklist`
- method: `GET`
- response body: `object`
- response sample: [picklist.json](./api-lists/picklist.json)