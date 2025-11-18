<?php

/**
 * JSON Generation Examples
 *
 * Demonstrates JSON API response generation with proper escaping,
 * REST API patterns, GraphQL-like structures, and JSON-LD.
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

$tsuku = new Tsuku();

echo "=== JSON GENERATION EXAMPLES ===\n\n";

// Example 1: REST API Product Response
echo "1. REST API - Product List Response\n";
echo str_repeat('-', 60) . "\n";

$template1 = '{
  "status": "success",
  "data": {
    "products": [@for(products as product, index)
      {
        "id": @json(product.id),
        "sku": @json(product.sku),
        "name": @json(product.name),
        "description": @json(product.description),
        "price": {product.price},
        "sale_price": @?{product.sale_price product.sale_price : "null"},
        "currency": "USD",
        "stock": {product.stock},
        "in_stock": @?{product.stock > 0 "true" : "false"},
        "images": [
          @for(product.images as img, i)
          @json(img)@if(i < length(product.images) - 1),@end
          @end
        ],
        "categories": [
          @for(product.categories as cat, i)
          @json(cat)@if(i < length(product.categories) - 1),@end
          @end
        ],
        "url": @json(product.url)
      }@if(index < length(products) - 1),@end
@end
    ],
    "total": {meta.total},
    "page": {meta.page},
    "per_page": {meta.per_page}
  },
  "meta": {
    "timestamp": @date("Y-m-d\TH:i:s\Z"),
    "version": "1.0.0"
  }
}';

$data1 = [
    'products' => [
        [
            'id' => 'WID-001',
            'sku' => 'WID-001-PRO',
            'name' => 'Premium Widget "Pro Edition"',
            'description' => 'A <premium> widget with advanced features & professional-grade quality',
            'price' => 129.99,
            'sale_price' => 99.99,
            'stock' => 50,
            'images' => [
                'https://example.com/images/widget-1.jpg',
                'https://example.com/images/widget-2.jpg'
            ],
            'categories' => ['Electronics', 'Gadgets', 'Premium'],
            'url' => 'https://example.com/products/premium-widget'
        ],
        [
            'id' => 'GAD-002',
            'sku' => 'GAD-002-ULT',
            'name' => 'Ultimate Gadget <Professional>',
            'description' => 'The ultimate gadget for professionals',
            'price' => 599.00,
            'sale_price' => null,
            'stock' => 0,
            'images' => ['https://example.com/images/gadget.jpg'],
            'categories' => ['Electronics', 'Professional'],
            'url' => 'https://example.com/products/ultimate-gadget'
        ],
    ],
    'meta' => [
        'total' => 2,
        'page' => 1,
        'per_page' => 10
    ]
];

echo $tsuku->process($template1, $data1);
echo "\n\n";

// Example 2: REST API Error Response
echo "2. REST API - Error Response\n";
echo str_repeat('-', 60) . "\n";

$template2 = '{
  "status": "error",
  "error": {
    "code": @json(error.code),
    "message": @json(error.message),
    "details": @json(error.details),
    "field": @?{error.field @json(error.field) : "null"}
  },
  "meta": {
    "timestamp": @date("Y-m-d\TH:i:s\Z"),
    "request_id": @json(meta.request_id)
  }
}';

$data2 = [
    'error' => [
        'code' => 'VALIDATION_ERROR',
        'message' => 'Validation failed for the provided input',
        'details' => 'The email field must be a valid email address',
        'field' => 'email'
    ],
    'meta' => [
        'request_id' => 'req_abc123xyz'
    ]
];

echo $tsuku->process($template2, $data2);
echo "\n\n";

// Example 3: Complex Nested JSON (User Profile)
echo "3. Complex Nested User Profile\n";
echo str_repeat('-', 60) . "\n";

$template3 = '{
  "user": {
    "id": @json(user.id),
    "username": @json(user.username),
    "email": @json(user.email),
    "name": @json(user.name),
    "bio": @json(user.bio),
    "avatar": @json(user.avatar),
    "profile": {
      "phone": @json(user.profile.phone),
      "website": @json(user.profile.website),
      "location": {
        "city": @json(user.profile.location.city),
        "country": @json(user.profile.location.country),
        "timezone": @json(user.profile.location.timezone)
      }
    },
    "stats": {
      "orders": {user.stats.orders},
      "reviews": {user.stats.reviews},
      "wishlist": {user.stats.wishlist}
    },
    "addresses": [@for(user.addresses as addr, i)
      {
        "id": @json(addr.id),
        "label": @json(addr.label),
        "is_default": @?{addr.is_default "true" : "false"},
        "street": @json(addr.street),
        "city": @json(addr.city),
        "state": @json(addr.state),
        "zip": @json(addr.zip),
        "country": @json(addr.country)
      }@if(i < length(user.addresses) - 1),@end
@end
    ],
    "preferences": {
      "newsletter": @?{user.preferences.newsletter "true" : "false"},
      "notifications": @?{user.preferences.notifications "true" : "false"},
      "theme": @json(user.preferences.theme)
    }
  }
}';

$data3 = [
    'user' => [
        'id' => 'usr_12345',
        'username' => 'johndoe',
        'email' => 'john.doe@example.com',
        'name' => 'John Doe <Admin>',
        'bio' => 'Software developer & tech enthusiast. Love building <amazing> things!',
        'avatar' => 'https://example.com/avatars/john.jpg',
        'profile' => [
            'phone' => '+1 (555) 123-4567',
            'website' => 'https://johndoe.com',
            'location' => [
                'city' => 'San Francisco',
                'country' => 'USA',
                'timezone' => 'America/Los_Angeles'
            ]
        ],
        'stats' => [
            'orders' => 42,
            'reviews' => 128,
            'wishlist' => 5
        ],
        'addresses' => [
            [
                'id' => 'addr_1',
                'label' => 'Home',
                'is_default' => true,
                'street' => '123 Main St, Apt #4',
                'city' => 'San Francisco',
                'state' => 'CA',
                'zip' => '94102',
                'country' => 'USA'
            ],
            [
                'id' => 'addr_2',
                'label' => 'Office',
                'is_default' => false,
                'street' => '456 Business Ave, Suite 200',
                'city' => 'New York',
                'state' => 'NY',
                'zip' => '10001',
                'country' => 'USA'
            ],
        ],
        'preferences' => [
            'newsletter' => true,
            'notifications' => false,
            'theme' => 'dark'
        ]
    ]
];

echo $tsuku->process($template3, $data3);
echo "\n\n";

// Example 4: JSON-LD (Structured Data for SEO)
echo "4. JSON-LD Structured Data (Product)\n";
echo str_repeat('-', 60) . "\n";

$template4 = '{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": @json(product.name),
  "description": @json(product.description),
  "image": [@for(product.images as img, i)
    @json(img)@if(i < length(product.images) - 1),@end
@end
  ],
  "brand": {
    "@type": "Brand",
    "name": @json(product.brand)
  },
  "offers": {
    "@type": "Offer",
    "price": @json(product.price),
    "priceCurrency": "USD",
    "availability": @?{product.stock > 0 @json("https://schema.org/InStock") : @json("https://schema.org/OutOfStock")},
    "url": @json(product.url)
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": @json(product.rating.value),
    "reviewCount": {product.rating.count}
  }@if(product.reviews),
  "review": [@for(product.reviews as review, i)
    {
      "@type": "Review",
      "author": {
        "@type": "Person",
        "name": @json(review.author)
      },
      "datePublished": "@date("Y-m-d", review.date)",
      "reviewBody": @json(review.body),
      "reviewRating": {
        "@type": "Rating",
        "ratingValue": {review.rating}
      }
    }@if(i < length(product.reviews) - 1),@end
@end
  ]@end
}';

$data4 = [
    'product' => [
        'name' => 'Premium Widget "Pro Edition"',
        'description' => 'A professional-grade widget with <advanced> features & capabilities',
        'images' => [
            'https://example.com/images/widget-1.jpg',
            'https://example.com/images/widget-2.jpg'
        ],
        'brand' => 'WidgetCo',
        'price' => '99.99',
        'stock' => 50,
        'url' => 'https://example.com/products/premium-widget',
        'rating' => [
            'value' => '4.8',
            'count' => 128
        ],
        'reviews' => [
            [
                'author' => 'Jane Smith',
                'date' => strtotime('2025-01-10'),
                'body' => 'Excellent product! <Highly> recommended.',
                'rating' => 5
            ],
            [
                'author' => 'Bob Johnson',
                'date' => strtotime('2025-01-05'),
                'body' => 'Good quality, fast shipping & great support.',
                'rating' => 4
            ],
        ]
    ]
];

echo $tsuku->process($template4, $data4);
echo "\n\n";

// Example 5: GraphQL-like Response
echo "5. GraphQL-like Response Structure\n";
echo str_repeat('-', 60) . "\n";

$template5 = '{
  "data": {
    "user": @?{user {
      "id": @json(user.id),
      "name": @json(user.name),
      "email": @json(user.email),
      "posts": [@for(user.posts as post, i)
        {
          "id": @json(post.id),
          "title": @json(post.title),
          "content": @json(post.content),
          "published": "@date("Y-m-d", post.published)",
          "author": {
            "id": @json(user.id),
            "name": @json(user.name)
          },
          "comments": [@for(post.comments as comment, ci)
            {
              "id": @json(comment.id),
              "body": @json(comment.body),
              "author": {
                "name": @json(comment.author)
              }
            }@if(ci < length(post.comments) - 1),@end
@end
          ]
        }@if(i < length(user.posts) - 1),@end
@end
      ]
    } : "null"}
  },
  "errors": @?{errors [@for(errors as error, i)
    {
      "message": @json(error.message),
      "path": [@for(error.path as p, pi)
        @json(p)@if(pi < length(error.path) - 1),@end
@end
      ]
    }@if(i < length(errors) - 1),@end
@end
  ] : "null"}
}';

$data5 = [
    'user' => [
        'id' => 'usr_123',
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'posts' => [
            [
                'id' => 'post_1',
                'title' => 'Getting Started with <Modern> PHP',
                'content' => 'Learn how to write clean, maintainable PHP code...',
                'published' => strtotime('2025-01-15'),
                'comments' => [
                    [
                        'id' => 'cmt_1',
                        'body' => 'Great article! Very helpful.',
                        'author' => 'Jane Smith'
                    ],
                    [
                        'id' => 'cmt_2',
                        'body' => 'Thanks for sharing this <knowledge>!',
                        'author' => 'Bob Johnson'
                    ],
                ]
            ],
        ]
    ],
    'errors' => null
];

echo $tsuku->process($template5, $data5);
echo "\n\n";

// Example 6: Webhook Payload
echo "6. Webhook Payload (Order Notification)\n";
echo str_repeat('-', 60) . "\n";

$template6 = '{
  "event": @json(webhook.event),
  "timestamp": @date("Y-m-d\TH:i:s\Z", webhook.timestamp),
  "data": {
    "order": {
      "id": @json(webhook.data.order.id),
      "status": @json(webhook.data.order.status),
      "total": {webhook.data.order.total},
      "currency": "USD",
      "customer": {
        "id": @json(webhook.data.order.customer.id),
        "name": @json(webhook.data.order.customer.name),
        "email": @json(webhook.data.order.customer.email)
      },
      "items": [@for(webhook.data.order.items as item, i)
        {
          "product_id": @json(item.product_id),
          "name": @json(item.name),
          "quantity": {item.quantity},
          "price": {item.price}
        }@if(i < length(webhook.data.order.items) - 1),@end
@end
      ],
      "shipping_address": {
        "street": @json(webhook.data.order.shipping.street),
        "city": @json(webhook.data.order.shipping.city),
        "state": @json(webhook.data.order.shipping.state),
        "zip": @json(webhook.data.order.shipping.zip),
        "country": @json(webhook.data.order.shipping.country)
      }
    }
  }
}';

$data6 = [
    'webhook' => [
        'event' => 'order.created',
        'timestamp' => time(),
        'data' => [
            'order' => [
                'id' => 'ORD-1001',
                'status' => 'pending',
                'total' => 299.99,
                'customer' => [
                    'id' => 'cust_123',
                    'name' => 'John Doe & Associates',
                    'email' => 'john@example.com'
                ],
                'items' => [
                    [
                        'product_id' => 'WID-001',
                        'name' => 'Premium Widget "Pro"',
                        'quantity' => 2,
                        'price' => 99.99
                    ],
                    [
                        'product_id' => 'GAD-002',
                        'name' => 'Gadget <Ultimate>',
                        'quantity' => 1,
                        'price' => 100.01
                    ],
                ],
                'shipping' => [
                    'street' => '123 Main St, Apt #4',
                    'city' => 'San Francisco',
                    'state' => 'CA',
                    'zip' => '94102',
                    'country' => 'USA'
                ]
            ]
        ]
    ]
];

echo $tsuku->process($template6, $data6);
echo "\n\n";

echo "=== All JSON examples completed! ===\n";
