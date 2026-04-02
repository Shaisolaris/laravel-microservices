<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'sku', 'description', 'price', 'stock', 'category', 'low_stock_threshold', 'is_active'];
    protected $casts = ['price' => 'float', 'is_active' => 'boolean'];
    public function scopeInStock($query) { return $query->where('stock', '>', 0); }
}
