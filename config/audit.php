<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

/*
 | Ce se înregistrează în jurnalul de audit (spatie/laravel-activitylog).
 |  - fields: coloanele urmărite (restul schimbărilor sunt ignorate);
 |  - title:  coloana care identifică înregistrarea în jurnal (rămâne vizibilă
 |            și după ștergere);
 |  - label:  numele afișat al tipului;
 |  - events: opțional, limitează evenimentele (implicit toate patru).
 | Stocul fizic nu apare aici: are propriul registru (inventory_movements).
 */
return [

    'models' => [
        Product::class => [
            'log' => 'catalog', 'label' => 'produs', 'title' => 'name',
            'fields' => ['name', 'slug', 'brand_id', 'type', 'status', 'published_at'],
        ],
        ProductVariant::class => [
            'log' => 'catalog', 'label' => 'variantă', 'title' => 'sku',
            'fields' => [
                'sku', 'barcode', 'price', 'compare_at_price', 'cost_price', 'weight_g',
                'low_stock_threshold', 'track_inventory', 'allow_backorder', 'is_default',
            ],
        ],
        Category::class => [
            'log' => 'catalog', 'label' => 'categorie', 'title' => 'name',
            'fields' => ['parent_id', 'name', 'slug', 'position', 'is_active', 'meta_title', 'meta_description'],
        ],
        Brand::class => [
            'log' => 'catalog', 'label' => 'brand', 'title' => 'name',
            'fields' => ['name', 'slug', 'is_active'],
        ],
        Attribute::class => [
            'log' => 'catalog', 'label' => 'atribut', 'title' => 'name',
            'fields' => ['name', 'type', 'is_filterable'],
        ],
        AttributeValue::class => [
            'log' => 'catalog', 'label' => 'valoare de atribut', 'title' => 'value',
            'fields' => ['attribute_id', 'value'],
        ],
        Coupon::class => [
            'log' => 'sales', 'label' => 'cupon', 'title' => 'code',
            'fields' => ['code', 'type', 'value', 'min_subtotal', 'max_uses', 'max_uses_per_user', 'starts_at', 'ends_at', 'is_active'],
        ],
        User::class => [
            'log' => 'users', 'label' => 'utilizator', 'title' => 'email',
            'fields' => ['name', 'email', 'is_active'],
            'events' => ['updated', 'deleted'],
        ],
    ],

];
