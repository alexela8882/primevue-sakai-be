<?php

namespace App\Models\Product;

use App\Models\Model\Base;

class Category extends Base
{
    protected $connection = 'mongodb';

    protected $collection = 'product_categories';
}
