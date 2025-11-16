# Tsuku Examples

This directory contains practical examples demonstrating Tsuku's features.

## Running the Examples

```bash
php examples/01-basic.php
php examples/02-object-access.php
php examples/03-functions.php
php examples/04-advanced.php
```

## Example Overview

### 01-basic.php
Demonstrates fundamental Tsuku features:
- Variable interpolation: `{name}`
- For loops: `@for(items as item)`
- For loops with keys: `@for(users as user, key)` (value first, then key!)
- Conditionals: `@if(condition)...@else...@end`
- Unless directive: `@unless(condition)...@else...@end`
- Nested data access: `{user.address.city}`

### 02-object-access.php
Shows smart object/array access capabilities:
- Access object properties and methods transparently
- Same syntax works for both arrays and objects
- Automatic getter method detection (`getName()`)
- Boolean getter support (`isActive()`)
- Mixed array and object data structures
- Objects in conditionals and loops

**Access Strategy Priority:**
1. Array access: `$data['key']`
2. Getter methods: `$object->getKey()`
3. Boolean getters: `$object->isKey()`
4. Direct methods: `$object->key()`
5. Public properties: `$object->key`

### 03-functions.php
Demonstrates built-in function usage:
- String functions: `@upper()`, `@lower()`
- Array functions: `@join()`, `@length()`
- Default values: `@default()`
- Nested functions: `@upper(@default(name, "guest"))`
- Functions in conditionals: `@if(@length(items) > 2)`
- Functions with variables as arguments

### 04-advanced.php
Complex real-world scenarios:
- Order invoice template with nested objects
- Product listing with complex conditional logic
- User dashboard with notification handling
- Nested conditionals and loops
- Mixed data types (arrays, objects, primitives)
- Complex business logic in templates

## Key Features Demonstrated

### Variable Interpolation
```
{variable}
{object.property}
{array.key}
```

### Control Flow
```
@if(condition)
  ...
@else
  ...
@end

@unless(condition)
  ...
@end

@for(items as item)
  ...
@end

@for(items as item, key)
  ... {item} ... {key} ...
@end
```
**Note:** In `@for` loops with keys, the value comes first, then the key: `@for(collection as value, key)`

### Functions
```
@functionName(arg1, arg2)
@nested(@functions(work))
```

### Comparisons in Conditionals
```
@if(stock > 0)
@if(role == "admin")
@if(price >= 100)
```

## Tips

1. **Dot Notation**: Works seamlessly with both arrays and objects
2. **Nested Access**: Chain properties like `order.user.name`
3. **Type Flexibility**: Mix arrays, objects, and primitives freely
4. **Smart Getters**: Private properties accessible via getter methods
5. **Null Safety**: Missing values return empty strings (configurable with StrictnessMode)
