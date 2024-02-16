# API LISTS

1. `/menu`
- method: `GET`
- response body: `object`
- response sample: [menu.json](./api-lists/menu.json)

2. `/modules`
- method: `GET`
- response body `array`
- response sample: [modules.json](./api-lists/modules.json)

3. `/module-attributes/{module_name}` eg. `/module-attributes/leads`
- method: `POST`
- request body: `['panels', 'fields', 'collection', 'viewFilters']`
- response body: `array`
- response sample: [leads-attributes](./api-lists/leads-attributes.json)

4. `/modules/{module_name}` eg. `/modules/leads` `/modules/inquiries`
- method: `GET`
- response body: `object`
- response sample: [leads.json](./api-lists/leads.json)

5. `/module-item/{module_name}/{id}` eg. `/module-item/leads/1`
- method: `GET`
- response body: `object`
- response sample: [leads-item-id.json](./api-lists/leads-item-id.json)

6. `/module-related/{module_name}` eg. `/module-related/accounts`
- method: `GET`
- response body: `array`
- response sample: [accounts-related.json](./api-lists/accounts-related.json)

7. `/picklist`
- method: `GET`
- response body: `object`
- response sample: [picklist.json](./api-lists/picklist.json)